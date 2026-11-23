<?php
defined('APP_ROOT') || exit;
$role = $_SESSION['role'] ?? '';
if ($role === 'student') {
    $items = [['me', '대시보드'], ['me/grades', '성적'], ['me/graduation', '졸업요건'], ['me/scholarships', '장학금'],
              ['me/plan', '수강 계획'], ['me/import', '성적 가져오기']];
} elseif ($role === 'admin') {
    $items = [['admin/students', '학생 목록'], ['admin/stats', '학과 통계'], ['admin/grad-reqs', '졸업요건'],
              ['admin/groups', '과목군'], ['admin/scholarships', '장학금']];
} else {
    $items = [];
}
$route_now = current_route();
?><!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · HONGIK 진단센터</title>
  <link href="<?= h(asset('vendor/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= h(asset('style.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="quick-rail" aria-label="학교 바로가기">
  <a href="https://www.hongik.ac.kr" target="_blank" rel="noopener"><span class="ico" style="background:#0a2a6b">H</span>홍익대</a>
  <a href="https://software.hongik.ac.kr/home" target="_blank" rel="noopener"><span class="ico" style="background:#1f4fd6">SW</span>소융과</a>
  <a href="https://www.hongik.ac.kr/kr/education/school-scholarship.do" target="_blank" rel="noopener"><span class="ico" style="background:#12a150">₩</span>장학안내</a>
</nav>

<header class="site-hero <?= h($hero_class) ?>">
  <div class="container">
    <div class="topbar">
      <a class="brand-tab" href="<?= h(url()) ?>">
        <span class="mark">H</span>
        <span>HONGIK 진단센터<small>장학 · 졸업요건 자동 진단</small></span>
      </a>
      <?php if ($items): ?>
      <button class="nav-toggle" type="button" onclick="document.getElementById('topnav').classList.toggle('open')">메뉴</button>
      <div class="topnav" id="topnav">
        <?php foreach ($items as [$r, $label]): ?>
          <a class="<?= $route_now === $r ? 'active' : '' ?>" href="<?= h(url($r)) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
        <a class="user-chip" href="<?= h(url('account/password')) ?>" title="비밀번호 변경">
          <?= $role === 'admin' ? h(admin_dept_name()) . ' 담당자' : h($_SESSION['student_id']) ?>
        </a>
        <a class="btn-ghost ms-2" href="<?= h(url('logout')) ?>">로그아웃</a>
      </div>
      <?php else: ?>
      <div class="topnav d-flex" id="topnav">
        <a href="#service">서비스 소개</a>
        <a href="#criteria">판정 기준</a>
        <a href="#sources">데이터 출처</a>
      </div>
      <?php endif; ?>
    </div>
    <div class="hero-body">
      <?= $hero_html ?>
    </div>
  </div>
</header>

<main class="container content-shell">
  <div class="px-2 px-md-4">
  <?php foreach (take_flashes() as [$cat, $msg]): ?>
    <div class="alert alert-<?= h($cat) ?> alert-dismissible fade show rounded-4" role="alert">
      <?= h($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>
  <?= $content_html ?>
  </div>
</main>

<footer class="site-footer mt-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="f-brand mb-2">HONGIK 진단센터</div>
        <p class="mb-0">홍익대학교 클래스룸의 이수 과목·성적을 바탕으로 졸업요건과 교내장학금 지원 자격을 진단하는 DB 팀프로젝트(3팀) 시연용 사이트입니다.
          판정은 참고용이며, 실제 졸업사정과 장학생 선발은 학교 공식 절차를 따릅니다.</p>
      </div>
      <div class="col-6 col-lg-4">
        <div class="text-white fw-bold mb-2">기준 자료</div>
        <ul>
          <li><a href="https://www.hongik.ac.kr/kr/education/school-scholarship.do" target="_blank" rel="noopener">교내장학금 안내 (hongik.ac.kr)</a></li>
          <li><a href="https://software.hongik.ac.kr/home/templates/curriculum/curriculum" target="_blank" rel="noopener">소프트웨어융합학과 교과과정</a></li>
          <li><a href="https://software.hongik.ac.kr/home/templates/curriculum/courses" target="_blank" rel="noopener">설계 / 대체 교과목</a></li>
          <li>2026 교과과정 책자 · 졸업학점 현황</li>
        </ul>
      </div>
      <div class="col-6 col-lg-4">
        <div class="text-white fw-bold mb-2">시스템</div>
        <ul>
          <li>PHP + Oracle (테이블 14 · 뷰 10 · 트리거 4 · 프로시저 3)</li>
          <li>학생·성적은 가상의 데이터</li>
          <li>3팀 정찬영 · 유승환 · 최유림 · 강민우</li>
        </ul>
      </div>
    </div>
    <div class="f-bottom d-flex justify-content-between flex-wrap gap-2">
      <span>© 2026 HONGIK 진단센터 · DB 팀프로젝트</span>
      <span>홍익대학교 공식 서비스가 아닙니다.</span>
    </div>
  </div>
</footer>
<script src="<?= h(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= h(asset('app.js')) ?>"></script>
</body>
</html>
