<?php defined('APP_ROOT') || exit;
/* 장학금별 판정 */ ?>
<div class="card p-4">
  <div class="card-title-sm">교내장학금 지원 자격 <span class="sub">최소 기준 충족 여부</span></div>
  <p class="small text-muted">
    <?php if ($r['last_sem']): ?>
      직전 정규학기(<?= h($r['last_sem']['year']) ?>년 <?= term_label($r['last_sem']['term']) ?>) 평점 <strong class="text-body"><?= gpa($r['last_sem']['gpa']) ?></strong>,
      취득 <strong class="text-body"><?= h($r['last_sem']['earned_credits']) ?>학점</strong>, 소득분위 <strong class="text-body"><?= h($r['info']['income_bracket']) ?></strong>,
      봉사 <strong class="text-body"><?= h($r['info']['volunteer_hours']) ?>시간</strong>, <?= h($r['info']['grade_level']) ?>학년 기준.
      기준을 충족해도 실제 선발은 학과 내 성적 경쟁으로 정해지며, 국가장학금 1차 신청이 필요합니다.
    <?php else: ?>수강 이력이 없어 판정할 수 없습니다.<?php endif; ?>
  </p>
  <div class="table-responsive">
  <table class="table mb-0">
    <thead><tr><th>장학금</th><th>지급</th><th>평점</th><th>취득학점</th><th>소득분위</th><th>봉사</th><th>결과</th></tr></thead>
    <tbody>
    <?php foreach ($r['scholarships'] as $s): ?>
      <tr>
        <td><div class="fw-semibold"><?= h($s['sch_name']) ?></div><span class="small text-muted"><?= h($s['category']) ?><?= $s['sch_dept_id'] ? ' · 학과' : '' ?></span></td>
        <td class="small"><?= h($s['amount_desc'] ?: '-') ?></td>
        <td><span class="cond <?= $s['gpa_ok'] ? 'ok' : 'no' ?>"><?= gpa($s['min_gpa']) ?> 이상
          <?php if (!$s['gpa_ok']): ?><small><?= gpa($s['min_gpa'] - ($s['last_gpa'] ?? 0)) ?> 부족</small><?php endif; ?></span></td>
        <td><span class="cond <?= $s['credits_ok'] ? 'ok' : 'no' ?>"><?= h($s['min_credits']) ?> 이상
          <?php if (!$s['credits_ok']): ?><small><?= $s['min_credits'] - ($s['last_credits'] ?? 0) ?>학점 부족</small><?php endif; ?></span></td>
        <td><span class="cond <?= $s['income_ok'] ? 'ok' : 'no' ?>"><?= $s['max_income'] !== null ? h($s['max_income']) . '분위 이하' : '무관' ?></span></td>
        <td><span class="cond <?= $s['volunteer_ok'] ? 'ok' : 'no' ?>">
          <?php if ($s['min_volunteer_hours'] === null): ?>없음<?php else: ?><?= h($s['min_volunteer_hours']) ?>시간
            <?php if ($s['volunteer_exempt']): ?><small><?= h($s['volunteer_exempt_level']) ?>학년 면제</small>
            <?php elseif (!$s['volunteer_ok']): ?><small><?= $s['min_volunteer_hours'] - $s['volunteer_hours'] ?>시간 부족</small><?php endif; ?><?php endif; ?></span></td>
        <td><?php if ($s['eligible']): ?><span class="pill pill-ok">지원 가능</span><?php else: ?><span class="pill pill-no">미달</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$r['scholarships']): ?>
      <tr><td colspan="7" class="text-muted">등록된 장학금이 없습니다.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
