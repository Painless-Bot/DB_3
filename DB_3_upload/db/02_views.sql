-- =====================================================================
-- 02_views.sql : 판정 결과 뷰
--   V_STUDENT_REQ          학생 → 적용 졸업요건 (입학연도가 구간 안에 드는 것)
--   V_LATEST_ENROLLMENT    학생·과목별 가장 최근 수강 1건 (재수강 시 최신 성적 인정)
--   V_EARNED_COURSE        그 중 학점이 인정된 과목
--   V_GRAD_AREA_STATUS     영역별 기준/이수/남은 학점 (묶음 영역은 하위 영역 합산)   FR-03
--   V_GRAD_GROUP_STATUS    과목군별 기준/이수/남은 학점 (택1, SW/데이터활용역량 등)
--   V_MISSING_REQUIRED     미이수 필수과목 (NOT EXISTS)                               FR-04
--   V_GRAD_STATUS          학생별 졸업 판정 요약                                      FR-01, FR-07
--   V_SCHOLARSHIP_ELIGIBLE 장학금별 신청 가능 여부 (직전 정규학기 기준)               FR-02
-- =====================================================================
USE grad_check;

-- 학기 순서: 1학기(1) → 여름(3) → 2학기(2) → 겨울(4)
DROP FUNCTION IF EXISTS fn_term_order;
CREATE FUNCTION fn_term_order(p_term TINYINT) RETURNS TINYINT DETERMINISTIC
RETURN CASE p_term WHEN 1 THEN 1 WHEN 3 THEN 2 WHEN 2 THEN 3 ELSE 4 END;

-- 입학연도가 [from_year, to_year] 구간에 드는 졸업요건 (21학번 이전 / 22학번 이후 분리)
CREATE OR REPLACE VIEW V_STUDENT_REQ AS
SELECT s.student_id, gr.req_id
FROM STUDENT s
JOIN GRAD_REQ gr
  ON gr.dept_id = s.dept_id
 AND s.admission_year >= gr.from_year
 AND (gr.to_year IS NULL OR s.admission_year <= gr.to_year);

CREATE OR REPLACE VIEW V_LATEST_ENROLLMENT AS
SELECT t.enroll_id, t.student_id, t.course_id, t.year, t.term, t.grade,
       c.course_name, c.credits, c.design_credits, c.area_code,
       g.grade_point, g.earns_credit, g.in_gpa
FROM (
    SELECT e.*,
           ROW_NUMBER() OVER (PARTITION BY e.student_id, e.course_id
                              ORDER BY e.year DESC, fn_term_order(e.term) DESC) AS rn
    FROM ENROLLMENT e
) t
JOIN COURSE      c ON c.course_id = t.course_id
JOIN GRADE_SCALE g ON g.grade     = t.grade
WHERE t.rn = 1;

CREATE OR REPLACE VIEW V_EARNED_COURSE AS
SELECT v.student_id, v.course_id, v.course_name, v.credits, v.design_credits,
       v.area_code, a.parent_code, v.grade, v.grade_point, v.in_gpa, v.year, v.term
FROM V_LATEST_ENROLLMENT v
JOIN AREA a ON a.area_code = v.area_code
WHERE v.earns_credit = TRUE;

-- 영역별 이수 학점. 요건 영역이 묶음(예: MJ 전공)이면 하위 영역 과목도 합친다.
CREATE OR REPLACE VIEW V_GRAD_AREA_STATUS AS
SELECT sr.student_id,
       gra.area_code,
       a.area_name,
       a.parent_code,
       a.sort_order,
       gra.min_credits,
       COALESCE(SUM(e.credits), 0)                                AS earned_credits,
       GREATEST(gra.min_credits - COALESCE(SUM(e.credits), 0), 0) AS remaining_credits
FROM V_STUDENT_REQ sr
JOIN GRAD_REQ_AREA gra ON gra.req_id = sr.req_id
JOIN AREA          a   ON a.area_code = gra.area_code
LEFT JOIN V_EARNED_COURSE e
       ON e.student_id = sr.student_id
      AND (e.area_code = gra.area_code OR e.parent_code = gra.area_code)
GROUP BY sr.student_id, gra.area_code, a.area_name, a.parent_code, a.sort_order, gra.min_credits;

-- 과목군별 이수 학점 (택1 과목, SW/데이터활용역량 소양·기초·심화 등)
CREATE OR REPLACE VIEW V_GRAD_GROUP_STATUS AS
SELECT sr.student_id,
       cg.group_code,
       cg.group_name,
       cg.description,
       grg.min_credits,
       COALESCE(SUM(e.credits), 0)                                AS earned_credits,
       GREATEST(grg.min_credits - COALESCE(SUM(e.credits), 0), 0) AS remaining_credits,
       GROUP_CONCAT(e.course_name ORDER BY e.year, e.term SEPARATOR ', ') AS earned_courses
FROM V_STUDENT_REQ sr
JOIN GRAD_REQ_GROUP grg ON grg.req_id = sr.req_id
JOIN COURSE_GROUP   cg  ON cg.group_code = grg.group_code
LEFT JOIN COURSE_GROUP_MEMBER m ON m.group_code = cg.group_code
LEFT JOIN V_EARNED_COURSE e ON e.student_id = sr.student_id AND e.course_id = m.course_id
GROUP BY sr.student_id, cg.group_code, cg.group_name, cg.description, grg.min_credits;

CREATE OR REPLACE VIEW V_MISSING_REQUIRED AS
SELECT sr.student_id, c.course_id, c.course_name, c.credits, c.area_code, a.area_name
FROM V_STUDENT_REQ sr
JOIN REQUIRED_COURSE rc ON rc.req_id = sr.req_id
JOIN COURSE          c  ON c.course_id = rc.course_id
JOIN AREA            a  ON a.area_code = c.area_code
WHERE NOT EXISTS (
    SELECT 1 FROM V_EARNED_COURSE v
    WHERE v.student_id = sr.student_id AND v.course_id = rc.course_id
);

-- 학생별 졸업 판정 요약 (적용 졸업요건이 없으면 req_id 가 NULL)
CREATE OR REPLACE VIEW V_GRAD_STATUS AS
SELECT s.student_id,
       s.name,
       s.dept_id,
       s.admission_year,
       gr.req_id,
       gr.from_year                                            AS req_from_year,
       gr.to_year                                              AS req_to_year,
       gr.total_credits                                        AS required_total,
       COALESCE(tot.earned, 0)                                 AS earned_total,
       GREATEST(gr.total_credits - COALESCE(tot.earned, 0), 0) AS remaining_total,
       gpa.cum_gpa,
       gr.min_gpa,
       gr.min_design_credits,
       COALESCE(tot.design, 0)                                 AS earned_design,
       COALESCE(ar.short_areas, 0)                             AS short_area_count,
       COALESCE(gg.short_groups, 0)                            AS short_group_count,
       COALESCE(mr.missing, 0)                                 AS missing_required_count,
       (gr.min_gpa IS NULL OR COALESCE(gpa.cum_gpa, 0) >= gr.min_gpa) AS gpa_ok,
       (gr.req_id IS NOT NULL
        AND COALESCE(tot.earned, 0) >= gr.total_credits
        AND COALESCE(tot.design, 0) >= gr.min_design_credits
        AND COALESCE(ar.short_areas, 0) = 0
        AND COALESCE(gg.short_groups, 0) = 0
        AND COALESCE(mr.missing, 0) = 0
        AND (gr.min_gpa IS NULL OR COALESCE(gpa.cum_gpa, 0) >= gr.min_gpa)) AS graduatable
FROM STUDENT s
LEFT JOIN V_STUDENT_REQ sr ON sr.student_id = s.student_id
LEFT JOIN GRAD_REQ gr      ON gr.req_id = sr.req_id
LEFT JOIN (
    SELECT student_id, SUM(credits) AS earned, SUM(design_credits) AS design
    FROM V_EARNED_COURSE GROUP BY student_id
) tot ON tot.student_id = s.student_id
LEFT JOIN (
    SELECT student_id,
           ROUND(SUM(grade_point * credits) / NULLIF(SUM(credits), 0), 2) AS cum_gpa
    FROM V_LATEST_ENROLLMENT WHERE in_gpa = TRUE GROUP BY student_id
) gpa ON gpa.student_id = s.student_id
LEFT JOIN (
    SELECT student_id, COUNT(*) AS short_areas
    FROM V_GRAD_AREA_STATUS WHERE remaining_credits > 0 GROUP BY student_id
) ar ON ar.student_id = s.student_id
LEFT JOIN (
    SELECT student_id, COUNT(*) AS short_groups
    FROM V_GRAD_GROUP_STATUS WHERE remaining_credits > 0 GROUP BY student_id
) gg ON gg.student_id = s.student_id
LEFT JOIN (
    SELECT student_id, COUNT(*) AS missing
    FROM V_MISSING_REQUIRED GROUP BY student_id
) mr ON mr.student_id = s.student_id;

-- 장학금 판정: 직전 "정규" 학기(1·2학기) 평점·취득학점, 소득분위, 봉사시간을 기준과 비교.
-- 이 판정은 최소 지원 자격이다. 실제 선발은 학과 내 성적 경쟁으로 정해진다.
CREATE OR REPLACE VIEW V_SCHOLARSHIP_ELIGIBLE AS
SELECT s.student_id,
       sc.sch_id,
       sc.sch_name,
       sc.category,
       sc.amount_desc,
       sc.dept_id                         AS sch_dept_id,
       sc.min_gpa,
       sc.min_credits,
       sc.max_income,
       sc.min_volunteer_hours,
       sc.volunteer_exempt_level,
       lr.year                            AS last_year,
       lr.term                            AS last_term,
       lr.gpa                             AS last_gpa,
       lr.earned_credits                  AS last_credits,
       s.income_bracket,
       s.volunteer_hours,
       s.grade_level,
       (COALESCE(lr.gpa, 0) >= sc.min_gpa)                              AS gpa_ok,
       (COALESCE(lr.earned_credits, 0) >= sc.min_credits)               AS credits_ok,
       (sc.max_income IS NULL OR s.income_bracket <= sc.max_income)     AS income_ok,
       (sc.min_volunteer_hours IS NULL
        OR (sc.volunteer_exempt_level IS NOT NULL AND s.grade_level >= sc.volunteer_exempt_level)
        OR s.volunteer_hours >= sc.min_volunteer_hours)                 AS volunteer_ok,
       (sc.min_volunteer_hours IS NOT NULL
        AND sc.volunteer_exempt_level IS NOT NULL
        AND s.grade_level >= sc.volunteer_exempt_level)                 AS volunteer_exempt,
       (COALESCE(lr.gpa, 0) >= sc.min_gpa
        AND COALESCE(lr.earned_credits, 0) >= sc.min_credits
        AND (sc.max_income IS NULL OR s.income_bracket <= sc.max_income)
        AND (sc.min_volunteer_hours IS NULL
             OR (sc.volunteer_exempt_level IS NOT NULL AND s.grade_level >= sc.volunteer_exempt_level)
             OR s.volunteer_hours >= sc.min_volunteer_hours))           AS eligible
FROM STUDENT s
JOIN SCHOLARSHIP sc ON sc.dept_id IS NULL OR sc.dept_id = s.dept_id
LEFT JOIN (
    SELECT student_id, year, term, gpa, earned_credits
    FROM (
        SELECT sr.*,
               ROW_NUMBER() OVER (PARTITION BY sr.student_id
                                  ORDER BY sr.year DESC, sr.term DESC) AS rn
        FROM SEMESTER_RECORD sr
        WHERE sr.term IN (1, 2)
    ) x
    WHERE x.rn = 1
) lr ON lr.student_id = s.student_id;
