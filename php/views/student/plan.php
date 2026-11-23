<?php defined('APP_ROOT') || exit;
$title = '수강 계획 시뮬레이터';
ob_start();
$page_label = 'STUDENT · PLANNER';
$page_title = '수강 계획 시뮬레이션';
$page_desc = '다음 학기에 들을 과목과 예상 성적을 고르면, 실제 판정 뷰로 졸업요건과 장학금 결과가 어떻게 바뀌는지 미리 보여 줍니다. 계산이 끝나면 DB 에는 아무것도 남기지 않습니다(트랜잭션 ROLLBACK).';
include APP_ROOT . '/views/partials/student_hero.php';
$hero_html = ob_get_clean();
$kinds = [1 => ['필수', 'pill-bad'], 2 => ['과목군', 'pill-info'], 3 => ['영역', 'pill-no'], 4 => ['설계', 'pill-no']];
$rec = array_values(array_filter($r['suggestions'], function ($s) use ($cand) { return isset($cand[$s['course_id']]); }));
// 추천에 없는 과목을 이수구분(sort_order)별로 묶는다
$by_area = [];
foreach ($candidates as $c) {
    if (!isset($suggested[$c['course_id']])) {
        $by_area[$c['sort_order']][] = $c;
    }
}
ksort($by_area);
?>
<form method="post" id="plan-form" action="<?= h(url('me/plan')) ?>">
<?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-4 mb-3">
      <div class="card-title-sm">계획할 학기</div>
      <div class="row g-2 align-items-end">
        <div class="col-5 col-sm-3"><label class="form-label small" for="year">연도</label>
          <input class="form-control" id="year" name="year" type="number" min="2000" max="2100" value="<?= h($year) ?>"></div>
        <div class="col-7 col-sm-4"><label class="form-label small" for="term">학기</label>
          <select class="form-select" id="term" name="term">
            <?php foreach ([1 => '1학기', 2 => '2학기', 3 => '여름학기', 4 => '겨울학기'] as $t => $label): ?>
              <option value="<?= $t ?>" <?= $t === $term ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-sm-5 small text-muted">장학 판정은 정규학기(1·2학기)를 "직전 학기"로 봅니다.</div>
      </div>
    </div>

    <div class="card p-4 mb-3">
      <div class="card-title-sm">추천 과목 <span class="sub">부족한 요건을 채우는 과목 · 체크하고 예상 성적을 고르세요</span></div>
      <?php if ($rec): ?>
      <div class="plan-list">
        <?php foreach ($rec as $s) { $c = $cand[$s['course_id']]; include APP_ROOT . '/views/partials/plan_row.php'; } ?>
      </div>
      <?php else: ?>
        <p class="small text-muted mb-0">부족한 영역·과목군·필수과목이 없습니다. 아래 전체 과목에서 고르세요.</p>
      <?php endif; ?>
    </div>

    <div class="card p-4">
      <div class="card-title-sm">전체 과목 <span class="sub">아직 학점을 받지 않은 우리 학과·공통 과목</span>
        <input class="form-control form-control-sm ms-auto" style="max-width: 200px" type="search" placeholder="과목명 검색" data-filter="#all-courses .plan-row"></div>
      <div id="all-courses">
      <?php $first = true; foreach ($by_area as $items): ?>
        <details class="plan-group" <?= $first ? 'open' : '' ?>>
          <summary><?= h($items[0]['area_name']) ?> <span class="text-muted small"><?= count($items) ?>과목</span></summary>
          <div class="plan-list">
            <?php foreach ($items as $c) { $s = null; include APP_ROOT . '/views/partials/plan_row.php'; } ?>
          </div>
        </details>
      <?php $first = false; endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5 order-first order-lg-0">
    <div class="plan-side">
      <div class="card p-4 mb-3">
        <div class="card-title-sm">선택한 과목</div>
        <div class="d-flex gap-4 align-items-end mb-2">
          <div><div class="stat-label">과목</div><div class="stat-value" id="pick-count"><?= count($picks) ?></div></div>
          <div><div class="stat-label">학점</div><div class="stat-value" id="pick-credits">0</div></div>
          <div><div class="stat-label">예상 학기 평점</div><div class="stat-value" id="pick-gpa">-</div></div>
        </div>
        <div class="small text-muted mb-3" id="pick-warn"></div>
        <button class="btn btn-primary w-100 btn-lg">이대로 들으면? 판정해 보기</button>
        <?php if ($picks): ?><a class="btn btn-link w-100 btn-sm mt-1" href="<?= h(url('me/plan')) ?>">선택 초기화</a><?php endif; ?>
      </div>

      <?php if ($result):
        $b = $result['before'];
        $a = $result['after'];
        $bs = $b['status'];
        $as = $a['status'];
        $delta_row = function ($label, $x, $y) {
            return '<tr><td>' . h($label) . '</td><td class="text-end text-muted">' . h($x) . '</td>'
                . '<td class="text-end fw-semibold ' . ($x != $y ? 'text-success' : '') . '">' . h($y) . '</td></tr>';
        };
        $changed_areas = [];
        foreach ($a['areas'] as $code => $y) {
            if (isset($b['areas'][$code]) && $b['areas'][$code]['earned_credits'] != $y['earned_credits']) {
                $changed_areas[] = [$b['areas'][$code], $y];
            }
        }
        $done_groups = [];
        foreach ($a['groups'] as $code => $y) {
            if (isset($b['groups'][$code]) && $b['groups'][$code]['remaining_credits'] && !$y['remaining_credits']) {
                $done_groups[] = $y['group_name'];
            }
        }
        $done_required = array_values(array_diff(array_column($b['missing'], 'course_name'), array_column($a['missing'], 'course_name')));
      ?>
      <div class="card p-4 plan-result" id="result">
        <div class="card-title-sm"><?= h($year) ?>년 <?= term_label($term) ?> 계획 결과 <span class="sub"><?= count($picks) ?>과목 · <?= h($result['credits']) ?>학점</span></div>
        <div class="verdict <?= $as['graduatable'] ? 'ok' : 'no' ?> mb-3">
          <span class="fs-4"><?= $as['graduatable'] ? '🎓' : '📋' ?></span>
          <div>
            <?php if ($as['graduatable'] && !$bs['graduatable']): ?><div class="big">이 계획대로면 졸업 요건을 모두 충족합니다!</div>
            <?php elseif ($as['graduatable']): ?><div class="big">이미 졸업 요건을 충족한 상태입니다.</div>
            <?php else: ?><div class="big">계획 후에도 남는 요건이 있습니다.</div>
              <div class="small">총학점 <?= h($as['remaining_total']) ?>학점 남음 · 미충족 영역 <?= h($as['short_area_count']) ?> · 과목군 <?= h($as['short_group_count']) ?> · 필수과목 <?= h($as['missing_required_count']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <table class="table table-sm mb-3">
          <thead><tr><th></th><th class="text-end">지금</th><th class="text-end">계획 후</th></tr></thead>
          <tbody>
            <?= $delta_row('이수 학점', $bs['earned_total'], $as['earned_total']) ?>
            <?= $delta_row('남은 졸업학점', $bs['remaining_total'], $as['remaining_total']) ?>
            <?= $delta_row('설계학점', $bs['earned_design'] . '/' . $bs['min_design_credits'], $as['earned_design'] . '/' . $as['min_design_credits']) ?>
            <?= $delta_row('누적 평점', gpa($bs['cum_gpa']), gpa($as['cum_gpa'])) ?>
            <?= $delta_row('미충족 항목', $bs['short_area_count'] + $bs['short_group_count'] + $bs['missing_required_count'],
                                        $as['short_area_count'] + $as['short_group_count'] + $as['missing_required_count']) ?>
            <tr><td><?= h($year) ?>년 <?= term_label($term) ?> 평점</td><td class="text-end text-muted">-</td>
              <td class="text-end fw-semibold"><?= $a['sem'] ? gpa($a['sem']['gpa']) : '-' ?> <span class="small text-muted">(<?= $a['sem'] ? h($a['sem']['earned_credits']) : 0 ?>학점)</span></td></tr>
          </tbody>
        </table>

        <?php if ($changed_areas): ?>
        <div class="small fw-bold mb-1">영역별 변화</div>
        <ul class="small list-unstyled mb-3">
          <?php foreach ($changed_areas as [$x, $y]): ?>
            <li><?= h($y['area_name']) ?> <?= h($x['earned_credits']) ?> → <strong><?= h($y['earned_credits']) ?></strong>/<?= h($y['min_credits']) ?>
              <?php if ($x['remaining_credits'] && !$y['remaining_credits']): ?><span class="pill pill-ok">충족</span><?php elseif ($y['remaining_credits']): ?><span class="text-muted">(<?= h($y['remaining_credits']) ?> 남음)</span><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if ($done_groups || $done_required): ?>
        <div class="small fw-bold mb-1">새로 채우는 요건</div>
        <div class="mb-3"><?php foreach ($done_groups as $g): ?><span class="pill pill-ok me-1"><?= h($g) ?></span><?php endforeach; ?><?php foreach ($done_required as $m): ?><span class="pill pill-ok me-1">필수 <?= h($m) ?></span><?php endforeach; ?></div>
        <?php endif; ?>

        <?php if (in_array($term, [1, 2], true)): ?>
        <div class="small fw-bold mb-1">이 학기 성적으로 본 다음 장학 선발</div>
        <ul class="small list-unstyled mb-0">
          <?php foreach ($a['sch'] as $sch_id => $s): ?>
            <li class="d-flex justify-content-between border-top py-1">
              <span><?= h($s['sch_name']) ?> <span class="text-muted"><?= gpa($s['min_gpa']) ?> 이상</span></span>
              <?php if ($s['eligible']): ?><span class="pill pill-ok">지원 가능<?php if (empty($b['sch'][$sch_id]['eligible'])): ?> · NEW<?php endif; ?></span>
              <?php else: ?><span class="pill pill-no"><?php
                if (!$s['gpa_ok']) { echo '평점 ' . gpa($s['min_gpa'] - ($s['last_gpa'] ?? 0)) . ' 부족'; }
                elseif (!$s['credits_ok']) { echo ($s['min_credits'] - ($s['last_credits'] ?? 0)) . '학점 부족'; }
                elseif (!$s['income_ok']) { echo '소득분위'; }
                else { echo '봉사시간'; } ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="small text-muted mb-0">계절학기는 장학금 "직전 학기"에 들어가지 않습니다.</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</form>
