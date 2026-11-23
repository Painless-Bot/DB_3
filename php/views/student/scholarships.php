<?php defined('APP_ROOT') || exit;
$title = '장학금 진단';
ob_start();
$page_label = 'STUDENT · SCHOLARSHIP';
$page_title = '장학금 진단';
$page_desc = '교내장학금 7종의 최소 기준과 직전 정규학기 성적을 비교합니다.';
include APP_ROOT . '/views/partials/student_hero.php';
$hero_html = ob_get_clean();
include APP_ROOT . '/views/partials/scholarships.php';
