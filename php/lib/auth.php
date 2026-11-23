<?php
/** 로그인 확인. 역할이 맞지 않으면 403, 로그인하지 않았으면 로그인 화면으로 보낸다. */
defined('APP_ROOT') || exit;

function require_role($role)
{
    if (empty($_SESSION['login_id'])) {
        redirect(url('login', ['next' => current_url()]));
    }
    if (($_SESSION['role'] ?? '') !== $role) {
        abort_page(403);
    }
}

function my_dept()
{
    return $_SESSION['dept_id'];
}

function my_student()
{
    return $_SESSION['student_id'];
}
