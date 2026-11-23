<?php defined('APP_ROOT') || exit;
/* 영역별 학점 + 과목군 + 미이수 필수과목 + 요건 안내 */
$st = $r['status'];
if (!$st['req_id']): ?>
  <div class="card p-4"><p class="text-danger mb-0"><?= h($r['info']['dept_name']) ?> <?= h($r['info']['admission_year']) ?>학번에 적용할 졸업요건이 아직 등록되지 않았습니다. 학과 담당자 화면에서 입학연도 구간을 추가하면 바로 진단됩니다.</p></div>
<?php else:
  $dleft = $st['min_design_credits'] - $st['earned_design'];
  $detail = [];
  foreach ($r['earned_by_area'] as $a) {
      if ($a['parent_code'] || in_array($a['area_code'], ['SL', 'EN', 'FE'], true)) {
          $detail[] = $a['area_name'] . ' ' . $a['earned_credits'];
      }
  }
?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-4 h-100">
      <div class="card-title-sm">영역별 이수 학점 <span class="sub"><?= h($r['info']['dept_name']) ?> · <?= h(years_label($r['req'])) ?> 기준</span></div>
      <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>영역</th><th class="text-end">기준</th><th class="text-end">이수</th><th class="text-end">남음</th><th style="width:32%"></th></tr></thead>
        <tbody>
        <?php foreach ($r['areas'] as $a): ?>
          <tr class="<?= $a['parent_code'] ? '' : 'fw-semibold' ?>">
            <td><?php if ($a['parent_code']): ?><span class="text-muted ms-2">└ </span><?php endif; ?><?= h($a['area_name']) ?></td>
            <td class="text-end"><?= h($a['min_credits']) ?></td>
            <td class="text-end"><?= h($a['earned_credits']) ?></td>
            <td class="text-end <?= $a['remaining_credits'] ? 'text-danger' : '' ?>"><?= $a['remaining_credits'] ?: '✓' ?></td>
            <td><div class="progress"><div class="progress-bar <?= !$a['remaining_credits'] ? 'bg-success' : '' ?>" style="width: <?= pct($a['earned_credits'], $a['min_credits']) ?>%"></div></div></td>
          </tr>
        <?php endforeach; ?>
          <tr class="fw-bold" style="border-top:2px solid var(--line)">
            <td>총 이수학점</td>
            <td class="text-end"><?= h($st['required_total']) ?></td>
            <td class="text-end"><?= h($st['earned_total']) ?></td>
            <td class="text-end <?= $st['remaining_total'] ? 'text-danger' : '' ?>"><?= $st['remaining_total'] ?: '✓' ?></td>
            <td><div class="progress"><div class="progress-bar <?= !$st['remaining_total'] ? 'bg-success' : '' ?>" style="width: <?= pct($st['earned_total'], $st['required_total']) ?>%"></div></div></td>
          </tr>
          <tr>
            <td>설계학점</td>
            <td class="text-end"><?= h($st['min_design_credits']) ?></td>
            <td class="text-end"><?= h($st['earned_design']) ?></td>
            <td class="text-end <?= $dleft > 0 ? 'text-danger' : '' ?>"><?= $dleft > 0 ? $dleft : '✓' ?></td>
            <td><div class="progress"><div class="progress-bar <?= $dleft <= 0 ? 'bg-success' : '' ?>" style="width: <?= pct($st['earned_design'], $st['min_design_credits']) ?>%"></div></div></td>
          </tr>
          <tr>
            <td>누적 평점</td>
            <td class="text-end"><?= $st['min_gpa'] !== null ? gpa($st['min_gpa']) : '기준 없음' ?></td>
            <td class="text-end"><?= gpa($st['cum_gpa']) ?></td>
            <td colspan="2" class="small <?= $st['gpa_ok'] ? 'text-success' : 'text-danger' ?>"><?= $st['gpa_ok'] ? '충족' : '기준 미달' ?></td>
          </tr>
        </tbody>
      </table>
      </div>
      <p class="small text-muted mt-3 mb-0">
        세부 이수: <?= h(implode(' · ', $detail)) ?>
        <br>재수강 과목은 가장 최근 성적만 인정하고, F·NP 는 학점에 넣지 않습니다.
      </p>
    </div>
  </div>
  <div class="col-lg-5 d-flex flex-column gap-3">
    <div class="card p-4">
      <div class="card-title-sm">필수과목
        <?php foreach ($r['required_groups'] as $g): ?><span class="pill <?= !$g['remaining_credits'] ? 'pill-ok' : 'pill-bad' ?>" title="<?= h($g['group_name']) ?>"><?= h($g['earned_count']) ?>/<?= h($g['member_count']) ?>과목</span><?php endforeach; ?>
        <span class="sub"><?= h(implode(' · ', array_column($r['required_groups'], 'group_name'))) ?></span></div>
      <?php if ($r['missing']): ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($r['missing'] as $m): ?>
            <li class="list-group-item px-0 d-flex justify-content-between">
              <span><span class="text-danger">✕</span> <?= h($m['course_name']) ?> <span class="text-muted small"><?= h($m['course_id']) ?></span></span>
              <span class="text-muted small"><?= h($m['area_name']) ?> · <?= h($m['credits']) ?>학점</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-success mb-0 small"><?= $r['required_groups'] ? '지정된 필수과목을 모두 이수했습니다.' : '이 요건에는 지정된 필수과목이 없습니다.' ?></p>
      <?php endif; ?>
    </div>
    <div class="card p-4">
      <div class="card-title-sm">과목군 요건 <span class="sub">택1 · 필수 영역 · SW/데이터활용역량</span></div>
      <?php foreach ($r['groups'] as $i => $g): ?>
        <div class="d-flex justify-content-between align-items-start py-2 <?= $i ? 'border-top' : '' ?>">
          <div class="me-2">
            <div class="fw-semibold small"><?= h($g['group_name']) ?></div>
            <div class="small text-muted"><?= h($g['description']) ?></div>
            <?php if ($g['earned_courses']): ?><div class="small text-muted">이수: <?= h(truncate_text($g['earned_courses'], 45)) ?></div><?php endif; ?>
          </div>
          <span class="pill <?= !$g['remaining_credits'] ? 'pill-ok' : 'pill-bad' ?> text-nowrap"><?php
            if ($g['min_credits'] == 1) {
                echo !$g['remaining_credits'] ? '이수' : '미이수';
            } else {
                echo h((!$g['remaining_credits'] ? $g['min_credits'] : $g['earned_credits']) . '/' . $g['min_credits']);
            } ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$r['groups']): ?>
        <p class="small text-muted mb-0">이 요건에는 과목군 조건이 없습니다.</p>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($r['req']['note'])): ?>
  <div class="col-12">
    <div class="card p-3 small text-muted">
      <strong class="text-body">시스템이 판정하지 않는 요건</strong> <?= h($r['req']['note']) ?>
      <?php if ($r['req']['source_url']): ?><div class="mt-1">기준: <?= h($r['req']['source_url']) ?></div><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
