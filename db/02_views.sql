-- =====================================================================
-- 02_views.sql : 판정 결과 뷰 (schema v4)
--   V_STUDENT_REQ          학생 → 적용 졸업요건 (입학연도가 구간 안에 드는 것)
--   V_LATEST_ENROLLMENT    학생·과목별 가장 최근 수강 1건 (재수강 시 최신 성적 인정)
--   V_EARNED_COURSE        그 중 학점이 인정된 과목
--   V_GRAD_AREA_STATUS     영역별 기준/이수/남은 학점 (묶음 영역은 하위 영역 합산)   FR-03
--   V_GRAD_GROUP_STATUS    과목군별 기준/이수/남은 학점 (택1, SW/데이터, 필수과목)
--   V_MISSING_REQUIRED     미이수 필수과목 (NOT EXISTS)                               FR-04
--   V_GRAD_STATUS          학생별 졸업 판정 요약                                      FR-01, FR-07
--   V_LAST_SEMESTER        학생별 직전 정규학기 (장학 판정 기준 학기)
--   V_SCHOLARSHIP_ELIGIBLE 장학금별 신청 가능 여부                                    FR-02
--   V_COURSE_SUGGESTION    부족한 요건을 채울 수 있는 미이수 과목 (추천)
--
-- 효율을 위한 작성 규칙 (v3 → v4)
--   1) 학생 한 명을 조회할 때 전체 학생을 계산하지 않도록 뷰를 MERGE 가능하게 쓴다.
--      ROW_NUMBER() 나 바깥 GROUP BY 가 있는 뷰는 MySQL 이 전체를 임시 테이블로 만든 뒤 거른다.
--      → "더 최근 수강이 없는 행"(NOT EXISTS + sem_order 인덱스)으로 최신 성적을 고르고,
--        학생별 합계는 LATERAL 파생 테이블로 그 학생 행에 대해서만 계산한다.
--   2) 학생 수 2000명·수강 8.7만 건에서 학생 1명 판정 쿼리 합계가 약 11.8초 → 약 30ms 로 줄었다 (tools/bench.py).
-- =====================================================================
USE grad_check;

-- 입학연도가 [from_year, to_year] 구간에 드는 졸업요건 (21학번 이전 / 22학번 이후 분리)
CREATE OR REPLACE VIEW V_STUDENT_REQ AS
SELECT s.student_id, gr.req_id
FROM STUDENT s
JOIN GRAD_REQ gr
  ON gr.dept_id = s.dept_id
 AND s.admission_year >= gr.from_year
 AND (gr.to_year IS NULL OR s.admission_year <= gr.to_year);

-- 같은 학생·과목에 더 최근(sem_order 가 큰) 수강이 없으면 그 행이 최신 성적이다.
-- uq_enroll(student_id, course_id, sem_order) 인덱스로 바로 확인된다.
CREATE OR REPLACE VIEW V_LATEST_ENROLLMENT AS
SELECT e.enroll_id, e.student_id, e.course_id, e.year, e.term, e.sem_order, e.grade,
       c.course_name, c.credits, c.design_credits, c.area_code,
       g.grade_point, g.earns_credit, g.in_gpa
FROM ENROLLMENT e
JOIN COURSE      c ON c.course_id = e.course_id
JOIN GRADE_SCALE g ON g.grade     = e.grade
WHERE NOT EXISTS (
    SELECT 1 FROM ENROLLMENT later
    WHERE later.student_id = e.student_id
      AND later.course_id  = e.course_id
      AND later.sem_order  > e.sem_order
);

CREATE OR REPLACE VIEW V_EARNED_COURSE AS
SELECT v.student_id, v.course_id, v.course_name, v.credits, v.design_credits,
       v.area_code, a.parent_code, v.grade, v.grade_point, v.in_gpa, v.year, v.term, v.sem_order
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
       x.earned_credits,
       GREATEST(gra.min_credits - x.earned_credits, 0) AS remaining_credits
FROM V_STUDENT_REQ sr
JOIN GRAD_REQ_AREA gra ON gra.req_id = sr.req_id
JOIN AREA          a   ON a.area_code = gra.area_code
JOIN LATERAL (
    SELECT COALESCE(SUM(e.credits), 0) AS earned_credits
    FROM V_EARNED_COURSE e
    WHERE e.student_id = sr.student_id
      AND (e.area_code = gra.area_code OR e.parent_code = gra.area_code)
) x ON TRUE;

-- 과목군별 이수 현황.
--   CREDITS 과목군 : 기준 = GRAD_REQ_GROUP.min_credits
--   ALL 과목군     : 기준 = 구성 과목 학점 합계 (모두 이수해야 0 이 됨)
CREATE OR REPLACE VIEW V_GRAD_GROUP_STATUS AS
SELECT sr.student_id,
       cg.group_code,
       cg.group_name,
       cg.rule_type,
       cg.description,
       CASE cg.rule_type WHEN 'ALL' THEN mem.total_credits ELSE grg.min_credits END AS min_credits,
       x.earned_credits,
       GREATEST(CASE cg.rule_type WHEN 'ALL' THEN mem.total_credits ELSE grg.min_credits END
                - x.earned_credits, 0)                                                AS remaining_credits,
       mem.member_count,
       x.earned_count,
       x.earned_courses
FROM V_STUDENT_REQ sr
JOIN GRAD_REQ_GROUP grg ON grg.req_id = sr.req_id
JOIN COURSE_GROUP   cg  ON cg.group_code = grg.group_code
JOIN LATERAL (
    SELECT COUNT(*) AS member_count, COALESCE(SUM(c.credits), 0) AS total_credits
    FROM COURSE_GROUP_MEMBER m JOIN COURSE c ON c.course_id = m.course_id
    WHERE m.group_code = cg.group_code
) mem ON TRUE
JOIN LATERAL (
    SELECT COALESCE(SUM(e.credits), 0) AS earned_credits,
           COUNT(e.course_id)          AS earned_count,
           GROUP_CONCAT(e.course_name ORDER BY e.sem_order SEPARATOR ', ') AS earned_courses
    FROM COURSE_GROUP_MEMBER m
    JOIN V_EARNED_COURSE e ON e.course_id = m.course_id AND e.student_id = sr.student_id
    WHERE m.group_code = cg.group_code
) x ON TRUE;

-- 미이수 필수과목: 학생에게 적용되는 ALL 과목군의 과목 중 학점 인정 기록이 없는 것 (NOT EXISTS)
CREATE OR REPLACE VIEW V_MISSING_REQUIRED AS
SELECT sr.student_id, cg.group_code, cg.group_name,
       c.course_id, c.course_name, c.credits, c.design_credits, c.area_code, a.area_name
FROM V_STUDENT_REQ sr
JOIN GRAD_REQ_GROUP      grg ON grg.req_id = sr.req_id
JOIN COURSE_GROUP        cg  ON cg.group_code = grg.group_code AND cg.rule_type = 'ALL'
JOIN COURSE_GROUP_MEMBER m   ON m.group_code = cg.group_code
JOIN COURSE              c   ON c.course_id = m.course_id
JOIN AREA                a   ON a.area_code = c.area_code
WHERE NOT EXISTS (
    SELECT 1 FROM V_EARNED_COURSE v
    WHERE v.student_id = sr.student_id AND v.course_id = m.course_id
);

-- 학생별 졸업 판정 요약 (적용 졸업요건이 없으면 req_id 가 NULL)
-- 합계들은 LATERAL 로 "이 학생" 행에 대해서만 계산하므로 WHERE student_id = ? 가 바로 먹힌다.
CREATE OR REPLACE VIEW V_GRAD_STATUS AS
SELECT s.student_id,
       s.name,
       s.dept_id,
       s.admission_year,
       s.grade_level,
       gr.req_id,
       gr.from_year                                            AS req_from_year,
       gr.to_year                                              AS req_to_year,
       gr.total_credits                                        AS required_total,
       tot.earned                                              AS earned_total,
       GREATEST(gr.total_credits - tot.earned, 0)              AS remaining_total,
       tot.cum_gpa,
       gr.min_gpa,
       gr.min_design_credits,
       tot.design                                              AS earned_design,
       chk.short_areas                                         AS short_area_count,
       chk.short_groups                                        AS short_group_count,
       chk.missing                                             AS missing_required_count,
       (gr.min_gpa IS NULL OR COALESCE(tot.cum_gpa, 0) >= gr.min_gpa) AS gpa_ok,
       (gr.req_id IS NOT NULL
        AND tot.earned >= gr.total_credits
        AND tot.design >= gr.min_design_credits
        AND chk.short_areas = 0
        AND chk.short_groups = 0
        AND chk.missing = 0
        AND (gr.min_gpa IS NULL OR COALESCE(tot.cum_gpa, 0) >= gr.min_gpa)) AS graduatable
FROM STUDENT s
LEFT JOIN GRAD_REQ gr
       ON gr.dept_id = s.dept_id
      AND s.admission_year >= gr.from_year
      AND (gr.to_year IS NULL OR s.admission_year <= gr.to_year)
JOIN LATERAL (
    SELECT COALESCE(SUM(CASE WHEN v.earns_credit THEN v.credits END), 0)        AS earned,
           COALESCE(SUM(CASE WHEN v.earns_credit THEN v.design_credits END), 0) AS design,
           ROUND(SUM(CASE WHEN v.in_gpa THEN v.grade_point * v.credits END)
                 / NULLIF(SUM(CASE WHEN v.in_gpa THEN v.credits END), 0), 2)    AS cum_gpa
    FROM V_LATEST_ENROLLMENT v
    WHERE v.student_id = s.student_id
) tot ON TRUE
JOIN LATERAL (
    SELECT (SELECT COUNT(*) FROM V_GRAD_AREA_STATUS  a WHERE a.student_id = s.student_id AND a.remaining_credits > 0) AS short_areas,
           (SELECT COUNT(*) FROM V_GRAD_GROUP_STATUS g WHERE g.student_id = s.student_id AND g.rule_type = 'CREDITS'
                                                          AND g.remaining_credits > 0)                                  AS short_groups,
           (SELECT COUNT(*) FROM V_MISSING_REQUIRED  m WHERE m.student_id = s.student_id)                               AS missing
) chk ON TRUE;

-- 직전 정규학기(1·2학기): 더 최근의 정규학기 기록이 없는 학기
CREATE OR REPLACE VIEW V_LAST_SEMESTER AS
SELECT r.student_id, r.year, r.term, r.sem_order, r.attempted_credits, r.earned_credits, r.gpa
FROM SEMESTER_RECORD r
WHERE r.term IN (1, 2)
  AND NOT EXISTS (
      SELECT 1 FROM SEMESTER_RECORD later
      WHERE later.student_id = r.student_id AND later.term IN (1, 2) AND later.sem_order > r.sem_order
  );

-- 장학금 판정: 직전 정규학기 평점·취득학점, 소득분위, 봉사시간을 기준과 비교.
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
LEFT JOIN V_LAST_SEMESTER lr ON lr.student_id = s.student_id;

-- 추천 과목: 아직 학점을 받지 못한 과목 중 부족한 요건을 채우는 것.
--   REQUIRED 미이수 필수과목 / GROUP 학점이 모자란 과목군의 과목 /
--   AREA 학점이 모자란 영역의 과목(우리 학과 또는 공통 과목) / DESIGN 설계학점이 모자랄 때 설계 과목
CREATE OR REPLACE VIEW V_COURSE_SUGGESTION AS
SELECT m.student_id, 'REQUIRED' AS reason_kind, 1 AS priority, m.group_code AS reason_code,
       m.group_name AS reason_name, m.course_id, m.course_name, m.credits, m.design_credits, m.area_name
FROM V_MISSING_REQUIRED m
UNION ALL
SELECT g.student_id, 'GROUP', 2, g.group_code, g.group_name,
       c.course_id, c.course_name, c.credits, c.design_credits, a.area_name
FROM V_GRAD_GROUP_STATUS g
JOIN COURSE_GROUP_MEMBER cm ON cm.group_code = g.group_code
JOIN COURSE c ON c.course_id = cm.course_id
JOIN AREA   a ON a.area_code = c.area_code
WHERE g.rule_type = 'CREDITS' AND g.remaining_credits > 0
  AND NOT EXISTS (SELECT 1 FROM V_EARNED_COURSE e WHERE e.student_id = g.student_id AND e.course_id = c.course_id)
UNION ALL
SELECT ar.student_id, 'AREA', 3, ar.area_code, ar.area_name,
       c.course_id, c.course_name, c.credits, c.design_credits, ca.area_name
FROM V_GRAD_AREA_STATUS ar
JOIN STUDENT s ON s.student_id = ar.student_id
JOIN AREA ca   ON ca.area_code = ar.area_code OR ca.parent_code = ar.area_code
JOIN COURSE c  ON c.area_code = ca.area_code AND (c.dept_id IS NULL OR c.dept_id = s.dept_id)
WHERE ar.remaining_credits > 0 AND c.course_id NOT LIKE 'U%'
  AND NOT EXISTS (SELECT 1 FROM V_EARNED_COURSE e WHERE e.student_id = ar.student_id AND e.course_id = c.course_id)
UNION ALL
SELECT st.student_id, 'DESIGN', 4, 'DESIGN', '설계학점',
       c.course_id, c.course_name, c.credits, c.design_credits, a.area_name
FROM V_GRAD_STATUS st
JOIN COURSE c ON c.design_credits > 0 AND c.dept_id = st.dept_id
JOIN AREA   a ON a.area_code = c.area_code
WHERE st.req_id IS NOT NULL AND st.earned_design < st.min_design_credits
  AND NOT EXISTS (SELECT 1 FROM V_EARNED_COURSE e WHERE e.student_id = st.student_id AND e.course_id = c.course_id);
