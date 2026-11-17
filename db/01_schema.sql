-- =====================================================================
-- 01_schema.sql : 장학·졸업 요건 진단 시스템 테이블 (MySQL 8)  schema v4
--
--   테이블 14개 = 원래 ERD 6개 + 그것을 정규화하면서 생긴 8개
--     핵심 (원래 ERD)  DEPARTMENT, STUDENT, COURSE, ENROLLMENT, GRAD_REQ, SCHOLARSHIP
--     코드             AREA(이수구분), GRADE_SCALE(등급→평점)
--     요건 상세        GRAD_REQ_AREA(영역별 학점), COURSE_GROUP(과목군), COURSE_GROUP_MEMBER,
--                      GRAD_REQ_GROUP(요건↔과목군)
--     집계             SEMESTER_RECORD (트리거가 관리하는 학기별 평점, FR-05)
--     로그인           USER_ACCOUNT
--
--   v3 → v4 에서 줄인 것 (16개 → 14개)
--     DB_META          버전 한 줄만 담던 테이블 → 저장 함수 fn_schema_version() 으로 대체
--     REQUIRED_COURSE  필수과목은 "전부 이수해야 하는 과목군"과 같다 →
--                      COURSE_GROUP.rule_type = 'ALL' 로 통합.
--                      요건 3구간이 같은 7과목을 각각 저장하던 21행이 과목군 7행 + 연결 3행이 됨
--
--   v4 에서 효율을 위해 더한 것
--     ENROLLMENT.sem_order / SEMESTER_RECORD.sem_order : 학기 순서(1학기<여름<2학기<겨울)를
--       저장된 생성 칼럼으로 두고 인덱스를 걸어, "가장 최근 수강"과 "직전 학기"를 인덱스로 찾는다
--     복합 인덱스 : ENROLLMENT(student_id, year, term), STUDENT(dept_id, admission_year),
--                   GRAD_REQ(dept_id, from_year)
-- =====================================================================

DROP DATABASE IF EXISTS grad_check;
CREATE DATABASE grad_check DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE grad_check;

-- init_db.py --if-needed 가 이 값으로 DB 구조가 최신인지 확인한다
CREATE FUNCTION fn_schema_version() RETURNS SMALLINT DETERMINISTIC RETURN 4;

-- ---------------------------------------------------------------- 코드
-- 이수구분. parent_code 로 묶음 영역을 만든다 (예: 전공 = 전공필수 + 전공선택).
-- 졸업요건에서 묶음 영역(MJ)을 지정하면 하위 영역 과목 학점을 합쳐서 센다.
CREATE TABLE AREA (
    area_code    CHAR(2)      NOT NULL,
    area_name    VARCHAR(20)  NOT NULL,
    parent_code  CHAR(2)      NULL,
    sort_order   TINYINT      NOT NULL DEFAULT 0,
    PRIMARY KEY (area_code),
    CONSTRAINT fk_area_parent FOREIGN KEY (parent_code) REFERENCES AREA (area_code)
);

CREATE TABLE GRADE_SCALE (
    grade         VARCHAR(2)    NOT NULL,
    grade_point   DECIMAL(2,1)  NULL,           -- P/NP 는 평점 없음
    earns_credit  BOOLEAN       NOT NULL,       -- 학점 인정 여부 (F, NP 는 FALSE)
    in_gpa        BOOLEAN       NOT NULL,       -- 평점 계산 포함 여부 (P, NP 는 FALSE)
    PRIMARY KEY (grade),
    CONSTRAINT ck_grade_point CHECK (grade_point IS NULL OR grade_point BETWEEN 0 AND 4.5)
);

-- ---------------------------------------------------------------- 기본
CREATE TABLE DEPARTMENT (
    dept_id    VARCHAR(10)   NOT NULL,
    dept_name  VARCHAR(50)   NOT NULL,
    campus     VARCHAR(10)   NOT NULL,
    homepage   VARCHAR(200)  NULL,
    PRIMARY KEY (dept_id),
    UNIQUE KEY uq_dept_name (dept_name)
);

CREATE TABLE STUDENT (
    student_id       CHAR(8)      NOT NULL,
    name             VARCHAR(30)  NOT NULL,
    dept_id          VARCHAR(10)  NOT NULL,
    admission_year   SMALLINT     NOT NULL,
    grade_level      TINYINT      NOT NULL,            -- 현재 학년 (봉사시간 예외 판정)
    income_bracket   TINYINT      NOT NULL,
    volunteer_hours  SMALLINT     NOT NULL DEFAULT 0,  -- 직전 학기까지 인정 봉사시간
    PRIMARY KEY (student_id),
    -- 학과별 학생 목록과 "학생 → 적용 졸업요건(입학연도 구간)" 조인에 쓰는 인덱스
    KEY idx_student_dept_year (dept_id, admission_year),
    CONSTRAINT fk_student_dept FOREIGN KEY (dept_id) REFERENCES DEPARTMENT (dept_id),
    CONSTRAINT ck_income  CHECK (income_bracket BETWEEN 0 AND 10),
    CONSTRAINT ck_level   CHECK (grade_level BETWEEN 1 AND 5),
    CONSTRAINT ck_volunteer CHECK (volunteer_hours >= 0)
);

-- course_id 는 홍익대 학수번호(6자리). 성적 가져오기로 새로 등록된 과목은 U 로 시작한다.
CREATE TABLE COURSE (
    course_id       VARCHAR(10)  NOT NULL,
    course_name     VARCHAR(60)  NOT NULL,
    credits         TINYINT      NOT NULL,
    design_credits  TINYINT      NOT NULL DEFAULT 0,   -- 공학인증 설계학점
    area_code       CHAR(2)      NOT NULL,
    dept_id         VARCHAR(10)  NULL,                 -- 교양·MSC 는 NULL
    PRIMARY KEY (course_id),
    CONSTRAINT fk_course_area FOREIGN KEY (area_code) REFERENCES AREA (area_code),
    CONSTRAINT fk_course_dept FOREIGN KEY (dept_id)  REFERENCES DEPARTMENT (dept_id),
    CONSTRAINT ck_credits CHECK (credits BETWEEN 0 AND 6),
    CONSTRAINT ck_design  CHECK (design_credits BETWEEN 0 AND credits)
);

-- term: 1 = 1학기, 2 = 2학기, 3 = 여름 계절학기, 4 = 겨울 계절학기
-- sem_order: 학기를 시간 순서로 정렬하는 값 (2025년 1학기=20251, 여름=20252, 2학기=20253, 겨울=20254).
--            year·term 에서 계산되는 저장 생성 칼럼이라 따로 고칠 일이 없고(갱신 이상 없음),
--            인덱스를 걸 수 있어 "재수강 중 가장 최근 성적"을 인덱스만으로 찾는다.
CREATE TABLE ENROLLMENT (
    enroll_id   INT          NOT NULL AUTO_INCREMENT,
    student_id  CHAR(8)      NOT NULL,
    course_id   VARCHAR(10)  NOT NULL,
    year        SMALLINT     NOT NULL,
    term        TINYINT      NOT NULL,
    grade       VARCHAR(2)   NOT NULL,
    sem_order   INT AS (year * 10 + CASE term WHEN 1 THEN 1 WHEN 3 THEN 2 WHEN 2 THEN 3 ELSE 4 END) STORED,
    PRIMARY KEY (enroll_id),
    -- 같은 학생이 같은 학기에 같은 과목을 두 번 들을 수 없다 + 재수강 최신 성적 조회용
    UNIQUE KEY uq_enroll (student_id, course_id, sem_order),
    -- 학기 단위 재계산(트리거)과 학기별 성적 조회용
    KEY idx_enroll_term (student_id, year, term),
    CONSTRAINT fk_enr_student FOREIGN KEY (student_id) REFERENCES STUDENT (student_id) ON DELETE CASCADE,
    CONSTRAINT fk_enr_course  FOREIGN KEY (course_id)  REFERENCES COURSE (course_id),
    CONSTRAINT fk_enr_grade   FOREIGN KEY (grade)      REFERENCES GRADE_SCALE (grade),
    CONSTRAINT ck_term CHECK (term BETWEEN 1 AND 4),
    CONSTRAINT ck_year CHECK (year BETWEEN 1990 AND 2100)
);

-- ---------------------------------------------------------------- 기준
-- 졸업요건은 학과 + 입학연도 "구간" 단위 (예: 2019~2021학번, 2022학번~).
-- to_year 가 NULL 이면 그 이후 입학생 전체. 구간이 겹치지 않는지는 트리거가 검사한다.
CREATE TABLE GRAD_REQ (
    req_id              INT           NOT NULL AUTO_INCREMENT,
    dept_id             VARCHAR(10)   NOT NULL,
    from_year           SMALLINT      NOT NULL,
    to_year             SMALLINT      NULL,
    total_credits       SMALLINT      NOT NULL,
    min_gpa             DECIMAL(3,2)  NULL,            -- NULL 이면 평점 기준 없음
    min_design_credits  SMALLINT      NOT NULL DEFAULT 0,
    note                VARCHAR(500)  NULL,            -- 시스템이 판정하지 않는 요건 안내 (영어 성적 등)
    source_url          VARCHAR(300)  NULL,
    PRIMARY KEY (req_id),
    KEY idx_gradreq_dept_year (dept_id, from_year),
    CONSTRAINT fk_gradreq_dept FOREIGN KEY (dept_id) REFERENCES DEPARTMENT (dept_id),
    CONSTRAINT ck_gradreq_years CHECK (to_year IS NULL OR to_year >= from_year),
    CONSTRAINT ck_gradreq_total CHECK (total_credits > 0),
    CONSTRAINT ck_gradreq_gpa   CHECK (min_gpa IS NULL OR min_gpa BETWEEN 0 AND 4.5)
);

CREATE TABLE GRAD_REQ_AREA (
    req_id       INT       NOT NULL,
    area_code    CHAR(2)   NOT NULL,
    min_credits  SMALLINT  NOT NULL,
    PRIMARY KEY (req_id, area_code),
    CONSTRAINT fk_gra_req  FOREIGN KEY (req_id)    REFERENCES GRAD_REQ (req_id) ON DELETE CASCADE,
    CONSTRAINT fk_gra_area FOREIGN KEY (area_code) REFERENCES AREA (area_code),
    CONSTRAINT ck_gra_min  CHECK (min_credits >= 0)
);

-- 과목군: 졸업요건에 쓰이는 과목 묶음. 규칙(rule_type)이 두 가지다.
--   CREDITS : 이 과목들 중에서 n학점 이상 (택1 글쓰기, SW/데이터활용역량 모듈 등). n 은 GRAD_REQ_GROUP 에
--   ALL     : 이 과목들을 모두 이수 (전공필수·인증필수 = v3 의 REQUIRED_COURSE)
-- 한 과목이 여러 과목군에 속할 수 있다 (예: 컴퓨터응용통계 = MSC 수학 이면서 SW 기초 모듈).
-- dept_id 가 NULL 이면 여러 학과가 같이 쓰는 공통 과목군, 아니면 그 학과 담당자만 고칠 수 있다.
CREATE TABLE COURSE_GROUP (
    group_code   VARCHAR(20)   NOT NULL,
    group_name   VARCHAR(50)   NOT NULL,
    rule_type    ENUM('CREDITS', 'ALL') NOT NULL DEFAULT 'CREDITS',
    dept_id      VARCHAR(10)   NULL,
    description  VARCHAR(300)  NULL,
    PRIMARY KEY (group_code),
    CONSTRAINT fk_cg_dept FOREIGN KEY (dept_id) REFERENCES DEPARTMENT (dept_id)
);

CREATE TABLE COURSE_GROUP_MEMBER (
    group_code  VARCHAR(20)  NOT NULL,
    course_id   VARCHAR(10)  NOT NULL,
    PRIMARY KEY (group_code, course_id),
    KEY idx_cgm_course (course_id),
    CONSTRAINT fk_cgm_group  FOREIGN KEY (group_code) REFERENCES COURSE_GROUP (group_code)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cgm_course FOREIGN KEY (course_id)  REFERENCES COURSE (course_id)
);

-- 요건 ↔ 과목군. min_credits 는 CREDITS 과목군에서만 쓰고, ALL 과목군은 NULL.
CREATE TABLE GRAD_REQ_GROUP (
    req_id       INT          NOT NULL,
    group_code   VARCHAR(20)  NOT NULL,
    min_credits  SMALLINT     NULL,
    PRIMARY KEY (req_id, group_code),
    KEY idx_grg_group (group_code),
    CONSTRAINT fk_grg_req   FOREIGN KEY (req_id)     REFERENCES GRAD_REQ (req_id) ON DELETE CASCADE,
    CONSTRAINT fk_grg_group FOREIGN KEY (group_code) REFERENCES COURSE_GROUP (group_code)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_grg_min CHECK (min_credits IS NULL OR min_credits > 0)
);

CREATE TABLE SCHOLARSHIP (
    sch_id                  INT           NOT NULL AUTO_INCREMENT,
    sch_name                VARCHAR(50)   NOT NULL,
    category                ENUM('성적우수', '가계지원', '학과') NOT NULL DEFAULT '성적우수',
    amount_desc             VARCHAR(30)   NULL,        -- 등록금 전액, 80%, 90만원 ...
    min_gpa                 DECIMAL(3,2)  NOT NULL,
    min_credits             SMALLINT      NOT NULL,    -- 직전 학기 취득 학점 기준
    max_income              TINYINT       NULL,        -- NULL 이면 소득 무관
    min_volunteer_hours     SMALLINT      NULL,        -- NULL 이면 봉사 조건 없음
    volunteer_exempt_level  TINYINT       NULL,        -- 이 학년 이상은 봉사 조건 면제 (예: 4)
    dept_id                 VARCHAR(10)   NULL,        -- NULL 이면 전체 학과 대상
    source_url              VARCHAR(300)  NULL,
    PRIMARY KEY (sch_id),
    CONSTRAINT fk_sch_dept FOREIGN KEY (dept_id) REFERENCES DEPARTMENT (dept_id),
    CONSTRAINT ck_sch_gpa    CHECK (min_gpa BETWEEN 0 AND 4.5),
    CONSTRAINT ck_sch_income CHECK (max_income IS NULL OR max_income BETWEEN 0 AND 10)
);

-- ---------------------------------------------------------------- 집계
-- ENROLLMENT 에서 계산되는 값을 저장해 두는 표(MySQL 에는 materialized view 가 없음).
-- ENROLLMENT·COURSE 트리거만 이 표를 고치므로 원본과 어긋나지 않는다 (03_triggers.sql).
-- 장학 판정의 "직전 학기 평점"을 학생마다 매번 다시 계산하지 않으려고 둔다.
CREATE TABLE SEMESTER_RECORD (
    student_id         CHAR(8)       NOT NULL,
    year               SMALLINT      NOT NULL,
    term               TINYINT       NOT NULL,
    attempted_credits  SMALLINT      NOT NULL,   -- 신청 학점 (P/NP 포함)
    earned_credits     SMALLINT      NOT NULL,   -- 취득 학점 (F, NP 제외)
    gpa                DECIMAL(3,2)  NULL,       -- 평점 대상 과목이 없으면 NULL
    sem_order          INT AS (year * 10 + CASE term WHEN 1 THEN 1 WHEN 3 THEN 2 WHEN 2 THEN 3 ELSE 4 END) STORED,
    PRIMARY KEY (student_id, year, term),
    KEY idx_sr_order (student_id, sem_order),
    CONSTRAINT fk_sr_student FOREIGN KEY (student_id) REFERENCES STUDENT (student_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------- 로그인
CREATE TABLE USER_ACCOUNT (
    login_id       VARCHAR(20)   NOT NULL,
    password_hash  VARCHAR(255)  NOT NULL,
    role           ENUM('student', 'admin') NOT NULL,
    student_id     CHAR(8)       NULL,
    dept_id        VARCHAR(10)   NULL,
    last_login     DATETIME      NULL,
    PRIMARY KEY (login_id),
    UNIQUE KEY uq_ua_student (student_id),          -- 학생 한 명에 계정 하나
    CONSTRAINT fk_ua_student FOREIGN KEY (student_id) REFERENCES STUDENT (student_id) ON DELETE CASCADE,
    CONSTRAINT fk_ua_dept    FOREIGN KEY (dept_id)    REFERENCES DEPARTMENT (dept_id),
    CONSTRAINT ck_ua_role CHECK (
        (role = 'student' AND student_id IS NOT NULL AND dept_id IS NULL) OR
        (role = 'admin'   AND dept_id    IS NOT NULL AND student_id IS NULL)
    )
);
