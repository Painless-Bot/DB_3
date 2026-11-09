from functools import wraps

from flask import Blueprint, abort, flash, redirect, render_template, request, session, url_for
from werkzeug.security import check_password_hash

from .db import query_all, query_one

bp = Blueprint("auth", __name__)


def login_required(role):
    """역할이 맞지 않으면 로그인 화면으로 보낸다."""
    def deco(view):
        @wraps(view)
        def wrapped(*args, **kwargs):
            if "login_id" not in session:
                return redirect(url_for("auth.login", next=request.path))
            if session.get("role") != role:
                abort(403)
            return view(*args, **kwargs)
        return wrapped
    return deco


@bp.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        login_id = request.form.get("login_id", "").strip()
        password = request.form.get("password", "")
        user = query_one(
            "SELECT login_id, password_hash, role, student_id, dept_id "
            "FROM USER_ACCOUNT WHERE login_id = %s", (login_id,))
        if user and check_password_hash(user["password_hash"], password):
            session.clear()
            session["login_id"] = user["login_id"]
            session["role"] = user["role"]
            session["student_id"] = user["student_id"]
            session["dept_id"] = user["dept_id"]
            nxt = request.args.get("next")
            if nxt and nxt.startswith("/") and not nxt.startswith("//"):
                return redirect(nxt)
            return redirect(url_for("index"))
        flash("아이디 또는 비밀번호가 올바르지 않습니다.", "danger")
    return render_template("login.html", reqs=query_all(LANDING_REQS), scholarships=query_all(LANDING_SCH))


# 첫 화면 '판정 기준' 섹션: DB 에 들어 있는 기준을 그대로 보여 준다
LANDING_REQS = """
SELECT r.from_year, r.to_year, r.total_credits,
       (SELECT GROUP_CONCAT(DISTINCT CASE WHEN g.group_code LIKE 'SWD%%' THEN 'SW/데이터활용역량 9학점'
                                         WHEN g.group_code = 'SPECIAL_LA' THEN '특성화교양 3학점' END
                            ORDER BY g.group_code SEPARATOR ' · ')
          FROM GRAD_REQ_GROUP g WHERE g.req_id = r.req_id) AS group_names
FROM GRAD_REQ r ORDER BY r.dept_id DESC, r.from_year DESC
"""
LANDING_SCH = """
SELECT sch_name, amount_desc, min_gpa, max_income, min_volunteer_hours
FROM SCHOLARSHIP WHERE dept_id IS NULL
ORDER BY FIELD(category, '성적우수', '가계지원'), min_gpa DESC
"""


@bp.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("auth.login"))
