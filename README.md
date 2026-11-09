[README.md](https://github.com/user-attachments/files/34570035/README.md)
# 홍익대학교 클래스룸 기반 장학·졸업 요건 자동 진단 시스템

3팀 DB 팀프로젝트 웹사이트입니다. Flask + MySQL 8 + Jinja2로 만들었습니다.
장학금·졸업 판정 계산은 DB의 뷰와 트리거가 하고, 웹은 결과를 조회해 보여 줍니다.

## 가장 쉬운 실행 방법 (Windows)

> `app/templates/` 안의 HTML 파일을 브라우저로 직접 열면 `{{ }}` 같은 글자만 보입니다.
> 이 프로그램은 Python 서버가 DB 에서 데이터를 채워 화면을 만들기 때문에, 반드시 서버를 켜고 브라우저로 접속해야 합니다.

1. [Python](https://www.python.org/downloads/) 설치 (설치 첫 화면에서 **Add python.exe to PATH** 체크)
2. [MySQL Installer](https://dev.mysql.com/downloads/installer/) 로 MySQL Server 설치 (root 비밀번호를 정해 두기)
3. 이 폴더의 **start.bat** 더블클릭
   - 처음 한 번은 MySQL 비밀번호를 물어봅니다. 입력하면 `.env` 를 만들고 DB 와 샘플 데이터를 자동으로 넣습니다.
   - 잠시 후 브라우저가 http://127.0.0.1:5000 을 엽니다. 검은 창은 서버이므로 닫지 마세요.
4. 학생 `20211001` / `20211001`, 체험용 `demo` / `demo1234` 또는 담당자 `admin_sw` / `admin1234` 로 로그인

macOS 는 `start.command` 를 더블클릭하면 같은 순서로 실행됩니다.
DB 를 처음 상태로 되돌리려면 명령 창에서 `python init_db.py` 를 실행하세요.

아래는 같은 과정을 손으로 하는 방법입니다.

## 1. 준비물

- Python 3.10 이상
- MySQL 8.0 이상 (MySQL Workbench 를 써도 됩니다)

## 2. DB 만들기

`db/` 폴더의 SQL 파일 네 개를 **번호 순서대로** 실행합니다. 01번은 `grad_check` DB를 지우고 새로 만듭니다.

```bash
mysql -u root -p --default-character-set=utf8mb4 < db/01_schema.sql
mysql -u root -p --default-character-set=utf8mb4 < db/02_views.sql
mysql -u root -p --default-character-set=utf8mb4 < db/03_triggers.sql
mysql -u root -p --default-character-set=utf8mb4 < db/04_sample_data.sql
```

Workbench 에서는 파일을 열고 번개 아이콘(전체 실행)을 누르면 됩니다.

| 파일 | 내용 |
| --- | --- |
| 01_schema.sql | 테이블 16개 (기본·코드·기준·집계 + 과목군 3개 + USER_ACCOUNT, DB_META) |
| 02_views.sql | 판정 뷰 8개 (V_STUDENT_REQ, V_LATEST_ENROLLMENT, V_EARNED_COURSE, V_GRAD_AREA_STATUS, V_GRAD_GROUP_STATUS, V_MISSING_REQUIRED, V_GRAD_STATUS, V_SCHOLARSHIP_ELIGIBLE) |
| 03_triggers.sql | ENROLLMENT 트리거 3개 + GRAD_REQ 구간 겹침 검사 트리거 2개 + 재계산 프로시저 2개 |
| 04_sample_data.sql | 실제 공개 자료 기반 과목 79개·졸업요건 3구간·교내장학금 7종 + 가상 학생 6명 |

> 예전 버전 DB 가 있어도 start.bat 을 실행하면 구조가 바뀐 것을 알아채고 grad_check DB 를 새로 만듭니다.

## 3. 웹 실행

```bash
python -m venv .venv
# Windows: .venv\Scripts\activate   /   macOS·Linux: source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env      # Windows: copy .env.example .env
# .env 의 DB_USER, DB_PASSWORD 를 본인 MySQL 계정으로 수정
python run.py
```

브라우저에서 http://127.0.0.1:5000 을 엽니다.

## 4. 시연 계정

| 아이디 | 비밀번호 | 설명 |
| --- | --- | --- |
| 20211001 | 20211001 | 김홍익, 소프트웨어융합학과 2021학번(19~21학번 요건) 4학년. 종합설계(2) 미이수, 13학점 부족, 직전학기 3.40, 자료구조 재수강·자바 F |
| 20211003 | 20211003 | 박미대, 2021학번. 졸업 요건 모두 충족 |
| 20221005 | 20221005 | 이와우, 2022학번(22학번 이후 요건, SW/데이터활용역량 포함). 졸업 요건 모두 충족 |
| 20241002 | 20241002 | 최세종, 2024학번 3학년. 직전학기 4.32, 봉사 12시간, 소득 8분위 → 홍익인간 등 6종 지원 가능 |
| 20251004 | 20251004 | 정서울, 컴퓨터공학과 2025학번. 봉사 0시간이라 성적우수 4종 미달, 졸업요건 미등록 학과 예시 |
| demo | demo1234 | 체험학생(소융 2022학번), 성적 0건. **성적 가져오기**로 본인 성적을 넣어 보는 용도 |
| admin_sw | admin1234 | 소프트웨어융합학과 담당자 |
| admin_ce | admin1234 | 컴퓨터공학과 담당자 |

## 5. 발표 시연 순서

1. `20211001` 로그인 → 장학금 진단: 자주·창조장학금이 평점 0.10 부족으로 미달
2. `admin_sw` 로그인 → 학생 목록 → 김홍익 → "성적 · 수정" → 2026년 1학기 소프트웨어공학 B0 → A0
3. "2026년 1학기 평점 3.40 → 3.60, 새로 신청 가능: 자주장학금, 창조장학금" 표시 (UPDATE 트리거가 SEMESTER_RECORD 재계산)
4. 학번별 요건: `20211003`(2019~2021 기준)과 `20221005`(2022 이후 기준)의 졸업요건 화면을 비교하면 SW/데이터활용역량 과목군이 22학번에만 붙습니다.
5. 졸업요건 관리에서 2020~2023 처럼 겹치는 구간을 추가하면 트리거가 막습니다.
6. `demo` 로그인 → 성적 가져오기 → 클래스룸 성적표를 붙여 넣고 미리보기 → 저장 → 대시보드가 바로 바뀝니다.
   처음 상태로 되돌리려면 `python init_db.py`

## 5-1. 성적 가져오기 (클래스룸)

클래스룸 전체 성적 조회 화면의 표를 머리글부터 드래그해 복사(Ctrl+C)한 뒤 붙여 넣습니다. 엑셀에서 저장한 CSV 파일도 됩니다.
학교 계정이나 비밀번호는 쓰지 않습니다. 칸 순서는 상관없고 다음 머리글을 알아봅니다:
년도/연도/학년도, 학기(1·2·여름·겨울), 학수번호, 과목명/교과목명, 이수구분(전필·전선·MSC·기교·교선 등), 학점, 등급/성적.
머리글이 없으면 값 모양으로 칸을 추정하고, "2025학년도 1학기" 같은 제목 줄 형식도 읽습니다.
학수번호나 과목명(“(sw심화)” 표기 무시)으로 기존 과목과 맞추고, 없는 과목은 붙여 넣은 이수구분·학점으로 새로 등록합니다(학수번호가 없으면 U 로 시작하는 코드).
클래스룸 성적표 형식("2026학년도 3학년 1학기" 제목 줄, 학수번호·과목명·영문과목명·학점·성적·재수강, "신청학점 19 신청평점 4.16" 줄)을 여러 학기 한꺼번에 붙여 넣어도 읽습니다. 성적표에는 이수구분이 없어서, DB 에 없는 과목은 학수번호 앞자리(001 기초교양, 002 일반교양, 004 핵심교양, 012x MSC, 7xxxxx 전공선택 등)로 짐작합니다.

## 6. 화면과 요구사항

| 화면 | URL | 요구사항 |
| --- | --- | --- |
| 로그인 | /login | - |
| 대시보드 | /me | FR-07 |
| 성적 조회 | /me/grades | 재수강 표시 (ROW_NUMBER) |
| 졸업요건 진단 | /me/graduation | FR-01, FR-03, FR-04 |
| 장학금 진단 | /me/scholarships | FR-02 |
| 성적 가져오기 | /me/import | 붙여넣기·CSV → COURSE/ENROLLMENT (트랜잭션) |
| 학생 목록 | /admin/students | 검색(LIKE), 영역 미달 필터(HAVING) |
| 학생 상세 | /admin/students/<학번> | FR-01~04 |
| 성적 수정 | /admin/enrollments/<id>/edit | FR-05 (트리거) |
| 졸업요건 관리 | /admin/grad-reqs | FR-06 (트랜잭션) |
| 장학금 관리 | /admin/scholarships | FR-06 |

## 7. 판정 기준과 데이터 출처

판정 규칙
- 졸업요건은 학과 + **입학연도 구간**(2016~2018 / 2019~2021 / 2022~) 단위. 학생 입학연도가 든 구간이 자동 적용
- 영역은 묶음 구조: 전공(=전공필수+전공선택), MSC(=수학+과학+전산), 전문교양(=기초+핵심+일반교양)
- "이 과목들 중 n학점" 요건은 과목군(COURSE_GROUP)으로 판정: 글쓰기 택1, 특성화교양 택1, 예술과디자인·제2외국어와한문 영역, SW/데이터활용역량
- SW/데이터활용역량(2022학번~)은 "심화 3 · 기초 이상 6 · 전체 9학점" 세 과목군으로 표현해 상위 모듈이 하위 모듈을 대신하는 규칙을 반영
- 재수강은 최근 성적만, F·NP 는 학점 미인정, P·NP 는 평점 제외
- 장학금 "직전 학기"는 계절학기를 뺀 가장 최근 정규학기. 평점·취득학점·소득분위·봉사시간(4학년 면제)으로 최소 자격만 판정 (실제 선발은 학과 내 경쟁)

실제 자료에서 가져온 것
| 항목 | 출처 |
| --- | --- |
| 학번별 졸업학점 현황 (132학점, 전공 54, MSC 30, 전문교양 23, 특성화교양 3, SW/데이터 9, 전공기초영어 2) | 2026 교과과정 책자 "교과과정 개편에 따른 졸업학점 현황" AID융합과학기술대학 |
| 과목·학수번호·학점·이수구분, MSC 과목, 교양 과목, SW/데이터 인증 과목 | 2026 교과과정 책자, https://software.hongik.ac.kr/home/templates/curriculum/curriculum |
| 전공필수 5과목, 인증필수(창의적공학설계입문) | https://software.hongik.ac.kr/home/templates/curriculum/required |
| 설계학점(과목별), 최소 설계 12학점 | https://software.hongik.ac.kr/home/templates/curriculum/courses |
| 교내장학금 7종 (홍익인간·자주·창조·협동·정진·한마음·면학), 최소 평점, 15학점, 봉사 10시간, 소득분위 | https://www.hongik.ac.kr/kr/education/school-scholarship.do |
| 성적경고 기준 1.75 (한마음·면학 최소 평점으로 사용) | 컴퓨터공학과 2026-2 교내장학생 선발 기준 공지 |

확인하지 못했거나 단순화한 것
- DB 에 없는 과목의 이수구분 (학수번호로 짐작)
- 전문교양 "7개 영역 중 6개 영역", 교양 최대 40학점 인정, MSC 과학 "실험 포함 1set", 영어 요건: 시스템이 판정하지 않고 화면에 안내만 함
- 교양 과목의 핵심/일반교양 구분 일부와 SW/데이터 인증 교양과목 학점(3학점으로 가정)
- 한마음·면학의 최소 취득학점 (15학점으로 가정), 국가장학금 신청 여부, 학과별 자체 선발 규정
- 졸업 최소 평점 기준 (자료에서 찾지 못해 "기준 없음")
- 컴퓨터공학과(서울) 졸업요건은 이번 범위에서 등록하지 않음
- 학생과 성적은 모두 가상의 데이터

## 8. 폴더 구조

```
db/            SQL 스크립트 (01~04)
app/
  __init__.py  앱 생성
  db.py        PyMySQL 연결
  auth.py      로그인, 역할 체크
  student.py   학생 화면, 성적 가져오기
  importer.py  클래스룸 성적표 붙여넣기 / CSV 파서
  admin.py     담당자 화면
  queries/     화면별 SQL 모음 (발표 때 보여주기 좋게 분리)
  templates/   HTML (Jinja2)
  static/      CSS, Bootstrap 5.3.3 (오프라인에서도 동작하도록 포함)
config.py      .env 에서 DB 접속 정보 읽기
init_db.py     db/01~04 를 순서대로 실행 (start.bat 이 사용)
start.bat      Windows 원클릭 실행 / start.command 는 macOS 용
run.py         실행 파일
```
