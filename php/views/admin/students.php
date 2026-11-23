<?php defined('APP_ROOT') || exit;
$title = '학생 목록';
ob_start(); ?>
<div class="eyebrow">ADMIN · STUDENTS</div>
<h1 class="hero-title"><strong><?= h(admin_dept_name()) ?></strong> 학생 진단 현황</h1>
<p class="hero-sub">학번·이름으로 찾고, 판정 상태별로 골라 보거나 CSV 로 내려받을 수 있습니다. 열 머리글을 누르면 정렬됩니다.</p>
<?php $hero_html = ob_get_clean(); ?>
<form class="row g-2 align-items-center mb-3" method="get" action="index.php">
  <input type="hidden" name="r" value="admin/students">
  <div class="col-sm-5 col-lg-4"><input class="form-control" name="q" value="<?= h($q) ?>" placeholder="학번 또는 이름"></div>
  <input type="hidden" name="f" value="<?= h($f) ?>">
  <div class="col-auto"><button class="btn btn-primary">검색</button></div>
  <div class="col-auto ms-auto"><a class="btn btn-outline-primary" href="<?= h(url('admin/students.csv', ['q' => $q, 'f' => $f])) ?>">⬇ CSV 내려받기</a></div>
</form>
<div class="filter-chips mb-3">
  <?php foreach ($filters as $key => [$label, $_]): ?>
    <a class="chip <?= $key === $f ? 'on' : '' ?>" href="<?= h(url('admin/students', ['q' => $q, 'f' => $key])) ?>"><?= h($label) ?> <b><?= $counts[$key] ?></b></a>
  <?php endforeach; ?>
</div>
<div class="card p-4">
<div class="table-responsive">
<table class="table table-hover mb-0 sortable">
  <thead><tr><th data-sort>학번</th><th data-sort>이름</th><th data-sort="num">학년</th><th>적용 요건</th>
    <th class="text-end" data-sort="num">이수/졸업학점</th><th class="text-end" data-sort="num">누적평점</th>
    <th class="text-center" data-sort="num">부족 영역·과목군</th><th class="text-center" data-sort="num">미이수 필수</th><th data-sort>판정</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $s): $sg = $s['short_area_count'] + $s['short_group_count']; ?>
    <tr>
      <td><a href="<?= h(url('admin/student', ['id' => $s['student_id']])) ?>"><?= h($s['student_id']) ?></a></td>
      <td><?= h($s['name']) ?></td>
      <td data-v="<?= h($s['grade_level']) ?>"><?= h($s['grade_level']) ?>학년 <span class="small text-muted">(<?= h($s['admission_year']) ?>)</span></td>
      <td class="small"><?php if ($s['req_from_year']): ?><?= h($s['req_from_year']) ?><?= $s['req_to_year'] ? '~' . h($s['req_to_year']) . '학번' : '학번 이후' ?><?php else: ?>-<?php endif; ?></td>
      <td class="text-end" data-v="<?= h($s['earned_total']) ?>"><?= h($s['earned_total']) ?> / <?= $s['required_total'] ? h($s['required_total']) : '-' ?>
        <?php if ($s['required_total']): ?><div class="progress progress-thin mt-1"><div class="progress-bar <?= !$s['remaining_total'] ? 'bg-success' : '' ?>" style="width: <?= pct($s['earned_total'], $s['required_total']) ?>%"></div></div><?php endif; ?></td>
      <td class="text-end" data-v="<?= h($s['cum_gpa'] ?? 0) ?>"><?= gpa($s['cum_gpa']) ?></td>
      <td class="text-center <?= $sg ? 'text-danger' : '' ?>"><?= $sg ?></td>
      <td class="text-center <?= $s['missing_required_count'] ? 'text-danger' : '' ?>"><?= h($s['missing_required_count']) ?></td>
      <td><?php if ($s['required_total'] === null): ?><span class="pill pill-bad">요건 미등록</span>
          <?php elseif ($s['graduatable']): ?><span class="pill pill-ok">졸업 가능</span>
          <?php else: ?><span class="pill pill-no">미충족</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="9" class="text-muted">조건에 맞는 학생이 없습니다.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<p class="small text-muted mt-3 mb-0"><?= count($rows) ?>명 · 학과 전체 판정은 WITH 절 집합 쿼리 한 번으로 계산합니다(학생별 판정 뷰 V_GRAD_STATUS 와 같은 규칙).</p>
</div>
