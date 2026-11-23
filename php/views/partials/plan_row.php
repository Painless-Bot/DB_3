<?php defined('APP_ROOT') || exit;
/* 수강 계획 과목 한 줄. $c = 후보 과목, $s = 추천 정보(없으면 null), $picks = 선택한 과목 → 성적 */
$on = isset($picks[$c['course_id']]); ?>
<label class="plan-row <?= $on ? 'on' : '' ?>" data-name="<?= h($c['course_name']) ?>">
  <input class="form-check-input" type="checkbox" name="course[]" value="<?= h($c['course_id']) ?>" data-credits="<?= h($c['credits']) ?>" <?= $on ? 'checked' : '' ?>>
  <span class="flex-grow-1">
    <span class="fw-semibold"><?= h($c['course_name']) ?></span>
    <span class="small text-muted"><?= h($c['credits']) ?>학점 · <?= h($c['area_name']) ?><?php if ($c['design_credits']): ?> · 설계 <?= h($c['design_credits']) ?><?php endif; ?></span>
    <?php if ($c['last_grade']): ?><span class="pill pill-bad">재수강 (<?= h($c['last_grade']) ?>)</span><?php endif; ?>
    <?php if ($s): ?><br><?php foreach ($s['reasons'] as $i => $reason): ?><span class="pill <?= $i === 0 ? $kinds[$s['priority']][1] : 'pill-no' ?> me-1"><?= h($reason) ?></span><?php endforeach; ?><?php endif; ?>
  </span>
  <select class="form-select form-select-sm grade-pick" name="g_<?= h($c['course_id']) ?>" aria-label="예상 성적">
    <?php foreach ($grades as $g): ?><option <?= ($picks[$c['course_id']] ?? 'A0') === $g ? 'selected' : '' ?>><?= h($g) ?></option><?php endforeach; ?>
  </select>
</label>
