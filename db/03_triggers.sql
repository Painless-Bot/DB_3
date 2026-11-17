-- =====================================================================
-- 03_triggers.sql : 성적 변경 시 자동 재판정 (FR-05)  schema v4
--   ENROLLMENT 가 바뀌면 해당 학기의 SEMESTER_RECORD 를 다시 계산한다.
--   COURSE 의 학점이 바뀌면 그 과목을 들은 모든 학기를 다시 계산한다 (v4 추가:
--   v3 에서는 학점을 고쳐도 SEMESTER_RECORD 가 예전 값으로 남는 갱신 이상이 있었다).
--   뷰는 조회할 때마다 계산되므로, 트리거는 저장된 집계(학기별 평점)만 원본과 맞춘다.
-- =====================================================================
USE grad_check;

DROP PROCEDURE IF EXISTS sp_recalc_semester;
DROP PROCEDURE IF EXISTS sp_recalc_course;
DROP PROCEDURE IF EXISTS sp_recalc_all;
DROP TRIGGER IF EXISTS trg_enrollment_ai;
DROP TRIGGER IF EXISTS trg_enrollment_au;
DROP TRIGGER IF EXISTS trg_enrollment_ad;
DROP TRIGGER IF EXISTS trg_course_au;
DROP TRIGGER IF EXISTS trg_gradreq_bi;
DROP TRIGGER IF EXISTS trg_gradreq_bu;
DROP TRIGGER IF EXISTS trg_grg_bi;
DROP TRIGGER IF EXISTS trg_grg_bu;

DELIMITER //

-- 한 학생의 한 학기 성적 요약을 다시 계산 (idx_enroll_term 인덱스 사용)
CREATE PROCEDURE sp_recalc_semester(IN p_student CHAR(8), IN p_year SMALLINT, IN p_term TINYINT)
BEGIN
    DELETE FROM SEMESTER_RECORD
    WHERE student_id = p_student AND year = p_year AND term = p_term;

    INSERT INTO SEMESTER_RECORD (student_id, year, term, attempted_credits, earned_credits, gpa)
    SELECT e.student_id, e.year, e.term,
           SUM(c.credits),
           SUM(CASE WHEN g.earns_credit THEN c.credits ELSE 0 END),
           ROUND(SUM(CASE WHEN g.in_gpa THEN g.grade_point * c.credits END)
                 / NULLIF(SUM(CASE WHEN g.in_gpa THEN c.credits END), 0), 2)
    FROM ENROLLMENT e FORCE INDEX (idx_enroll_term)
    JOIN COURSE      c ON c.course_id = e.course_id
    JOIN GRADE_SCALE g ON g.grade     = e.grade
    WHERE e.student_id = p_student AND e.year = p_year AND e.term = p_term
    GROUP BY e.student_id, e.year, e.term;
END //

-- 한 과목을 들은 모든 학생·학기를 다시 계산 (과목 학점 수정 시)
CREATE PROCEDURE sp_recalc_course(IN p_course VARCHAR(10))
BEGIN
    DELETE r FROM SEMESTER_RECORD r
    JOIN (SELECT DISTINCT student_id, year, term FROM ENROLLMENT WHERE course_id = p_course) k
      ON k.student_id = r.student_id AND k.year = r.year AND k.term = r.term;

    INSERT INTO SEMESTER_RECORD (student_id, year, term, attempted_credits, earned_credits, gpa)
    SELECT e.student_id, e.year, e.term,
           SUM(c.credits),
           SUM(CASE WHEN g.earns_credit THEN c.credits ELSE 0 END),
           ROUND(SUM(CASE WHEN g.in_gpa THEN g.grade_point * c.credits END)
                 / NULLIF(SUM(CASE WHEN g.in_gpa THEN c.credits END), 0), 2)
    FROM ENROLLMENT e
    JOIN COURSE      c ON c.course_id = e.course_id
    JOIN GRADE_SCALE g ON g.grade     = e.grade
    WHERE (e.student_id, e.year, e.term) IN
          (SELECT student_id, year, term FROM ENROLLMENT WHERE course_id = p_course)
    GROUP BY e.student_id, e.year, e.term;
END //

-- 전체 재계산 (데이터를 트리거 없이 넣었을 때 한 번 실행)
CREATE PROCEDURE sp_recalc_all()
BEGIN
    DELETE FROM SEMESTER_RECORD;
    INSERT INTO SEMESTER_RECORD (student_id, year, term, attempted_credits, earned_credits, gpa)
    SELECT e.student_id, e.year, e.term,
           SUM(c.credits),
           SUM(CASE WHEN g.earns_credit THEN c.credits ELSE 0 END),
           ROUND(SUM(CASE WHEN g.in_gpa THEN g.grade_point * c.credits END)
                 / NULLIF(SUM(CASE WHEN g.in_gpa THEN c.credits END), 0), 2)
    FROM ENROLLMENT e
    JOIN COURSE      c ON c.course_id = e.course_id
    JOIN GRADE_SCALE g ON g.grade     = e.grade
    GROUP BY e.student_id, e.year, e.term;
END //

CREATE TRIGGER trg_enrollment_ai AFTER INSERT ON ENROLLMENT
FOR EACH ROW
BEGIN
    CALL sp_recalc_semester(NEW.student_id, NEW.year, NEW.term);
END //

CREATE TRIGGER trg_enrollment_au AFTER UPDATE ON ENROLLMENT
FOR EACH ROW
BEGIN
    CALL sp_recalc_semester(NEW.student_id, NEW.year, NEW.term);
    -- 학기나 학생이 바뀐 경우 이전 학기도 다시 계산
    IF OLD.student_id <> NEW.student_id OR OLD.year <> NEW.year OR OLD.term <> NEW.term THEN
        CALL sp_recalc_semester(OLD.student_id, OLD.year, OLD.term);
    END IF;
END //

CREATE TRIGGER trg_enrollment_ad AFTER DELETE ON ENROLLMENT
FOR EACH ROW
BEGIN
    CALL sp_recalc_semester(OLD.student_id, OLD.year, OLD.term);
END //

CREATE TRIGGER trg_course_au AFTER UPDATE ON COURSE
FOR EACH ROW
BEGIN
    IF OLD.credits <> NEW.credits THEN
        CALL sp_recalc_course(NEW.course_id);
    END IF;
END //

-- 같은 학과에서 입학연도 구간이 겹치는 졸업요건을 막는다 (한 학생에게 요건이 둘 적용되지 않도록)
CREATE TRIGGER trg_gradreq_bi BEFORE INSERT ON GRAD_REQ
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM GRAD_REQ g
               WHERE g.dept_id = NEW.dept_id
                 AND NEW.from_year <= COALESCE(g.to_year, 9999)
                 AND g.from_year  <= COALESCE(NEW.to_year, 9999)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GRAD_REQ_OVERLAP';
    END IF;
END //

CREATE TRIGGER trg_gradreq_bu BEFORE UPDATE ON GRAD_REQ
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM GRAD_REQ g
               WHERE g.dept_id = NEW.dept_id AND g.req_id <> NEW.req_id
                 AND NEW.from_year <= COALESCE(g.to_year, 9999)
                 AND g.from_year  <= COALESCE(NEW.to_year, 9999)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GRAD_REQ_OVERLAP';
    END IF;
END //

-- 요건 ↔ 과목군 연결 규칙: CREDITS 과목군은 학점 기준이 꼭 있어야 하고, ALL 과목군은 기준을 두지 않는다.
-- (다른 테이블의 값에 따른 조건이라 CHECK 제약으로는 표현할 수 없어 트리거로 검사)
CREATE TRIGGER trg_grg_bi BEFORE INSERT ON GRAD_REQ_GROUP
FOR EACH ROW
BEGIN
    IF (SELECT rule_type FROM COURSE_GROUP WHERE group_code = NEW.group_code) = 'ALL' THEN
        SET NEW.min_credits = NULL;
    ELSEIF NEW.min_credits IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GROUP_MIN_CREDITS_REQUIRED';
    END IF;
END //

CREATE TRIGGER trg_grg_bu BEFORE UPDATE ON GRAD_REQ_GROUP
FOR EACH ROW
BEGIN
    IF (SELECT rule_type FROM COURSE_GROUP WHERE group_code = NEW.group_code) = 'ALL' THEN
        SET NEW.min_credits = NULL;
    ELSEIF NEW.min_credits IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GROUP_MIN_CREDITS_REQUIRED';
    END IF;
END //

DELIMITER ;
