import hashlib
import re
from datetime import datetime

import pymysql

from flask import Blueprint, flash, redirect, render_template, request, session, url_for

from . import importer
from .auth import login_required
from .db import execute, get_db, query_all, query_one
from .queries import student_q as Q

bp = Blueprint("student", __name__, url_prefix="/me")


def load_report(student_id):
    """한 학생의 진단 결과 전체. 학생 화면과 담당자 학생 상세가 같이 쓴다."""
    semesters = query_all(Q.SEMESTER_RECORDS, (student_id,))
    enrollments = query_all(Q.ENROLLMENTS, (student_id,))
    by_sem = {}
    for e in enrollments:
        by_sem.setdefault((e["year"], e["term"]), []).append(e)
    scholarships = query_all(Q.SCHOLARSHIPS, (student_id,))
    regular = [s for s in semesters if s["term"] in (1, 2)]
    groups = query_all(Q.GRAD_GROUP_STATUS, (student_id,))
    return {
        "info": query_one(Q.STUDENT_INFO, (student_id,)),
        "status": query_one(Q.GRAD_STATUS, (student_id,)),
        "req": query_one(Q.GRAD_REQ_INFO, (student_id,)),
        "areas": query_all(Q.GRAD_AREA_STATUS, (student_id,)),
        "groups": [g for g in groups if g["rule_type"] == "CREDITS"],
        "required_groups": [g for g in groups if g["rule_type"] == "ALL"],
        "earned_by_area": query_all(Q.EARNED_BY_AREA, (student_id,)),
        "missing": query_all(Q.MISSING_REQUIRED, (student_id,)),
        "scholarships": scholarships,
        "eligible_count": sum(1 for s in scholarships if s["eligible"]),
        "semesters": semesters,
        "last_sem": regular[-1] if regular else None,
        "sem_courses": [(s, by_sem.get((s["year"], s["term"]), [])) for s in reversed(semesters)],
        "suggestions": group_suggestions(query_all(Q.SUGGESTIONS, (student_id,))),
    }


def group_suggestions(rows):
    """V_COURSE_SUGGESTION 결과를 과목 단위로 묶는다 (한 과목이 채우는 요건 목록 + 가장 높은 우선순위)."""
    out = {}
    for r in rows:
        c = out.setdefault(r["course_id"], dict(r, reasons=[]))
        if r["reason_name"] not in c["reasons"]:
            c["reasons"].append(r["reason_name"])
        c["priority"] = min(c["priority"], r["priority"])
    return sorted(out.values(), key=lambda c: (c["priority"], -len(c["reasons"]), c["course_name"]))


@bp.route("")
@login_required("student")
def dashboard():
    return render_template("student/dashboard.html", r=load_report(session["student_id"]))


@bp.route("/grades")
@login_required("student")
def grades():
    return render_template("student/grades.html", r=load_report(session["student_id"]))


@bp.route("/graduation")
@login_required("student")
def graduation():
    return render_template("student/graduation.html", r=load_report(session["student_id"]))


@bp.route("/scholarships")
@login_required("student")
def scholarships():
    return render_template("student/scholarships.html", r=load_report(session["student_id"]))


# ---------------------------------------------------------------- 성적 가져오기
def name_key(name):
    """과목명 비교용: 공백·괄호 표기 차이, 'sw심화' 접두어를 무시한다."""
    n = re.sub(r"\s+", "", name or "").lower()
    n = re.sub(r"[(\[]?sw심화[)\]]?", "", n)
    return n.replace("（", "(").replace("）", ")")


def resolve(rows, dept_id):
    """붙여 넣은 행을 COURSE 와 맞춘다. 없는 과목은 새로 만들 과목으로 표시한다."""
    courses = query_all(Q.ALL_COURSES)
    by_id = {c["course_id"]: c for c in courses}
    by_name = {name_key(c["course_name"]): c for c in courses}
    area_names = {a["area_code"]: a["area_name"] for a in query_all(Q.AREAS)}
    out, problems = {}, []
    for r in rows:
        c = by_id.get(r["course_id"]) if r["course_id"] else None
        c = c or by_name.get(name_key(r["course_name"]))
        if c:
            item = dict(r, course_id=c["course_id"], course_name=c["course_name"], credits=c["credits"],
                        area_code=c["area_code"], new=c.get("new", False),
                        dept_id=c.get("dept_id"))
            if r["credits"] is not None and r["credits"] != c["credits"]:
                item["warn"] = f"붙여 넣은 학점 {r['credits']} 대신 등록된 학점 {c['credits']} 사용"
        else:
            if r["credits"] is None:
                problems.append(f"{r['line']}번째 줄 '{r['course_name']}': 처음 보는 과목인데 학점이 없어 건너뜁니다.")
                continue
            cid = r["course_id"] or ("U" + hashlib.md5(name_key(r["course_name"]).encode()).hexdigest()[:7].upper())
            area, how = r["area_code"], None
            if not area:
                area, how = importer.area_from_code(r["course_id"])
            if not area:
                area, how = "FE", "이수구분을 알 수 없어 일반선택으로 넣음"
            item = dict(r, course_id=cid[:10], area_code=area, new=True,
                        dept_id=dept_id if area in ("MR", "ME") else None)
            if how:
                item["warn"] = how
            by_name[name_key(r["course_name"])] = item   # 같은 새 과목이 두 번 나오면 하나로
            by_id[item["course_id"]] = item
        item["area_name"] = area_names.get(item["area_code"], item["area_code"])
        out[(item["course_id"], item["year"], item["term"])] = item
    return sorted(out.values(), key=lambda x: (x["year"], {1: 1, 3: 2, 2: 3, 4: 4}[x["term"]], x["course_name"])), problems


GRADE_POINT = {"A+": 4.5, "A0": 4.0, "B+": 3.5, "B0": 3.0, "C+": 2.5, "C0": 2.0,
               "D+": 1.5, "D0": 1.0, "F": 0.0}
TERM_ORDER = {1: 1, 3: 2, 2: 3, 4: 4}


def totals(rows):
    """미리보기용 합계: 재수강은 가장 최근 성적 1건만, P/NP 는 평점에서 뺀다 (DB 뷰와 같은 규칙)."""
    latest = {}
    for r in sorted(rows, key=lambda x: (x["year"], TERM_ORDER[x["term"]])):
        latest[r["course_id"]] = r
    earned = [r for r in latest.values() if r["grade"] not in ("F", "NP")]
    graded = [r for r in latest.values() if r["grade"] in GRADE_POINT]
    pts = sum(GRADE_POINT[r["grade"]] * r["credits"] for r in graded)
    crs = sum(r["credits"] for r in graded)
    return {"credits": sum(r["credits"] for r in earned),
            "gpa": round(pts / crs + 1e-9, 2) if crs else None,
            "retakes": len(rows) - len(latest)}


@bp.route("/import", methods=["GET", "POST"])
@login_required("student")
def import_grades():
    sid = session["student_id"]
    info = query_one(Q.STUDENT_INFO, (sid,))
    text = request.form.get("text", "")
    if request.method == "POST" and request.files.get("file") and request.files["file"].filename:
        try:
            text = importer.read_upload(request.files["file"])
        except ValueError as e:
            flash(str(e), "danger")
            return redirect(url_for("student.import_grades"))

    if request.method == "GET" or not text.strip():
        if request.method == "POST":
            flash("붙여 넣은 내용이나 CSV 파일이 없습니다.", "warning")
        return render_template("student/import.html", info=info, text="", rows=None, problems=[],
                               current=query_one(Q.ENROLLMENT_COUNT, (sid,))["n"])

    rows, problems, summary = importer.parse(text)
    rows, more = resolve(rows, info["dept_id"])
    problems += more
    check = totals(rows)

    if request.form.get("action") != "save" or not rows:
        return render_template("student/import.html", info=info, text=text, rows=rows, problems=problems,
                               summary=summary, check=check, mode=request.form.get("mode", "replace"),
                               current=query_one(Q.ENROLLMENT_COUNT, (sid,))["n"])

    # 저장: 새 과목 등록 + 성적 반영을 한 트랜잭션으로
    mode = request.form.get("mode", "replace")
    conn = get_db()
    try:
        if mode == "replace":
            execute(Q.ENROLLMENT_DELETE_ALL, (sid,))      # 트리거가 학기 기록도 지운다
        for r in rows:
            if r["new"] and not query_one(Q.COURSE_EXISTS, (r["course_id"],)):
                execute(Q.COURSE_INSERT, (r["course_id"], r["course_name"][:60], r["credits"],
                                          r["area_code"], r.get("dept_id")))
            for g in r.get("groups", []):
                execute(Q.COURSE_GROUP_ADD, (r["course_id"], g))
            execute(Q.ENROLLMENT_UPSERT, (sid, r["course_id"], r["year"], r["term"], r["grade"]))
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    new_courses = sum(1 for r in rows if r["new"])
    flash(f"성적 {len(rows)}건을 저장했습니다"
          + (f" (새 과목 {new_courses}개 등록)" if new_courses else "")
          + ". 졸업요건과 장학금 판정이 바로 다시 계산되었습니다.", "success")
    return redirect(url_for("student.dashboard"))


# ---------------------------------------------------------------- 수강 계획 시뮬레이터
TERM_NEXT = {1: (0, 2), 3: (0, 2), 2: (1, 1), 4: (0, 1)}   # 마지막 학기 → 다음 정규학기 (연도 증가, 학기)


def next_regular_term(student_id, admission_year):
    last = query_one(Q.LAST_TERM, (student_id,))
    if not last:
        return admission_year, 1
    dy, t = TERM_NEXT[last["term"]]
    return last["year"] + dy, t


def snapshot(student_id, year, term):
    """판정 뷰 결과 한 묶음. 가상 수강을 넣은 상태와 넣기 전 상태를 비교하는 데 쓴다."""
    sch = query_all(Q.SCHOLARSHIPS, (student_id,))
    groups = query_all(Q.GRAD_GROUP_STATUS, (student_id,))
    return {
        "status": query_one(Q.GRAD_STATUS, (student_id,)),
        "areas": {a["area_code"]: a for a in query_all(Q.GRAD_AREA_STATUS, (student_id,))},
        "groups": {g["group_code"]: g for g in groups},
        "missing": query_all(Q.MISSING_REQUIRED, (student_id,)),
        "sch": {s["sch_id"]: s for s in sch},
        "sem": query_one("SELECT * FROM SEMESTER_RECORD WHERE student_id = %s AND year = %s AND term = %s",
                         (student_id, year, term)),
    }


@bp.route("/plan", methods=["GET", "POST"])
@login_required("student")
def plan():
    sid = session["student_id"]
    info = query_one(Q.STUDENT_INFO, (sid,))
    ny, nt = next_regular_term(sid, info["admission_year"])
    year = request.values.get("year", type=int) or ny
    term = request.values.get("term", type=int) or nt
    if term not in (1, 2, 3, 4) or not 2000 <= year <= 2100:
        year, term = ny, nt
    report = load_report(sid)
    candidates = query_all(Q.PLAN_CANDIDATES, {"sid": sid, "dept": info["dept_id"]})
    grades = [g["grade"] for g in query_all(Q.GRADE_SCALE)]
    chosen = [c for c in request.form.getlist("course") if c in {x["course_id"] for x in candidates}]
    picks = {c: (request.form.get(f"g_{c}") if request.form.get(f"g_{c}") in grades else "A0") for c in chosen}

    result = None
    if request.method == "POST" and picks:
        conn = get_db()
        before = snapshot(sid, year, term)
        try:
            # 같은 트랜잭션 안에서 가상 수강을 넣으면 트리거·뷰가 실제와 똑같이 다시 판정한다
            for cid, grade in picks.items():
                execute(Q.PLAN_INSERT, (sid, cid, year, term, grade))
            after = snapshot(sid, year, term)
        except pymysql.err.IntegrityError:
            conn.rollback()
            flash("이미 같은 학기에 들은 과목이 있어 계산하지 못했습니다. 학기를 바꿔 보세요.", "warning")
            return redirect(url_for("student.plan"))
        finally:
            conn.rollback()          # 무엇을 했든 DB 에는 남기지 않는다
        result = {"before": before, "after": after,
                  "credits": sum(c["credits"] for c in candidates if c["course_id"] in picks)}
    elif request.method == "POST":
        flash("계획에 넣을 과목을 하나 이상 고르세요.", "warning")

    suggested = {s["course_id"]: s for s in report["suggestions"]}
    return render_template("student/plan.html", r=report, candidates=candidates, suggested=suggested,
                           cand={c["course_id"]: c for c in candidates},
                           grades=grades, picks=picks, year=year, term=term, result=result)


# ---------------------------------------------------------------- 인쇄용 진단서
@bp.route("/report")
@login_required("student")
def report():
    return render_template("report.html", r=load_report(session["student_id"]), now=datetime.now())
