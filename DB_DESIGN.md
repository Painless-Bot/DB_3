# DB 설계 설명 (schema v4)

장학·졸업 요건 진단 시스템의 테이블·뷰·트리거 구성과, v3(16개 테이블)에서 v4(14개 테이블)로 정리한 근거를 정리한 문서입니다.
발표 때 "왜 테이블이 이렇게 많은가?"라는 질문에 답하는 용도로 씁니다.

## 1. 테이블 14개 한눈에 보기

| 묶음 | 테이블 | 왜 따로 있어야 하나 |
| --- | --- | --- |
| 핵심 (원래 ERD 6개) | DEPARTMENT, STUDENT, COURSE, ENROLLMENT, GRAD_REQ, SCHOLARSHIP | 요구사항 정의서 DR-01~06 그대로 |
| 코드 | AREA | 이수구분 이름·묶음(전공 = 전공필수 + 전공선택)을 과목마다 반복하지 않으려고 (3NF) |
| 코드 | GRADE_SCALE | 등급 → 평점, 학점 인정 여부, 평점 포함 여부(P/NP/F). 수강 행마다 평점을 저장하면 갱신 이상 |
| 요건 상세 | GRAD_REQ_AREA | 졸업요건 1건에 영역별 최소 학점이 여러 개 (1:N, 반복 그룹 제거 = 1NF) |
| 요건 상세 | COURSE_GROUP, COURSE_GROUP_MEMBER | "이 과목들 중 n학점"·"이 과목들 모두" 같은 과목 묶음. 한 과목이 여러 묶음에 들어가므로 M:N |
| 요건 상세 | GRAD_REQ_GROUP | 졸업요건 ↔ 과목군 M:N. 같은 과목군을 여러 학번 구간이 같이 씀 |
| 집계 | SEMESTER_RECORD | 학기별 평점·취득학점. ENROLLMENT 에서 계산되는 값이지만 장학 판정마다 다시 계산하지 않도록 저장 (MySQL 에는 materialized view 가 없음). 트리거만 고치므로 원본과 어긋나지 않음 (FR-05) |
| 로그인 | USER_ACCOUNT | 학생·담당자 계정. 비밀번호 해시와 역할을 학생 정보와 분리 |

## 2. v3 → v4 에서 줄인 것 (16 → 14)

| v3 | v4 | 근거 |
| --- | --- | --- |
| DB_META (버전 숫자 한 줄) | 저장 함수 `fn_schema_version()` | 업무 데이터가 아닌 설치용 정보라 테이블로 둘 이유가 없음 |
| REQUIRED_COURSE (요건 × 필수과목) | COURSE_GROUP 에 `rule_type = 'ALL'` 과목군으로 통합 | 필수과목 목록은 "모두 이수해야 하는 과목 묶음"이라 과목군과 구조가 같음. v3 에서는 학번 구간 3개가 같은 7과목을 각각 저장(21행, 같은 목록이 세 번 반복)했는데, v4 는 과목군 1개(7행)를 세 요건이 연결만 함(3행). 필수과목을 바꿀 때 한 곳만 고치면 됨 |

더 줄이지 않은 이유
- GRAD_REQ_AREA·GRAD_REQ_GROUP 을 한 테이블로 합치면 `area_code` 와 `group_code` 중 하나만 채우는 칼럼이 생겨 외래키를 걸 수 없음 (NFR-03 정합성 위반).
- AREA·GRADE_SCALE 을 없애고 COURSE·ENROLLMENT 에 이름·평점을 직접 쓰면 이행 종속이 생겨 3NF 를 깸.
- SEMESTER_RECORD 를 뷰로 바꾸면 테이블은 하나 줄지만 FR-05(트리거로 자동 재판정) 시연 대상이 사라지고, 학과 전체 장학 판정이 느려짐.

## 3. 효율을 위해 바꾼 것

| 바꾼 것 | 이유 |
| --- | --- |
| `ENROLLMENT.sem_order`, `SEMESTER_RECORD.sem_order` 저장 생성 칼럼 (2025년 1학기 = 20251, 여름 20252, 2학기 20253, 겨울 20254) | 학기 순서(1학기 < 여름 < 2학기 < 겨울)를 함수 없이 인덱스로 비교. year·term 에서 자동 계산되므로 갱신 이상 없음 |
| UNIQUE `(student_id, course_id, sem_order)` | 중복 수강 방지 + "더 최근 수강이 있는가"를 인덱스만으로 확인 |
| 인덱스 `ENROLLMENT(student_id, year, term)`, `STUDENT(dept_id, admission_year)`, `GRAD_REQ(dept_id, from_year)` 등 | 트리거의 학기 재계산, 학과별 목록, 학생 → 적용 요건 조인 |
| V_LATEST_ENROLLMENT: `ROW_NUMBER()` → `NOT EXISTS (더 최근 수강)` | 윈도 함수가 든 뷰는 MySQL 이 전체 학생을 임시 테이블로 만든 뒤 거름. NOT EXISTS 는 뷰가 합쳐져(MERGE) 학번 조건이 바로 먹힘 |
| V_GRAD_STATUS·V_GRAD_AREA_STATUS·V_GRAD_GROUP_STATUS: 바깥 GROUP BY → `LATERAL` 파생 테이블 | 학생 한 명을 조회하면 그 학생 행에 대해서만 합계를 계산 |
| 학과 전체 목록·통계: CTE 집합 쿼리 (`app/queries/admin_q.py`) | 학생별 LATERAL 을 학생 수만큼 반복하지 않고, 학과 학생의 인정 과목을 한 번만 만들어 GROUP BY. 결과가 V_GRAD_STATUS 와 모든 학생에서 같은지 `tools/bench.py --check` 로 검사 |
| CHECK 제약 추가 (평점 0~4.5, 설계학점 ≤ 학점, 소득분위 0~10 등), SCHOLARSHIP.category ENUM | 잘못된 기준값이 들어가지 않도록 |
| 트리거 `trg_course_au` | 과목 학점을 고치면 그 과목을 들은 모든 학기의 SEMESTER_RECORD 를 다시 계산 (v3 에서는 예전 값이 남는 갱신 이상) |
| 트리거 `trg_grg_bi/bu` | "n학점 이상" 과목군은 기준 학점 필수, "모두 이수" 과목군은 기준 없음 — 다른 테이블 값에 따른 조건이라 CHECK 대신 트리거 |

### 측정 결과 (`python tools/bench.py 2000 --check`)

가상 학생 2000명(한 학과), 수강 8.7만 건, MySQL 8.0.46. 같은 장비에서 v3 와 v4 를 비교했습니다.

| 화면 | v3 | v4 |
| --- | ---: | ---: |
| 학생 1명 대시보드 (load_report 판정 쿼리 8개 합계) | 11,837 ms | 약 30 ms |
|  └ 졸업 판정 요약 V_GRAD_STATUS | 8,765 ms | 약 14 ms |
| 추천 과목 V_COURSE_SUGGESTION (v4 신규) | - | 약 37 ms |
| 담당자 학생 목록 (학과 2000명 전체 판정) | 9,589 ms | 약 2,900 ms |
| 성적 수정 1건 (트리거 재계산) | 2.2 ms | 1.5 ms |

판정 결과는 v3 와 v4 가 샘플 학생 전원에서 같습니다 (졸업 판정·영역·과목군·미이수 필수·장학 판정·학기 기록 비교).

## 4. 뷰 10개

| 뷰 | 내용 | 요구사항 |
| --- | --- | --- |
| V_STUDENT_REQ | 학생 → 입학연도 구간에 맞는 졸업요건 | |
| V_LATEST_ENROLLMENT | 재수강 시 최신 성적 1건 | |
| V_EARNED_COURSE | 학점 인정 과목 (F·NP 제외) | |
| V_GRAD_AREA_STATUS | 영역별 기준/이수/남은 학점 (묶음 영역 합산) | FR-03 |
| V_GRAD_GROUP_STATUS | 과목군별 이수 현황 (CREDITS·ALL) | |
| V_MISSING_REQUIRED | 미이수 필수과목 (NOT EXISTS) | FR-04 |
| V_GRAD_STATUS | 학생별 졸업 판정 요약 | FR-01, FR-07 |
| V_LAST_SEMESTER | 직전 정규학기 | |
| V_SCHOLARSHIP_ELIGIBLE | 장학금별 지원 가능 여부 | FR-02 |
| V_COURSE_SUGGESTION | 부족한 요건을 채우는 미이수 과목 (UNION ALL) | v4 추가 |

## 5. 트리거 8개 · 프로시저 3개

- ENROLLMENT INSERT/UPDATE/DELETE → `sp_recalc_semester` (FR-05)
- COURSE UPDATE(학점 변경) → `sp_recalc_course`
- GRAD_REQ INSERT/UPDATE → 같은 학과 입학연도 구간 겹침 차단
- GRAD_REQ_GROUP INSERT/UPDATE → 과목군 규칙에 맞는 기준 학점 검사
- `sp_recalc_all` : 트리거 없이 넣은 데이터를 한 번에 재계산

## 6. 수강 계획 시뮬레이터가 DB 를 쓰는 방법

학생이 고른 가상 수강을 **같은 트랜잭션 안에서 ENROLLMENT 에 INSERT** → 트리거가 SEMESTER_RECORD 를 계산 → 판정 뷰를 읽음 → **ROLLBACK**.
판정 로직을 파이썬에 다시 만들지 않고 실제 뷰·트리거를 그대로 쓰며, 다른 사용자에게는 커밋 전 데이터가 보이지 않고(격리성) 끝나면 아무것도 남지 않습니다.
