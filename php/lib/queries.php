<?php
/**
 * 화면에서 쓰는 SQL (Oracle). 판정 로직은 DB 뷰(sql/02_views.sql)에 있고 여기서는 학번·학과로 거르기만 한다.
 * MySQL 판(app/queries/*.py)과 같은 쿼리이며, Oracle 문법으로 바꾼 곳만 다르다.
 *   FIELD(category, ...)       → CASE category WHEN ... (CAT_ORDER)
 *   x IS NULL 정렬 / 불리언 정렬 → NULLS LAST, CASE
 *   LIMIT n                    → FETCH FIRST n ROWS ONLY
 *   INSERT IGNORE / ON DUPLICATE KEY UPDATE → INSERT ... WHERE NOT EXISTS / MERGE
 */
defined('APP_ROOT') || exit;

const CAT_ORDER = "CASE category WHEN '성적우수' THEN 1 WHEN '가계지원' THEN 2 ELSE 3 END";

// ================================================================ 로그인 화면
const Q_LOGIN_USER = "
SELECT login_id, password_hash, role, student_id, dept_id FROM USER_ACCOUNT WHERE login_id = :login_id";
const Q_LOGIN_TOUCH = "UPDATE USER_ACCOUNT SET last_login = SYSDATE WHERE login_id = :login_id";
const Q_PASSWORD_HASH = "SELECT password_hash FROM USER_ACCOUNT WHERE login_id = :login_id";
const Q_PASSWORD_SET = "UPDATE USER_ACCOUNT SET password_hash = :hash WHERE login_id = :login_id";
const Q_LAST_LOGIN = "SELECT last_login FROM USER_ACCOUNT WHERE login_id = :login_id";

// 첫 화면 '판정 기준' 섹션: DB 에 들어 있는 기준을 그대로 보여 준다
const Q_LANDING_REQS = "
SELECT r.from_year, r.to_year, r.total_credits,
       (SELECT LISTAGG(x.label, ' · ') WITHIN GROUP (ORDER BY x.label)
          FROM (SELECT DISTINCT CASE WHEN g.group_code LIKE 'SWD%' THEN 'SW/데이터활용역량 9학점'
                                     WHEN g.group_code = 'SPECIAL_LA' THEN '특성화교양 3학점' END AS label
                  FROM GRAD_REQ_GROUP g WHERE g.req_id = r.req_id) x
         WHERE x.label IS NOT NULL) AS group_names
FROM GRAD_REQ r ORDER BY r.dept_id DESC, r.from_year DESC";

const Q_LANDING_SCH = "
SELECT sch_name, amount_desc, min_gpa, max_income, min_volunteer_hours
FROM SCHOLARSHIP WHERE dept_id IS NULL
ORDER BY " . CAT_ORDER . ", min_gpa DESC";

// ================================================================ 학생 화면 (담당자 학생 상세도 같이 씀)
const Q_STUDENT_INFO = "
SELECT s.student_id, s.name, s.admission_year, s.grade_level, s.income_bracket, s.volunteer_hours,
       d.dept_id, d.dept_name, d.campus
FROM STUDENT s JOIN DEPARTMENT d ON d.dept_id = s.dept_id
WHERE s.student_id = :sid";

// FR-01, FR-07: 졸업 판정 요약
const Q_GRAD_STATUS = "SELECT * FROM V_GRAD_STATUS WHERE student_id = :sid";

// 적용된 졸업요건 (입학연도 구간으로 고른 것)
const Q_GRAD_REQ_INFO = "
SELECT gr.* FROM V_STUDENT_REQ sr JOIN GRAD_REQ gr ON gr.req_id = sr.req_id
WHERE sr.student_id = :sid";

// FR-03: 영역별 학점 (묶음 영역 합산은 뷰 안에 있음)
const Q_GRAD_AREA_STATUS = "
SELECT area_code, area_name, parent_code, min_credits, earned_credits, remaining_credits
FROM V_GRAD_AREA_STATUS
WHERE student_id = :sid
ORDER BY sort_order";

// 과목군 요건 (택1, SW/데이터활용역량 등 CREDITS 과목군 + 필수과목 ALL 과목군)
const Q_GRAD_GROUP_STATUS = "
SELECT group_code, group_name, rule_type, description, min_credits, earned_credits, remaining_credits,
       member_count, earned_count, earned_courses
FROM V_GRAD_GROUP_STATUS
WHERE student_id = :sid
ORDER BY CASE rule_type WHEN 'ALL' THEN 0 ELSE 1 END,
         CASE WHEN group_code LIKE 'SWD%' THEN 1 ELSE 0 END, group_code";

// 세부 이수구분별 이수 학점 (요건에 없는 영역 포함)
const Q_EARNED_BY_AREA = "
SELECT a.area_code, a.area_name, a.parent_code, COALESCE(SUM(v.credits), 0) AS earned_credits
FROM AREA a
LEFT JOIN V_EARNED_COURSE v ON v.area_code = a.area_code AND v.student_id = :sid
GROUP BY a.area_code, a.area_name, a.parent_code, a.sort_order
ORDER BY a.sort_order";

// FR-04: 미이수 필수과목 (NOT EXISTS 는 뷰 안에 있음)
const Q_MISSING_REQUIRED = "
SELECT course_id, course_name, credits, design_credits, area_name, group_name
FROM V_MISSING_REQUIRED
WHERE student_id = :sid
ORDER BY course_id";

// FR-02: 장학금별 판정
const Q_SCHOLARSHIPS = "
SELECT * FROM V_SCHOLARSHIP_ELIGIBLE
WHERE student_id = :sid
ORDER BY eligible DESC, " . CAT_ORDER . ", min_gpa DESC, sch_name";

const Q_SEMESTER_RECORDS = "
SELECT year, term, attempted_credits, earned_credits, gpa
FROM SEMESTER_RECORD
WHERE student_id = :sid
ORDER BY sem_order";

// 성적 조회: 재수강으로 이전 성적이 대체됐는지 ROW_NUMBER 로 표시
const Q_ENROLLMENTS = "
SELECT e.enroll_id, e.year, e.term, e.course_id, c.course_name, c.credits, c.design_credits,
       a.area_name, e.grade, g.grade_point,
       CASE WHEN ROW_NUMBER() OVER (PARTITION BY e.course_id ORDER BY e.sem_order DESC) > 1
            THEN 1 ELSE 0 END AS replaced
FROM ENROLLMENT e
JOIN COURSE      c ON c.course_id = e.course_id
JOIN AREA        a ON a.area_code = c.area_code
JOIN GRADE_SCALE g ON g.grade     = e.grade
WHERE e.student_id = :sid
ORDER BY e.sem_order, a.sort_order, e.course_id";

// ---------------------------------------------------------------- 성적 가져오기
const Q_ALL_COURSES = "SELECT course_id, course_name, credits, area_code, dept_id FROM COURSE";
const Q_AREAS = "SELECT area_code, area_name FROM AREA";
const Q_ENROLLMENT_COUNT = "SELECT COUNT(*) AS n FROM ENROLLMENT WHERE student_id = :sid";
const Q_ENROLLMENT_DELETE_ALL = "DELETE FROM ENROLLMENT WHERE student_id = :sid";
const Q_COURSE_EXISTS = "SELECT 1 AS x FROM COURSE WHERE course_id = :cid";
const Q_COURSE_INSERT = "
INSERT INTO COURSE (course_id, course_name, credits, area_code, dept_id)
VALUES (:cid, :name, :credits, :area, :dept)";
// 성적표 표기(*sw(심화), 일교5 등)로 알게 된 과목군 소속. 등록된 과목군이고 아직 없을 때만 넣는다.
const Q_COURSE_GROUP_ADD = "
INSERT INTO COURSE_GROUP_MEMBER (group_code, course_id)
SELECT g.group_code, :cid FROM COURSE_GROUP g
WHERE g.group_code = :grp
  AND NOT EXISTS (SELECT 1 FROM COURSE_GROUP_MEMBER m WHERE m.group_code = g.group_code AND m.course_id = :cid)";
// 같은 학기·과목이 이미 있으면 성적만 바꾼다 (트리거가 학기 평점을 다시 계산)
const Q_ENROLLMENT_UPSERT = "
MERGE INTO ENROLLMENT e
USING (SELECT :sid AS student_id, :cid AS course_id, :yr AS year, :tm AS term, :grade AS grade FROM dual) n
   ON (e.student_id = n.student_id AND e.course_id = n.course_id AND e.year = n.year AND e.term = n.term)
 WHEN MATCHED THEN UPDATE SET e.grade = n.grade
 WHEN NOT MATCHED THEN INSERT (student_id, course_id, year, term, grade)
      VALUES (n.student_id, n.course_id, n.year, n.term, n.grade)";

// ---------------------------------------------------------------- 추천 과목 · 수강 계획
// 부족한 요건별 미이수 과목 (V_COURSE_SUGGESTION). 한 과목이 여러 요건을 채우면 여러 줄로 나온다.
const Q_SUGGESTIONS = "
SELECT reason_kind, priority, reason_code, reason_name, course_id, course_name, credits, design_credits, area_name
FROM V_COURSE_SUGGESTION
WHERE student_id = :sid
ORDER BY priority, reason_name, course_id";

// 계획에 넣을 수 있는 과목: 아직 학점을 받지 못한 우리 학과·공통 과목 (F·NP 는 재수강 대상으로 포함)
const Q_PLAN_CANDIDATES = "
SELECT c.course_id, c.course_name, c.credits, c.design_credits, a.area_name, a.sort_order,
       lt.grade AS last_grade
FROM COURSE c
JOIN AREA a ON a.area_code = c.area_code
LEFT JOIN V_LATEST_ENROLLMENT lt ON lt.student_id = :sid AND lt.course_id = c.course_id
WHERE (c.dept_id IS NULL OR c.dept_id = :dept)
  AND c.course_id NOT LIKE 'U%'
  AND NOT EXISTS (SELECT 1 FROM V_EARNED_COURSE e WHERE e.student_id = :sid AND e.course_id = c.course_id)
ORDER BY a.sort_order, c.course_name";

// 마지막 수강 학기 (다음 정규학기를 정하는 데 씀)
const Q_LAST_TERM = "
SELECT year, term FROM SEMESTER_RECORD WHERE student_id = :sid ORDER BY sem_order DESC FETCH FIRST 1 ROWS ONLY";
const Q_GRADE_SCALE = "SELECT grade, grade_point FROM GRADE_SCALE ORDER BY grade_point DESC NULLS LAST, grade";
const Q_SEMESTER_ONE = "
SELECT * FROM SEMESTER_RECORD WHERE student_id = :sid AND year = :yr AND term = :tm";
// 가상 수강: 트랜잭션 안에서 넣고, 판정 뷰를 읽은 뒤 ROLLBACK 한다 (DB 에 남지 않음)
const Q_PLAN_INSERT = "
INSERT INTO ENROLLMENT (student_id, course_id, year, term, grade) VALUES (:sid, :cid, :yr, :tm, :grade)";

// ================================================================ 담당자 화면 (모든 조회는 담당자 학과로 제한)
// 학생 한 명을 볼 때는 V_GRAD_STATUS 가 그 학생 행만 계산하지만(LATERAL), 학과 전체를 볼 때
// 학생마다 따로 계산하면 학생 수만큼 반복된다. 그래서 목록·통계는 WITH 절로 학과 학생의 인정 과목을
// 한 번만 만들고(latest) 집계(GROUP BY)를 한꺼번에 한다. 판정 규칙은 V_GRAD_STATUS 와 같다.
const Q_DEPT_STATUS_CTE = "
WITH st AS (
    SELECT s.student_id, s.name, s.admission_year, s.grade_level, s.income_bracket, s.volunteer_hours,
           gr.req_id, gr.from_year AS req_from_year, gr.to_year AS req_to_year,
           gr.total_credits AS required_total, gr.min_gpa, gr.min_design_credits
    FROM STUDENT s
    LEFT JOIN GRAD_REQ gr
           ON gr.dept_id = s.dept_id
          AND s.admission_year >= gr.from_year
          AND (gr.to_year IS NULL OR s.admission_year <= gr.to_year)
    WHERE s.dept_id = :dept
      AND (:q IS NULL OR s.student_id LIKE '%' || :q || '%' OR s.name LIKE '%' || :q || '%')
),
latest AS (               -- 학과 학생들의 과목별 최신 성적 (MATERIALIZE: 한 번만 만들어 아래에서 재사용)
    SELECT /*+ MATERIALIZE */
           v.student_id, v.course_id, v.credits, v.design_credits, v.area_code,
           v.grade_point, v.earns_credit, v.in_gpa
    FROM STUDENT s JOIN V_LATEST_ENROLLMENT v ON v.student_id = s.student_id
    WHERE s.dept_id = :dept
),
tot AS (
    SELECT student_id,
           SUM(CASE WHEN earns_credit = 1 THEN credits ELSE 0 END)        AS earned,
           SUM(CASE WHEN earns_credit = 1 THEN design_credits ELSE 0 END) AS design,
           ROUND(SUM(CASE WHEN in_gpa = 1 THEN grade_point * credits END)
                 / NULLIF(SUM(CASE WHEN in_gpa = 1 THEN credits END), 0), 2) AS cum_gpa
    FROM latest GROUP BY student_id
),
by_area AS (              -- 학생 × 세부 이수구분별 인정 학점
    SELECT l.student_id, l.area_code, a.parent_code, SUM(l.credits) AS credits
    FROM latest l JOIN AREA a ON a.area_code = l.area_code
    WHERE l.earns_credit = 1
    GROUP BY l.student_id, l.area_code, a.parent_code
),
area_short AS (           -- FR-03: 영역별로 묶어(GROUP BY) 기준에 못 미치는 영역만(HAVING)
    SELECT st.student_id, gra.area_code, gra.min_credits,
           COALESCE(SUM(b.credits), 0) AS earned_credits
    FROM st
    JOIN GRAD_REQ_AREA gra ON gra.req_id = st.req_id
    LEFT JOIN by_area b ON b.student_id = st.student_id
                       AND (b.area_code = gra.area_code OR b.parent_code = gra.area_code)
    GROUP BY st.student_id, gra.area_code, gra.min_credits
    HAVING COALESCE(SUM(b.credits), 0) < gra.min_credits
),
grp AS (                  -- 학생 × 과목군 이수 현황
    SELECT st.student_id, cg.group_code, cg.rule_type, grg.min_credits,
           COUNT(m.course_id)                                     AS member_count,
           COUNT(l.course_id)                                     AS earned_count,
           COALESCE(SUM(l.credits), 0)                            AS earned_credits
    FROM st
    JOIN GRAD_REQ_GROUP grg ON grg.req_id = st.req_id
    JOIN COURSE_GROUP cg ON cg.group_code = grg.group_code
    JOIN COURSE_GROUP_MEMBER m ON m.group_code = cg.group_code
    LEFT JOIN latest l ON l.student_id = st.student_id AND l.course_id = m.course_id AND l.earns_credit = 1
    GROUP BY st.student_id, cg.group_code, cg.rule_type, grg.min_credits
),
chk AS (
    SELECT st.student_id,
           (SELECT COUNT(*) FROM area_short x WHERE x.student_id = st.student_id) AS short_areas,
           COALESCE(SUM(CASE WHEN g.rule_type = 'CREDITS' AND g.earned_credits < g.min_credits THEN 1 ELSE 0 END), 0) AS short_groups,
           COALESCE(SUM(CASE WHEN g.rule_type = 'ALL' THEN g.member_count - g.earned_count END), 0) AS missing
    FROM st LEFT JOIN grp g ON g.student_id = st.student_id
    GROUP BY st.student_id
)";

const Q_STUDENT_LIST = Q_DEPT_STATUS_CTE . "
SELECT st.student_id, st.name, st.admission_year, st.grade_level, st.req_id,
       st.req_from_year, st.req_to_year, st.required_total,
       COALESCE(tot.earned, 0)                                        AS earned_total,
       GREATEST(st.required_total - COALESCE(tot.earned, 0), 0)       AS remaining_total,
       tot.cum_gpa,
       COALESCE(tot.design, 0)                                        AS earned_design,
       st.min_design_credits,
       chk.short_areas  AS short_area_count,
       chk.short_groups AS short_group_count,
       chk.missing      AS missing_required_count,
       CASE WHEN st.req_id IS NOT NULL
             AND COALESCE(tot.earned, 0) >= st.required_total
             AND COALESCE(tot.design, 0) >= st.min_design_credits
             AND chk.short_areas = 0 AND chk.short_groups = 0 AND chk.missing = 0
             AND (st.min_gpa IS NULL OR COALESCE(tot.cum_gpa, 0) >= st.min_gpa)
            THEN 1 ELSE 0 END AS graduatable
FROM st
JOIN chk ON chk.student_id = st.student_id
LEFT JOIN tot ON tot.student_id = st.student_id
ORDER BY st.student_id";

// 통계: 영역별로 기준 미달 학생 수와 평균 부족 학점 (area_short 를 다시 GROUP BY)
const Q_AREA_SHORTAGE = Q_DEPT_STATUS_CTE . "
SELECT x.area_code, ar.area_name, COUNT(*) AS student_count,
       ROUND(AVG(x.min_credits - x.earned_credits), 1) AS avg_short
FROM area_short x
JOIN AREA ar ON ar.area_code = x.area_code
GROUP BY x.area_code, ar.area_name, ar.sort_order
ORDER BY student_count DESC, ar.sort_order";

// 통계: 과목군·필수과목별 미충족 학생 수
const Q_GROUP_SHORTAGE = Q_DEPT_STATUS_CTE . "
SELECT g.group_code, cg.group_name, g.rule_type, COUNT(*) AS student_count
FROM grp g JOIN COURSE_GROUP cg ON cg.group_code = g.group_code
WHERE (g.rule_type = 'CREDITS' AND g.earned_credits < g.min_credits)
   OR (g.rule_type = 'ALL' AND g.earned_count < g.member_count)
GROUP BY g.group_code, cg.group_name, g.rule_type
ORDER BY student_count DESC, g.group_code";

// 통계: 장학금별 지원 가능 학생 수
const Q_SCHOLARSHIP_COUNTS = "
SELECT v.sch_id, v.sch_name, v.category, v.min_gpa,
       SUM(v.eligible) AS eligible_count, COUNT(*) AS student_count
FROM STUDENT s JOIN V_SCHOLARSHIP_ELIGIBLE v ON v.student_id = s.student_id
WHERE s.dept_id = :dept
GROUP BY v.sch_id, v.sch_name, v.category, v.min_gpa
ORDER BY CASE v.category WHEN '성적우수' THEN 1 WHEN '가계지원' THEN 2 ELSE 3 END, v.min_gpa DESC";
const Q_ANY_SCHOLARSHIP = "
SELECT COUNT(DISTINCT v.student_id) AS n
FROM STUDENT s JOIN V_SCHOLARSHIP_ELIGIBLE v ON v.student_id = s.student_id AND v.eligible = 1
WHERE s.dept_id = :dept";

// 직전 정규학기 평점 분포 (0.5 단위 구간). Oracle 은 GROUP BY 에 별칭을 못 쓰므로 안쪽에서 계산
const Q_LAST_GPA_HISTOGRAM = "
SELECT bin, COUNT(*) AS n FROM (
    SELECT LEAST(FLOOR(l.gpa * 2) / 2, 4.0) AS bin
    FROM STUDENT s JOIN V_LAST_SEMESTER l ON l.student_id = s.student_id
    WHERE s.dept_id = :dept AND l.gpa IS NOT NULL
) GROUP BY bin ORDER BY bin";

// 학기별 학과 평균 평점 추이 (최근 8개 정규학기)
const Q_DEPT_TERM_TREND = "
SELECT * FROM (
    SELECT r.year, r.term, r.sem_order, COUNT(*) AS n, ROUND(AVG(r.gpa), 2) AS avg_gpa
    FROM SEMESTER_RECORD r JOIN STUDENT s ON s.student_id = r.student_id
    WHERE s.dept_id = :dept AND r.term IN (1, 2) AND r.gpa IS NOT NULL
    GROUP BY r.year, r.term, r.sem_order
    ORDER BY r.sem_order DESC
    FETCH FIRST 8 ROWS ONLY
) t ORDER BY sem_order";

const Q_STUDENT_DEPT = "SELECT dept_id FROM STUDENT WHERE student_id = :sid";

const Q_ENROLLMENT_ONE = "
SELECT e.enroll_id, e.student_id, s.name, s.dept_id, e.year, e.term,
       e.course_id, c.course_name, c.credits, e.grade
FROM ENROLLMENT e
JOIN STUDENT s ON s.student_id = e.student_id
JOIN COURSE  c ON c.course_id  = e.course_id
WHERE e.enroll_id = :id";

// 이 UPDATE 가 trg_enrollment_recalc 트리거를 실행해 SEMESTER_RECORD 를 다시 계산한다 (FR-05)
const Q_UPDATE_GRADE = "UPDATE ENROLLMENT SET grade = :grade WHERE enroll_id = :id";

const Q_ELIGIBLE_NAMES = "
SELECT sch_name FROM V_SCHOLARSHIP_ELIGIBLE
WHERE student_id = :sid AND eligible = 1 ORDER BY sch_name";

const Q_GRAD_FLAG = "SELECT graduatable, remaining_total FROM V_GRAD_STATUS WHERE student_id = :sid";

// ---------------------------------------------------------------- 졸업요건 관리 (FR-06)
const Q_GRAD_REQ_LIST = "
SELECT r.req_id, r.from_year, r.to_year, r.total_credits, r.min_gpa, r.min_design_credits,
       (SELECT COUNT(*) FROM GRAD_REQ_AREA a WHERE a.req_id = r.req_id) AS area_count,
       (SELECT COUNT(*) FROM GRAD_REQ_GROUP g JOIN COURSE_GROUP cg ON cg.group_code = g.group_code
         WHERE g.req_id = r.req_id AND cg.rule_type = 'CREDITS') AS group_count,
       (SELECT COUNT(*) FROM GRAD_REQ_GROUP g JOIN COURSE_GROUP cg ON cg.group_code = g.group_code
         JOIN COURSE_GROUP_MEMBER m ON m.group_code = cg.group_code
         WHERE g.req_id = r.req_id AND cg.rule_type = 'ALL') AS required_count,
       (SELECT COUNT(*) FROM V_STUDENT_REQ sr WHERE sr.req_id = r.req_id) AS student_count
FROM GRAD_REQ r
WHERE r.dept_id = :dept
ORDER BY r.from_year DESC";

const Q_GRAD_REQ_ONE = "SELECT * FROM GRAD_REQ WHERE req_id = :id AND dept_id = :dept";

const Q_GRAD_REQ_AREAS = "
SELECT a.area_code, a.area_name, a.parent_code, gra.min_credits
FROM AREA a
LEFT JOIN GRAD_REQ_AREA gra ON gra.area_code = a.area_code AND gra.req_id = :id
ORDER BY a.sort_order";

// 담당자가 연결할 수 있는 과목군: 공통 과목군 + 우리 학과 과목군
const Q_GRAD_REQ_GROUPS = "
SELECT cg.group_code, cg.group_name, cg.rule_type, cg.description, cg.dept_id,
       CASE WHEN grg.req_id IS NOT NULL THEN 1 ELSE 0 END AS linked, grg.min_credits,
       (SELECT COUNT(*) FROM COURSE_GROUP_MEMBER m WHERE m.group_code = cg.group_code) AS member_count,
       (SELECT LISTAGG(c.course_name, ', ') WITHIN GROUP (ORDER BY c.course_name)
          FROM COURSE_GROUP_MEMBER m JOIN COURSE c ON c.course_id = m.course_id
         WHERE m.group_code = cg.group_code) AS courses
FROM COURSE_GROUP cg
LEFT JOIN GRAD_REQ_GROUP grg ON grg.group_code = cg.group_code AND grg.req_id = :id
WHERE cg.dept_id IS NULL OR cg.dept_id = :dept
ORDER BY CASE cg.rule_type WHEN 'ALL' THEN 0 ELSE 1 END, cg.group_code";

const Q_GRAD_REQ_INSERT = "
INSERT INTO GRAD_REQ (dept_id, from_year, to_year, total_credits, min_gpa, min_design_credits)
VALUES (:dept, :from_year, :to_year, 132, NULL, 0)
RETURNING req_id INTO :new_id";
const Q_GRAD_REQ_UPDATE = "
UPDATE GRAD_REQ SET from_year = :from_year, to_year = :to_year, total_credits = :total, min_gpa = :min_gpa,
                    min_design_credits = :design, note = :note
WHERE req_id = :id";
const Q_GRAD_REQ_DELETE = "DELETE FROM GRAD_REQ WHERE req_id = :id AND dept_id = :dept";
const Q_GRAD_REQ_AREA_DELETE = "DELETE FROM GRAD_REQ_AREA WHERE req_id = :id";
const Q_GRAD_REQ_AREA_INSERT = "INSERT INTO GRAD_REQ_AREA (req_id, area_code, min_credits) VALUES (:id, :code, :credits)";
const Q_GRAD_REQ_GROUP_DELETE = "DELETE FROM GRAD_REQ_GROUP WHERE req_id = :id";
const Q_GRAD_REQ_GROUP_INSERT = "INSERT INTO GRAD_REQ_GROUP (req_id, group_code, min_credits) VALUES (:id, :code, :credits)";

// ---------------------------------------------------------------- 과목군 관리 (필수과목 포함)
const Q_GROUP_LIST = "
SELECT cg.group_code, cg.group_name, cg.rule_type, cg.dept_id, cg.description,
       COUNT(DISTINCT m.course_id) AS member_count,
       (SELECT COALESCE(SUM(c.credits), 0) FROM COURSE_GROUP_MEMBER m2 JOIN COURSE c ON c.course_id = m2.course_id
         WHERE m2.group_code = cg.group_code) AS total_credits,
       (SELECT COUNT(*) FROM GRAD_REQ_GROUP g WHERE g.group_code = cg.group_code) AS req_count
FROM COURSE_GROUP cg
LEFT JOIN COURSE_GROUP_MEMBER m ON m.group_code = cg.group_code
WHERE cg.dept_id IS NULL OR cg.dept_id = :dept
GROUP BY cg.group_code, cg.group_name, cg.rule_type, cg.dept_id, cg.description
ORDER BY CASE WHEN cg.dept_id IS NULL THEN 1 ELSE 0 END, CASE cg.rule_type WHEN 'ALL' THEN 0 ELSE 1 END, cg.group_code";
const Q_GROUP_ONE = "SELECT * FROM COURSE_GROUP WHERE group_code = :code AND (dept_id IS NULL OR dept_id = :dept)";
const Q_GROUP_USED_BY = "
SELECT r.req_id, r.dept_id, r.from_year, r.to_year, d.dept_name
FROM GRAD_REQ_GROUP g JOIN GRAD_REQ r ON r.req_id = g.req_id JOIN DEPARTMENT d ON d.dept_id = r.dept_id
WHERE g.group_code = :code ORDER BY r.dept_id, r.from_year";
// 과목군에 넣을 수 있는 과목: 우리 학과 과목 + 교양·MSC 과목
const Q_GROUP_COURSE_CHOICES = "
SELECT c.course_id, c.course_name, c.credits, c.design_credits, a.area_name, a.sort_order,
       CASE WHEN EXISTS (SELECT 1 FROM COURSE_GROUP_MEMBER m
                         WHERE m.group_code = :code AND m.course_id = c.course_id) THEN 1 ELSE 0 END AS selected
FROM COURSE c JOIN AREA a ON a.area_code = c.area_code
WHERE c.dept_id = :dept OR c.dept_id IS NULL
ORDER BY a.sort_order, c.course_name";
const Q_ALLOWED_COURSE = "SELECT 1 AS x FROM COURSE WHERE course_id = :cid AND (dept_id = :dept OR dept_id IS NULL)";
const Q_GROUP_INSERT = "
INSERT INTO COURSE_GROUP (group_code, group_name, rule_type, dept_id, description)
VALUES (:code, :name, :rule, :dept, :descr)";
const Q_GROUP_UPDATE = "UPDATE COURSE_GROUP SET group_name = :name, description = :descr WHERE group_code = :code";
const Q_GROUP_DELETE = "DELETE FROM COURSE_GROUP WHERE group_code = :code AND dept_id = :dept";
const Q_MEMBER_DELETE = "DELETE FROM COURSE_GROUP_MEMBER WHERE group_code = :code";
const Q_MEMBER_INSERT = "INSERT INTO COURSE_GROUP_MEMBER (group_code, course_id) VALUES (:code, :cid)";
const Q_GROUP_EXISTS = "SELECT 1 AS x FROM COURSE_GROUP WHERE group_code = :code";

// ---------------------------------------------------------------- 장학금 관리 (FR-06)
// 담당자는 우리 학과 장학금과 전체 대상 장학금을 관리한다
const Q_SCHOLARSHIP_LIST = "
SELECT sc.*, d.dept_name,
       (SELECT COUNT(*) FROM V_SCHOLARSHIP_ELIGIBLE v
         JOIN STUDENT s ON s.student_id = v.student_id
         WHERE v.sch_id = sc.sch_id AND v.eligible = 1 AND s.dept_id = :dept) AS eligible_count
FROM SCHOLARSHIP sc LEFT JOIN DEPARTMENT d ON d.dept_id = sc.dept_id
WHERE sc.dept_id IS NULL OR sc.dept_id = :dept
ORDER BY CASE WHEN sc.dept_id IS NULL THEN 0 ELSE 1 END,
         CASE sc.category WHEN '성적우수' THEN 1 WHEN '가계지원' THEN 2 ELSE 3 END, sc.min_gpa DESC";
const Q_SCHOLARSHIP_ONE = "
SELECT * FROM SCHOLARSHIP WHERE sch_id = :id AND (dept_id IS NULL OR dept_id = :dept)";
const Q_SCHOLARSHIP_INSERT = "
INSERT INTO SCHOLARSHIP (sch_name, category, amount_desc, min_gpa, min_credits, max_income,
                         min_volunteer_hours, volunteer_exempt_level, dept_id)
VALUES (:sch_name, :category, :amount_desc, :min_gpa, :min_credits, :max_income,
        :min_volunteer_hours, :volunteer_exempt_level, :dept_id)";
const Q_SCHOLARSHIP_UPDATE = "
UPDATE SCHOLARSHIP SET sch_name = :sch_name, category = :category, amount_desc = :amount_desc,
       min_gpa = :min_gpa, min_credits = :min_credits, max_income = :max_income,
       min_volunteer_hours = :min_volunteer_hours, volunteer_exempt_level = :volunteer_exempt_level,
       dept_id = :dept_id
WHERE sch_id = :id";
const Q_SCHOLARSHIP_DELETE = "DELETE FROM SCHOLARSHIP WHERE sch_id = :id";

const Q_DEPT_NAME = "SELECT dept_name FROM DEPARTMENT WHERE dept_id = :dept";
