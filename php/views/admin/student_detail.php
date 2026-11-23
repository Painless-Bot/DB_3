<?php defined('APP_ROOT') || exit;
$title = $r['info']['name'] . ' 상세';
ob_start(); ?>
<a class="small text-white-50 text-decoration-none" href="<?= h(url('admin/students')) ?>">← 학생 목록</a>
<?php
$page_label = 'ADMIN · STUDENT DETAIL';
$page_title = '진단 상세 (' . $r['info']['student_id'] . ')';
$page_desc = '성적 · 수정 탭에서 성적을 바꾸면 트리거가 학기 평점을 다시 계산하고 판정이 바로 바뀝니다.';
include APP_ROOT . '/views/partials/student_hero.php';
$hero_html = ob_get_clean();
include APP_ROOT . '/views/partials/summary_cards.php'; ?>
<ul class="nav nav-pills mb-3 gap-1" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-grad" type="button">졸업요건</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-sch" type="button">장학금</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-grades" type="button">성적 · 수정</button></li>
</ul>
<div class="tab-content">
  <div class="tab-pane fade show active" id="t-grad"><?php include APP_ROOT . '/views/partials/graduation.php'; ?></div>
  <div class="tab-pane fade" id="t-sch"><?php include APP_ROOT . '/views/partials/scholarships.php'; ?></div>
  <div class="tab-pane fade" id="t-grades"><?php $editable = true; include APP_ROOT . '/views/partials/grades.php'; ?></div>
</div>
