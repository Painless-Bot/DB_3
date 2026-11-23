<?php defined('APP_ROOT') || exit;
/* 부족한 요건별 추천 과목 전체 (V_COURSE_SUGGESTION) */ ?>
<div class="card p-4 mt-3">
  <div class="card-title-sm">부족한 요건을 채우는 과목 <span class="sub">아직 학점을 받지 않은 과목 · 한 과목이 여러 요건을 채우면 함께 표시</span>
    <a class="ms-auto btn btn-sm btn-primary" href="<?= h(url('me/plan')) ?>">수강 계획 시뮬레이터 →</a></div>
  <?php if ($r['suggestions']): ?>
  <div class="table-responsive">
  <table class="table table-sm mb-0 align-middle">
    <thead><tr><th>과목</th><th>이수구분</th><th class="text-end">학점</th><th>채우는 요건</th></tr></thead>
    <tbody>
    <?php foreach ($r['suggestions'] as $s): ?>
      <tr>
        <td><?= h($s['course_name']) ?> <span class="small text-muted"><?= h($s['course_id']) ?></span></td>
        <td class="small"><?= h($s['area_name']) ?><?php if ($s['design_credits']): ?> <span class="pill pill-info">설계 <?= h($s['design_credits']) ?></span><?php endif; ?></td>
        <td class="text-end"><?= h($s['credits']) ?></td>
        <td><?php foreach ($s['reasons'] as $i => $reason): ?><span class="pill <?= $s['priority'] == 1 && $i === 0 ? 'pill-bad' : 'pill-no' ?> me-1"><?= h($reason) ?></span><?php endforeach; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="small text-muted mb-0"><?= $r['status']['remaining_total'] ? '영역·과목군·필수과목 요건은 모두 채웠습니다. 남은 ' . h($r['status']['remaining_total']) . '학점은 자유롭게 채우면 됩니다.' : '채워야 할 요건이 없습니다.' ?></p>
  <?php endif; ?>
</div>
