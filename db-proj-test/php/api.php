<?php
// 실습 서버 팀 폴더(~/api.php)에 업로드. Next.js가 POST(JSON)로 호출한다.
// 공통 요청: {"user":"db2026_501_1","password":"...","action":"tables|data|query", ...}
//   tables : 테이블별 행 수 + 컬럼 구조(PK/FK 포함)
//   data   : {"table":"STUDENT","page":1,"q":"검색어"}  (페이지당 50행)
//   query  : {"sql":"SELECT ..."}  읽기 전용, 최대 200행
header('Content-Type: application/json; charset=utf-8');

const PAGE_SIZE = 50;
const QUERY_MAX = 200;

function out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function fail($msg, $code = 400) { out(['error' => $msg], $code); }

// 바인드 변수를 지원하는 실행 헬퍼
function run($conn, $sql, $binds = []) {
    $st = oci_parse($conn, $sql);
    foreach ($binds as $k => &$v) oci_bind_by_name($st, $k, $v);
    if (!@oci_execute($st)) {
        $e = oci_error($st);
        throw new Exception($e['message'] ?? 'query failed');
    }
    return $st;
}
function fetchAll($st, $max = PHP_INT_MAX) {
    $rows = [];
    while (count($rows) < $max && ($r = oci_fetch_array($st, OCI_NUM + OCI_RETURN_NULLS + OCI_RETURN_LOBS))) {
        $rows[] = array_map(fn($v) => $v === null ? null : (string)$v, $r);
    }
    return $rows;
}
function colNames($st) {
    $n = oci_num_fields($st);
    $c = [];
    for ($i = 1; $i <= $n; $i++) $c[] = oci_field_name($st, $i);
    return $c;
}
function typeLabel($r) {
    [$type, $len, $prec, $scale] = [$r[2], $r[3], $r[4], $r[5]];
    if ($type === 'NUMBER') {
        if ($prec === null) return 'NUMBER';
        return $scale ? "NUMBER($prec,$scale)" : "NUMBER($prec)";
    }
    if (in_array($type, ['VARCHAR2', 'CHAR', 'NVARCHAR2', 'NCHAR'], true)) return "$type($len)";
    return $type;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail('JSON 본문이 필요합니다.');

$user = $in['user'] ?? '';
$pass = $in['password'] ?? '';
$action = $in['action'] ?? 'tables';

if (!preg_match('/^db2026_\d+_\d+$/', $user)) fail('계정 형식이 올바르지 않습니다.');
if ($pass === '') fail('비밀번호가 비어 있습니다.');

$conn = @oci_connect($user, $pass, '//203.249.87.57:1521/orcl', 'AL32UTF8');
if (!$conn) {
    $e = oci_error();
    fail('Oracle 접속 실패: ' . ($e['message'] ?? 'unknown'), 401);
}

try {
    // 내 테이블 목록 (이후 테이블 이름 화이트리스트로 사용)
    $names = [];
    foreach (fetchAll(run($conn, 'SELECT table_name FROM user_tables ORDER BY table_name')) as $r) $names[] = $r[0];

    if ($action === 'tables') {
        $cols = [];
        foreach (fetchAll(run($conn, 'SELECT table_name, column_name, data_type, data_length, data_precision, data_scale, nullable
                                       FROM user_tab_columns ORDER BY table_name, column_id')) as $r) {
            $cols[$r[0]][$r[1]] = ['name' => $r[1], 'type' => typeLabel($r), 'nullable' => $r[6] === 'Y', 'pk' => false, 'fk' => null];
        }
        foreach (fetchAll(run($conn, "SELECT cc.table_name, cc.column_name FROM user_constraints c
                                       JOIN user_cons_columns cc ON cc.constraint_name = c.constraint_name
                                       WHERE c.constraint_type = 'P'")) as $r) {
            if (isset($cols[$r[0]][$r[1]])) $cols[$r[0]][$r[1]]['pk'] = true;
        }
        foreach (fetchAll(run($conn, "SELECT cc.table_name, cc.column_name, rc.table_name, rcc.column_name
                                       FROM user_constraints c
                                       JOIN user_cons_columns cc ON cc.constraint_name = c.constraint_name
                                       JOIN user_constraints rc ON rc.constraint_name = c.r_constraint_name
                                       JOIN user_cons_columns rcc ON rcc.constraint_name = rc.constraint_name AND rcc.position = cc.position
                                       WHERE c.constraint_type = 'R'")) as $r) {
            if (isset($cols[$r[0]][$r[1]])) $cols[$r[0]][$r[1]]['fk'] = $r[2] . '.' . $r[3];
        }
        $tables = [];
        foreach ($names as $t) {
            $cnt = null;
            try { $cnt = (int)fetchAll(run($conn, 'SELECT COUNT(*) FROM "' . $t . '"'))[0][0]; } catch (Exception $e) {}
            $tables[] = ['name' => $t, 'rows' => $cnt, 'columns' => array_values($cols[$t] ?? [])];
        }
        out(['tables' => $tables]);
    }

    if ($action === 'data') {
        $table = $in['table'] ?? '';
        if (!in_array($table, $names, true)) fail('존재하지 않는 테이블입니다.', 404);
        $page = max(1, (int)($in['page'] ?? 1));
        $q = trim((string)($in['q'] ?? ''));

        // 검색: 문자/숫자/날짜 컬럼 전체에서 부분 일치 (LOB 등은 제외)
        $where = '';
        $binds = [];
        if ($q !== '') {
            $conds = [];
            foreach (fetchAll(run($conn, 'SELECT column_name, data_type FROM user_tab_columns WHERE table_name = :t ORDER BY column_id', [':t' => $table])) as $c) {
                if (preg_match('/^(VARCHAR2|CHAR|NVARCHAR2|NCHAR|NUMBER|FLOAT|DATE|TIMESTAMP)/', $c[1])) {
                    $conds[] = 'UPPER(TO_CHAR("' . $c[0] . '")) LIKE :s';
                }
            }
            if ($conds) {
                $where = ' WHERE ' . implode(' OR ', $conds);
                $binds[':s'] = '%' . strtoupper(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q)) . '%';
                $where = str_replace(' LIKE :s', " LIKE :s ESCAPE '\\'", $where);
            }
        }
        $total = (int)fetchAll(run($conn, 'SELECT COUNT(*) FROM "' . $table . '"' . $where, $binds))[0][0];
        $off = ($page - 1) * PAGE_SIZE;
        $binds[':off'] = $off;
        $binds[':n'] = PAGE_SIZE;
        $st = run($conn, 'SELECT * FROM "' . $table . '"' . $where . ' ORDER BY ROWID OFFSET :off ROWS FETCH NEXT :n ROWS ONLY', $binds);
        out(['table' => $table, 'columns' => colNames($st), 'rows' => fetchAll($st),
             'total' => $total, 'page' => $page, 'size' => PAGE_SIZE]);
    }

    if ($action === 'query') {
        $sql = rtrim(trim((string)($in['sql'] ?? '')), "; \t\r\n");
        if ($sql === '') fail('SQL을 입력하세요.');
        if (strpos($sql, ';') !== false) fail('한 번에 하나의 SELECT 문만 실행할 수 있습니다.');
        if (!preg_match('/^(select|with)\b/i', $sql)) fail('읽기 전용입니다. SELECT(또는 WITH) 문만 실행할 수 있습니다.');
        if (preg_match('/\bfor\s+update\b/i', $sql)) fail('FOR UPDATE는 사용할 수 없습니다.');

        run($conn, 'SET TRANSACTION READ ONLY');   // DML 이중 차단
        $st = run($conn, "SELECT * FROM (\n" . $sql . "\n) FETCH FIRST " . (QUERY_MAX + 1) . ' ROWS ONLY');
        $rows = fetchAll($st);
        $truncated = count($rows) > QUERY_MAX;
        out(['columns' => colNames($st), 'rows' => array_slice($rows, 0, QUERY_MAX), 'truncated' => $truncated, 'max' => QUERY_MAX]);
    }

    fail('알 수 없는 action 입니다.');
} catch (Exception $e) {
    fail($e->getMessage(), 400);
}
