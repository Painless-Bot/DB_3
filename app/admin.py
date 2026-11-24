import csv
import io
import re
from datetime import datetime
from decimal import Decimal, InvalidOperation

import pymysql

from flask import Blueprint, Response, abort, flash, redirect, render_template, request, session, url_for

from .auth import login_required
from .db import execute, get_db, query_all, query_one
from .queries import admin_q as Q
from .student import load_report

bp = Blueprint("admin", __name__, url_prefix="/admin")


def my_dept():
    return session["dept_id"]


def check_student(student_id):
    row = query_one(Q.STUDENT_DEPT, (student_id,))
    if not row or row["dept_id"] != my_dept():
        abort(404)


@bp.context_processor
def inject_dept():
    if session.get("role") == "admin":
        row = query_one(Q.DEPT_NAME, (session["dept_id"],))
        return {"admin_dept_name": row["dept_name"] if row else session["dept_id"]}
    return {}


# ---------------------------------------------------------------- 학생 목록·상세
FILTERS = {
    "all": ("전체", lambda r: True),
    "ok": ("졸업 가능", lambda r: r["graduatable"]),
    "short": ("영역·과목군 미달", lambda r: r["short_area_count"] + r["short_group_count"] > 0),
    "missing": ("필수과목 미이수", lambda r: r["missing_required_count"] > 0),
    "senior": ("4학년 이상 미충족", lambda r: r["grade_level"] >= 4 and not r["graduatable"]),
    "noreq": ("요건 미등록", lambda r: r["req_id"] is None),
}


def student_rows():
    q = request.args.get("q", "").strip()
    f = request.args.get("f", "all")
    if request.args.get("only_short") == "1":      # 예전 주소 호환
        f = "short"
    if f not in FILTERS:
        f = "all"
    rows = query_all(Q.STUDENT_LIST, {"dept": my_dept(), "q": q})
    counts = {k: sum(1 for r in rows if fn(r)) for k, (_, fn) in FILTERS.items()}
    return [r for r in rows if FILTERS[f][1](r)], q, f, counts


@bp.route("/students")
@login_required("admin")
def students():
    rows, q, f, counts = student_rows()
    return render_template("admin/students.html", rows=rows, q=q, f=f, counts=counts, filters=FILTERS)


@bp.route("/students.csv")
@login_required("admin")
def students_csv():
    """현재 검색·필터 조건의 학생 진단 결과를 엑셀에서 바로 열리는 CSV(UTF-8 BOM)로 내려 준다."""
    rows, *_ = student_rows()
    buf = io.StringIO()
    w = csv.writer(buf)
    w.writerow(["학번", "이름", "입학연도", "학년", "적용요건", "이수학점", "졸업학점", "남은학점", "누적평점",
                "설계학점", "부족 영역", "부족 과목군", "미이수 필수", "판정"])
    for r in rows:
        req = "-" if r["req_id"] is None else (f"{r['req_from_year']}~{r['req_to_year'] or ''}")
        verdict = "요건 미등록" if r["req_id"] is None else ("졸업 가능" if r["graduatable"] else "미충족")
        w.writerow([r["student_id"], r["name"], r["admission_year"], r["grade_level"], req, r["earned_total"],
                    r["required_total"] or "", r["remaining_total"] if r["required_total"] else "",
                    r["cum_gpa"] if r["cum_gpa"] is not None else "", r["earned_design"],
                    r["short_area_count"], r["short_group_count"], r["missing_required_count"], verdict])
    name = f"students_{my_dept()}_{datetime.now():%Y%m%d}.csv"
    return Response("\ufeff" + buf.getvalue(), mimetype="text/csv",
                    headers={"Content-Disposition": f"attachment; filename={name}"})


@bp.route("/students/<student_id>")
@login_required("admin")
def student_detail(student_id):
    check_student(student_id)
    return render_template("admin/student_detail.html", r=load_report(student_id))


@bp.route("/students/<student_id>/report")
@login_required("admin")
def student_report(student_id):
    check_student(student_id)
    return render_template("report.html", r=load_report(student_id), now=datetime.now())


# ---------------------------------------------------------------- 학과 통계
@bp.route("/stats")
@login_required("admin")
def stats():
    p = {"dept": my_dept(), "q": ""}
    rows = query_all(Q.STUDENT_LIST, p)
    with_req = [r for r in rows if r["req_id"] is not None]
    gpas = [float(r["cum_gpa"]) for r in rows if r["cum_gpa"] is not None]
    seniors = [r for r in with_req if r["grade_level"] >= 4]
    kpi = {
        "students": len(rows),
        "graduatable": sum(1 for r in with_req if r["graduatable"]),
        "seniors": len(seniors),
        "senior_short": sum(1 for r in seniors if not r["graduatable"]),
        "avg_gpa": round(sum(gpas) / len(gpas), 2) if gpas else None,
        "any_sch": query_one(Q.ANY_SCHOLARSHIP, p)["n"],
        "noreq": len(rows) - len(with_req),
    }
    # 남은 학점 분포 (0, 1~12, 13~30, 31~60, 61+)
    buckets = [("충족", 0, 0), ("1~12", 1, 12), ("13~30", 13, 30), ("31~60", 31, 60), ("61 이상", 61, 999)]
    remaining = [(label, sum(1 for r in with_req if lo <= r["remaining_total"] <= hi)) for label, lo, hi in buckets]
    by_year = {}
    for r in rows:
        y = by_year.setdefault(r["admission_year"], {"n": 0, "ok": 0, "gpa": []})
        y["n"] += 1
        y["ok"] += 1 if r["graduatable"] else 0
        if r["cum_gpa"] is not None:
            y["gpa"].append(float(r["cum_gpa"]))
    cohorts = [{"year": y, "n": v["n"], "ok": v["ok"],
                "gpa": round(sum(v["gpa"]) / len(v["gpa"]), 2) if v["gpa"] else None}
               for y, v in sorted(by_year.items())]
    return render_template("admin/stats.html", kpi=kpi, remaining=remaining, cohorts=cohorts,
                           areas=query_all(Q.AREA_SHORTAGE, p), groups=query_all(Q.GROUP_SHORTAGE, p),
                           sch=query_all(Q.SCHOLARSHIP_COUNTS, p), hist=query_all(Q.LAST_GPA_HISTOGRAM, p),
                           trend=query_all(Q.DEPT_TERM_TREND, p))


@bp.route("/enrollments/<int:enroll_id>/edit", methods=["GET", "POST"])
@login_required("admin")
def grade_edit(enroll_id):
    e = query_one(Q.ENROLLMENT_ONE, (enroll_id,))
    if not e or e["dept_id"] != my_dept():
        abort(404)
    grades = query_all(Q.GRADES)

    if request.method == "POST":
        new_grade = request.form.get("grade")
        if new_grade not in {g["grade"] for g in grades}:
            flash("등급을 선택하세요.", "danger")
            return redirect(request.url)
        sem_key = (e["student_id"], e["year"], e["term"])
        before = query_one(Q.SEMESTER_ONE, sem_key)
        before_sch = {r["sch_name"] for r in query_all(Q.ELIGIBLE_NAMES, (e["student_id"],))}
        before_grad = query_one(Q.GRAD_FLAG, (e["student_id"],))

        execute(Q.UPDATE_GRADE, (new_grade, enroll_id))   # 트리거가 SEMESTER_RECORD 재계산
        get_db().commit()

        after = query_one(Q.SEMESTER_ONE, sem_key)
        after_sch = {r["sch_name"] for r in query_all(Q.ELIGIBLE_NAMES, (e["student_id"],))}
        msg = (f"{e['course_name']} 성적을 {e['grade']} → {new_grade}(으)로 바꿨습니다. "
               f"{e['year']}년 {e['term']}학기 평점 {fmt(before['gpa'])} → {fmt(after['gpa'])}, "
               f"취득학점 {before['earned_credits']} → {after['earned_credits']}.")
        after_grad = query_one(Q.GRAD_FLAG, (e["student_id"],))
        if before_grad["remaining_total"] != after_grad["remaining_total"]:
            msg += f" 남은 졸업학점 {before_grad['remaining_total']} → {after_grad['remaining_total']}."
        if bool(before_grad["graduatable"]) != bool(after_grad["graduatable"]):
            msg += " 졸업 판정: " + ("졸업 가능으로 바뀜." if after_grad["graduatable"] else "미충족으로 바뀜.")
        gained, lost = after_sch - before_sch, before_sch - after_sch
        if gained:
            msg += f" 새로 신청 가능: {', '.join(sorted(gained))}."
        if lost:
            msg += f" 신청 불가로 바뀜: {', '.join(sorted(lost))}."
        flash(msg, "success")
        return redirect(url_for("admin.student_detail", student_id=e["student_id"]))

    return render_template("admin/grade_edit.html", e=e, grades=grades)


def fmt(v):
    return "-" if v is None else f"{v:.2f}"


# ---------------------------------------------------------------- 졸업요건 관리
OVERLAP_MSG = "입학연도 구간이 이 학과의 다른 졸업요건과 겹칩니다."


def is_overlap(e):
    return isinstance(e, pymysql.err.OperationalError) and "GRAD_REQ_OVERLAP" in str(e)


def read_years(form):
    from_year = int(form["from_year"])
    raw_to = form.get("to_year", "").strip()
    to_year = int(raw_to) if raw_to else None
    if to_year is not None and to_year < from_year:
        raise ValueError
    return from_year, to_year


@bp.route("/grad-reqs", methods=["GET", "POST"])
@login_required("admin")
def grad_reqs():
    if request.method == "POST":
        try:
            from_year, to_year = read_years(request.form)
        except (KeyError, ValueError):
            flash("입학연도 구간을 확인하세요. 끝 연도는 비워 두면 '이후 계속'입니다.", "danger")
            return redirect(url_for("admin.grad_reqs"))
        try:
            req_id = execute(Q.GRAD_REQ_INSERT, (my_dept(), from_year, to_year))   # 트리거가 구간 겹침 검사
            get_db().commit()
        except pymysql.err.OperationalError as e:
            get_db().rollback()
            if is_overlap(e):
                flash(OVERLAP_MSG, "warning")
                return redirect(url_for("admin.grad_reqs"))
            raise
        flash("졸업요건을 만들었습니다. 영역별 학점, 과목군, 필수과목을 입력하세요.", "success")
        return redirect(url_for("admin.grad_req_edit", req_id=req_id))
    return render_template("admin/grad_reqs.html", rows=query_all(Q.GRAD_REQ_LIST, (my_dept(),)))


@bp.route("/grad-reqs/<int:req_id>", methods=["GET", "POST"])
@login_required("admin")
def grad_req_edit(req_id):
    req = query_one(Q.GRAD_REQ_ONE, (req_id, my_dept()))
    if not req:
        abort(404)
    areas = query_all(Q.GRAD_REQ_AREAS, (req_id,))
    groups = query_all(Q.GRAD_REQ_GROUPS, (req_id, my_dept()))

    if request.method == "POST":
        if request.form.get("action") == "delete":
            execute(Q.GRAD_REQ_DELETE, (req_id, my_dept()))     # 영역·과목군 연결은 ON DELETE CASCADE
            get_db().commit()
            flash(f"{years_label(req['from_year'], req['to_year'])} 졸업요건을 삭제했습니다.", "success")
            return redirect(url_for("admin.grad_reqs"))
        try:
            from_year, to_year = read_years(request.form)
            total = int(request.form["total_credits"])
            design = int(request.form.get("min_design_credits") or 0)
            raw_gpa = request.form.get("min_gpa", "").strip()
            min_gpa = Decimal(raw_gpa) if raw_gpa else None
            area_vals, group_vals = {}, {}
            for a in areas:
                raw = request.form.get(f"area_{a['area_code']}", "").strip()
                if raw:
                    area_vals[a["area_code"]] = int(raw)
            for g in groups:
                if g["rule_type"] == "ALL":
                    if request.form.get(f"group_{g['group_code']}"):
                        group_vals[g["group_code"]] = None     # ALL 과목군: 연결만 (기준 학점 없음)
                else:
                    raw = request.form.get(f"group_{g['group_code']}", "").strip()
                    if raw and int(raw) > 0:
                        group_vals[g["group_code"]] = int(raw)
        except (KeyError, ValueError, InvalidOperation):
            flash("연도·학점·평점을 숫자로 입력하세요.", "danger")
            return redirect(request.url)
        if (min_gpa is not None and not (0 <= min_gpa <= Decimal("4.5"))) or total <= 0 or design < 0:
            flash("총학점은 1 이상, 최소평점은 0~4.5 사이여야 합니다.", "danger")
            return redirect(request.url)
        note = request.form.get("note", "").strip()[:500] or None

        # 요건 한 건의 헤더·영역·과목군 연결을 한 트랜잭션으로 교체
        conn = get_db()
        try:
            execute(Q.GRAD_REQ_UPDATE, (from_year, to_year, total, min_gpa, design, note, req_id))
            execute(Q.GRAD_REQ_AREA_DELETE, (req_id,))
            for code, credits in area_vals.items():
                execute(Q.GRAD_REQ_AREA_INSERT, (req_id, code, credits))
            execute(Q.GRAD_REQ_GROUP_DELETE, (req_id,))
            for code, credits in group_vals.items():
                execute(Q.GRAD_REQ_GROUP_INSERT, (req_id, code, credits))
            conn.commit()
        except pymysql.err.OperationalError as e:
            conn.rollback()
            if is_overlap(e):
                flash(OVERLAP_MSG, "warning")
                return redirect(request.url)
            raise
        except Exception:
            conn.rollback()
            raise
        flash(f"{years_label(from_year, to_year)} 졸업요건을 저장했습니다.", "success")
        return redirect(url_for("admin.grad_reqs"))

    return render_template("admin/grad_req_edit.html", req=req, areas=areas, groups=groups)


def years_label(f, t):
    return f"{f}~{t}학번" if t else f"{f}학번 이후"


# ---------------------------------------------------------------- 과목군 관리 (필수과목 포함)
CODE_RE = re.compile(r"^[A-Z0-9_]{2,20}$")


@bp.route("/groups", methods=["GET", "POST"])
@login_required("admin")
def groups():
    if request.method == "POST":
        code = request.form.get("group_code", "").strip().upper()
        name = request.form.get("group_name", "").strip()[:50]
        rule = request.form.get("rule_type") if request.form.get("rule_type") in ("CREDITS", "ALL") else "CREDITS"
        if not CODE_RE.match(code) or not name:
            flash("코드는 영문 대문자·숫자·밑줄 2~20자, 이름은 필수입니다.", "danger")
            return redirect(url_for("admin.groups"))
        if query_one(Q.GROUP_EXISTS, (code,)):
            flash(f"{code} 코드는 이미 있습니다.", "warning")
            return redirect(url_for("admin.groups"))
        execute(Q.GROUP_INSERT, (code, name, rule, my_dept(), request.form.get("description", "").strip()[:300] or None))
        get_db().commit()
        flash(f"과목군 '{name}'을(를) 만들었습니다. 과목을 고르세요.", "success")
        return redirect(url_for("admin.group_edit", code=code))
    return render_template("admin/groups.html", rows=query_all(Q.GROUP_LIST, (my_dept(),)))


@bp.route("/groups/<code>", methods=["GET", "POST"])
@login_required("admin")
def group_edit(code):
    g = query_one(Q.GROUP_ONE, (code, my_dept()))
    if not g:
        abort(404)
    editable = g["dept_id"] == my_dept()
    if request.method == "POST":
        if not editable:
            abort(403)
        if request.form.get("action") == "delete":
            execute(Q.GROUP_DELETE, (code, my_dept()))     # 구성 과목·요건 연결은 ON DELETE CASCADE
            get_db().commit()
            flash(f"과목군 '{g['group_name']}'을(를) 삭제했습니다.", "success")
            return redirect(url_for("admin.groups"))
        name = request.form.get("group_name", "").strip()[:50]
        if not name:
            flash("이름을 입력하세요.", "danger")
            return redirect(request.url)
        course_ids = [c for c in dict.fromkeys(request.form.getlist("course"))
                      if query_one(Q.ALLOWED_COURSE, (c, my_dept()))]
        conn = get_db()
        try:                                              # 이름·설명·구성 과목을 한 트랜잭션으로 교체
            execute(Q.GROUP_UPDATE, (name, request.form.get("description", "").strip()[:300] or None, code))
            execute(Q.MEMBER_DELETE, (code,))
            for cid in course_ids:
                execute(Q.MEMBER_INSERT, (code, cid))
            conn.commit()
        except Exception:
            conn.rollback()
            raise
        flash(f"'{name}' 과목군을 저장했습니다 ({len(course_ids)}과목). 연결된 졸업요건 판정에 바로 반영됩니다.", "success")
        return redirect(url_for("admin.groups"))
    return render_template("admin/group_edit.html", g=g, editable=editable,
                           courses=query_all(Q.GROUP_COURSE_CHOICES, (code, my_dept())),
                           used_by=query_all(Q.GROUP_USED_BY, (code,)))


# ---------------------------------------------------------------- 장학금 관리
def read_scholarship_form():
    f = request.form

    def opt_int(key):
        raw = f.get(key, "").strip()
        return int(raw) if raw else None

    name = f.get("sch_name", "").strip()
    category = f.get("category") if f.get("category") in ("성적우수", "가계지원", "학과") else "성적우수"
    amount = f.get("amount_desc", "").strip()[:30] or None
    min_gpa = Decimal(f["min_gpa"])
    min_credits = int(f["min_credits"])
    max_income = opt_int("max_income")
    volunteer = opt_int("min_volunteer_hours")
    exempt = opt_int("volunteer_exempt_level")
    dept = my_dept() if f.get("scope") == "dept" else None
    if not name or not (0 <= min_gpa <= Decimal("4.5")) or min_credits < 0 \
            or (max_income is not None and not 0 <= max_income <= 10) \
            or (volunteer is not None and volunteer < 0):
        raise ValueError
    return name, category, amount, min_gpa, min_credits, max_income, volunteer, exempt, dept


@bp.route("/scholarships", methods=["GET", "POST"])
@login_required("admin")
def scholarships():
    if request.method == "POST":
        try:
            vals = read_scholarship_form()
        except (KeyError, ValueError, InvalidOperation):
            flash("입력값을 확인하세요. 평점 0~4.5, 소득분위 0~10(비우면 무관)입니다.", "danger")
            return redirect(url_for("admin.scholarships"))
        execute(Q.SCHOLARSHIP_INSERT, vals)
        get_db().commit()
        flash(f"{vals[0]}을(를) 등록했습니다.", "success")
        return redirect(url_for("admin.scholarships"))
    rows = query_all(Q.SCHOLARSHIP_LIST, {"dept": my_dept()})
    return render_template("admin/scholarships.html", rows=rows, sch=None)


@bp.route("/scholarships/<int:sch_id>", methods=["GET", "POST"])
@login_required("admin")
def scholarship_edit(sch_id):
    sch = query_one(Q.SCHOLARSHIP_ONE, (sch_id, my_dept()))
    if not sch:
        abort(404)
    if request.method == "POST":
        if request.form.get("action") == "delete":
            execute(Q.SCHOLARSHIP_DELETE, (sch_id,))
            get_db().commit()
            flash(f"{sch['sch_name']}을(를) 삭제했습니다.", "success")
            return redirect(url_for("admin.scholarships"))
        try:
            vals = read_scholarship_form()
        except (KeyError, ValueError, InvalidOperation):
            flash("입력값을 확인하세요. 평점 0~4.5, 소득분위 0~10(비우면 무관)입니다.", "danger")
            return redirect(request.url)
        execute(Q.SCHOLARSHIP_UPDATE, (*vals, sch_id))
        get_db().commit()
        flash(f"{vals[0]}을(를) 수정했습니다.", "success")
        return redirect(url_for("admin.scholarships"))
    rows = query_all(Q.SCHOLARSHIP_LIST, {"dept": my_dept()})
    return render_template("admin/scholarships.html", rows=rows, sch=sch)
