# 학교 서버에 올리기 (PHP + Oracle 판)

HONGIK 진단센터를 학교 웹 서버(203.249.87.58)와 Oracle 서버(203.249.87.57)에 올리는 순서입니다.
`php/` 폴더 하나가 사이트 전체입니다. Flask/MySQL 판(`app/`, `db/`)과는 따로 동작합니다.

> ## ⚠️ 먼저 꼭 읽기: Oracle 비밀번호 10번 틀리면 팀 계정이 하루 잠깁니다
> - Oracle 비밀번호는 **Ubuntu(SSH) 비밀번호와 다릅니다.** 개인 Oracle 계정도 아니고 **팀 Oracle 계정**입니다.
> - SQL Developer·DBeaver 같은 DB 도구나 `config.php`에 **예전 비밀번호가 저장돼 있으면** 자동으로 계속 틀린 비밀번호로 접속을 시도합니다. 비밀번호를 바꿨다면 저장해 둔 곳도 모두 고치세요.
> - 이 앱은 `config.php`의 비밀번호가 한 번 틀리면(ORA-01017) **그 뒤로는 접속을 시도하지 않습니다.** `config.php`를 고쳐 저장하면 그때 다시 한 번 시도합니다. 그래서 새로고침을 여러 번 눌러도 횟수가 늘지 않습니다.
> - 그래도 팀원끼리 같은 날 여러 명이 비밀번호를 시험해 보지 마세요. 한 사람이 맡아서 하는 게 안전합니다.

아래에서 `db2026_501_1`은 안내 PDF의 예시 계정입니다. **우리 팀의 분반·팀 번호로 바꿔 읽으세요.**

---

## 1. 서버 접속 + (처음 한 번) Oracle 비밀번호 바꾸기

```bash
ssh -p 20022 db2026_501_1@203.249.87.58          # Ubuntu 비밀번호 입력
```

Oracle 초기 비밀번호는 계정 이름과 같습니다. 처음이면 바꿔 두세요 (이미 바꿨으면 건너뛰기).

```bash
sqlplus -L "db2026_501_1@//203.249.87.57:1521/orcl"
# 비밀번호 입력 → 만료 안내가 나오면 새 비밀번호를 두 번 입력
exit
```

`-L`은 틀렸을 때 다시 묻지 않고 끝내라는 뜻입니다. 틀린 비밀번호로 여러 번 재시도하지 않게 해 줍니다.

## 2. 예시 파일 받기 (안내 PDF 그대로)

```bash
cp -rn /opt/db2026-example/* ~/
ls ~                     # index.php, README.txt 등이 생김
cat ~/README.txt         # 학교 쪽 안내. 아래 내용과 다르면 README.txt 를 따르세요
```

홈 폴더(`~`)가 곧 웹 폴더입니다. 브라우저에서 `http://203.249.87.58/db2026_분반/팀계정/`을 열어
예시 화면이 뜨고, 팀 Oracle 비밀번호를 넣었을 때 표가 보이면 서버 쪽 PHP·OCI8은 정상입니다.

예시 `index.php`는 우리 앱의 `index.php`와 이름이 같으니 **덮어쓰기 전에 이름을 바꿔 보관**합니다.

```bash
mv ~/index.php ~/index_example.php
```

## 3. 우리 앱 파일 올리기

**방법 A. 내 컴퓨터에서 scp로 올리기** (저장소를 받은 폴더에서 실행)

```bash
scp -P 20022 -r php/* php/.gitignore db2026_501_1@203.249.87.58:~/
```

**방법 B. 서버에서 git으로 받기** (저장소가 비공개라면 GitHub 토큰이 필요합니다)

```bash
cd ~ && git clone https://github.com/Painless-Bot/DB_3.git DB_3_src
cp -r DB_3_src/php/. ~/
```

올린 뒤 `~` 안에 `index.php`, `config.sample.php`, `lib/`, `pages/`, `views/`, `static/`, `sql/`이 있으면 됩니다.

## 4. 테이블·뷰·트리거·샘플 데이터 만들기

> **주의:** `install.sql`은 먼저 이 시스템의 테이블(STUDENT, COURSE, ENROLLMENT, GRAD_REQ 등 14개)을 지우고 새로 만듭니다.
> 팀 계정에 **이름이 같은 테이블이 이미 있고 그 데이터가 필요하면** 먼저 백업하세요. 확인 방법:
>
> ```sql
> SELECT table_name FROM user_tables ORDER BY 1;
> ```

```bash
cd ~/sql
export NLS_LANG=AMERICAN_AMERICA.AL32UTF8         # 한글 깨짐 방지 (꼭 먼저)
sqlplus -L "db2026_501_1@//203.249.87.57:1521/orcl" @install.sql
```

끝에 이렇게 나오면 성공입니다.

| 확인 항목 | 기대값 |
|---|---|
| 컴파일 오류 | `no rows selected` |
| 학생 / 수강 / 학기기록 / 계정 | 6 / 187 / 31 / 8 |
| TABLE / VIEW / TRIGGER / PROCEDURE / FUNCTION | 14 / 10 / 4 / 3 / 1, 모두 VALID |

`exit`로 SQL*Plus를 닫습니다. 다시 실행하면 처음 샘플 상태로 되돌아갑니다 (시연 전에 초기화할 때 쓰면 편합니다).

설치가 끝나면 `sql/` 폴더는 웹에서 볼 필요가 없으니 막아 두세요 (아래 6번).

## 5. config.php 만들기 (비밀번호는 여기 한 곳에만)

```bash
cd ~
cp config.sample.php config.php
nano config.php           # DB_USER 를 팀 계정으로, DB_PASS 에 팀 Oracle 비밀번호
```

- `DB_CONN`은 `//203.249.87.57:1521/orcl` 그대로 둡니다.
- **저장하기 전에 비밀번호를 한 번 더 확인하세요.** 틀리면 앱이 접속을 멈추고 화면에 "Oracle 계정 또는 비밀번호가 틀렸습니다"라고 알려 줍니다. 고쳐서 저장하면 다시 접속합니다.
- `config.php`는 `.gitignore`에 들어 있어 GitHub에 올라가지 않습니다. 비밀번호를 커밋하거나 단톡방에 올리지 마세요.

## 6. 권한 정리

```bash
chmod 600 ~/config.php    # 다른 계정이 비밀번호를 읽지 못하게
chmod 700 ~/sql           # SQL 파일은 웹에서 안 보이게
```

`chmod 600` 후 사이트에 "Permission denied"나 빈 화면이 나오면 웹 서버가 다른 사용자로 PHP를 돌리는 경우입니다.
그때는 `chmod 644 ~/config.php`로 되돌리세요 (예시 README.txt에 안내가 있으면 그걸 따르기).

`lib/`, `pages/`, `views/`, `sql/`에는 `.htaccess`(직접 접근 차단)가 들어 있습니다. 서버가 `.htaccess`를 꺼 둬도 이 폴더의 PHP 파일은 직접 열면 아무것도 출력하지 않게 만들어 두었습니다.

## 7. 접속해서 확인

`http://203.249.87.58/db2026_분반/팀계정/` → 로그인 화면이 뜹니다.

| 역할 | 아이디 | 비밀번호 |
|---|---|---|
| 학생 (6명) | 학번 (예: `20211001`) | 학번과 같음 |
| 데모 학생 (성적 없음, 가져오기 시연용) | `demo` | `demo1234` |
| 소프트웨어융합학과 담당자 | `admin_sw` | `admin1234` |
| 컴퓨터공학과 담당자 | `admin_ce` | `admin1234` |

시연용 비밀번호입니다. 공개된 주소이므로 발표가 끝나면 로그인 후 오른쪽 위 계정 버튼(비밀번호 변경)에서 바꾸는 걸 권합니다.

주소는 `index.php?r=me/grades`처럼 `r=` 뒤에 화면 경로가 붙는 형식입니다 (서버 설정을 바꾸지 않아도 됨).

---

## 문제가 생기면

| 증상 | 원인과 해결 |
|---|---|
| "Oracle 계정 또는 비밀번호가 틀렸습니다 (ORA-01017)" | `config.php`의 `DB_USER`·`DB_PASS` 확인 후 저장. 앱은 저장 전까지 재시도하지 않습니다. |
| "Oracle 계정 잠김 (ORA-28000)" | 10번 틀려 잠긴 상태. 하루 기다리거나 조교님께 해제 요청. 그동안 DB 도구·`config.php`의 저장된 비밀번호를 모두 고쳐 두세요. |
| "PHP OCI8 확장이 없습니다" | 2번의 예시 화면이 뜨는지 먼저 확인. 예시도 안 되면 서버 문제이니 조교님께 문의. |
| "config.php 가 없습니다" | 5번을 안 한 상태. |
| 한글이 `???`나 깨진 글자로 저장됨 | `install.sql` 실행 전에 `export NLS_LANG=AMERICAN_AMERICA.AL32UTF8`을 빠뜨림. 설정 후 `install.sql`을 다시 실행. |
| 설치 중 `ORA-00955 이미 존재` 등으로 멈춤 | 같은 이름의 다른 객체가 있음. 4번의 `user_tables` 조회로 확인하고 지우거나 이름을 정리한 뒤 다시 실행. |
| "DB 오류" 화면 | 화면에 나온 ORA 번호와 함께 팀 채팅에 공유. |
| 로그인했는데 바로 로그인 화면으로 돌아감 | 브라우저 쿠키 차단 여부 확인. 다른 팀 사이트와 세션이 섞이지 않게 쿠키 경로를 우리 폴더로 한정해 두었습니다. |

## 참고: 개발 때 확인한 범위

- Oracle Database 23ai Free에서 `install.sql`을 여러 번 설치·재설치해 보고, 모든 화면을 Flask/MySQL 판과 비교해 같은 결과가 나오는 걸 확인했습니다 (동점 과목 순서 등 사소한 차이만 있음).
- 학교 서버의 실제 OCI8 확장과 Oracle 버전에서는 아직 돌려 보지 못했습니다. 위 순서대로 올린 뒤 학생·관리자 계정으로 한 번씩 둘러보면서 오류 화면이 나오면 ORA 번호를 알려 주세요.
- 사용한 문법(IDENTITY 열, `FETCH FIRST`, `LATERAL`, compound trigger, `LISTAGG`)은 Oracle 12c 이상에서 동작합니다.
