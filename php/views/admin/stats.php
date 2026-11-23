<?php defined('APP_ROOT') || exit;
$title = '학과 통계';
ob_start(); ?>
<div class="eyebrow">ADMIN · STATISTICS</div>
<h1 class="hero-title"><strong><?= h(admin_dept_name()) ?></strong> 진단 통계</h1>
<p class="hero-sub">학과 학생 전체의 졸업요건·장학금 판정을 GROUP BY 로 모아 봅니다. 어떤 영역에서 학생들이 많이 막히는지, 장학금 기준을 몇 명이 넘는지 확인하세요.</p>
<?php $hero_html = ob_get_clean();
$denom = $kpi['students'] - $kpi['noreq'];
$hb = array_map(function ($x) { return [number_format($x['bin'], 1) . '~', $x['n']]; }, $hist);
$ai = array_map(function ($a) { return [$a['area_name'], $a['student_count'], '평균 ' . number_format($a['avg_short'], 1) . '학점 부족']; }, $areas);
$gi = array_map(function ($g) { return [$g['group_name'], $g['student_count'], $g['rule_type'] === 'ALL' ? '필수' : '']; }, $groups);
$si = array_map(function ($s) { return [$s['sch_name'], (int)$s['eligible_count'], $s['category'] . ' · ' . gpa($s['min_gpa']) . ' 이상']; }, $sch);
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3"><div class="card stat-card"><div class="stat-label">학생 수</div>
    <div class="stat-value"><?= $kpi['students'] ?><span class="fs-6 text-muted fw-normal">명</span></div>
    <div class="stat-sub"><?= $kpi['noreq'] ? '요건 미등록 ' . $kpi['noreq'] . '명' : '모든 학생에게 요건 적용' ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="card stat-card"><div class="stat-label">졸업 가능</div>
    <div class="stat-value text-success"><?= $kpi['graduatable'] ?><span class="fs-6 text-muted fw-normal">명</span></div>
    <div class="stat-sub">4학년 이상 <?= $kpi['seniors'] ?>명 중 미충족 <strong class="text-danger"><?= $kpi['senior_short'] ?></strong>명</div></div></div>
  <div class="col-6 col-xl-3"><div class="card stat-card"><div class="stat-label">평균 누적평점</div>
    <div class="stat-value"><?= gpa($kpi['avg_gpa']) ?></div>
    <div class="stat-sub">재수강은 최신 성적 기준</div></div></div>
  <div class="col-6 col-xl-3"><div class="card stat-card"><div class="stat-label">장학금 1종 이상 지원 가능</div>
    <div class="stat-value"><?= h($kpi['any_sch']) ?><span class="fs-6 text-muted fw-normal">명</span></div>
    <div class="stat-sub">직전 정규학기 기준</div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card p-4 h-100 text-center">
      <div class="card-title-sm">졸업 요건 충족률</div>
      <div class="d-flex justify-content-center gap-4 flex-wrap">
        <?= chart_ring($denom ? (int)round(100 * $kpi['graduatable'] / $denom) : 0, '전체', $kpi['graduatable'] . '/' . $denom . '명', true) ?>
        <?= chart_ring($kpi['seniors'] ? (int)round(100 * ($kpi['seniors'] - $kpi['senior_short']) / $kpi['seniors']) : 0, '4학년 이상',
                       ($kpi['seniors'] - $kpi['senior_short']) . '/' . $kpi['seniors'] . '명') ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card p-4 h-100">
      <div class="card-title-sm">남은 졸업학점 분포</div>
      <?= chart_vbars($remaining) ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card p-4 h-100">
      <div class="card-title-sm">직전 학기 평점 분포 <span class="sub">0.5 단위</span></div>
      <?= chart_vbars($hb) ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card p-4 h-100">
      <div class="card-title-sm">영역별 기준 미달 학생 <span class="sub">FR-03 · 평균 부족 학점</span></div>
      <?= chart_hbars($ai, $kpi['students'], '명', 'bad') ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-4 h-100">
      <div class="card-title-sm">과목군·필수과목 미충족 학생</div>
      <?= chart_hbars($gi, $kpi['students'], '명', 'warn') ?>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-4 h-100">
      <div class="card-title-sm">장학금별 지원 가능 학생</div>
      <?= chart_hbars($si, $kpi['students'], '명', 'ok') ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-4 h-100">
      <div class="card-title-sm">입학연도별 현황</div>
      <table class="table table-sm mb-3">
        <thead><tr><th>입학</th><th class="text-end">학생</th><th class="text-end">졸업 가능</th><th class="text-end">평균 누적평점</th></tr></thead>
        <tbody><?php foreach ($cohorts as $c): ?><tr><td><?= h($c['year']) ?>학번</td><td class="text-end"><?= $c['n'] ?></td><td class="text-end"><?= $c['ok'] ?></td><td class="text-end"><?= gpa($c['gpa']) ?></td></tr><?php endforeach; ?></tbody>
      </table>
      <?php if ($trend): ?>
      <div class="small fw-bold mb-1">학기별 학과 평균 평점</div>
      <div class="small text-muted"><?= implode(' · ', array_map(function ($t) {
          return ($t['year'] % 100) . '-' . $t['term'] . ' <strong class="text-body">' . gpa($t['avg_gpa']) . '</strong>';
      }, $trend)) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
