"""db/04_sample_data.sql 생성기.  사용: python tools/gen_sample.py db/04_sample_data.sql"""
import sys
from werkzeug.security import generate_password_hash

OUT = sys.argv[1]
SRC_CUR = "https://software.hongik.ac.kr/home/templates/curriculum/curriculum"
SRC_DES = "https://software.hongik.ac.kr/home/templates/curriculum/courses"
SRC_REQ = "https://software.hongik.ac.kr/home/templates/curriculum/required"
SRC_STD = "https://software.hongik.ac.kr/home/templates/curriculum/standard"
SRC_ICEE = "http://icee.hongik.ac.kr/_2011/authentication/requirements.php"
SRC_2020 = "https://software.hongik.ac.kr/home/templates/community/home_upload/20200221094733.pdf"
SRC_SWD = "https://france.hongik.ac.kr/france/0401.do?mode=view&articleNo=132497"
SRC_MSE = "https://mse.hongik.ac.kr/mse/0403.do"
SRC_BOOK = "2026 교과과정 책자 (홍익대학교, 교과과정 개편에 따른 졸업학점 현황 - AID융합과학기술대학)"
SRC_SCH = "https://www.hongik.ac.kr/kr/education/school-scholarship.do"
SRC_CE_SCH = "https://wwwce.hongik.ac.kr/wwwce/0401.do?mode=view&articleNo=148975"

AREAS = [  # code, name, parent, order
    ("MJ", "전공", None, 10), ("MR", "전공필수", "MJ", 11), ("ME", "전공선택", "MJ", 12),
    ("MS", "MSC", None, 20), ("MM", "MSC 수학", "MS", 21), ("MP", "MSC 과학", "MS", 22), ("MC", "MSC 전산", "MS", 23),
    ("LA", "전문교양", None, 30), ("BL", "기초교양", "LA", 31), ("CL", "핵심교양", "LA", 32), ("GL", "일반교양", "LA", 33),
    ("SL", "특성화교양", None, 40), ("EN", "전공기초영어", None, 50), ("FE", "일반선택", None, 60),
]

# (학수번호, 과목명, 학점, 설계학점, 이수구분, 학과)
C = [
    # 기초교양
    ("001012", "논리적사고와글쓰기(공학)", 3, 0, "BL", None),
    ("001020", "공학글쓰기", 3, 0, "BL", None),
    ("001023", "대학영어", 3, 0, "BL", None),
    ("004004", "대학생활세미나", 1, 0, "BL", None),
    # 핵심·일반교양 (영역)
    ("002586", "동양사의이해", 3, 0, "GL", None),          # 3영역 역사와문화
    ("002442", "경제학입문", 3, 0, "GL", None),            # 2영역 사회와경제
    ("002059", "현대생활과디자인", 3, 0, "GL", None),      # 4영역 예술과디자인
    ("002605", "교양한문(1)", 3, 0, "GL", None),           # 5영역 제2외국어와한문
    ("004176", "컴퓨터정보통신공학개론", 3, 0, "CL", None),  # 7영역 공학의이해
    ("008753", "CTO특강", 2, 0, "CL", None),               # 7영역 공학의이해
    ("004189", "산업·데이터공학의이해", 3, 0, "CL", None),   # 6영역 법과생활
    ("961823", "컴퓨터소프트웨어개론", 3, 0, "GL", None),    # SW 소양
    ("002641", "체험인공지능", 3, 0, "GL", None),           # SW 소양
    ("002618", "빅데이터의이해", 3, 0, "GL", None),          # SW 기초
    ("002534", "컴퓨터활용기초", 3, 0, "GL", None),          # SW 기초
    ("002579", "컴퓨터SW기초", 3, 0, "GL", None),            # SW 기초
    ("002682", "인터페이스디자인입문", 3, 0, "GL", None),    # SW 기초
    ("002822", "데이터분석의이해", 3, 0, "GL", None),        # SW 기초
    ("002653", "언어와빅데이터", 3, 0, "GL", None),          # SW 심화
    ("002654", "통계와코딩", 3, 0, "GL", None),              # SW 심화
    ("002658", "데이터시각화와스토리텔링", 3, 0, "GL", None), # SW 심화
    # 특성화교양 / 전공기초영어
    ("008751", "디자인씽킹", 3, 0, "SL", None),
    ("008752", "창업과실용법률", 3, 0, "SL", None),
    ("008754", "컴퓨팅사고", 3, 0, "SL", None),
    ("007114", "전공기초영어(Ⅰ)", 2, 0, "EN", None),
    ("007115", "전공기초영어(Ⅱ)", 2, 0, "EN", None),
    # MSC
    ("012201", "대학수학(1)", 3, 0, "MM", None),
    ("012202", "대학수학(2)", 3, 0, "MM", None),
    ("012205", "선형대수학", 3, 0, "MM", None),
    ("012206", "이산수학", 3, 0, "MM", None),
    ("012208", "컴퓨터응용통계", 3, 0, "MM", None),
    ("012102", "대학물리(1)", 3, 0, "MP", None),
    ("012103", "대학물리실험(1)", 1, 0, "MP", None),
    ("012105", "대학물리(2)", 3, 0, "MP", None),
    ("012106", "대학물리실험(2)", 1, 0, "MP", None),
    ("012108", "대학화학(1)", 3, 0, "MP", None),
    ("012109", "대학화학실험(1)", 1, 0, "MP", None),
    ("012112", "대학화학(2)", 3, 0, "MP", None),
    ("012113", "대학화학실험(2)", 1, 0, "MP", None),
    ("012301", "공학컴퓨터입문및실습", 3, 0, "MC", None),
    ("012316", "파이썬프로그래밍(입문)", 3, 0, "MC", None),
    ("012317", "파이썬프로그래밍(응용)", 3, 0, "MC", None),
    ("012315", "파이썬프로그래밍", 3, 0, "MC", None),
    ("101810", "C-프로그래밍", 3, 0, "MC", None),
    ("012306", "웹프로그래밍", 3, 0, "MC", None),
    # 소프트웨어융합학과 전공
    ("725843", "창의적공학설계입문", 2, 2, "ME", "SW"),
    ("704305", "회로이론", 3, 0, "ME", "SW"),
    ("704818", "자료구조및프로그래밍실습", 3, 0, "MR", "SW"),
    ("704308", "지능형전자시스템", 3, 0, "ME", "SW"),
    ("704509", "디지털공학", 3, 0, "ME", "SW"),
    ("704413", "머신러닝및실습(1)", 3, 0, "MR", "SW"),
    ("704406", "신호및시스템", 3, 0, "ME", "SW"),
    ("704410", "컴퓨터구조", 3, 0, "ME", "SW"),
    ("704827", "자바프로그래밍및실습", 3, 0, "ME", "SW"),
    ("704831", "인터넷공학(1)", 3, 0, "ME", "SW"),
    ("704612", "머신러닝및실습(2)", 3, 1, "MR", "SW"),
    ("704826", "알고리즘및실습", 4, 1, "MR", "SW"),
    ("704502", "확률및불규칙변수", 3, 0, "ME", "SW"),
    ("704512", "운영체제", 3, 0, "ME", "SW"),
    ("704828", "디자인패턴프로그래밍및실습", 3, 2, "ME", "SW"),
    ("704832", "인터넷공학(2)", 3, 0, "ME", "SW"),
    ("704511", "네트워크실험", 3, 1, "ME", "SW"),
    ("704514", "통신이론", 3, 0, "ME", "SW"),
    ("704816", "데이터사이언스", 3, 0, "ME", "SW"),
    ("704822", "데이터베이스및실습", 3, 1, "ME", "SW"),
    ("704833", "오픈소스소프트웨어", 3, 1, "ME", "SW"),
    ("704836", "통신프로그래밍", 3, 1, "ME", "SW"),
    ("190426", "엔지니어앙트레프레너십", 1, 0, "ME", "SW"),
    ("704711", "종합설계(1)", 3, 3, "ME", "SW"),
    ("704713", "가상현실프로그래밍", 3, 0, "ME", "SW"),
    ("704805", "소프트웨어공학", 3, 1, "ME", "SW"),
    ("704811", "컴퓨터보안", 3, 1, "ME", "SW"),
    ("704817", "모바일시스템융합및실습", 3, 0, "ME", "SW"),
    ("704814", "종합설계(2)", 3, 3, "MR", "SW"),
    ("704825", "안드로이드프로그래밍및실습", 3, 0, "ME", "SW"),
    ("704837", "블록체인개론", 3, 0, "ME", "SW"),
    ("704839", "지능형무선통신네트워크", 3, 0, "ME", "SW"),
    ("704840", "인공지능", 3, 0, "ME", "SW"),
    ("002742", "졸업논문", 3, 0, "FE", None),
]
CID = {c[0] for c in C}

SWD_ADV = ["012316", "012317", "012315", "002653", "002654", "002658",          # 인증트랙 심화
           "012301", "101810", "012306",                                        # MSC 전산 (전문대체)
           "704818", "704413", "704308", "704509", "704406", "704410", "704827", "704831",
           "704612", "704826", "704502", "704512", "704828", "704832", "704511", "704514",
           "704816", "704822", "704833", "704836", "704814", "704711", "704713", "704805",
           "704811", "704817", "704825", "704837", "704839", "704840"]          # (sw심화) 전공
SWD_BASIC = ["002534", "002579", "002618", "012208", "004176", "002682", "002822"]
SWD_CORE = ["008754", "961823", "002641"]

GROUPS = [
    ("WRITING", "글쓰기 (택1)", "논리적사고와글쓰기(공학) 또는 공학글쓰기 중 1과목 (기초교양 필수)",
     ["001012", "001020"]),
    ("SPECIAL_LA", "특성화교양 (택1)", "디자인씽킹 또는 창업과실용법률 중 1과목 (2019학번~)", ["008751", "008752"]),
    ("ART_DESIGN", "예술과디자인 영역", "전문교양 4영역 1과목 이상 (클래스룸 일교4)", ["002059"]),
    ("LANG_HANMUN", "제2외국어와한문 영역", "전문교양 5영역 1과목 이상 (클래스룸 일교5)", ["002605"]),
    ("SWD_1_ADV", "SW/데이터 심화 모듈", "심화 모듈 1과목 이상. (sw심화) 표기 전공과목·MSC 전산 인정 (2022학번~)", SWD_ADV),
    ("SWD_2_BASIC", "SW/데이터 기초 모듈 이상", "기초+심화 모듈 6학점 이상. 상위 모듈이 하위 모듈을 대신함 (2022학번~)", SWD_BASIC + SWD_ADV),
    ("SWD_3_TOTAL", "SW/데이터활용역량 9학점", "소양·기초·심화 모듈 합계 9학점 (2022학번~)", SWD_CORE + SWD_BASIC + SWD_ADV),
]

MAJOR_REQ = ["704818", "704413", "704612", "704826", "704814", "725843", "704711"]
NOTE = ("시스템 미판정: 전문교양은 1~7영역 중 6개 영역(예술과디자인·제2외국어와한문 포함) 각 1과목, "
        "교양 인정 최대 40학점, MSC 과학은 실험 포함 1set, 영어·설계 세부사항은 공학교육인증 이수내규를 따름.")
# 택1·영역 필수는 '1과목 이상' 이라 최소 1학점으로 둔다 (2학점짜리 교양프랑스어(2)도 인정)
BASE_GROUPS = [("WRITING", 1), ("ART_DESIGN", 1), ("LANG_HANMUN", 1)]
REQS = [  # from, to, 특성화교양 학점, groups
    (2016, 2018, 0, BASE_GROUPS),
    (2019, 2021, 3, BASE_GROUPS + [("SPECIAL_LA", 1)]),
    (2022, None, 3, BASE_GROUPS + [("SPECIAL_LA", 1), ("SWD_1_ADV", 3), ("SWD_2_BASIC", 6), ("SWD_3_TOTAL", 9)]),
]
AREA_REQ = [("MJ", 54), ("MS", 30), ("MM", 3), ("MP", 4), ("MC", 3), ("LA", 23), ("BL", 6), ("EN", 2)]

# 4년 표준 이수 계획 (학년-학기 → 과목)
PLAN = {
    "1-1": ["001012", "004004", "012201", "012102", "012103", "012301", "725843"],
    "1-2": ["001023", "002586", "002442", "012202", "101810", "012108", "012109"],
    "2-1": ["002059", "012205", "012208", "704818", "704308", "012105"],
    "2-2": ["012206", "704413", "704410", "704827", "704831", "012316"],
    "3-1": ["002605", "704612", "704826", "704512", "704828", "008751"],
    "3-2": ["704822", "704833", "704816", "004176", "007114", "704305"],
    "4-1": ["004189", "704711", "704805", "704811", "704817", "008753"],
    "4-2": ["704814", "704840", "002742"],
}

def grades(seq, pattern):
    return [(c, pattern[i % len(pattern)]) for i, c in enumerate(seq)]

ENR = []
def add(sid, year, term, pairs):
    for c, g in pairs:
        assert c in CID, c
        ENR.append((sid, c, year, term, g))

# 박미대: 2021학번, 2019~2021 기준 전부 충족 (2024 휴학)
for (k, y, t) in [("1-1", 2021, 1), ("1-2", 2021, 2), ("2-1", 2022, 1), ("2-2", 2022, 2),
                  ("3-1", 2023, 1), ("3-2", 2023, 2), ("4-1", 2025, 2), ("4-2", 2026, 1)]:
    add("20211003", y, t, grades(PLAN[k], ["A0", "A+", "B+", "A0"]))

# 이와우: 2022학번, 같은 과목을 들었지만 22학번 기준 SW/데이터활용역량 '소양' 미이수 (2025-1 휴학)
for (k, y, t) in [("1-1", 2022, 1), ("1-2", 2022, 2), ("2-1", 2023, 1), ("2-2", 2023, 2),
                  ("3-1", 2024, 1), ("3-2", 2024, 2), ("4-1", 2025, 2), ("4-2", 2026, 1)]:
    add("20221005", y, t, grades(PLAN[k], ["B+", "A0", "B0", "A0", "B+"]))

# 김홍익: 2021학번, 군휴학 2023~2024, 현재 4학년 2학기. 자료구조 재수강, 자바 F
add("20211001", 2021, 1, grades(PLAN["1-1"], ["B+", "A0", "B0"]))
add("20211001", 2021, 2, grades(PLAN["1-2"], ["B0", "B+", "A0"]))
add("20211001", 2022, 1, grades(PLAN["2-1"], ["B+", "B0", "A0", "C+", "B+", "B0"]))
add("20211001", 2022, 2, grades(PLAN["2-2"], ["B0", "B+", "B0", "F", "A0", "B+"]))
add("20211001", 2025, 1, grades(PLAN["3-1"], ["B+", "B0", "B+", "A0", "B0", "A0"]) + [("704818", "A0")])
add("20211001", 2025, 2, grades(PLAN["3-2"], ["A0", "B+", "B0", "B+", "A0", "B+"]))
add("20211001", 2026, 1, [("004189", "A0"), ("704711", "B+"), ("704805", "B0"), ("704811", "B+"), ("704817", "B0")])

# 최세종: 2024학번(22학번 이후 기준), 3학년, 성적 최상위, 봉사 12시간, 소득 8분위
add("20241002", 2024, 1, grades(PLAN["1-1"], ["A+", "A0"]))
add("20241002", 2024, 2, grades(PLAN["1-2"] + ["961823"], ["A0", "A+"]))
add("20241002", 2025, 1, grades(PLAN["2-1"], ["A+", "A+", "A0"]))
add("20241002", 2025, 2, grades(PLAN["2-2"], ["A0", "A+", "A+"]))
add("20241002", 2026, 1, grades(PLAN["3-1"], ["A+", "A+", "A0", "A+", "A0", "A+"]))

# 정서울: 컴퓨터공학과 2025학번, 2학년, 봉사 0시간 → 성적우수(봉사 필수)는 미달, 정진·가계지원은 가능
add("20251004", 2025, 1, grades(["001012", "004004", "012201", "012102", "012103", "012301"], ["A0", "B+"]))
add("20251004", 2025, 2, grades(["001023", "002442", "012202", "101810", "002641"], ["B+", "A0", "B0"]))
add("20251004", 2026, 1, grades(["002586", "012205", "012206", "012316", "002618"], ["A0", "B+", "A0", "A+", "B+"]))

STUDENTS = [  # id, name, dept, adm, level, income, volunteer
    ("20211001", "김홍익", "SW", 2021, 4, 3, 0),
    ("20211003", "박미대", "SW", 2021, 4, 7, 0),
    ("20221005", "이와우", "SW", 2022, 4, 6, 4),
    ("20241002", "최세종", "SW", 2024, 3, 8, 12),
    ("20251004", "정서울", "CE", 2025, 2, 2, 0),
    ("20229999", "체험학생", "SW", 2022, 4, 5, 10),
]
ACCOUNTS = [(s[0], s[0], "student", s[0], None) for s in STUDENTS if s[0] != "20229999"] + [
    ("demo", "demo1234", "student", "20229999", None),
    ("admin_sw", "admin1234", "admin", None, "SW"),
    ("admin_ce", "admin1234", "admin", None, "CE"),
]

SCH = [  # name, cat, amount, gpa, credits, income, vol, exempt
    ("홍익인간장학금", "성적우수", "등록금 전액", "4.00", 15, None, 10, 4),
    ("자주장학금", "성적우수", "등록금 80%", "3.50", 15, None, 10, 4),
    ("창조장학금", "성적우수", "등록금 60%", "3.50", 15, None, 10, 4),
    ("협동장학금", "성적우수", "등록금 40%", "3.30", 15, None, 10, 4),
    ("정진장학금", "성적우수", "90만원", "3.00", 15, None, None, None),
    ("한마음장학금", "가계지원", "등록금 50%", "1.75", 15, 5, None, None),
    ("면학장학금", "가계지원", "120만원", "1.75", 15, 8, None, None),
]

def q(v):
    if v is None:
        return "NULL"
    if isinstance(v, int):
        return str(v)
    return "'" + str(v).replace("'", "''") + "'"

def row(*vals):
    return "(" + ", ".join(q(v) for v in vals) + ")"

L = []
w = L.append
w("""-- =====================================================================
-- 04_sample_data.sql : 시연용 데이터 (schema v2)
--   과목·졸업요건·장학금 = 홍익대학교 공개 자료 기준 (출처는 각 INSERT 위 주석과 README 참고)
--   학생·성적 = 가상의 인물 (실제 클래스룸 데이터 아님)
--   course_id 는 2026 교과과정 책자의 학수번호. 성적 가져오기로 새로 생긴 과목은 U 로 시작한다.
--   ENROLLMENT 를 넣는 순간 트리거가 SEMESTER_RECORD 를 채운다.
--   이 파일은 tools/gen_sample.py 로 생성했다.
-- =====================================================================
USE grad_check;
""")
w("INSERT INTO AREA (area_code, area_name, parent_code, sort_order) VALUES")
# parents first
ordered = [a for a in AREAS if a[2] is None] + [a for a in AREAS if a[2] is not None]
w(",\n".join("    " + row(*a) for a in ordered) + ";\n")
w("""INSERT INTO GRADE_SCALE (grade, grade_point, earns_credit, in_gpa) VALUES
    ('A+', 4.5, 1, 1), ('A0', 4.0, 1, 1), ('B+', 3.5, 1, 1), ('B0', 3.0, 1, 1),
    ('C+', 2.5, 1, 1), ('C0', 2.0, 1, 1), ('D+', 1.5, 1, 1), ('D0', 1.0, 1, 1),
    ('F', 0.0, 0, 1), ('P', NULL, 1, 0), ('NP', NULL, 0, 0);
""")
w("""INSERT INTO DEPARTMENT (dept_id, dept_name, campus, homepage) VALUES
    ('SW', '소프트웨어융합학과', '세종', 'https://software.hongik.ac.kr/home'),
    ('CE', '컴퓨터공학과', '서울', 'https://wwwce.hongik.ac.kr/wwwce/index.do');
""")
w(f"-- 과목: 교과과정 {SRC_CUR}\n--       설계학점 {SRC_DES}\n--       SW/데이터활용역량 과목 {SRC_SWD}")
w("INSERT INTO COURSE (course_id, course_name, credits, design_credits, area_code, dept_id) VALUES")
w(",\n".join("    " + row(*c) for c in C) + ";\n")
w(f"-- 과목군: 기초교양 필수(택1) {SRC_CUR}, 전문교양 필수영역 {SRC_ICEE},\n--         기초과학 2세트(2022~) {SRC_MSE}, SW/데이터활용역량(2022~) {SRC_SWD}")
w("INSERT INTO COURSE_GROUP (group_code, group_name, description) VALUES")
w(",\n".join("    " + row(g[0], g[1], g[2]) for g in GROUPS) + ";")
w("INSERT INTO COURSE_GROUP_MEMBER (group_code, course_id) VALUES")
w(",\n".join("    " + row(g[0], c) for g in GROUPS for c in g[3]) + ";\n")

w(f"-- 졸업요건: {SRC_BOOK}\n--   132학점, 전공 54, MSC 30(과학 1set·수학 1과목·전산 1과목), 전문교양 23(기초교양 6), 특성화교양 3(2019~), SW/데이터 9(2022~), 전공기초영어 2, 설계 12\n"
  f"--   {SRC_ICEE}\n--   {SRC_2020}\n--   {SRC_DES} (설계 12학점)\n--   {SRC_REQ} (전공필수·인증필수)")
for i, (fy, ty, sl, groups) in enumerate(REQS, start=1):
    w(f"INSERT INTO GRAD_REQ (req_id, dept_id, from_year, to_year, total_credits, min_gpa, min_design_credits, note, source_url) VALUES "
      + row(i, "SW", fy, ty, 132, None, 12, NOTE, SRC_BOOK) + ";")
    areas = AREA_REQ + ([("SL", sl)] if sl else [])
    w("INSERT INTO GRAD_REQ_AREA (req_id, area_code, min_credits) VALUES " + ", ".join(row(i, a, m) for a, m in areas) + ";")
    w("INSERT INTO GRAD_REQ_GROUP (req_id, group_code, min_credits) VALUES " + ", ".join(row(i, g, m) for g, m in groups) + ";")
    w("INSERT INTO REQUIRED_COURSE (req_id, course_id) VALUES " + ", ".join(row(i, c) for c in MAJOR_REQ) + ";")
w("-- 컴퓨터공학과(서울) 졸업요건은 이번 범위에서 등록하지 않았다 (담당자 화면에서 추가 가능).\n")

w(f"-- 교내장학금 7종: {SRC_SCH}\n--   최소 취득학점 15, 성적우수 4종은 봉사 10시간(26-2 선발부터, 4학년 예외), 한마음·면학은 성적경고가 아닌 학생\n"
  f"--   성적경고 기준 1.75 는 컴퓨터공학과 공지의 '가계 한마음 1.75 이상'을 따름: {SRC_CE_SCH}")
w("INSERT INTO SCHOLARSHIP (sch_name, category, amount_desc, min_gpa, min_credits, max_income, min_volunteer_hours, volunteer_exempt_level, dept_id, source_url) VALUES")
w(",\n".join("    " + row(n, c, a, g, cr, inc, v, ex, None, SRC_SCH) for n, c, a, g, cr, inc, v, ex in SCH) + ";\n")

w("-- 가상의 학생")
w("INSERT INTO STUDENT (student_id, name, dept_id, admission_year, grade_level, income_bracket, volunteer_hours) VALUES")
w(",\n".join("    " + row(*s) for s in STUDENTS) + ";\n")
w("INSERT INTO ENROLLMENT (student_id, course_id, year, term, grade) VALUES")
w(",\n".join("    " + row(*e) for e in ENR) + ";\n")
w("INSERT INTO USER_ACCOUNT (login_id, password_hash, role, student_id, dept_id) VALUES")
w(",\n".join("    " + row(a[0], generate_password_hash(a[1]), a[2], a[3], a[4]) for a in ACCOUNTS) + ";")
open(OUT, "w", encoding="utf-8").write("\n".join(L) + "\n")
print("courses", len(C), "enrollments", len(ENR))
