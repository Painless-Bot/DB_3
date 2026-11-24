from flask import Flask, redirect, session, url_for

from config import Config


def create_app():
    app = Flask(__name__)
    app.config.from_object(Config)

    from . import db
    db.init_app(app)

    from .auth import bp as auth_bp
    from .student import bp as student_bp
    from .admin import bp as admin_bp
    app.register_blueprint(auth_bp)
    app.register_blueprint(student_bp)
    app.register_blueprint(admin_bp)

    from .filters import register_filters
    register_filters(app)

    @app.route("/")
    def index():
        role = session.get("role")
        if role == "student":
            return redirect(url_for("student.dashboard"))
        if role == "admin":
            return redirect(url_for("admin.students"))
        return redirect(url_for("auth.login"))

    return app
