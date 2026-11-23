-- =====================================================================
-- 00_drop.sql : 이 시스템이 만드는 객체만 지운다 (팀 계정의 다른 테이블은 건드리지 않음)
--   처음 설치할 때는 "없음" 오류를 무시하고 넘어간다.
--   주의: 팀 계정에 이름이 같은 테이블(STUDENT, COURSE 등)이 이미 있으면 그것도 지워진다.
--         실행 전에  SELECT table_name FROM user_tables;  로 확인하세요.
-- =====================================================================
BEGIN
    FOR o IN (
        SELECT object_name, object_type FROM user_objects
        WHERE (object_type = 'VIEW' AND object_name IN (
                  'V_STUDENT_REQ', 'V_LATEST_ENROLLMENT', 'V_EARNED_COURSE', 'V_GRAD_AREA_STATUS',
                  'V_GRAD_GROUP_STATUS', 'V_MISSING_REQUIRED', 'V_GRAD_STATUS', 'V_LAST_SEMESTER',
                  'V_SCHOLARSHIP_ELIGIBLE', 'V_COURSE_SUGGESTION'))
           OR (object_type IN ('PROCEDURE', 'FUNCTION') AND object_name IN (
                  'SP_RECALC_SEMESTER', 'SP_RECALC_COURSE', 'SP_RECALC_ALL', 'FN_SCHEMA_VERSION'))
    ) LOOP
        EXECUTE IMMEDIATE 'DROP ' || o.object_type || ' ' || o.object_name;
    END LOOP;

    -- 자식 테이블부터 지운다 (CASCADE CONSTRAINTS 로 남은 FK 도 함께 제거)
    FOR t IN (
        SELECT column_value AS name FROM TABLE(sys.odcivarchar2list(
            'USER_ACCOUNT', 'SEMESTER_RECORD', 'SCHOLARSHIP', 'GRAD_REQ_GROUP', 'COURSE_GROUP_MEMBER',
            'COURSE_GROUP', 'GRAD_REQ_AREA', 'GRAD_REQ', 'ENROLLMENT', 'COURSE', 'STUDENT',
            'DEPARTMENT', 'GRADE_SCALE', 'AREA'))
    ) LOOP
        BEGIN
            EXECUTE IMMEDIATE 'DROP TABLE ' || t.name || ' CASCADE CONSTRAINTS PURGE';
        EXCEPTION WHEN OTHERS THEN
            IF SQLCODE != -942 THEN RAISE; END IF;   -- ORA-00942: 테이블 없음 → 무시
        END;
    END LOOP;
END;
/
