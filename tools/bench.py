"""대량 데이터 성능 측정.

    python tools/bench.py [학생 수]      (기본 2000명)

grad_check 와 같은 SQL(db/01~04)로 별도 DB `grad_bench` 를 만들고, 가상 학생을 N명 더 넣은 뒤
웹 화면이 실제로 쓰는 쿼리(app/queries)의 실행 시간을 잰다. grad_check 는 건드리지 않는다.
"""
import os
import random
import statistics
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, ROOT)

import pymysql  # noqa: E402

from config import Config  # noqa: E402
from init_db import SQL_FILES, statements  # noqa: E402
from app.queries import admin_q as A, student_q as S  # noqa: E402

ARGS = [a for a in sys.argv[1:] if not a.startswith("--")]
N = int(ARGS[0]) if ARGS else 2000
DB = "grad_bench"
GRADES = ["A+", "A0", "A0", "B+", "B+", "B0", "B0", "C+", "C0", "D0", "F", "P"]


def build(cur):
    for name in SQL_FILES:
        for stmt in statements(os.path.join(ROOT, "db", name)):
            cur.execute(stmt.replace("grad_check", DB))

    cur.execute("SELECT course_id, area_code FROM COURSE")
    courses = [r[0] for r in cur.fetchall()]
    rnd = random.Random(42)
    t0 = time.perf_counter()
    students, enr = [], []
    for i in range(N):
        sid = f"9{i:07d}"
        adm = rnd.randint(2016, 2025)
        level = min(4, 2026 - adm + 1)
        students.append((sid, f"학생{i}", "SW", adm, level, rnd.randint(0, 10), rnd.choice([0, 4, 10, 20])))
        pool = rnd.sample(courses, min(len(courses), level * 12))
        k = 0
        for y in range(adm, adm + level):
            for t in (1, 2):
                if y == 2026 and t == 2:
                    break
                for _ in range(6):
                    if k >= len(pool):
                        break
                    enr.append((sid, pool[k], y, t, rnd.choice(GRADES)))
                    k += 1
        if level >= 2:   # 재수강 1건
            enr.append((sid, pool[0], adm + level - 1, 1 if adm + level - 1 == 2026 else 2, "A0"))
    cur.executemany("INSERT INTO STUDENT (student_id, name, dept_id, admission_year, grade_level, income_bracket, "
                    "volunteer_hours) VALUES (%s,%s,%s,%s,%s,%s,%s)", students)
    for i in range(0, len(enr), 500):
        cur.executemany("INSERT IGNORE INTO ENROLLMENT (student_id, course_id, year, term, grade) "
                        "VALUES (%s,%s,%s,%s,%s)", enr[i:i + 500])
        if i % 10000 == 0:
            cur.execute("ANALYZE TABLE ENROLLMENT, SEMESTER_RECORD"); cur.fetchall()
    print(f"학생 {N}명, 수강 {len(enr)}건 입력 ({time.perf_counter() - t0:.1f}s, 행마다 트리거 실행)")


def timeit(cur, label, sql, args=None, repeat=5):
    ts = []
    for _ in range(repeat):
        t0 = time.perf_counter()
        cur.execute(sql, args)
        cur.fetchall()
        ts.append((time.perf_counter() - t0) * 1000)
    ms = statistics.median(ts)
    print(f"  {label:<38} {ms:9.1f} ms")
    return ms


def check(cur):
    """학과 목록(집합 쿼리) 결과가 학생별 뷰 V_GRAD_STATUS 와 모든 학생에서 같은지 확인"""
    cols = ["earned_total", "remaining_total", "cum_gpa", "earned_design", "short_area_count",
            "short_group_count", "missing_required_count", "graduatable"]
    d = pymysql.cursors.DictCursor
    with cur.connection.cursor(d) as c:
        c.execute(A.STUDENT_LIST, {"dept": "SW", "q": ""})
        listed = {r["student_id"]: r for r in c.fetchall()}
        c.execute("SELECT * FROM V_GRAD_STATUS WHERE dept_id = 'SW'")
        view = {r["student_id"]: r for r in c.fetchall()}
    bad = [(sid, k, listed[sid][k], view[sid][k]) for sid in view for k in cols
           if sid not in listed or listed[sid][k] != view[sid][k]]
    print(f"일치 검사: 학생 {len(view)}명, 불일치 {len(bad)}건", bad[:5])


def main():
    conn = pymysql.connect(host=Config.DB_HOST, port=Config.DB_PORT, user=Config.DB_USER,
                           password=Config.DB_PASSWORD, charset="utf8mb4", autocommit=True)
    cur = conn.cursor()
    if "--reuse" not in sys.argv:
        build(cur)
    cur.execute(f"USE {DB}")
    cur.execute("ANALYZE TABLE ENROLLMENT, STUDENT, COURSE, SEMESTER_RECORD")
    cur.fetchall()
    sid = "90000123"
    print("학생 1명 화면 (load_report 가 쓰는 쿼리)")
    total = 0
    for label in ["GRAD_STATUS", "GRAD_AREA_STATUS", "GRAD_GROUP_STATUS", "MISSING_REQUIRED",
                  "SCHOLARSHIPS", "ENROLLMENTS", "SEMESTER_RECORDS", "EARNED_BY_AREA"]:
        total += timeit(cur, label, getattr(S, label), (sid,))
    print(f"  {'합계':<38} {total:9.1f} ms")
    print("담당자 화면")
    timeit(cur, "STUDENT_LIST (학과 전체)", A.STUDENT_LIST, {"dept": "SW", "q": ""}, repeat=3)
    if hasattr(A, "SHORT_AREA_STUDENTS"):
        timeit(cur, "SHORT_AREA_STUDENTS", A.SHORT_AREA_STUDENTS, ("SW", "SW"), repeat=3)
    timeit(cur, "SCHOLARSHIP_LIST", A.SCHOLARSHIP_LIST, {"dept": "SW"}, repeat=3)
    for extra in ("AREA_SHORTAGE", "GROUP_SHORTAGE", "SCHOLARSHIP_COUNTS", "LAST_GPA_HISTOGRAM", "DEPT_TERM_TREND"):
        if hasattr(A, extra):
            timeit(cur, extra, getattr(A, extra), {"dept": "SW", "q": ""}, repeat=3)
    if hasattr(S, "SUGGESTIONS"):
        timeit(cur, "SUGGESTIONS (학생 1명)", S.SUGGESTIONS, (sid,))
    if "--check" in sys.argv:
        check(cur)
    print("성적 수정 (UPDATE → 트리거 재계산)")
    cur.execute("SELECT enroll_id FROM ENROLLMENT WHERE student_id = %s LIMIT 1", (sid,))
    eid = cur.fetchone()[0]
    timeit(cur, "UPDATE_GRADE", A.UPDATE_GRADE, ("B0", eid))


if __name__ == "__main__":
    main()
