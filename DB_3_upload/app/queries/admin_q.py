"""학과 담당자 화면에서 쓰는 쿼리. 모든 조회는 담당자의 학과(dept_id)로 범위를 제한한다."""

# 학생 목록 + 졸업 판정 요약. 이름/학번 검색은 LIKE
STUDENT_LIST = """
SELECT g.student_id, g.name, g.admission_year, g.short_group_count, g.req_from_year, g.req_to_year, g.earned_total, g.required_total,
       g.remaining_total, g.cum_gpa, g.short_area_count, g.missing_required_count, g.graduatable
FROM V_GRAD_STATUS g
WHERE g.dept_id = %(dept)s
  AND (%(q)s = '' OR g.student_id LIKE CONCAT('%%', %(q)s, '%%') OR g.name LIKE CONCAT('%%', %(q)s, '%%'))
ORDER BY g.student_id
"""

# '영역 학점 미달' 학생: GROUP BY + HAVING
SHORT_AREA_STUDENTS = """
SELECT a.student_id
FROM V_GRAD_AREA_STATUS a
JOIN STUDENT s ON s.student_id = a.student_id
WHERE s.dept_id = %s
GROUP BY a.student_id
HAVING SUM(a.remaining_credits) > 0
UNION
SELECT g.student_id FROM V_GRAD_GROUP_STATUS g JOIN STUDENT s ON s.student_id = g.student_id
WHERE s.dept_id = %s AND g.remaining_credits > 0
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

# ---------------------------------------------------------------- 졸업요건 관리 (FR-06)
# 졸업요건은 입학연도 구간 단위. 해당 학생 수는 구간에 드는 입학생 수
GRAD_REQ_LIST = """
SELECT r.req_id, r.from_year, r.to_year, r.total_credits, r.min_gpa, r.min_design_credits,
       (SELECT COUNT(*) FROM GRAD_REQ_AREA   a WHERE a.req_id = r.req_id) AS area_count,
       (SELECT COUNT(*) FROM GRAD_REQ_GROUP  g WHERE g.req_id = r.req_id) AS group_count,
       (SELECT COUNT(*) FROM REQUIRED_COURSE c WHERE c.req_id = r.req_id) AS required_count,
       (SELECT COUNT(*) FROM V_STUDENT_REQ  sr WHERE sr.req_id = r.req_id) AS student_count
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

GRAD_REQ_GROUPS = """
SELECT cg.group_code, cg.group_name, cg.description, grg.min_credits,
       (SELECT GROUP_CONCAT(c.course_name ORDER BY c.course_name SEPARATOR ', ')
          FROM COURSE_GROUP_MEMBER m JOIN COURSE c ON c.course_id = m.course_id
         WHERE m.group_code = cg.group_code) AS courses
FROM COURSE_GROUP cg
LEFT JOIN GRAD_REQ_GROUP grg ON grg.group_code = cg.group_code AND grg.req_id = %s
ORDER BY cg.group_code
"""

# 필수과목으로 고를 수 있는 과목: 우리 학과 과목 + 교양·MSC 과목
COURSE_CHOICES = """
SELECT c.course_id, c.course_name, c.credits, a.area_name,
       EXISTS (SELECT 1 FROM REQUIRED_COURSE rc
               WHERE rc.req_id = %s AND rc.course_id = c.course_id) AS selected
FROM COURSE c JOIN AREA a ON a.area_code = c.area_code
WHERE c.dept_id = %s OR c.dept_id IS NULL
ORDER BY a.sort_order, c.course_id
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
GRAD_REQ_AREA_DELETE = "DELETE FROM GRAD_REQ_AREA WHERE req_id = %s"
GRAD_REQ_AREA_INSERT = "INSERT INTO GRAD_REQ_AREA (req_id, area_code, min_credits) VALUES (%s, %s, %s)"
GRAD_REQ_GROUP_DELETE = "DELETE FROM GRAD_REQ_GROUP WHERE req_id = %s"
GRAD_REQ_GROUP_INSERT = "INSERT INTO GRAD_REQ_GROUP (req_id, group_code, min_credits) VALUES (%s, %s, %s)"
REQUIRED_DELETE = "DELETE FROM REQUIRED_COURSE WHERE req_id = %s"
REQUIRED_INSERT = "INSERT INTO REQUIRED_COURSE (req_id, course_id) VALUES (%s, %s)"
ALLOWED_COURSE = "SELECT 1 FROM COURSE WHERE course_id = %s AND (dept_id = %s OR dept_id IS NULL)"

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
