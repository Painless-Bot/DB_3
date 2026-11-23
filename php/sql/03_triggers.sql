-- =====================================================================
-- 03_triggers.sql : 성적 변경 시 자동 재판정 (FR-05)  Oracle, schema v4
--   ENROLLMENT 가 바뀌면 해당 학기의 SEMESTER_RECORD 를 다시 계산한다.
--   COURSE 의 학점이 바뀌면 그 과목을 들은 모든 학기를 다시 계산한다.
--   뷰는 조회할 때마다 계산되므로, 트리거는 저장된 집계(학기별 평점)만 원본과 맞춘다.
--
-- MySQL 판과 다른 점: Oracle 의 행(FOR EACH ROW) 트리거는 자기 테이블을 다시 읽을 수 없다
-- (ORA-04091 mutating table). 그래서 COMPOUND TRIGGER 로
--   AFTER EACH ROW  : 바뀐 학생·학기만 모아 두고
--   AFTER STATEMENT : 문장이 끝난 뒤 모아 둔 학기를 한 번에 다시 계산한다.
-- 오류는 SIGNAL SQLSTATE 대신 RAISE_APPLICATION_ERROR 로 낸다 (메시지 앞부분이 같은 코드).
-- =====================================================================

-- 한 학생의 한 학기 성적 요약을 다시 계산 (idx_enroll_term 인덱스 사용)
CREATE OR REPLACE PROCEDURE sp_recalc_semester(p_student IN CHAR, p_year IN NUMBER, p_term IN NUMBER) IS
BEGIN
    DELETE FROM SEMESTER_RECORD
    WHERE student_id = p_student AND year = p_year AND term = p_term;

    INSERT INTO SEMESTER_RECORD (student_id, year, term, attempted_credits, earned_credits, gpa)
    SELECT e.student_id, e.year, e.term,
           SUM(c.credits),
           SUM(CASE WHEN g.earns_credit = 1 THEN c.credits ELSE 0 END),
           ROUND(SUM(CASE WHEN g.in_gpa = 1 THEN g.grade_point * c.credits END)
                 / NULLIF(SUM(CASE WHEN g.in_gpa = 1 THEN c.credits END), 0), 2)
    FROM ENROLLMENT e
    JOIN COURSE      c ON c.course_id = e.course_id
    JOIN GRADE_SCALE g ON g.grade     = e.grade
    WHERE e.student_id = p_student AND e.year = p_year AND e.term = p_term
    GROUP BY e.student_id, e.year, e.term;
END;
/

-- 한 과목을 들은 모든 학생·학기를 다시 계산 (과목 학점 수정 시)
CREATE OR REPLACE PROCEDURE sp_recalc_course(p_course IN VARCHAR2) IS
BEGIN
    FOR k IN (SELECT DISTINCT student_id, year, term FROM ENROLLMENT WHERE course_id = p_course) LOOP
        sp_recalc_semester(k.student_id, k.year, k.term);
    END LOOP;
END;
/

-- 전체 재계산 (데이터를 트리거 없이 넣었을 때 한 번 실행:  EXEC sp_recalc_all)
CREATE OR REPLACE PROCEDURE sp_recalc_all IS
BEGIN
    DELETE FROM SEMESTER_RECORD;
    INSERT INTO SEMESTER_RECORD (student_id, year, term, attempted_credits, earned_credits, gpa)
    SELECT e.student_id, e.year, e.term,
           SUM(c.credits),
           SUM(CASE WHEN g.earns_credit = 1 THEN c.credits ELSE 0 END),
           ROUND(SUM(CASE WHEN g.in_gpa = 1 THEN g.grade_point * c.credits END)
                 / NULLIF(SUM(CASE WHEN g.in_gpa = 1 THEN c.credits END), 0), 2)
    FROM ENROLLMENT e
    JOIN COURSE      c ON c.course_id = e.course_id
    JOIN GRADE_SCALE g ON g.grade     = e.grade
    GROUP BY e.student_id, e.year, e.term;
END;
/

-- ENROLLMENT INSERT / UPDATE / DELETE → 바뀐 학기의 SEMESTER_RECORD 재계산
CREATE OR REPLACE TRIGGER trg_enrollment_recalc
FOR INSERT OR UPDATE OR DELETE ON ENROLLMENT
COMPOUND TRIGGER
    TYPE t_key  IS RECORD (student_id CHAR(8), year NUMBER(4), term NUMBER(1));
    TYPE t_keys IS TABLE OF t_key INDEX BY VARCHAR2(20);
    g_keys t_keys;   -- '학번:연도:학기' → 키 (같은 학기는 한 번만 계산)

    PROCEDURE remember(p_student CHAR, p_year NUMBER, p_term NUMBER) IS
        k t_key;
    BEGIN
        k.student_id := p_student; k.year := p_year; k.term := p_term;
        g_keys(p_student || ':' || p_year || ':' || p_term) := k;
    END remember;

    AFTER EACH ROW IS
    BEGIN
        IF INSERTING OR UPDATING THEN
            remember(:NEW.student_id, :NEW.year, :NEW.term);
        END IF;
        -- 삭제, 또는 학기·학생이 바뀐 수정이면 이전 학기도 다시 계산
        IF DELETING OR (UPDATING AND (:OLD.student_id <> :NEW.student_id
                                      OR :OLD.year <> :NEW.year OR :OLD.term <> :NEW.term)) THEN
            remember(:OLD.student_id, :OLD.year, :OLD.term);
        END IF;
    END AFTER EACH ROW;

    AFTER STATEMENT IS
        i VARCHAR2(20);
    BEGIN
        i := g_keys.FIRST;
        WHILE i IS NOT NULL LOOP
            sp_recalc_semester(g_keys(i).student_id, g_keys(i).year, g_keys(i).term);
            i := g_keys.NEXT(i);
        END LOOP;
        g_keys.DELETE;
    END AFTER STATEMENT;
END trg_enrollment_recalc;
/

-- COURSE 학점 수정 → 그 과목을 들은 학기 재계산
CREATE OR REPLACE TRIGGER trg_course_au
FOR UPDATE OF credits ON COURSE
COMPOUND TRIGGER
    TYPE t_ids IS TABLE OF VARCHAR2(10) INDEX BY VARCHAR2(10);
    g_ids t_ids;

    AFTER EACH ROW IS
    BEGIN
        IF :OLD.credits <> :NEW.credits THEN
            g_ids(:NEW.course_id) := :NEW.course_id;
        END IF;
    END AFTER EACH ROW;

    AFTER STATEMENT IS
        i VARCHAR2(10);
    BEGIN
        i := g_ids.FIRST;
        WHILE i IS NOT NULL LOOP
            sp_recalc_course(g_ids(i));
            i := g_ids.NEXT(i);
        END LOOP;
        g_ids.DELETE;
    END AFTER STATEMENT;
END trg_course_au;
/

-- 같은 학과에서 입학연도 구간이 겹치는 졸업요건을 막는다 (한 학생에게 요건이 둘 적용되지 않도록)
CREATE OR REPLACE TRIGGER trg_gradreq_overlap
FOR INSERT OR UPDATE OF dept_id, from_year, to_year ON GRAD_REQ
COMPOUND TRIGGER
    TYPE t_depts IS TABLE OF VARCHAR2(10) INDEX BY VARCHAR2(10);
    g_depts t_depts;

    AFTER EACH ROW IS
    BEGIN
        g_depts(:NEW.dept_id) := :NEW.dept_id;
    END AFTER EACH ROW;

    AFTER STATEMENT IS
        i VARCHAR2(10);
        n NUMBER;
    BEGIN
        i := g_depts.FIRST;
        WHILE i IS NOT NULL LOOP
            SELECT COUNT(*) INTO n
            FROM GRAD_REQ a JOIN GRAD_REQ b
              ON b.dept_id = a.dept_id AND b.req_id > a.req_id
             AND a.from_year <= COALESCE(b.to_year, 9999)
             AND b.from_year <= COALESCE(a.to_year, 9999)
            WHERE a.dept_id = g_depts(i);
            IF n > 0 THEN
                g_depts.DELETE;
                RAISE_APPLICATION_ERROR(-20001, 'GRAD_REQ_OVERLAP');
            END IF;
            i := g_depts.NEXT(i);
        END LOOP;
        g_depts.DELETE;
    END AFTER STATEMENT;
END trg_gradreq_overlap;
/

-- 요건 ↔ 과목군 연결 규칙: CREDITS 과목군은 학점 기준이 꼭 있어야 하고, ALL 과목군은 기준을 두지 않는다.
-- (다른 테이블의 값에 따른 조건이라 CHECK 제약으로는 표현할 수 없어 트리거로 검사)
CREATE OR REPLACE TRIGGER trg_grg_rule
BEFORE INSERT OR UPDATE ON GRAD_REQ_GROUP
FOR EACH ROW
DECLARE
    v_rule COURSE_GROUP.rule_type%TYPE;
BEGIN
    SELECT rule_type INTO v_rule FROM COURSE_GROUP WHERE group_code = :NEW.group_code;
    IF v_rule = 'ALL' THEN
        :NEW.min_credits := NULL;
    ELSIF :NEW.min_credits IS NULL THEN
        RAISE_APPLICATION_ERROR(-20002, 'GROUP_MIN_CREDITS_REQUIRED');
    END IF;
EXCEPTION
    WHEN NO_DATA_FOUND THEN NULL;   -- 없는 과목군이면 FK 제약이 막는다
END;
/
