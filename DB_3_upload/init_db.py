"""db/01~04 SQL 을 순서대로 실행해 grad_check DB 를 만든다.

    python init_db.py              DB 를 새로 만든다 (기존 grad_check 는 지워짐)
    python init_db.py --if-needed  DB 가 없거나 구버전일 때만 만든다 (start.bat 이 사용)

.env 가 없으면 MySQL 비밀번호를 물어보고 .env 를 만들어 준다.
"""
import getpass
import os
import secrets
import sys

ROOT = os.path.dirname(os.path.abspath(__file__))
ENV = os.path.join(ROOT, ".env")
SCHEMA_VERSION = 3   # db/01_schema.sql 의 DB_META 값과 같게 유지
SQL_FILES = ["01_schema.sql", "02_views.sql", "03_triggers.sql", "04_sample_data.sql"]


def ensure_env():
    if os.path.exists(ENV):
        return
    print("MySQL 접속 정보를 입력하세요. (엔터를 누르면 [ ] 안의 값 사용)")
    user = input("  MySQL 사용자 [root]: ").strip() or "root"
    pw = getpass.getpass("  MySQL 비밀번호 (입력해도 화면에 안 보입니다): ")
    with open(ENV, "w", encoding="utf-8") as f:
        f.write(f"DB_HOST=127.0.0.1\nDB_PORT=3306\nDB_USER={user}\nDB_PASSWORD={pw}\n"
                f"DB_NAME=grad_check\nSECRET_KEY={secrets.token_hex(16)}\n")
    print("  .env 파일을 만들었습니다.\n")


def statements(path):
    """mysql CLI 처럼 DELIMITER 를 처리하면서 문장 단위로 나눈다."""
    delim, buf = ";", []
    with open(path, encoding="utf-8") as f:
        for line in f:
            s = line.strip()
            if s.upper().startswith("DELIMITER"):
                delim = s.split()[1]
                continue
            if not buf and (not s or s.startswith("--")):
                continue
            buf.append(line)
            if s.endswith(delim):
                stmt = "".join(buf).rstrip()
                yield stmt[: -len(delim)]
                buf = []
    if "".join(buf).strip():
        yield "".join(buf)


def main():
    ensure_env()
    sys.path.insert(0, ROOT)
    from config import Config
    import pymysql

    try:
        conn = pymysql.connect(host=Config.DB_HOST, port=Config.DB_PORT, user=Config.DB_USER,
                               password=Config.DB_PASSWORD, charset="utf8mb4", autocommit=True)
    except pymysql.err.OperationalError as e:
        code = e.args[0]
        if code == 1045:
            print("[오류] MySQL 비밀번호가 틀렸습니다. .env 파일의 DB_PASSWORD 를 고치거나 .env 를 지우고 다시 실행하세요.")
        else:
            print(f"[오류] MySQL 에 접속할 수 없습니다. MySQL 서버가 켜져 있는지 확인하세요. ({e})")
        sys.exit(1)

    with conn.cursor() as cur:
        if "--if-needed" in sys.argv:
            cur.execute("SELECT COUNT(*) FROM information_schema.tables "
                        "WHERE table_schema = %s AND table_name = 'DB_META'", (Config.DB_NAME,))
            version = 0
            if cur.fetchone()[0]:
                cur.execute(f"SELECT MAX(schema_version) FROM `{Config.DB_NAME}`.DB_META")
                version = cur.fetchone()[0] or 0
            if version >= SCHEMA_VERSION:
                print("DB 가 이미 최신입니다. (처음부터 다시 만들려면: python init_db.py)")
                return
            cur.execute("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = %s", (Config.DB_NAME,))
            if cur.fetchone()[0]:
                print("DB 구조가 새 버전으로 바뀌어 grad_check DB 를 다시 만듭니다. (예전 시연 데이터는 지워집니다)")
        for name in SQL_FILES:
            n = 0
            for stmt in statements(os.path.join(ROOT, "db", name)):
                cur.execute(stmt)
                n += 1
            print(f"  {name} 실행 완료 ({n}개 문장)")
    print("DB 준비 완료.")


if __name__ == "__main__":
    main()
