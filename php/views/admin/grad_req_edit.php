<?php defined('APP_ROOT') || exit;
$title = '졸업요건 수정';
ob_start(); ?>
<a class="small text-white-50 text-decoration-none" href="<?= h(url('admin/grad-reqs')) ?>">← 졸업요건 목록</a>
<div class="eyebrow mt-2">ADMIN · EDIT RULE</div>
<h1 class="hero-title"><?= h(admin_dept_name()) ?> <strong><?= h(years_label($req)) ?></strong> 졸업요건</h1>
<p class="hero-sub">저장하면 기본 요건·영역별 학점·과목군 연결을 한 트랜잭션으로 바꿉니다. 필수과목은 "모두 이수" 과목군으로 연결합니다.</p>
<?php $hero_html = ob_get_clean();
$all_groups = array_filter($groups, function ($g) { return $g['rule_type'] === 'ALL'; });
$credit_groups = array_filter($groups, function ($g) { return $g['rule_type'] === 'CREDITS'; });
?>
<form method="post" action="<?= h(url('admin/grad-req', ['id' => $req['req_id']])) ?>">
<?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card p-4 mb-3">
      <div class="card-title-sm">기본 요건</div>
      <div class="row g-2">
        <div class="col-6"><label class="form-label small">시작 입학연도</label>
          <input class="form-control" name="from_year" type="number" value="<?= h($req['from_year']) ?>" required></div>
        <div class="col-6"><label class="form-label small">끝 입학연도 (비우면 이후 계속)</label>
          <input class="form-control" name="to_year" type="number" value="<?= h($req['to_year']) ?>"></div>
        <div class="col-4"><label class="form-label small">총 졸업학점</label>
          <input class="form-control" name="total_credits" type="number" min="1" value="<?= h($req['total_credits']) ?>" required></div>
        <div class="col-4"><label class="form-label small">최소 설계학점</label>
          <input class="form-control" name="min_design_credits" type="number" min="0" value="<?= h($req['min_design_credits']) ?>"></div>
        <div class="col-4"><label class="form-label small">최소 평점 (비우면 없음)</label>
          <input class="form-control" name="min_gpa" type="number" step="0.01" min="0" max="4.5" value="<?= $req['min_gpa'] !== null ? gpa($req['min_gpa']) : '' ?>"></div>
        <div class="col-12"><label class="form-label small">시스템이 판정하지 않는 요건 안내</label>
          <textarea class="form-control" name="note" rows="3"><?= h($req['note']) ?></textarea></div>
      </div>
    </div>
    <div class="card p-4 mb-3">
      <div class="card-title-sm">영역별 최소 학점 <span class="sub">비우면 요건에서 뺌 · 묶음 영역은 하위 영역 합산</span></div>
      <?php foreach ($areas as $a): ?>
        <div class="row g-2 align-items-center mb-2">
          <label class="col-6 col-form-label small <?= $a['parent_code'] ? '' : 'fw-bold' ?>" for="area_<?= h($a['area_code']) ?>"><?= $a['parent_code'] ? '└ ' : '' ?><?= h($a['area_name']) ?> <span class="text-muted"><?= h($a['area_code']) ?></span></label>
          <div class="col-6"><input class="form-control form-control-sm" id="area_<?= h($a['area_code']) ?>" name="area_<?= h($a['area_code']) ?>" type="number" min="0"
               value="<?= h($a['min_credits']) ?>"></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card p-4 mb-3">
      <div class="card-title-sm">필수과목 <span class="sub">체크한 목록의 과목을 하나라도 이수하지 않으면 졸업할 수 없습니다</span></div>
      <?php foreach ($all_groups as $g): ?>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="group_<?= h($g['group_code']) ?>" value="1" id="g_<?= h($g['group_code']) ?>" <?= $g['linked'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="g_<?= h($g['group_code']) ?>"><strong><?= h($g['group_name']) ?></strong> <span class="small text-muted"><?= h($g['member_count']) ?>과목</span>
            <div class="small text-muted"><?= h($g['courses'] ?: '과목 없음') ?></div></label>
        </div>
      <?php endforeach; ?>
      <?php if (!$all_groups): ?>
        <p class="small text-muted">필수과목 목록(모두 이수 과목군)이 없습니다.</p>
      <?php endif; ?>
      <a class="small" href="<?= h(url('admin/groups')) ?>">필수과목 목록 편집 · 새로 만들기 →</a>
    </div>
    <div class="card p-4">
      <div class="card-title-sm">과목군 요건 <span class="sub">과목군 안에서 n학점 이상 · 1로 두면 "1과목 이상" · 비우면 요건에서 뺌</span></div>
      <?php foreach ($credit_groups as $g): ?>
        <div class="row g-2 align-items-center mb-2">
          <div class="col-8"><div class="small fw-semibold"><?= h($g['group_name']) ?></div><div class="small text-muted" title="<?= h($g['courses']) ?>"><?= h($g['description']) ?></div></div>
          <div class="col-4"><input class="form-control form-control-sm" name="group_<?= h($g['group_code']) ?>" type="number" min="0"
               value="<?= h($g['min_credits']) ?>" placeholder="미적용"></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<div class="d-flex gap-2 mt-3">
  <button class="btn btn-primary btn-lg px-4">저장</button>
  <button class="btn btn-outline-danger ms-auto" name="action" value="delete" formnovalidate
          onclick="return confirm('이 졸업요건을 삭제할까요? 해당 학번 학생은 요건 미등록이 됩니다.')">요건 삭제</button>
</div>
</form>
