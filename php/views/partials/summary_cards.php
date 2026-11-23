<?php defined('APP_ROOT') || exit;
/* 요약 카드 4개 + 졸업 판정. $r = load_report() 결과 */
$st = $r['status'];
$short = $st['short_area_count'] + $st['short_group_count'] + $st['missing_required_count'];
$ok_names = array_column(array_filter($r['scholarships'], function ($s) { return $s['eligible']; }), 'sch_name');
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3">
    <div class="card stat-card">
      <span class="stat-ico"><?= icon('grad') ?></span>
      <div class="stat-label">졸업 이수학점</div>
      <?php if ($st['req_id']): ?>
        <div class="stat-value"><?= h($st['earned_total']) ?><span class="fs-6 text-muted fw-normal"> / <?= h($st['required_total']) ?></span></div>
        <div class="progress my-2"><div class="progress-bar <?= !$st['remaining_total'] ? 'bg-success' : '' ?>" style="width: <?= pct($st['earned_total'], $st['required_total']) ?>%"></div></div>
        <div class="stat-sub"><?= $st['remaining_total'] ? h($st['remaining_total']) . '학점 남음' : '총학점 충족' ?> · 설계 <?= h($st['earned_design']) ?>/<?= h($st['min_design_credits']) ?></div>
      <?php else: ?>
        <div class="stat-value"><?= h($st['earned_total']) ?></div>
        <div class="stat-sub text-danger">이 학번의 졸업요건이 등록되지 않음</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card">
      <span class="stat-ico"><?= icon('table') ?></span>
      <div class="stat-label">평점 (누적 / 직전 학기)</div>
      <div class="stat-value"><?= gpa($st['cum_gpa']) ?><span class="fs-6 text-muted fw-normal"> / <?= $r['last_sem'] ? gpa($r['last_sem']['gpa']) : '-' ?></span></div>
      <div class="stat-sub mt-2">
        <?php if ($r['last_sem']): ?>직전 정규학기 <?= h($r['last_sem']['year']) ?>년 <?= term_label($r['last_sem']['term']) ?> · <?= h($r['last_sem']['earned_credits']) ?>학점 취득<?php else: ?>수강 이력 없음<?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card">
      <span class="stat-ico"><?= icon('layers') ?></span>
      <div class="stat-label">미충족 항목</div>
      <div class="stat-value <?= $short ? 'text-danger' : '' ?>"><?= $short ?><span class="fs-6 text-muted fw-normal">개</span></div>
      <div class="stat-sub mt-2">영역 <?= h($st['short_area_count']) ?> · 과목군 <?= h($st['short_group_count']) ?> · 필수과목 <?= h($st['missing_required_count']) ?></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card">
      <span class="stat-ico"><?= icon('award') ?></span>
      <div class="stat-label">지원 가능 장학금</div>
      <div class="stat-value <?= $r['eligible_count'] ? 'text-success' : '' ?>"><?= h($r['eligible_count']) ?><span class="fs-6 text-muted fw-normal"> / <?= count($r['scholarships']) ?></span></div>
      <div class="stat-sub mt-2"><?= h($ok_names ? implode(', ', $ok_names) : '현재 기준을 충족한 장학금 없음') ?></div>
    </div>
  </div>
</div>
<?php if ($st['req_id']): ?>
<div class="verdict <?= $st['graduatable'] ? 'ok' : 'no' ?> mb-4">
  <span class="fs-4"><?= $st['graduatable'] ? '🎓' : '📋' ?></span>
  <div>
  <?php if ($st['graduatable']): ?>
    <div class="big">졸업 요건을 모두 충족했습니다.</div>
    <div class="small"><?= h(years_label($r['req'])) ?> 기준 · 시스템이 판정하지 않는 요건(영어 성적 등)은 아래 안내를 확인하세요.</div>
  <?php else: ?>
    <div class="big">아직 졸업 요건을 충족하지 못했습니다.</div>
    <div class="small"><?= h(implode(' · ', grad_reasons($r))) ?></div>
  <?php endif; ?>
  </div>
</div>
<?php endif; ?>
