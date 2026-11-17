"""학과 담당자 화면에서 쓰는 쿼리. 모든 조회는 담당자의 학과(dept_id)로 범위를 제한한다."""

# ---------------------------------------------------------------- 학과 전체 진단 (목록·통계)
# 학생 한 명을 볼 때는 V_GRAD_STATUS 가 그 학생 행만 계산하지만(LATERAL), 학과 전체를 볼 때
# 학생마다 따로 계산하면 학생 수만큼 반복된다. 그래서 목록·통계는 CTE 로 학과 학생의 인정 과목을
# 한 번만 만들고(earned) 집계(GROUP BY)를 한꺼번에 한다. 판정 규칙은 V_GRAD_STATUS 와 같고,
# tools/bench.py 가 두 결과가 모든 학생에서 같은지 확인한다.
DEPT_STATUS_CTE = """
WITH st AS (
    SELECT s.student_id, s.name, s.admission_year, s.grade_level, s.income_bracket, s.volunteer_hours,
           gr.req_id, gr.from_year AS req_from_year, gr.to_year AS req_to_year,
           gr.total_credits AS required_total, gr.min_gpa, gr.min_design_credits
    FROM STUDENT s
    LEFT JOIN GRAD_REQ gr
           ON gr.dept_id = s.dept_id
          AND s.admission_year >= gr.from_year
          AND (gr.to_year IS NULL OR s.admission_year <= gr.to_year)
    WHERE s.dept_id = %(dept)s
      AND (%(q)s = '' OR s.student_id LIKE CONCAT('%%', %(q)s, '%%') OR s.name LIKE CONCAT('%%', %(q)s, '%%'))
),
latest AS (               -- 학과 학생들의 과목별 최신 성적. DISTINCT 로 CTE 를 한 번만 만들어(materialize)
    SELECT DISTINCT       -- 아래 tot·by_area·grp 가 재사용한다 (안 그러면 참조할 때마다 다시 계산)
           v.student_id, v.course_id, v.credits, v.design_credits, v.area_code,
           v.grade_point, v.earns_credit, v.in_gpa
    FROM STUDENT s JOIN V_LATEST_ENROLLMENT v ON v.student_id = s.student_id
    WHERE s.dept_id = %(dept)s
),
tot AS (
    SELECT student_id,
           SUM(CASE WHEN earns_credit THEN credits ELSE 0 END)        AS earned,
           SUM(CASE WHEN earns_credit THEN design_credits ELSE 0 END) AS design,
           ROUND(SUM(CASE WHEN in_gpa THEN grade_point * credits END)
                 / NULLIF(SUM(CASE WHEN in_gpa THEN credits END), 0), 2) AS cum_gpa
    FROM latest GROUP BY student_id
),
by_area AS (              -- 학생 × 세부 이수구분별 인정 학점
    SELECT l.student_id, l.area_code, a.parent_code, SUM(l.credits) AS credits
    FROM latest l JOIN AREA a ON a.area_code = l.area_code
    WHERE l.earns_credit
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
    LEFT JOIN latest l ON l.student_id = st.student_id AND l.course_id = m.course_id AND l.earns_credit
    GROUP BY st.student_id, cg.group_code, cg.rule_type, grg.min_credits
),
chk AS (
    SELECT st.student_id,
           (SELECT COUNT(*) FROM area_short x WHERE x.student_id = st.student_id) AS short_areas,
           COALESCE(SUM(g.rule_type = 'CREDITS' AND g.earned_credits < g.min_credits), 0) AS short_groups,
           COALESCE(SUM(CASE WHEN g.rule_type = 'ALL' THEN g.member_count - g.earned_count END), 0) AS missing
    FROM st LEFT JOIN grp g ON g.student_id = st.student_id
    GROUP BY st.student_id
)
"""

STUDENT_LIST = DEPT_STATUS_CTE + """
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
       (st.req_id IS NOT NULL
        AND COALESCE(tot.earned, 0) >= st.required_total
        AND COALESCE(tot.design, 0) >= st.min_design_credits
        AND chk.short_areas = 0 AND chk.short_groups = 0 AND chk.missing = 0
        AND (st.min_gpa IS NULL OR COALESCE(tot.cum_gpa, 0) >= st.min_gpa)) AS graduatable
FROM st
JOIN chk ON chk.student_id = st.student_id
LEFT JOIN tot ON tot.student_id = st.student_id
ORDER BY st.student_id
"""

# 통계: 영역별로 기준 미달 학생 수와 평균 부족 학점 (area_short 를 다시 GROUP BY)
AREA_SHORTAGE = DEPT_STATUS_CTE + """
SELECT x.area_code, ar.area_name, COUNT(*) AS student_count,
       ROUND(AVG(x.min_credits - x.earned_credits), 1) AS avg_short
FROM area_short x
JOIN AREA ar ON ar.area_code = x.area_code
GROUP BY x.area_code, ar.area_name, ar.sort_order
ORDER BY student_count DESC, ar.sort_order
"""

# 통계: 과목군·필수과목별 미충족 학생 수
GROUP_SHORTAGE = DEPT_STATUS_CTE + """
SELECT g.group_code, cg.group_name, g.rule_type, COUNT(*) AS student_count
FROM grp g JOIN COURSE_GROUP cg ON cg.group_code = g.group_code
WHERE (g.rule_type = 'CREDITS' AND g.earned_credits < g.min_credits)
   OR (g.rule_type = 'ALL' AND g.earned_count < g.member_count)
GROUP BY g.group_code, cg.group_name, g.rule_type
ORDER BY student_count DESC
"""

# 통계: 장학금별 지원 가능 학생 수 + 학생별 지원 가능 장학금 수
SCHOLARSHIP_COUNTS = """
SELECT v.sch_id, v.sch_name, v.category, v.min_gpa,
       SUM(v.eligible) AS eligible_count, COUNT(*) AS student_count
FROM STUDENT s JOIN V_SCHOLARSHIP_ELIGIBLE v ON v.student_id = s.student_id
WHERE s.dept_id = %(dept)s
GROUP BY v.sch_id, v.sch_name, v.category, v.min_gpa
ORDER BY FIELD(v.category, '성적우수', '가계지원', '학과'), v.min_gpa DESC
"""
ANY_SCHOLARSHIP = """
SELECT COUNT(DISTINCT v.student_id) AS n
FROM STUDENT s JOIN V_SCHOLARSHIP_ELIGIBLE v ON v.student_id = s.student_id AND v.eligible
WHERE s.dept_id = %(dept)s
"""

# 직전 정규학기 평점 분포 (0.5 단위 구간)
LAST_GPA_HISTOGRAM = """
SELECT LEAST(FLOOR(l.gpa * 2) / 2, 4.0) AS bin, COUNT(*) AS n
FROM STUDENT s JOIN V_LAST_SEMESTER l ON l.student_id = s.student_id
WHERE s.dept_id = %(dept)s AND l.gpa IS NOT NULL
GROUP BY bin ORDER BY bin
"""

# 학기별 학과 평균 평점 추이 (최근 8개 정규학기)
DEPT_TERM_TREND = """
SELECT * FROM (
    SELECT r.year, r.term, r.sem_order, COUNT(*) AS n, ROUND(AVG(r.gpa), 2) AS avg_gpa
    FROM SEMESTER_RECORD r JOIN STUDENT s ON s.student_id = r.student_id
    WHERE s.dept_id = %(dept)s AND r.term IN (1, 2) AND r.gpa IS NOT NULL
    GROUP BY r.year, r.term, r.sem_order
    ORDER BY r.sem_order DESC LIMIT 8
) t ORDER BY sem_order
"""

STUDENT_DEPT = "SELECT dept_id FROM STUDENT WHERE student_id = %s"

ENROLLMENT_ONE = """
SELECT e.enroll_id, e.student_id, s.name, s.dept_id, e.year, e.term,
       e.course_id, c.course_name, c.credits, e.grade
FROM ENROLLMENT e
JOIN STUDENT s ON s.student_id = e.student_id
JOIN COURSE  c ON c.course_id  = e.course_id
WHERE e.enroll_id = %s
"""

GRADES = "SELECT grade, grade_point FROM GRADE_SCALE ORDER BY grade_point IS NULL, grade_point DESC, grade"

# 이 UPDATE 가 trg_enrollment_au 트리거를 실행해 SEMESTER_RECORD 를 다시 계산한다 (FR-05)
UPDATE_GRADE = "UPDATE ENROLLMENT SET grade = %s WHERE enroll_id = %s"

SEMESTER_ONE = """
SELECT gpa, earned_credits FROM SEMESTER_RECORD
WHERE student_id = %s AND year = %s AND term = %s
"""

ELIGIBLE_NAMES = """
SELECT sch_name FROM V_SCHOLARSHIP_ELIGIBLE
WHERE student_id = %s AND eligible = TRUE ORDER BY sch_name
"""

GRAD_FLAG = "SELECT graduatable, remaining_total FROM V_GRAD_STATUS WHERE student_id = %s"

# ---------------------------------------------------------------- 졸업요건 관리 (FR-06)
# 졸업요건은 입학연도 구간 단위. 해당 학생 수는 구간에 드는 입학생 수
GRAD_REQ_LIST = """
SELECT r.req_id, r.from_year, r.to_year, r.total_credits, r.min_gpa, r.min_design_credits,
       (SELECT COUNT(*) FROM GRAD_REQ_AREA a WHERE a.req_id = r.req_id) AS area_count,
       (SELECT COUNT(*) FROM GRAD_REQ_GROUP g JOIN COURSE_GROUP cg ON cg.group_code = g.group_code
         WHERE g.req_id = r.req_id AND cg.rule_type = 'CREDITS') AS group_count,
       (SELECT COUNT(*) FROM GRAD_REQ_GROUP g JOIN COURSE_GROUP cg ON cg.group_code = g.group_code
         JOIN COURSE_GROUP_MEMBER m ON m.group_code = cg.group_code
         WHERE g.req_id = r.req_id AND cg.rule_type = 'ALL') AS required_count,
       (SELECT COUNT(*) FROM V_STUDENT_REQ sr WHERE sr.req_id = r.req_id) AS student_count
FROM GRAD_REQ r
WHERE r.dept_id = %s
ORDER BY r.from_year DESC
"""

GRAD_REQ_ONE = "SELECT * FROM GRAD_REQ WHERE req_id = %s AND dept_id = %s"

GRAD_REQ_AREAS = """
SELECT a.area_code, a.area_name, a.parent_code, gra.min_credits
FROM AREA a
LEFT JOIN GRAD_REQ_AREA gra ON gra.area_code = a.area_code AND gra.req_id = %s
ORDER BY a.sort_order
"""

# 담당자가 연결할 수 있는 과목군: 공통 과목군 + 우리 학과 과목군
GRAD_REQ_GROUPS = """
SELECT cg.group_code, cg.group_name, cg.rule_type, cg.description, cg.dept_id,
       grg.req_id IS NOT NULL AS linked, grg.min_credits,
       (SELECT COUNT(*) FROM COURSE_GROUP_MEMBER m WHERE m.group_code = cg.group_code) AS member_count,
       (SELECT GROUP_CONCAT(c.course_name ORDER BY c.course_name SEPARATOR ', ')
          FROM COURSE_GROUP_MEMBER m JOIN COURSE c ON c.course_id = m.course_id
         WHERE m.group_code = cg.group_code) AS courses
FROM COURSE_GROUP cg
LEFT JOIN GRAD_REQ_GROUP grg ON grg.group_code = cg.group_code AND grg.req_id = %s
WHERE cg.dept_id IS NULL OR cg.dept_id = %s
ORDER BY cg.rule_type = 'ALL' DESC, cg.group_code
"""

GRAD_REQ_INSERT = """
INSERT INTO GRAD_REQ (dept_id, from_year, to_year, total_credits, min_gpa, min_design_credits)
VALUES (%s, %s, %s, 132, NULL, 0)
"""
GRAD_REQ_UPDATE = """
UPDATE GRAD_REQ SET from_year = %s, to_year = %s, total_credits = %s, min_gpa = %s,
                    min_design_credits = %s, note = %s
WHERE req_id = %s
"""
GRAD_REQ_DELETE = "DELETE FROM GRAD_REQ WHERE req_id = %s AND dept_id = %s"
GRAD_REQ_AREA_DELETE = "DELETE FROM GRAD_REQ_AREA WHERE req_id = %s"
GRAD_REQ_AREA_INSERT = "INSERT INTO GRAD_REQ_AREA (req_id, area_code, min_credits) VALUES (%s, %s, %s)"
GRAD_REQ_GROUP_DELETE = "DELETE FROM GRAD_REQ_GROUP WHERE req_id = %s"
GRAD_REQ_GROUP_INSERT = "INSERT INTO GRAD_REQ_GROUP (req_id, group_code, min_credits) VALUES (%s, %s, %s)"

# ---------------------------------------------------------------- 과목군 관리 (필수과목 포함)
GROUP_LIST = """
SELECT cg.group_code, cg.group_name, cg.rule_type, cg.dept_id, cg.description,
       COUNT(DISTINCT m.course_id) AS member_count,
       (SELECT COALESCE(SUM(c.credits), 0) FROM COURSE_GROUP_MEMBER m2 JOIN COURSE c ON c.course_id = m2.course_id
         WHERE m2.group_code = cg.group_code) AS total_credits,
       (SELECT COUNT(*) FROM GRAD_REQ_GROUP g WHERE g.group_code = cg.group_code) AS req_count
FROM COURSE_GROUP cg
LEFT JOIN COURSE_GROUP_MEMBER m ON m.group_code = cg.group_code
WHERE cg.dept_id IS NULL OR cg.dept_id = %s
GROUP BY cg.group_code, cg.group_name, cg.rule_type, cg.dept_id, cg.description
ORDER BY cg.dept_id IS NULL, cg.rule_type = 'ALL' DESC, cg.group_code
"""
GROUP_ONE = "SELECT * FROM COURSE_GROUP WHERE group_code = %s AND (dept_id IS NULL OR dept_id = %s)"
GROUP_USED_BY = """
SELECT r.req_id, r.dept_id, r.from_year, r.to_year, d.dept_name
FROM GRAD_REQ_GROUP g JOIN GRAD_REQ r ON r.req_id = g.req_id JOIN DEPARTMENT d ON d.dept_id = r.dept_id
WHERE g.group_code = %s ORDER BY r.dept_id, r.from_year
"""
# 과목군에 넣을 수 있는 과목: 우리 학과 과목 + 교양·MSC 과목
GROUP_COURSE_CHOICES = """
SELECT c.course_id, c.course_name, c.credits, c.design_credits, a.area_name, a.sort_order,
       EXISTS (SELECT 1 FROM COURSE_GROUP_MEMBER m
               WHERE m.group_code = %s AND m.course_id = c.course_id) AS selected
FROM COURSE c JOIN AREA a ON a.area_code = c.area_code
WHERE c.dept_id = %s OR c.dept_id IS NULL
ORDER BY a.sort_order, c.course_name
"""
ALLOWED_COURSE = "SELECT 1 FROM COURSE WHERE course_id = %s AND (dept_id = %s OR dept_id IS NULL)"
GROUP_INSERT = """
INSERT INTO COURSE_GROUP (group_code, group_name, rule_type, dept_id, description) VALUES (%s, %s, %s, %s, %s)
"""
GROUP_UPDATE = "UPDATE COURSE_GROUP SET group_name = %s, description = %s WHERE group_code = %s"
GROUP_DELETE = "DELETE FROM COURSE_GROUP WHERE group_code = %s AND dept_id = %s"
MEMBER_DELETE = "DELETE FROM COURSE_GROUP_MEMBER WHERE group_code = %s"
MEMBER_INSERT = "INSERT INTO COURSE_GROUP_MEMBER (group_code, course_id) VALUES (%s, %s)"
GROUP_EXISTS = "SELECT 1 FROM COURSE_GROUP WHERE group_code = %s"

# ---------------------------------------------------------------- 장학금 관리 (FR-06)
# 담당자는 우리 학과 장학금과 전체 대상 장학금을 관리한다
SCHOLARSHIP_LIST = """
SELECT sc.*, d.dept_name,
       (SELECT COUNT(*) FROM V_SCHOLARSHIP_ELIGIBLE v
         JOIN STUDENT s ON s.student_id = v.student_id
         WHERE v.sch_id = sc.sch_id AND v.eligible = TRUE AND s.dept_id = %(dept)s) AS eligible_count
FROM SCHOLARSHIP sc LEFT JOIN DEPARTMENT d ON d.dept_id = sc.dept_id
WHERE sc.dept_id IS NULL OR sc.dept_id = %(dept)s
ORDER BY sc.dept_id IS NULL DESC, FIELD(sc.category, '성적우수', '가계지원', '학과'), sc.min_gpa DESC
"""
SCHOLARSHIP_ONE = """
SELECT * FROM SCHOLARSHIP WHERE sch_id = %s AND (dept_id IS NULL OR dept_id = %s)
"""
SCHOLARSHIP_COLS = ("sch_name, category, amount_desc, min_gpa, min_credits, max_income, "
                    "min_volunteer_hours, volunteer_exempt_level, dept_id")
SCHOLARSHIP_INSERT = f"INSERT INTO SCHOLARSHIP ({SCHOLARSHIP_COLS}) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)"
SCHOLARSHIP_UPDATE = """
UPDATE SCHOLARSHIP SET sch_name = %s, category = %s, amount_desc = %s, min_gpa = %s, min_credits = %s,
       max_income = %s, min_volunteer_hours = %s, volunteer_exempt_level = %s, dept_id = %s
WHERE sch_id = %s
"""
SCHOLARSHIP_DELETE = "DELETE FROM SCHOLARSHIP WHERE sch_id = %s"

DEPT_NAME = "SELECT dept_name FROM DEPARTMENT WHERE dept_id = %s"
