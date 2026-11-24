# DB 모니터링 웹사이트 (db-proj-test)

## 1. config.json 설정

`db-proj-test/config.json`에 분반, 팀, 비밀번호를 적습니다.

```json
{
  "section": "501",
  "team": "3",
  "password": "여기에_DB_비밀번호"
}
```

- `section`, `team`은 숫자만 적습니다.
- `password`는 팀 계정의 DB 비밀번호입니다.

## 2. 실행

```bash
cd db-proj-test
npm install   # 최초 1회
npm run dev
```

브라우저에서 http://localhost:3000 으로 접속하면 테이블 목록과 데이터를 확인할 수 있습니다.
