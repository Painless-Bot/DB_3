<?php
/**
 * Oracle(OCI8) 연결 헬퍼. 요청마다 연결 하나를 열고, 요청이 끝나면 PHP 가 닫는다.
 *
 *   q_all($sql, $binds)   SELECT 결과 전체 (칼럼 이름은 소문자, NUMBER 는 int/float, NULL 은 null)
 *   q_one($sql, $binds)   첫 행 하나 (없으면 null)
 *   q_exec($sql, $binds)  INSERT/UPDATE/DELETE. 커밋하지 않는다 → db_commit() / db_rollback()
 *
 * 바인드 변수는 :name 형식이고 $binds 는 ['name' => 값] 이다 (SQL 인젝션 방지).
 * Oracle 에서는 빈 문자열('')이 NULL 과 같다.
 */
defined('APP_ROOT') || exit;

class DbError extends RuntimeException
{
    public $ora_code;

    public function __construct($err)
    {
        $this->ora_code = (int)($err['code'] ?? 0);
        parent::__construct($err['message'] ?? 'Oracle 오류');
    }
}

/**
 * 비밀번호가 틀린 상태로 접속을 계속 시도하면 팀 Oracle 계정이 잠긴다(10회 실패 → 하루 잠김).
 * 그래서 ORA-01017(아이디/비밀번호 오류)이 한 번 나면 config.php 가 바뀔 때까지 다시 시도하지 않는다.
 */
function db_lock_file()
{
    return sys_get_temp_dir() . '/hgc_dbauth_' . md5(APP_ROOT . DB_USER) . '.lock';
}

function db_conn()
{
    static $conn = null;
    if ($conn !== null) {
        return $conn;
    }
    if (!function_exists('oci_connect')) {
        fatal_page('PHP OCI8 확장이 없습니다', '이 서버의 PHP 에서 oci_connect 를 쓸 수 없습니다. 예시 index.php 가 동작하는 서버인지 확인하세요.');
    }
    $lock = db_lock_file();
    $cfg_time = @filemtime(APP_ROOT . '/config.php');
    if (is_file($lock) && (int)@file_get_contents($lock) === (int)$cfg_time) {
        fatal_page('DB 비밀번호 확인 필요',
            'config.php 의 Oracle 비밀번호가 틀려 접속을 멈췄습니다. (틀린 비밀번호로 10번 접속하면 팀 계정이 하루 동안 잠깁니다.) '
            . 'config.php 의 DB_PASS 를 고쳐 저장하면 다시 접속합니다.');
    }
    $c = @oci_connect(DB_USER, DB_PASS, DB_CONN, 'AL32UTF8');
    if (!$c) {
        $e = oci_error();
        if ((int)($e['code'] ?? 0) === 1017) {
            @file_put_contents($lock, (string)$cfg_time);
            fatal_page('DB 비밀번호 오류',
                'config.php 의 Oracle 계정 또는 비밀번호가 틀렸습니다 (ORA-01017). 계정이 잠기지 않도록 더 이상 접속을 시도하지 않습니다. '
                . 'config.php 를 고쳐 저장한 뒤 새로고침하세요.');
        }
        if ((int)($e['code'] ?? 0) === 28000) {
            fatal_page('Oracle 계정 잠김', '팀 Oracle 계정이 잠겼습니다 (ORA-28000). 하루 뒤에 풀리거나, 담당 조교님께 해제를 요청하세요.');
        }
        fatal_page('DB 접속 실패', 'Oracle 에 접속할 수 없습니다: ' . ($e['message'] ?? '알 수 없는 오류'));
    }
    @unlink($lock);
    // 숫자·날짜를 항상 같은 모양으로 받는다 (소수점 '.', 날짜 'YYYY-MM-DD HH24:MI:SS')
    foreach (["ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,'",
              "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"] as $sql) {
        $st = oci_parse($c, $sql);
        oci_execute($st);
    }
    $conn = $c;
    return $conn;
}

function db_run($sql, array $binds = [])
{
    $c = db_conn();
    $st = oci_parse($c, $sql);
    if (!$st) {
        throw new DbError(oci_error($c));
    }
    foreach ($binds as $k => $v) {
        $binds[$k] = is_bool($v) ? (int)$v : $v;
        oci_bind_by_name($st, ':' . $k, $binds[$k]);
    }
    if (!@oci_execute($st, OCI_NO_AUTO_COMMIT)) {
        throw new DbError(oci_error($st));
    }
    return $st;
}

/** NUMBER 칼럼 값을 PHP 숫자로 바꾼다 (OCI8 은 모든 값을 문자열로 준다). */
function db_rows($st)
{
    $n = oci_num_fields($st);
    $num = [];
    for ($i = 1; $i <= $n; $i++) {
        if (oci_field_type($st, $i) === 'NUMBER') {
            $num[strtolower(oci_field_name($st, $i))] = true;
        }
    }
    $rows = [];
    while (($r = oci_fetch_array($st, OCI_ASSOC + OCI_RETURN_NULLS)) !== false) {
        $row = [];
        foreach ($r as $k => $v) {
            $k = strtolower($k);
            if ($v !== null && isset($num[$k])) {
                $v = (strpos($v, '.') === false) ? (int)$v : (float)$v;
            }
            $row[$k] = $v;
        }
        $rows[] = $row;
    }
    oci_free_statement($st);
    return $rows;
}

function q_all($sql, array $binds = [])
{
    return db_rows(db_run($sql, $binds));
}

function q_one($sql, array $binds = [])
{
    $rows = q_all($sql, $binds);
    return $rows ? $rows[0] : null;
}

/** 반환: 바뀐 행 수. $returning 을 주면 RETURNING ... INTO :이름 값을 돌려준다. */
function q_exec($sql, array $binds = [], $returning = null)
{
    $c = db_conn();
    $st = oci_parse($c, $sql);
    foreach ($binds as $k => $v) {
        $binds[$k] = is_bool($v) ? (int)$v : $v;
        oci_bind_by_name($st, ':' . $k, $binds[$k]);
    }
    $out = null;
    if ($returning) {
        oci_bind_by_name($st, ':' . $returning, $out, 40);
    }
    if (!@oci_execute($st, OCI_NO_AUTO_COMMIT)) {
        throw new DbError(oci_error($st));
    }
    $n = oci_num_rows($st);
    oci_free_statement($st);
    return $returning ? (int)$out : $n;
}

function db_commit()
{
    oci_commit(db_conn());
}

function db_rollback()
{
    oci_rollback(db_conn());
}
