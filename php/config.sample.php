<?php
/**
 * 설정 파일 견본. 서버에서  cp config.sample.php config.php  로 복사한 뒤 config.php 만 고치세요.
 * config.php 는 .gitignore 에 들어 있어 GitHub 에 올라가지 않습니다 (Oracle 비밀번호가 들어 있으므로).
 *
 * 주의: 비밀번호가 틀리면 앱이 접속을 멈춥니다(팀 계정 잠금 방지). 고쳐 저장하면 다시 접속합니다.
 */
define('DB_USER', 'db2026_501_1');                  // 팀 Oracle 계정 (분반·팀 번호로 바꾸기)
define('DB_PASS', '여기에_팀_Oracle_비밀번호');      // Ubuntu 비밀번호가 아니라 Oracle 비밀번호
define('DB_CONN', '//203.249.87.57:1521/orcl');      // 안내 PDF 의 DB 서버 주소 (바꿀 필요 없음)
