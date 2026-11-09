-- =====================================================================
-- 01_schema.sql : 장학·졸업 요건 진단 시스템 테이블 (MySQL 8)  schema v2
--   코드 테이블 : AREA(이수구분, 상위 영역 묶음), GRADE_SCALE
--   기본 데이터 : DEPARTMENT, STUDENT, COURSE, ENROLLMENT
--   기준 데이터 : GRAD_REQ(학과 + 입학연도 구간), GRAD_REQ_AREA, REQUIRED_COURSE,
--                 COURSE_GROUP, COURSE_GROUP_MEMBER, GRAD_REQ_GROUP, SCHOLARSHIP
--   집계 테이블 : SEMESTER_RECORD (ENROLLMENT 트리거로 갱신)
--   웹 로그인용 : USER_ACCOUNT,  버전 확인용 : DB_META
-- =====================================================================

DROP DATABASE IF EXISTS grad_check;
CREATE DATABASE grad_check DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE grad_check;

CREATE TABLE DB_META (
    schema_version  SMALLINT NOT NULL
);

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
    PRIMARY KEY (grade)
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
    CONSTRAINT fk_student_dept FOREIGN KEY (dept_id) REFERENCES DEPARTMENT (dept_id),
    CONSTRAINT ck_income CHECK (income_bracket BETWEEN 0 AND 10),
    CONSTRAINT ck_level  CHECK (grade_level BETWEEN 1 AND 5)
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
    CONSTRAINT ck_credits CHECK (credits BETWEEN 0 AND 6)
);

-- term: 1 = 1학기, 2 = 2학기, 3 = 여름 계절학기, 4 = 겨울 계절학기
CREATE TABLE ENROLLMENT (
    enroll_id   INT          NOT NULL AUTO_INCREMENT,
    student_id  CHAR(8)      NOT NULL,
    course_id   VARCHAR(10)  NOT NULL,
    year        SMALLINT     NOT NULL,
    term        TINYINT      NOT NULL,
    grade       VARCHAR(2)   NOT NULL,
    PRIMARY KEY (enroll_id),
    UNIQUE KEY uq_enroll (student_id, course_id, year, term),
    CONSTRAINT fk_enr_student FOREIGN KEY (student_id) REFERENCES STUDENT (student_id),
    CONSTRAINT fk_enr_course  FOREIGN KEY (course_id)  REFERENCES COURSE (course_id),
    CONSTRAINT fk_enr_grade   FOREIGN KEY (grade)      REFERENCES GRADE_SCALE (grade),
    CONSTRAINT ck_term CHECK (term BETWEEN 1 AND 4)
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
    CONSTRAINT fk_gradreq_dept FOREIGN KEY (dept_id) REFERENCES DEPARTMENT (dept_id),
    CONSTRAINT ck_gradreq_years CHECK (to_year IS NULL OR to_year >= from_year)
);

CREATE TABLE GRAD_REQ_AREA (
    req_id       INT       NOT NULL,
    area_code    CHAR(2)   NOT NULL,
    min_credits  SMALLINT  NOT NULL,
    PRIMARY KEY (req_id, area_code),
    CONSTRAINT fk_gra_req  FOREIGN KEY (req_id)    REFERENCES GRAD_REQ (req_id) ON DELETE CASCADE,
    CONSTRAINT fk_gra_area FOREIGN KEY (area_code) REFERENCES AREA (area_code)
);

CREATE TABLE REQUIRED_COURSE (
    req_id     INT          NOT NULL,
    course_id  VARCHAR(10)  NOT NULL,
    PRIMARY KEY (req_id, course_id),
    CONSTRAINT fk_rc_req    FOREIGN KEY (req_id)    REFERENCES GRAD_REQ (req_id) ON DELETE CASCADE,
    CONSTRAINT fk_rc_course FOREIGN KEY (course_id) REFERENCES COURSE (course_id)
);

-- 과목군: "이 과목들 중에서 n학점 이상" 형태의 요건 (택1 글쓰기, SW/데이터활용역량 모듈 등).
-- 한 과목이 여러 과목군에 속할 수 있다 (예: 컴퓨터응용통계 = MSC 수학 이면서 SW 기초 모듈).
CREATE TABLE COURSE_GROUP (
    group_code   VARCHAR(20)   NOT NULL,
    group_name   VARCHAR(50)   NOT NULL,
    description  VARCHAR(300)  NULL,
    PRIMARY KEY (group_code)
);

CREATE TABLE COURSE_GROUP_MEMBER (
    group_code  VARCHAR(20)  NOT NULL,
    course_id   VARCHAR(10)  NOT NULL,
    PRIMARY KEY (group_code, course_id),
    CONSTRAINT fk_cgm_group  FOREIGN KEY (group_code) REFERENCES COURSE_GROUP (group_code) ON DELETE CASCADE,
    CONSTRAINT fk_cgm_course FOREIGN KEY (course_id)  REFERENCES COURSE (course_id)
);

CREATE TABLE GRAD_REQ_GROUP (
    req_id       INT          NOT NULL,
    group_code   VARCHAR(20)  NOT NULL,
    min_credits  SMALLINT     NOT NULL,
    PRIMARY KEY (req_id, group_code),
    CONSTRAINT fk_grg_req   FOREIGN KEY (req_id)     REFERENCES GRAD_REQ (req_id) ON DELETE CASCADE,
    CONSTRAINT fk_grg_group FOREIGN KEY (group_code) REFERENCES COURSE_GROUP (group_code)
);

CREATE TABLE SCHOLARSHIP (
    sch_id               INT           NOT NULL AUTO_INCREMENT,
    sch_name             VARCHAR(50)   NOT NULL,
    category             VARCHAR(10)   NOT NULL DEFAULT '성적우수',   -- 성적우수 / 가계지원 / 학과
    amount_desc          VARCHAR(30)   NULL,                          -- 등록금 전액, 80%, 90만원 ...
    min_gpa              DECIMAL(3,2)  NOT NULL,
    min_credits          SMALLINT      NOT NULL,       -- 직전 학기 취득 학점 기준
    max_income           TINYINT       NULL,           -- NULL 이면 소득 무관
    min_volunteer_hours  SMALLINT      NULL,           -- NULL 이면 봉사 조건 없음
    volunteer_exempt_level TINYINT     NULL,           -- 이 학년 이상은 봉사 조건 면제 (예: 4)
    dept_id              VARCHAR(10)   NULL,           -- NULL 이면 전체 학과 대상
    source_url           VARCHAR(300)  NULL,
    PRIMARY KEY (sch_id),
    CONSTRAINT fk_sch_dept FOREIGN KEY (dept_id) REFERENCES DEPARTMENT (dept_id)
);

-- ---------------------------------------------------------------- 집계
CREATE TABLE SEMESTER_RECORD (
    student_id         CHAR(8)       NOT NULL,
    year               SMALLINT      NOT NULL,
    term               TINYINT       NOT NULL,
    attempted_credits  SMALLINT      NOT NULL,   -- 신청 학점 (P/NP 포함)
    earned_credits     SMALLINT      NOT NULL,   -- 취득 학점 (F, NP 제외)
    gpa                DECIMAL(3,2)  NULL,       -- 평점 대상 과목이 없으면 NULL
    PRIMARY KEY (student_id, year, term),
    CONSTRAINT fk_sr_student FOREIGN KEY (student_id) REFERENCES STUDENT (student_id)
);

-- ---------------------------------------------------------------- 로그인
CREATE TABLE USER_ACCOUNT (
    login_id       VARCHAR(20)   NOT NULL,
    password_hash  VARCHAR(255)  NOT NULL,
    role           ENUM('student', 'admin') NOT NULL,
    student_id     CHAR(8)       NULL,
    dept_id        VARCHAR(10)   NULL,
    PRIMARY KEY (login_id),
    CONSTRAINT fk_ua_student FOREIGN KEY (student_id) REFERENCES STUDENT (student_id),
    CONSTRAINT fk_ua_dept    FOREIGN KEY (dept_id)    REFERENCES DEPARTMENT (dept_id),
    CONSTRAINT ck_ua_role CHECK (
        (role = 'student' AND student_id IS NOT NULL) OR
        (role = 'admin'   AND dept_id    IS NOT NULL)
    )
);

INSERT INTO DB_META (schema_version) VALUES (3);
