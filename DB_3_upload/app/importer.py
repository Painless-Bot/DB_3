"""클래스룸 성적표 붙여넣기 / CSV 를 읽어 ENROLLMENT 행으로 바꾼다.

클래스룸 성적 조회 화면의 표를 드래그해서 복사하면 칸이 탭으로 구분된 텍스트가 된다.
표 모양을 정확히 알 수 없으므로 다음을 모두 받아들인다.
  - 구분자: 탭, 쉼표(CSV), 두 칸 이상 공백
  - 머리글: 년도/연도/학년도, 학기, 학수번호/과목코드, 과목명/교과목명, 이수구분, 학점, 등급/성적
    머리글 순서는 상관없고, 머리글 줄이 없으면 값 모양으로 칸을 추정한다.
  - "2024학년도 1학기" 처럼 학기가 표 위 제목 줄에만 있는 형식
"""
import csv
import io
import re

HEADER_ALIASES = {
    "year": ["년도", "연도", "학년도", "수강년도", "수강연도", "이수년도", "year"],
    "term": ["학기", "수강학기", "이수학기", "term", "semester"],
    "course_id": ["학수번호", "과목코드", "교과목번호", "과목번호", "코드", "code"],
    "course_name": ["과목명", "교과목명", "교과목", "과목", "강좌명", "name"],
    "area": ["이수구분", "구분", "이수영역", "영역", "area"],
    "credits": ["학점", "이수학점", "credit", "credits"],
    "grade": ["등급", "성적", "평가", "grade"],
}

GRADE_MAP = {"A+": "A+", "A0": "A0", "A": "A0", "B+": "B+", "B0": "B0", "B": "B0",
             "C+": "C+", "C0": "C0", "C": "C0", "D+": "D+", "D0": "D0", "D": "D0",
             "F": "F", "P": "P", "NP": "NP", "S": "P", "U": "NP", "PASS": "P", "FAIL": "NP"}

TERM_WORDS = {"1": 1, "2": 2, "1학기": 1, "2학기": 2, "여름": 3, "하계": 3, "여름학기": 3,
              "하계계절": 3, "겨울": 4, "동계": 4, "겨울학기": 4, "동계계절": 4}

# 이수구분 글자 → AREA 코드 (앞에서부터 먼저 맞는 것)
# 클래스룸 졸업요건조회 표기: 교필, 교선, 일교1~5, 핵교6~8, 일선, M수학/M과학/M전산, 전필, 전선, 전기(전공기초)
AREA_RULES = [
    ("전필", "MR"), ("전공필수", "MR"), ("전선", "ME"), ("전공선택", "ME"),
    ("전기", "EN"), ("전공기초", "EN"), ("전공", "ME"),
    ("msc수학", "MM"), ("수학", "MM"), ("msc과학", "MP"), ("과학", "MP"), ("msc전산", "MC"), ("전산", "MC"),
    ("msc", "MS"),
    ("특성화", "SL"), ("전공기초영어", "EN"), ("기초영어", "EN"),
    ("기초교양", "BL"), ("교필", "BL"), ("기교", "BL"), ("핵심", "CL"), ("핵교", "CL"),
    ("일반교양", "GL"), ("일교", "GL"), ("교선", "GL"), ("교양", "GL"),
    ("일선", "FE"), ("일반선택", "FE"), ("자유", "FE"),
]

# 전문교양 영역 번호 → 졸업요건 과목군 (4영역 예술과디자인, 5영역 제2외국어와한문)
AREA_NO_GROUP = {4: "ART_DESIGN", 5: "LANG_HANMUN"}
# 이수구분 칸이 없는 '전체 성적 조회' 를 붙여 넣어도 알 수 있게, 교양 외국어·한문 과목은 이름으로도 5영역으로 본다
LANG_NAME = re.compile(r"(프랑스어|독일어|일본어|중국어|스페인어|러시아어|이탈리아어|라틴어|아랍어|베트남어|한문)")
# 과목명 뒤 '*sw(심화)' 같은 SW/데이터활용역량 표기 → 인정되는 과목군
SWD_TAG = re.compile(r"\s*\*?\s*sw\s*\(\s*(심화|기초|소양)\s*\)\s*", re.I)
SWD_GROUPS = {"심화": ["SWD_1_ADV", "SWD_2_BASIC", "SWD_3_TOTAL"],
              "기초": ["SWD_2_BASIC", "SWD_3_TOTAL"],
              "소양": ["SWD_3_TOTAL"]}
# 클래스룸 졸업요건조회 아래 요약표의 합계 줄 (검증용)
SUMMARY_KEYS = {"취득학점": "credits", "평점": "gpa", "전공평점": "major_gpa"}

SEM_TITLE = re.compile(r"(20\d\d)\s*(?:학년도|년도|년)?\s*(?:\d\s*학년\s*)?[-./]?\s*(1|2|여름|겨울|하계|동계)\s*(?:학기|계절)?")
GRADE_RE = re.compile(r"^(A\+|A0|A|B\+|B0|B|C\+|C0|C|D\+|D0|D|F|P|NP|S|U)$", re.I)


def norm(s):
    return re.sub(r"\s+", "", str(s or "")).lower()


def course_groups(name, area_text):
    """과목명 표기와 이수구분 글자로 알 수 있는 과목군 목록."""
    groups = []
    m = SWD_TAG.search(name or "")
    if m:
        groups += SWD_GROUPS[m.group(1)]
    a = re.fullmatch(r"(?:일교|핵교)(\d)", norm(area_text))
    if a and int(a.group(1)) in AREA_NO_GROUP:
        groups.append(AREA_NO_GROUP[int(a.group(1))])
    if not a and LANG_NAME.search(name or "") and not re.search(r"전공|전필|전선", area_text or ""):
        groups.append("LANG_HANMUN")
    return groups


def clean_name(name):
    return SWD_TAG.sub("", name or "").strip()


def area_code(text):
    t = norm(text)
    for key, code in AREA_RULES:
        if key in t:
            return code
    return None


# 클래스룸 성적표에는 이수구분 칸이 없다. 홍익대 학수번호 앞자리로 이수구분을 짐작한다.
CODE_AREA = [("001", "BL"), ("002", "GL"), ("004", "CL"), ("007", "EN"), ("008", "SL"),
             ("0121", "MP"), ("0122", "MM"), ("0123", "MC"), ("1018", "MC")]


def area_from_code(code):
    """반환: (area_code, 짐작 여부 설명)"""
    if not code or not code.isdigit():
        return None, None
    for prefix, area in CODE_AREA:
        if code.startswith(prefix):
            return area, "학수번호로 이수구분을 짐작함"
    if not code.startswith("0"):
        return "ME", "전공 과목으로 보고 전공선택으로 넣음"
    return None, None


def split_line(line, delim):
    if delim == ",":
        return next(csv.reader([line]))
    if delim == "\t":
        return [c.strip() for c in line.split("\t")]
    return [c.strip() for c in re.split(r"\s{2,}", line.strip())]


def guess_delim(lines):
    sample = "\n".join(lines[:20])
    if "\t" in sample:
        return "\t"
    if sample.count(",") >= len(lines[:20]):
        return ","
    return "  "


def find_header(cells):
    """머리글 줄이면 {필드: 칸 번호} 를 돌려준다."""
    found = {}
    for i, c in enumerate(cells):
        n = norm(c)
        for field, names in HEADER_ALIASES.items():
            if field in found:
                continue
            if any(n == norm(a) for a in names):
                found[field] = i
                break
    # 과목명 + 학점 + 등급이 있으면 머리글로 본다
    if {"course_name", "credits", "grade"} <= set(found):
        return found
    return None


def guess_columns(cells):
    """머리글이 없을 때 값 모양으로 칸을 추정한다."""
    cols, texts = {}, []
    for i, c in enumerate(cells):
        v = c.strip()
        if not v:
            continue
        if "grade" not in cols and GRADE_RE.match(v):
            cols["grade"] = i
        elif "year" not in cols and re.fullmatch(r"20\d\d", v):
            cols["year"] = i
        elif "term" not in cols and "year" in cols and norm(v) in TERM_WORDS:
            cols["term"] = i
        elif "course_id" not in cols and re.fullmatch(r"[0-9A-Z]{5,8}", v):
            cols["course_id"] = i
        elif "credits" not in cols and re.fullmatch(r"[0-6](\.0)?", v):
            cols["credits"] = i
        elif re.search(r"[가-힣A-Za-z]", v):
            texts.append((i, v))
    # 짧고 이수구분처럼 생긴 칸 = 이수구분, 나머지 중 가장 긴 칸 = 과목명
    for i, v in texts:
        if len(v) <= 6 and area_code(v) and "area" not in cols and len(texts) > 1:
            cols["area"] = i
    rest = [(i, v) for i, v in texts if i != cols.get("area")]
    if rest:
        cols["course_name"] = max(rest, key=lambda t: len(t[1]))[0]
    if {"course_name", "grade"} <= set(cols):
        return cols
    return None


def parse(text):
    """반환: (rows, problems, summary). summary 는 클래스룸 요약표의 취득학점·평점(있을 때). rows 는 dict(year, term, course_id, course_name, area_code, credits, grade, line)."""
    lines = [l for l in text.replace("\r\n", "\n").replace("\r", "\n").split("\n")]
    nonblank = [l for l in lines if l.strip()]
    if not nonblank:
        return [], ["붙여 넣은 내용이 없습니다."], {}
    delim = guess_delim(nonblank)
    header = None
    cur_year = cur_term = None
    rows, problems = [], []
    summary = {}
    in_summary = False

    for no, line in enumerate(lines, start=1):
        if not line.strip():
            continue
        cells = split_line(line, delim)
        if "이수구분별" in line:      # 졸업요건조회 맨 아래 요약표: 성적 행이 아니라 합계만 읽는다
            in_summary = True
        if in_summary:
            vals = [c for c in cells if c]
            if len(vals) >= 2 and norm(vals[0]) in SUMMARY_KEYS:
                num = re.match(r"\d+(\.\d+)?", vals[1])
                if num:
                    summary.setdefault(SUMMARY_KEYS[norm(vals[0])], float(num.group()))
            continue
        h = find_header(cells)
        if h:
            header = h
            continue
        # "2024학년도 1학기" 같은 제목 줄
        m = SEM_TITLE.search(line)
        if m and len([c for c in cells if c]) <= 3:
            cur_year, cur_term = int(m.group(1)), TERM_WORDS.get(m.group(2), TERM_WORDS.get(m.group(2) + "학기"))
            continue
        cols = header or guess_columns(cells)
        if not cols:
            continue  # 합계 줄, 안내 문구 등

        def get(field):
            i = cols.get(field)
            return cells[i].strip() if i is not None and i < len(cells) else ""

        grade_raw = get("grade").upper().replace(" ", "")
        grade = GRADE_MAP.get(grade_raw)
        name = get("course_name")
        if not name or not grade:
            if name and grade_raw and not re.search(r"합계|평균|계$", name):
                problems.append(f"{no}번째 줄: 성적 '{get('grade')}' 을(를) 읽지 못해 건너뜁니다.")
            continue

        year_raw, term_raw = get("year"), get("term")
        ym = re.search(r"20\d\d", year_raw) or re.search(r"20\d\d", term_raw)
        year = int(ym.group()) if ym else cur_year
        tm = norm(term_raw).replace(str(year or ""), "").strip("-./")
        term = TERM_WORDS.get(tm) or TERM_WORDS.get(tm.replace("학기", "")) if tm else None
        if term is None:
            m2 = SEM_TITLE.search(year_raw + " " + term_raw)
            term = TERM_WORDS.get(m2.group(2)) if m2 else cur_term
        if not year or not term:
            problems.append(f"{no}번째 줄 '{name}': 년도/학기를 알 수 없어 건너뜁니다.")
            continue

        cr_raw = get("credits")
        try:
            credits = int(float(cr_raw)) if cr_raw else None
        except ValueError:
            credits = None

        rows.append({
            "year": year, "term": term,
            "course_id": get("course_id").upper() or None,
            "course_name": clean_name(name),
            "groups": course_groups(name, get("area")),
            "area_text": get("area"),
            "area_code": area_code(get("area")),
            "credits": credits,
            "grade": grade,
            "line": no,
        })
    if not rows and not problems:
        problems.append("성적 행을 찾지 못했습니다. 표의 머리글(과목명, 학점, 등급 등)까지 함께 복사해 주세요.")
    return rows, problems, summary


def read_upload(file_storage):
    """CSV 파일을 텍스트로 읽는다. 엑셀에서 저장한 CSV(cp949)도 받는다."""
    raw = file_storage.read()
    for enc in ("utf-8-sig", "cp949", "euc-kr"):
        try:
            return raw.decode(enc)
        except UnicodeDecodeError:
            continue
    raise ValueError("파일 인코딩을 읽을 수 없습니다. CSV(UTF-8) 로 저장해 주세요.")
