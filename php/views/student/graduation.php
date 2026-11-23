<?php defined('APP_ROOT') || exit;
$title = '졸업요건 진단';
ob_start();
$page_label = 'STUDENT · GRADUATION';
$page_title = '졸업요건 진단';
$page_desc = '입학연도 구간에 맞는 졸업요건으로 영역·과목군·필수과목을 판정합니다.';
include APP_ROOT . '/views/partials/student_hero.php';
$hero_html = ob_get_clean();
include APP_ROOT . '/views/partials/graduation.php';
if ($r['status']['req_id']) {
    include APP_ROOT . '/views/partials/suggestions_full.php';
}
