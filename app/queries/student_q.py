"""학생 화면에서 쓰는 SQL. 판정 로직은 DB 뷰에 있고 여기서는 학번으로 거르기만 한다.
담당자 화면의 학생 상세에서도 같은 쿼리를 재사용한다."""

STUDENT_INFO = """
SELECT s.student_id, s.name, s.admission_year, s.grade_level, s.income_bracket, s.volunteer_hours,
       d.dept_id, d.dept_name, d.campus
FROM STUDENT s JOIN DEPARTMENT d ON d.dept_id = s.dept_id
WHERE s.student_id = %s
"""

# FR-01, FR-07: 졸업 판정 요약
GRAD_STATUS = "SELECT * FROM V_GRAD_STATUS WHERE student_id = %s"

# 적용된 졸업요건 (입학연도 구간으로 고른 것)
GRAD_REQ_INFO = """
SELECT gr.* FROM V_STUDENT_REQ sr JOIN GRAD_REQ gr ON gr.req_id = sr.req_id
WHERE sr.student_id = %s
"""

# FR-03: 영역별 학점 (묶음 영역 합산은 뷰 안에 있음)
GRAD_AREA_STATUS = """
SELECT area_code, area_name, parent_code, min_credits, earned_credits, remaining_credits
FROM V_GRAD_AREA_STATUS
WHERE student_id = %s
ORDER BY sort_order
"""

# 과목군 요건 (택1, SW/데이터활용역량 등 CREDITS 과목군 + 필수과목 ALL 과목군)
GRAD_GROUP_STATUS = """
SELECT group_code, group_name, rule_type, description, min_credits, earned_credits, remaining_credits,
       member_count, earned_count, earned_courses
FROM V_GRAD_GROUP_STATUS
WHERE student_id = %s
ORDER BY rule_type = 'ALL' DESC, group_code LIKE 'SWD%%', group_code
"""

# 세부 이수구분별 이수 학점 (요건에 없는 영역 포함)
EARNED_BY_AREA = """
SELECT a.area_code, a.area_name, a.parent_code, COALESCE(SUM(v.credits), 0) AS earned_credits
FROM AREA a
LEFT JOIN V_EARNED_COURSE v ON v.area_code = a.area_code AND v.student_id = %s
GROUP BY a.area_code, a.area_name, a.parent_code, a.sort_order
ORDER BY a.sort_order
"""

# FR-04: 미이수 필수과목 (NOT EXISTS 는 뷰 안에 있음)
MISSING_REQUIRED = """
SELECT course_id, course_name, credits, design_credits, area_name, group_name
FROM V_MISSING_REQUIRED
WHERE student_id = %s
ORDER BY course_id
"""

# FR-02: 장학금별 판정
SCHOLARSHIPS = """
SELECT * FROM V_SCHOLARSHIP_ELIGIBLE
WHERE student_id = %s
ORDER BY eligible DESC, FIELD(category, '성적우수', '가계지원', '학과'), min_gpa DESC, sch_name
"""

SEMESTER_RECORDS = """
SELECT year, term, attempted_credits, earned_credits, gpa
FROM SEMESTER_RECORD
WHERE student_id = %s
ORDER BY sem_order
"""

# 성적 조회: 재수강으로 이전 성적이 대체됐는지 ROW_NUMBER 로 표시
ENROLLMENTS = """
SELECT e.enroll_id, e.year, e.term, e.course_id, c.course_name, c.credits, c.design_credits,
       a.area_name, e.grade, g.grade_point,
       ROW_NUMBER() OVER (PARTITION BY e.course_id
                          ORDER BY e.sem_order DESC) > 1 AS replaced
FROM ENROLLMENT e
JOIN COURSE      c ON c.course_id = e.course_id
JOIN AREA        a ON a.area_code = c.area_code
JOIN GRADE_SCALE g ON g.grade     = e.grade
WHERE e.student_id = %s
ORDER BY e.sem_order, a.sort_order, e.course_id
"""

# ---------------------------------------------------------------- 성적 가져오기
ALL_COURSES = "SELECT course_id, course_name, credits, area_code, dept_id FROM COURSE"
AREAS = "SELECT area_code, area_name FROM AREA"
ENROLLMENT_COUNT = "SELECT COUNT(*) AS n FROM ENROLLMENT WHERE student_id = %s"
ENROLLMENT_DELETE_ALL = "DELETE FROM ENROLLMENT WHERE student_id = %s"
COURSE_EXISTS = "SELECT 1 FROM COURSE WHERE course_id = %s"
COURSE_INSERT = """
INSERT INTO COURSE (course_id, course_name, credits, area_code, dept_id) VALUES (%s, %s, %s, %s, %s)
"""
# 성적표 표기(*sw(심화), 일교5 등)로 알게 된 과목군 소속. 등록된 과목군일 때만 넣는다.
COURSE_GROUP_ADD = """
INSERT IGNORE INTO COURSE_GROUP_MEMBER (group_code, course_id)
SELECT group_code, %s FROM COURSE_GROUP WHERE group_code = %s
"""
# 같은 학기·과목이 이미 있으면 성적만 바꾼다 (UPDATE 트리거가 학기 평점을 다시 계산)
ENROLLMENT_UPSERT = """
INSERT INTO ENROLLMENT (student_id, course_id, year, term, grade) VALUES (%s, %s, %s, %s, %s)
ON DUPLICATE KEY UPDATE grade = VALUES(grade)
"""

# ---------------------------------------------------------------- 추천 과목 · 수강 계획
# 부족한 요건별 미이수 과목 (V_COURSE_SUGGESTION). 한 과목이 여러 요건을 채우면 여러 줄로 나온다.
SUGGESTIONS = """
SELECT reason_kind, priority, reason_code, reason_name, course_id, course_name, credits, design_credits, area_name
FROM V_COURSE_SUGGESTION
WHERE student_id = %s
ORDER BY priority, reason_name, course_id
"""

# 계획에 넣을 수 있는 과목: 아직 학점을 받지 못한 우리 학과·공통 과목 (F·NP 는 재수강 대상으로 포함)
PLAN_CANDIDATES = """
SELECT c.course_id, c.course_name, c.credits, c.design_credits, a.area_name, a.sort_order,
       lt.grade AS last_grade
FROM COURSE c
JOIN AREA a ON a.area_code = c.area_code
LEFT JOIN V_LATEST_ENROLLMENT lt ON lt.student_id = %(sid)s AND lt.course_id = c.course_id
WHERE (c.dept_id IS NULL OR c.dept_id = %(dept)s)
  AND c.course_id NOT LIKE 'U%%'
  AND NOT EXISTS (SELECT 1 FROM V_EARNED_COURSE e WHERE e.student_id = %(sid)s AND e.course_id = c.course_id)
ORDER BY a.sort_order, c.course_name
"""

# 마지막 수강 학기 (다음 정규학기를 정하는 데 씀)
LAST_TERM = """
SELECT year, term FROM SEMESTER_RECORD WHERE student_id = %s ORDER BY sem_order DESC LIMIT 1
"""
GRADE_SCALE = "SELECT grade FROM GRADE_SCALE ORDER BY grade_point IS NULL, grade_point DESC, grade"
# 가상 수강: 트랜잭션 안에서 넣고, 판정 뷰를 읽은 뒤 ROLLBACK 한다 (DB 에 남지 않음)
PLAN_INSERT = "INSERT INTO ENROLLMENT (student_id, course_id, year, term, grade) VALUES (%s, %s, %s, %s, %s)"
