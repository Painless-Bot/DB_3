-- =====================================================================
-- 03_triggers.sql : 성적 변경 시 자동 재판정 (FR-05)
--   ENROLLMENT 가 바뀌면 해당 학기의 SEMESTER_RECORD 를 다시 계산한다.
--   뷰는 조회할 때마다 계산되므로, 트리거는 학기별 평점만 미리 맞춰 둔다.
-- =====================================================================
USE grad_check;

DROP PROCEDURE IF EXISTS sp_recalc_semester;
DROP PROCEDURE IF EXISTS sp_recalc_all;
DROP TRIGGER IF EXISTS trg_enrollment_ai;
DROP TRIGGER IF EXISTS trg_enrollment_au;
DROP TRIGGER IF EXISTS trg_enrollment_ad;
DROP TRIGGER IF EXISTS trg_gradreq_bi;
DROP TRIGGER IF EXISTS trg_gradreq_bu;

DELIMITER //

-- 한 학생의 한 학기 성적 요약을 다시 계산
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
    FROM ENROLLMENT e
    JOIN COURSE      c ON c.course_id = e.course_id
    JOIN GRADE_SCALE g ON g.grade     = e.grade
    WHERE e.student_id = p_student AND e.year = p_year AND e.term = p_term
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

DELIMITER ;
