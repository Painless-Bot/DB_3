<?php
/** 첫 화면(로그인), 로그아웃, 비밀번호 변경 */
defined('APP_ROOT') || exit;

function page_index()
{
    $role = $_SESSION['role'] ?? '';
    if ($role === 'student') {
        redirect(url('me'));
    }
    if ($role === 'admin') {
        redirect(url('admin/students'));
    }
    redirect(url('login'));
}

function page_login()
{
    if (is_post()) {
        $login_id = trim(post('login_id'));
        $user = $login_id === '' ? null : q_one(Q_LOGIN_USER, ['login_id' => $login_id]);
        if ($user && password_verify(post('password'), $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION = ['_csrf' => $_SESSION['_csrf'] ?? null];
            $_SESSION['login_id'] = $user['login_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['student_id'] = $user['student_id'] !== null ? trim($user['student_id']) : null;
            $_SESSION['dept_id'] = $user['dept_id'];
            q_exec(Q_LOGIN_TOUCH, ['login_id' => $login_id]);
            db_commit();
            $next = get_param('next');
            // 같은 사이트 안의 주소로만 돌아간다 (index.php?... 형식)
            if ($next !== '' && strpos($next, 'index.php') === 0) {
                redirect($next);
            }
            redirect(url());
        }
        flash('아이디 또는 비밀번호가 올바르지 않습니다.', 'danger');
    }
    render('login', [
        'reqs' => q_all(Q_LANDING_REQS),
        'scholarships' => q_all(Q_LANDING_SCH),
    ]);
}

function page_logout()
{
    $_SESSION = [];
    session_regenerate_id(true);
    redirect(url('login'));
}

function page_password()
{
    if (empty($_SESSION['login_id'])) {
        redirect(url('login', ['next' => current_url()]));
    }
    $login_id = $_SESSION['login_id'];
    if (is_post()) {
        $user = q_one(Q_PASSWORD_HASH, ['login_id' => $login_id]);
        $new = post('new');
        if (!$user || !password_verify(post('current'), $user['password_hash'])) {
            flash('현재 비밀번호가 맞지 않습니다.', 'danger');
        } elseif (mb_strlen($new) < 8 || $new !== post('confirm')) {
            flash('새 비밀번호는 8자 이상이고, 확인 칸과 같아야 합니다.', 'danger');
        } else {
            q_exec(Q_PASSWORD_SET, ['hash' => password_hash($new, PASSWORD_DEFAULT), 'login_id' => $login_id]);
            db_commit();
            flash('비밀번호를 바꿨습니다.', 'success');
            redirect(url());
        }
    }
    render('password', ['last' => q_one(Q_LAST_LOGIN, ['login_id' => $login_id])]);
}
