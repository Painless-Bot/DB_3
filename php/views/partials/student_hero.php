<?php defined('APP_ROOT') || exit;
/* 학생 화면 공통 히어로. $r, $page_label, $page_title, $page_desc */ ?>
<div class="eyebrow"><?= h($page_label) ?></div>
<h1 class="hero-title"><strong><?= h($r['info']['name']) ?></strong>님의 <?= h($page_title ?? '진단 결과') ?></h1>
<p class="hero-sub mb-3"><?= h($page_desc) ?></p>
<div class="hero-badges">
  <span class="badge-soft"><?= h($r['info']['dept_name']) ?> (<?= h($r['info']['campus']) ?>)</span>
  <span class="badge-soft"><?= h($r['info']['admission_year']) ?>학번 · <?= h($r['info']['grade_level']) ?>학년</span>
  <span class="badge-soft">적용 요건: <?= h($r['req'] ? years_label($r['req']) : '미등록') ?></span>
  <span class="badge-soft">소득분위 <?= h($r['info']['income_bracket']) ?></span>
  <span class="badge-soft">봉사 <?= h($r['info']['volunteer_hours']) ?>시간</span>
</div>
<div class="mt-3">
  <a class="btn-ghost" href="<?= h(($_SESSION['role'] ?? '') === 'admin' ? url('admin/student/report', ['id' => $r['info']['student_id']]) : url('me/report')) ?>">🖨 진단서 인쇄 / PDF</a>
</div>
