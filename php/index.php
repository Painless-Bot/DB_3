<?php
/**
 * HONGIK 진단센터 (PHP + Oracle 판) — 모든 화면의 입구.
 * 주소는 index.php?r=경로 형식이다 (예: index.php?r=me/grades). 경로 → 처리 함수는 아래 $routes 에 있다.
 */
define('APP_ROOT', __DIR__);
date_default_timezone_set('Asia/Seoul');

if (!is_file(APP_ROOT . '/config.php')) {
    header('Content-Type: text/html; charset=utf-8');
    exit('<p style="font-family:sans-serif">config.php 가 없습니다. 서버에서 <code>cp config.sample.php config.php</code> 후 '
        . 'Oracle 비밀번호를 넣어 주세요. (README_DEPLOY.md 참고)</p>');
}
require APP_ROOT . '/config.php';
require APP_ROOT . '/lib/helpers.php';
require APP_ROOT . '/lib/db.php';
require APP_ROOT . '/lib/queries.php';
require APP_ROOT . '/lib/auth.php';
require APP_ROOT . '/lib/report.php';
require APP_ROOT . '/lib/charts.php';
require APP_ROOT . '/pages/auth_pages.php';
require APP_ROOT . '/pages/student_pages.php';
require APP_ROOT . '/pages/admin_pages.php';

// 같은 서버의 다른 팀 사이트와 세션이 섞이지 않도록 쿠키 이름과 경로를 이 폴더로 한정한다
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
session_name('HGCSESS');
session_set_cookie_params(['path' => $base, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: text/html; charset=utf-8');

csrf_check();

$routes = [
    ''                    => 'page_index',
    'login'               => 'page_login',
    'logout'              => 'page_logout',
    'account/password'    => 'page_password',
    'me'                  => 'page_dashboard',
    'me/grades'           => 'page_grades',
    'me/graduation'       => 'page_graduation',
    'me/scholarships'     => 'page_scholarships',
    'me/import'           => 'page_import',
    'me/plan'             => 'page_plan',
    'me/report'           => 'page_report',
    'admin/students'      => 'page_admin_students',
    'admin/students.csv'  => 'page_admin_students_csv',
    'admin/student'       => 'page_admin_student',          // &id=학번
    'admin/student/report' => 'page_admin_student_report',  // &id=학번
    'admin/stats'         => 'page_admin_stats',
    'admin/grade-edit'    => 'page_admin_grade_edit',       // &id=enroll_id
    'admin/grad-reqs'     => 'page_admin_grad_reqs',
    'admin/grad-req'      => 'page_admin_grad_req_edit',    // &id=req_id
    'admin/groups'        => 'page_admin_groups',
    'admin/group'         => 'page_admin_group_edit',       // &code=과목군코드
    'admin/scholarships'  => 'page_admin_scholarships',
    'admin/scholarship'   => 'page_admin_scholarship_edit', // &id=sch_id
];

$route = current_route();
if (!isset($routes[$route])) {
    abort_page(404);
}
try {
    call_user_func($routes[$route]);
} catch (DbError $e) {
    db_rollback();
    error_log('[hgc] ' . $e->getMessage());
    http_response_code(500);
    render('error', ['code' => 500, 'msg' => 'DB 처리 중 오류가 발생했습니다.', 'detail' => $e->getMessage()]);
}
