<?php defined('APP_ROOT') || exit;
/* 추천 과목 상위 몇 개. $r['suggestions'] = group_suggestions() 결과 */
$kinds = [1 => ['필수', 'pill-bad'], 2 => ['과목군', 'pill-info'], 3 => ['영역', 'pill-no'], 4 => ['설계', 'pill-no']];
$st = $r['status'];
if ($r['suggestions']):
  $sug_cr = array_sum(array_column($r['suggestions'], 'credits')); ?>
  <ul class="list-unstyled mb-3 suggest-list">
  <?php foreach (array_slice($r['suggestions'], 0, 6) as $s): ?>
    <li>
      <span class="pill <?= $kinds[$s['priority']][1] ?>"><?= $kinds[$s['priority']][0] ?></span>
      <div class="flex-grow-1">
        <div class="fw-semibold small"><?= h($s['course_name']) ?> <span class="text-muted fw-normal"><?= h($s['credits']) ?>학점<?php if ($s['design_credits']): ?> · 설계 <?= h($s['design_credits']) ?><?php endif; ?></span></div>
        <div class="small text-muted"><?= h(implode(' · ', $s['reasons'])) ?></div>
      </div>
    </li>
  <?php endforeach; ?>
  </ul>
  <?php if ($st['remaining_total'] > $sug_cr): ?><p class="small text-muted">위 과목을 모두 들어도 총학점이 <?= $st['remaining_total'] - $sug_cr ?>학점 더 필요합니다. 나머지는 원하는 과목으로 채우면 됩니다.</p><?php endif; ?>
  <a class="btn btn-sm btn-primary" href="<?= h(url('me/plan')) ?>">이 과목들로 수강 계획 세워 보기 →</a>
<?php elseif ($st['req_id'] && $st['remaining_total']): ?>
  <p class="small text-muted mb-3">영역·과목군·필수과목은 모두 채웠습니다. 남은 <?= h($st['remaining_total']) ?>학점은 어떤 과목으로 채워도 됩니다.</p>
  <a class="btn btn-sm btn-primary" href="<?= h(url('me/plan')) ?>">수강 계획 세워 보기 →</a>
<?php elseif ($st['req_id']): ?>
  <p class="small text-success mb-0">채워야 할 요건이 없습니다. 졸업 요건을 모두 충족했습니다.</p>
<?php else: ?>
  <p class="small text-muted mb-0">적용할 졸업요건이 없어 추천할 수 없습니다.</p>
<?php endif; ?>
