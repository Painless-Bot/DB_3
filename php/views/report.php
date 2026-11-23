<?php defined('APP_ROOT') || exit;
/* 인쇄용 진단서 (레이아웃 없이 한 장). $r = load_report(), $back = 돌아갈 주소 */
$st = $r['status'];
$short = $st['short_area_count'] + $st['short_group_count'] + $st['missing_required_count'];
?><!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($r['info']['name']) ?> 졸업·장학 진단서</title>
  <link href="<?= h(asset('vendor/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= h(asset('style.css')) ?>" rel="stylesheet">
</head>
<body class="report-page">
<div class="report-toolbar no-print">
  <a href="<?= h($back) ?>">← 돌아가기</a>
  <button class="btn btn-primary btn-sm" onclick="window.print()">인쇄 / PDF 저장</button>
</div>
<main class="report-sheet">
  <header class="report-head">
    <div>
      <div class="report-brand"><span class="mark">H</span> HONGIK 진단센터</div>
      <h1>졸업요건 · 교내장학금 진단서</h1>
    </div>
    <div class="text-end small text-muted">발급 <?= date('Y-m-d H:i') ?><br>참고용 · 공식 증명서 아님</div>
  </header>

  <table class="report-info">
    <tr><th>성명</th><td><?= h($r['info']['name']) ?></td><th>학번</th><td><?= h($r['info']['student_id']) ?></td><th>학과</th><td><?= h($r['info']['dept_name']) ?> (<?= h($r['info']['campus']) ?>)</td></tr>
    <tr><th>입학</th><td><?= h($r['info']['admission_year']) ?>학번 · <?= h($r['info']['grade_level']) ?>학년</td><th>적용 요건</th><td><?= h($r['req'] ? years_label($r['req']) : '미등록') ?></td>
        <th>판정</th><td><strong class="<?= $st['graduatable'] ? 'text-success' : 'text-danger' ?>"><?= $st['graduatable'] ? '졸업 가능' : (!$st['req_id'] ? '요건 미등록' : '미충족') ?></strong></td></tr>
  </table>

  <section>
    <h2>1. 요약</h2>
    <div class="report-kpis">
      <div><span>이수 / 졸업학점</span><b><?= h($st['earned_total']) ?> / <?= h($st['required_total'] ?: '-') ?></b></div>
      <div><span>누적 평점</span><b><?= gpa($st['cum_gpa']) ?></b></div>
      <div><span>설계학점</span><b><?= h($st['earned_design']) ?> / <?= h($st['min_design_credits'] ?: 0) ?></b></div>
      <div><span>미충족 항목</span><b><?= $short ?></b></div>
      <div><span>지원 가능 장학금</span><b><?= h($r['eligible_count']) ?> / <?= count($r['scholarships']) ?></b></div>
    </div>
  </section>

  <?php if ($st['req_id']): ?>
  <section>
    <h2>2. 영역별 이수 학점</h2>
    <table class="table table-sm report-table">
      <thead><tr><th>영역</th><th class="text-end">기준</th><th class="text-end">이수</th><th class="text-end">남음</th></tr></thead>
      <tbody>
      <?php foreach ($r['areas'] as $a): ?>
        <tr><td><?= $a['parent_code'] ? '└ ' : '' ?><?= h($a['area_name']) ?></td><td class="text-end"><?= h($a['min_credits']) ?></td><td class="text-end"><?= h($a['earned_credits']) ?></td>
          <td class="text-end <?= $a['remaining_credits'] ? 'text-danger' : '' ?>"><?= $a['remaining_credits'] ?: '✓' ?></td></tr>
      <?php endforeach; ?>
        <tr class="fw-bold"><td>총 이수학점</td><td class="text-end"><?= h($st['required_total']) ?></td><td class="text-end"><?= h($st['earned_total']) ?></td><td class="text-end"><?= $st['remaining_total'] ?: '✓' ?></td></tr>
      </tbody>
    </table>
  </section>

  <section class="report-2col">
    <div>
      <h2>3. 과목군 요건</h2>
      <table class="table table-sm report-table">
        <tbody>
        <?php foreach ($r['groups'] as $g): ?>
          <tr><td><?= h($g['group_name']) ?></td><td class="text-end <?= $g['remaining_credits'] ? 'text-danger' : '' ?>"><?= $g['min_credits'] == 1 ? (!$g['remaining_credits'] ? '이수' : '미이수') : h($g['earned_credits'] . '/' . $g['min_credits']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$r['groups']): ?><tr><td class="text-muted">없음</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div>
      <h2>4. 미이수 필수과목</h2>
      <table class="table table-sm report-table">
        <tbody>
        <?php foreach ($r['missing'] as $m): ?><tr><td><?= h($m['course_name']) ?> <span class="text-muted"><?= h($m['course_id']) ?></span></td><td class="text-end"><?= h($m['credits']) ?>학점</td></tr><?php endforeach; ?>
        <?php if (!$r['missing']): ?><tr><td class="text-success">모두 이수</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <section>
    <h2>5. 교내장학금 최소 자격 <?php if ($r['last_sem']): ?><small>직전 정규학기 <?= h($r['last_sem']['year']) ?>년 <?= term_label($r['last_sem']['term']) ?> · 평점 <?= gpa($r['last_sem']['gpa']) ?> · <?= h($r['last_sem']['earned_credits']) ?>학점</small><?php endif; ?></h2>
    <table class="table table-sm report-table">
      <thead><tr><th>장학금</th><th>평점</th><th>학점</th><th>소득</th><th>봉사</th><th>결과</th></tr></thead>
      <tbody>
      <?php foreach ($r['scholarships'] as $s): ?>
        <tr><td><?= h($s['sch_name']) ?></td><td><?= $s['gpa_ok'] ? '✓' : '✕' ?> <?= gpa($s['min_gpa']) ?></td><td><?= $s['credits_ok'] ? '✓' : '✕' ?> <?= h($s['min_credits']) ?></td>
          <td><?= $s['income_ok'] ? '✓' : '✕' ?> <?= $s['max_income'] !== null ? h($s['max_income']) . '분위' : '무관' ?></td>
          <td><?= $s['volunteer_ok'] ? '✓' : '✕' ?> <?= $s['min_volunteer_hours'] !== null ? h($s['min_volunteer_hours']) . 'h' : '-' ?></td>
          <td class="fw-bold <?= $s['eligible'] ? 'text-success' : '' ?>"><?= $s['eligible'] ? '지원 가능' : '미달' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section>
    <h2>6. 학기별 성적</h2>
    <table class="table table-sm report-table">
      <thead><tr><th>학기</th><th class="text-end">신청</th><th class="text-end">취득</th><th class="text-end">평점</th></tr></thead>
      <tbody>
      <?php foreach ($r['semesters'] as $s): ?><tr><td><?= h($s['year']) ?>년 <?= term_label($s['term']) ?></td><td class="text-end"><?= h($s['attempted_credits']) ?></td><td class="text-end"><?= h($s['earned_credits']) ?></td><td class="text-end"><?= gpa($s['gpa']) ?></td></tr><?php endforeach; ?>
      <?php if (!$r['semesters']): ?><tr><td colspan="4" class="text-muted">수강 이력 없음</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>

  <?php if ($r['suggestions']): ?>
  <section>
    <h2>7. 남은 요건을 채우는 과목</h2>
    <p class="small"><?= h(implode(', ', array_map(function ($s) { return $s['course_name'] . '(' . $s['credits'] . ')'; }, array_slice($r['suggestions'], 0, 12)))) ?><?= count($r['suggestions']) > 12 ? ' 외 ' . (count($r['suggestions']) - 12) . '과목' : '' ?></p>
  </section>
  <?php endif; ?>

  <footer class="report-foot">재수강은 최근 성적만, F·NP 는 학점 미인정, P·NP 는 평점 제외. 장학금은 최소 지원 자격만 판정하며 실제 선발은 학교 공식 절차를 따릅니다.
    <?php if (!empty($r['req']['note'])): ?><br><?= h($r['req']['note']) ?><?php endif; ?></footer>
</main>
</body>
</html>
