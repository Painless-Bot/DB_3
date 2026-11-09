from decimal import Decimal, InvalidOperation

import pymysql

from flask import Blueprint, abort, flash, redirect, render_template, request, session, url_for

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
@bp.route("/students")
@login_required("admin")
def students():
    q = request.args.get("q", "").strip()
    only_short = request.args.get("only_short") == "1"
    rows = query_all(Q.STUDENT_LIST, {"dept": my_dept(), "q": q})
    if only_short:
        short_ids = {r["student_id"] for r in query_all(Q.SHORT_AREA_STUDENTS, (my_dept(), my_dept()))}
        rows = [r for r in rows if r["student_id"] in short_ids]
    return render_template("admin/students.html", rows=rows, q=q, only_short=only_short)


@bp.route("/students/<student_id>")
@login_required("admin")
def student_detail(student_id):
    check_student(student_id)
    return render_template("admin/student_detail.html", r=load_report(student_id))


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

        execute(Q.UPDATE_GRADE, (new_grade, enroll_id))   # 트리거가 SEMESTER_RECORD 재계산
        get_db().commit()

        after = query_one(Q.SEMESTER_ONE, sem_key)
        after_sch = {r["sch_name"] for r in query_all(Q.ELIGIBLE_NAMES, (e["student_id"],))}
        msg = (f"{e['course_name']} 성적을 {e['grade']} → {new_grade}(으)로 바꿨습니다. "
               f"{e['year']}년 {e['term']}학기 평점 {fmt(before['gpa'])} → {fmt(after['gpa'])}, "
               f"취득학점 {before['earned_credits']} → {after['earned_credits']}.")
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
    groups = query_all(Q.GRAD_REQ_GROUPS, (req_id,))

    if request.method == "POST":
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
                raw = request.form.get(f"group_{g['group_code']}", "").strip()
                if raw:
                    group_vals[g["group_code"]] = int(raw)
        except (KeyError, ValueError, InvalidOperation):
            flash("연도·학점·평점을 숫자로 입력하세요.", "danger")
            return redirect(request.url)
        if (min_gpa is not None and not (0 <= min_gpa <= Decimal("4.5"))) or total <= 0 or design < 0:
            flash("총학점은 1 이상, 최소평점은 0~4.5 사이여야 합니다.", "danger")
            return redirect(request.url)
        note = request.form.get("note", "").strip()[:500] or None
        course_ids = [c for c in request.form.getlist("required") if query_one(Q.ALLOWED_COURSE, (c, my_dept()))]

        # 요건 한 건의 헤더·영역·과목군·필수과목을 한 트랜잭션으로 교체
        conn = get_db()
        try:
            execute(Q.GRAD_REQ_UPDATE, (from_year, to_year, total, min_gpa, design, note, req_id))
            execute(Q.GRAD_REQ_AREA_DELETE, (req_id,))
            for code, credits in area_vals.items():
                execute(Q.GRAD_REQ_AREA_INSERT, (req_id, code, credits))
            execute(Q.GRAD_REQ_GROUP_DELETE, (req_id,))
            for code, credits in group_vals.items():
                execute(Q.GRAD_REQ_GROUP_INSERT, (req_id, code, credits))
            execute(Q.REQUIRED_DELETE, (req_id,))
            for cid in course_ids:
                execute(Q.REQUIRED_INSERT, (req_id, cid))
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
        label = f"{from_year}~{to_year}학번" if to_year else f"{from_year}학번 이후"
        flash(f"{label} 졸업요건을 저장했습니다.", "success")
        return redirect(url_for("admin.grad_reqs"))

    courses = query_all(Q.COURSE_CHOICES, (req_id, my_dept()))
    return render_template("admin/grad_req_edit.html", req=req, areas=areas, groups=groups, courses=courses)


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
