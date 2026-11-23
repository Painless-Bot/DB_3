<?php defined('APP_ROOT') || exit;
/* 학기별 성적. $editable = true 이면 담당자용 수정 링크 표시 */ ?>
<div class="card p-4 mb-3">
  <div class="card-title-sm">학기별 평점 <span class="sub">계절학기는 장학 판정의 직전 학기에서 제외</span></div>
  <?php if ($r['semesters']): ?><div class="mb-3"><?= chart_gpa_trend($r['semesters']) ?></div><?php endif; ?>
  <table class="table table-sm mb-0">
    <thead><tr><th>학기</th><th class="text-end">신청</th><th class="text-end">취득</th><th class="text-end">평점</th><th style="width:45%"></th></tr></thead>
    <tbody>
    <?php foreach ($r['semesters'] as $s): ?>
      <tr>
        <td><?= h($s['year']) ?>년 <?= term_label($s['term']) ?></td>
        <td class="text-end"><?= h($s['attempted_credits']) ?></td>
        <td class="text-end"><?= h($s['earned_credits']) ?></td>
        <td class="text-end fw-semibold"><?= gpa($s['gpa']) ?></td>
        <td><span class="gpa-bar" style="width: <?= round((float)($s['gpa'] ?? 0) / 4.5 * 100) ?>%"></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$r['semesters']): ?>
      <tr><td colspan="5" class="text-muted">수강 이력이 없습니다.<?php if (empty($editable)): ?> <a href="<?= h(url('me/import')) ?>">클래스룸 성적 가져오기 →</a><?php endif; ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php foreach ($r['sem_courses'] as [$sem, $courses]): ?>
<div class="card p-4 mb-3">
  <div class="d-flex justify-content-between">
    <h3 class="h6 fw-bold"><?= h($sem['year']) ?>년 <?= term_label($sem['term']) ?></h3>
    <span class="small text-muted">평점 <?= gpa($sem['gpa']) ?> · 취득 <?= h($sem['earned_credits']) ?>/<?= h($sem['attempted_credits']) ?>학점</span>
  </div>
  <table class="table table-sm mb-0">
    <thead><tr><th>과목</th><th>이수구분</th><th class="text-end">학점</th><th class="text-center">성적</th><th class="text-end">평점</th><?php if (!empty($editable)): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($courses as $c): ?>
      <tr class="<?= $c['replaced'] ? 'replaced' : '' ?>">
        <td><?= h($c['course_name']) ?> <span class="small text-muted"><?= h($c['course_id']) ?></span>
          <?php if ($c['replaced']): ?><span class="pill pill-no keep">재수강으로 대체</span><?php endif; ?></td>
        <td class="small"><?= h($c['area_name']) ?><?php if ($c['design_credits']): ?> <span class="pill pill-info">설계 <?= h($c['design_credits']) ?></span><?php endif; ?></td>
        <td class="text-end"><?= h($c['credits']) ?></td>
        <td class="text-center <?= in_array($c['grade'], ['F', 'NP'], true) ? 'text-danger fw-semibold' : '' ?>"><?= h($c['grade']) ?></td>
        <td class="text-end"><?= gpa($c['grade_point']) ?></td>
        <?php if (!empty($editable)): ?><td class="text-end keep"><a class="btn btn-sm btn-outline-primary py-0" href="<?= h(url('admin/grade-edit', ['id' => $c['enroll_id']])) ?>">성적 수정</a></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>
