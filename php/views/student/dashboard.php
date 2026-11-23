<?php defined('APP_ROOT') || exit;
$title = '대시보드';
ob_start();
$page_label = 'STUDENT · DASHBOARD';
$page_desc = '졸업요건과 교내장학금 지원 자격을 한눈에 봅니다. 성적이 바뀌면 판정도 즉시 바뀝니다.';
include APP_ROOT . '/views/partials/student_hero.php';
$hero_html = ob_get_clean();
include APP_ROOT . '/views/partials/summary_cards.php'; ?>
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card p-4 h-100">
      <div class="card-title-sm">학기별 평점 추이 <span class="sub">막대는 취득학점</span>
        <a class="ms-auto small" href="<?= h(url('me/grades')) ?>">성적 자세히 →</a></div>
      <?= chart_gpa_trend($r['semesters']) ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card p-4 h-100">
      <div class="card-title-sm">다음에 들으면 좋은 과목 <span class="sub">부족한 요건 기준</span></div>
      <?php include APP_ROOT . '/views/partials/suggestions_short.php'; ?>
    </div>
  </div>
</div>
<div class="section-tabs"><span class="on">QUICK MENU</span></div>
<div class="row g-3">
  <div class="col-6 col-lg-3"><a class="service-card" href="<?= h(url('me/graduation')) ?>"><div class="art art-1"><?= icon('grad') ?></div>
    <div class="body"><strong>졸업요건 진단</strong><span>영역·과목군·필수과목별 남은 학점</span></div></a></div>
  <div class="col-6 col-lg-3"><a class="service-card" href="<?= h(url('me/scholarships')) ?>"><div class="art art-3"><?= icon('award') ?></div>
    <div class="body"><strong>장학금 진단</strong><span>장학금별 충족 조건과 부족한 점</span></div></a></div>
  <div class="col-6 col-lg-3"><a class="service-card" href="<?= h(url('me/plan')) ?>"><div class="art art-2"><?= icon('layers') ?></div>
    <div class="body"><strong>수강 계획 시뮬레이터</strong><span>다음 학기 과목·예상 성적으로 미리 판정</span></div></a></div>
  <div class="col-6 col-lg-3"><a class="service-card" href="<?= h(url('me/import')) ?>"><div class="art art-4"><?= icon('upload') ?></div>
    <div class="body"><strong>성적 가져오기</strong><span>클래스룸 성적표 붙여넣기 · CSV</span></div></a></div>
</div>
