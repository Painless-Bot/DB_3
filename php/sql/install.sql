-- =====================================================================
-- install.sql : 테이블·뷰·트리거·샘플 데이터를 한 번에 만든다 (SQL*Plus 용)
--
--   서버에서 (~/sql 폴더 안에서) 실행:
--     export NLS_LANG=AMERICAN_AMERICA.AL32UTF8
--     sqlplus -L "팀계정@//203.249.87.57:1521/orcl" @install.sql
--
--   다시 실행하면 이 시스템의 테이블을 지우고 처음 상태로 되돌린다 (00_drop.sql).
-- =====================================================================
SET DEFINE OFF
SET SQLBLANKLINES ON
SET FEEDBACK OFF
SET ECHO OFF
SET SERVEROUTPUT ON

PROMPT [1/5] 기존 객체 정리 (00_drop.sql)
@@00_drop.sql

WHENEVER SQLERROR EXIT SQL.SQLCODE ROLLBACK

PROMPT [2/5] 테이블 14개 (01_schema.sql)
@@01_schema.sql
PROMPT [3/5] 뷰 10개 (02_views.sql)
@@02_views.sql
PROMPT [4/5] 프로시저 3개 · 트리거 4개 (03_triggers.sql)
@@03_triggers.sql
PROMPT [5/5] 샘플 데이터 (04_sample_data.sql)
@@04_sample_data.sql

-- 샘플 데이터의 ALTER TABLE 로 무효(INVALID)가 된 뷰·트리거를 다시 컴파일
EXEC DBMS_UTILITY.COMPILE_SCHEMA(schema => USER, compile_all => FALSE)

SET FEEDBACK ON
PROMPT
PROMPT 컴파일 오류 (no rows selected 가 정상)
SELECT name, type, line, text FROM user_errors ORDER BY name, line;
PROMPT 설치 결과 (기대값: 학생 6, 수강 187, 학기기록 > 0, 계정 8)
SELECT (SELECT COUNT(*) FROM STUDENT) AS students,
       (SELECT COUNT(*) FROM ENROLLMENT) AS enrollments,
       (SELECT COUNT(*) FROM SEMESTER_RECORD) AS semesters,
       (SELECT COUNT(*) FROM USER_ACCOUNT) AS accounts
FROM dual;
SELECT object_type, COUNT(*) AS n, SUM(CASE WHEN status = 'VALID' THEN 1 ELSE 0 END) AS valid
FROM user_objects
WHERE object_type IN ('TABLE', 'VIEW', 'TRIGGER', 'PROCEDURE', 'FUNCTION')
GROUP BY object_type ORDER BY object_type;
PROMPT 끝. exit 를 입력해 SQL*Plus 를 닫으세요.
