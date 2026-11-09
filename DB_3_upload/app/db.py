"""PyMySQL 연결 헬퍼. 요청마다 연결 하나를 열고, 요청이 끝나면 닫는다."""
import pymysql
from flask import current_app, g


def get_db():
    if "db" not in g:
        cfg = current_app.config
        g.db = pymysql.connect(
            host=cfg["DB_HOST"], port=cfg["DB_PORT"],
            user=cfg["DB_USER"], password=cfg["DB_PASSWORD"],
            database=cfg["DB_NAME"], charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor, autocommit=False,
        )
    return g.db


def close_db(_exc=None):
    conn = g.pop("db", None)
    if conn is not None:
        conn.close()


def init_app(app):
    app.teardown_appcontext(close_db)


def query_all(sql, args=None):
    with get_db().cursor() as cur:
        cur.execute(sql, args)
        return cur.fetchall()


def query_one(sql, args=None):
    with get_db().cursor() as cur:
        cur.execute(sql, args)
        return cur.fetchone()


def execute(sql, args=None):
    """INSERT/UPDATE/DELETE 한 문장 실행. 커밋은 호출한 쪽에서 한다."""
    with get_db().cursor() as cur:
        cur.execute(sql, args)
        return cur.lastrowid
