<?php defined('APP_ROOT') || exit;
$title = $g['group_name'];
ob_start(); ?>
<a class="small text-white-50 text-decoration-none" href="<?= h(url('admin/groups')) ?>">← 과목군 목록</a>
<div class="eyebrow mt-2">ADMIN · COURSE GROUP</div>
<h1 class="hero-title"><strong><?= h($g['group_name']) ?></strong> <span class="fs-5"><?= h($g['group_code']) ?></span></h1>
<p class="hero-sub"><?= $g['rule_type'] === 'ALL' ? "모두 이수해야 하는 필수과목 목록입니다. 빠진 과목은 '미이수 필수과목'으로 표시됩니다." : '이 과목들 중에서 요건별 기준 학점 이상을 들어야 합니다.' ?>
  <?php if ($used_by): ?>연결된 요건: <?= h(implode(', ', array_map(function ($u) { return $u['dept_name'] . ' ' . years_label($u); }, $used_by))) ?><?php else: ?>아직 연결된 졸업요건이 없습니다.<?php endif; ?></p>
<?php $hero_html = ob_get_clean();
$by_area = [];
foreach ($courses as $c) {
    $by_area[$c['sort_order']][] = $c;
}
ksort($by_area);
$dis = $editable ? '' : 'disabled';
?>
<form method="post" action="<?= h(url('admin/group', ['code' => $g['group_code']])) ?>">
<?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card p-4 mb-3">
      <div class="card-title-sm">기본 정보</div>
      <div class="mb-2"><label class="form-label small" for="group_name">이름</label>
        <input class="form-control" id="group_name" name="group_name" value="<?= h($g['group_name']) ?>" maxlength="50" <?= $dis ?>></div>
      <div class="mb-2"><label class="form-label small" for="description">설명</label>
        <textarea class="form-control" id="description" name="description" rows="3" maxlength="300" <?= $dis ?>><?= h($g['description']) ?></textarea></div>
      <div class="small text-muted">규칙: <?= $g['rule_type'] === 'ALL' ? '모두 이수 (ALL)' : 'n학점 이상 (CREDITS)' ?> · 소유: <?= $g['dept_id'] ? h(admin_dept_name()) : '공통' ?></div>
    </div>
    <div class="card p-4 plan-side">
      <div class="stat-label">선택한 과목</div>
      <div class="stat-value"><span id="pick-count">0</span><span class="fs-6 text-muted fw-normal">과목 · </span><span id="pick-credits">0</span><span class="fs-6 text-muted fw-normal">학점</span></div>
      <?php if ($editable): ?>
      <button class="btn btn-primary w-100 mt-3">저장</button>
      <button class="btn btn-outline-danger w-100 mt-2" name="action" value="delete"
              onclick="return confirm('이 과목군과 졸업요건 연결을 모두 삭제할까요?')">과목군 삭제</button>
      <?php else: ?>
      <p class="small text-muted mt-3 mb-0">공통 과목군은 볼 수만 있습니다.</p>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card p-4" id="plan-form">
      <div class="card-title-sm">구성 과목 <span class="sub">우리 학과 과목 + 교양·MSC 과목</span>
        <input class="form-control form-control-sm ms-auto" style="max-width: 200px" type="search" placeholder="과목명 검색" data-filter="#course-pick .plan-row"></div>
      <div id="course-pick">
      <?php $first = true; foreach ($by_area as $items):
        $sel = count(array_filter($items, function ($c) { return $c['selected']; })); ?>
        <details class="plan-group" <?= ($sel || $first) ? 'open' : '' ?>>
          <summary><?= h($items[0]['area_name']) ?> <span class="text-muted small"><?= $sel ?>/<?= count($items) ?></span></summary>
          <div class="plan-list">
          <?php foreach ($items as $c): ?>
            <label class="plan-row <?= $c['selected'] ? 'on' : '' ?>" data-name="<?= h($c['course_name']) ?>">
              <input class="form-check-input" type="checkbox" name="course[]" value="<?= h($c['course_id']) ?>" data-credits="<?= h($c['credits']) ?>" <?= $c['selected'] ? 'checked' : '' ?> <?= $dis ?>>
              <span class="flex-grow-1"><span class="fw-semibold"><?= h($c['course_name']) ?></span>
                <span class="small text-muted"><?= h($c['course_id']) ?> · <?= h($c['credits']) ?>학점<?php if ($c['design_credits']): ?> · 설계 <?= h($c['design_credits']) ?><?php endif; ?></span></span>
            </label>
          <?php endforeach; ?>
          </div>
        </details>
      <?php $first = false; endforeach; ?>
      </div>
    </div>
  </div>
</div>
</form>
