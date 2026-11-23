<?php
/** 화면 공통 도우미: 출력 이스케이프, 숫자 표기, 주소, 세션 메시지(flash), CSRF, 화면 그리기 */
defined('APP_ROOT') || exit;

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 평점 표기: 3.4 → "3.40", 없으면 "-" */
function gpa($v)
{
    return $v === null || $v === '' ? '-' : number_format((float)$v, 2, '.', '');
}

function term_label($t)
{
    $m = [1 => '1학기', 2 => '2학기', 3 => '여름학기', 4 => '겨울학기'];
    return $m[(int)$t] ?? ($t . '학기');
}

function pct($earned, $required)
{
    if (!$required) {
        return 100;
    }
    return (int)min(100, round(100 * ($earned ?: 0) / $required));
}

/** 졸업요건의 입학연도 구간: 2019~2021학번 / 2022학번 이후 */
function years_label($req)
{
    if (!$req) {
        return '-';
    }
    $f = $req['from_year'];
    $t = $req['to_year'];
    if ($t === null) {
        return "{$f}학번 이후";
    }
    if ($f == $t) {
        return "{$f}학번";
    }
    return "{$f}~{$t}학번";
}

function truncate_text($s, $n)
{
    $s = (string)$s;
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 3) . '...' : $s;
}

// ---------------------------------------------------------------- 주소
// mod_rewrite 없이 동작하도록 index.php?r=경로 형식을 쓴다.
function url($route = '', array $params = [])
{
    $q = $route === '' ? [] : ['r' => $route];
    $q += array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

function asset($path)
{
    $file = APP_ROOT . '/static/' . $path;
    return 'static/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function redirect($to)
{
    header('Location: ' . $to);
    exit;
}

function current_route()
{
    return trim($_GET['r'] ?? '', '/');
}

function current_url()
{
    return url(current_route(), array_diff_key($_GET, ['r' => 1]));
}

// ---------------------------------------------------------------- 요청 값
function post($key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

function get_param($key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

/** 정수로 읽을 수 있으면 int, 아니면 null */
function to_int($v)
{
    $v = trim((string)$v);
    return preg_match('/^-?\d+$/', $v) ? (int)$v : null;
}

function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------------------------------------------------------------- 알림 메시지 (다음 화면에 한 번 표시)
function flash($msg, $cat = 'info')
{
    $_SESSION['_flash'][] = [$cat, $msg];
}

function take_flashes()
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

// ---------------------------------------------------------------- CSRF (다른 사이트에서 몰래 보내는 POST 차단)
function csrf_token()
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check()
{
    if (!is_post()) {
        return;
    }
    if (!hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
        flash('세션이 만료되었습니다. 다시 시도해 주세요.', 'warning');
        redirect(current_url());
    }
}

// ---------------------------------------------------------------- 화면
/**
 * views/$view.php 를 그리고 views/layout.php 로 감싼다.
 * 뷰 파일 안에서 $title, $hero_class, $shell_class 를 정하고 hero()/endhero() 사이에 히어로 영역을 쓴다.
 */
function render($view, array $vars = [])
{
    extract($vars, EXTR_SKIP);
    $title = '장학·졸업 요건 진단';
    $hero_class = '';
    $hero_html = '';
    ob_start();
    include APP_ROOT . '/views/' . $view . '.php';
    $content_html = ob_get_clean();
    include APP_ROOT . '/views/layout.php';
    exit;
}

/** 뷰 조각 포함 (변수는 배열로 넘긴다) */
function partial($name, array $vars = [])
{
    extract($vars, EXTR_SKIP);
    include APP_ROOT . '/views/partials/' . $name . '.php';
}

function icon($name)
{
    $paths = [
        'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14l-1.5 7 5-3 5 3-1.5-7"/>',
        'grad' => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/><path d="M22 9v6"/>',
        'layers' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'table' => '<path d="M4 4h16v16H4z"/><path d="M4 9h16M9 9v11"/>',
        'upload' => '<path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

function abort_page($code)
{
    http_response_code($code);
    $msg = [403 => '이 화면을 볼 권한이 없습니다.', 404 => '찾는 화면이나 자료가 없습니다.'][$code] ?? '오류가 발생했습니다.';
    render('error', ['code' => $code, 'msg' => $msg]);
}

/** DB 연결 전에도 쓸 수 있는 단순 오류 화면 */
function fatal_page($title, $msg)
{
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>' . h($title) . '</title>'
        . '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f1c0c0;border-radius:12px;background:#fff6f6">'
        . '<h2 style="margin-top:0">' . h($title) . '</h2><p>' . h($msg) . '</p></div>';
    exit;
}
