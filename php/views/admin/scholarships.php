<?php defined('APP_ROOT') || exit;
$title = '장학금 관리';
ob_start(); ?>
<div class="eyebrow">ADMIN · SCHOLARSHIPS</div>
<h1 class="hero-title"><strong>장학금 기준</strong> 관리</h1>
<p class="hero-sub">전체 학과 대상 교내장학금과 <?= h(admin_dept_name()) ?> 학과 장학금의 최소 지원 기준을 관리합니다.</p>
<?php $hero_html = ob_get_clean(); ?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card p-4">
    <div class="table-responsive">
    <table class="table mb-0">
      <thead><tr><th>장학금</th><th>지급</th><th class="text-end">평점</th><th class="text-end">학점</th><th>소득</th><th>봉사</th><th class="text-center">우리 학과 가능</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $s): ?>
        <tr class="<?= $sch && $sch['sch_id'] == $s['sch_id'] ? 'table-active' : '' ?>">
          <td><div class="fw-semibold"><?= h($s['sch_name']) ?></div><span class="small text-muted"><?= h($s['category']) ?> · <?= h($s['dept_name'] ?: '전체 학과') ?></span></td>
          <td class="small"><?= h($s['amount_desc'] ?: '-') ?></td>
          <td class="text-end"><?= gpa($s['min_gpa']) ?></td>
          <td class="text-end"><?= h($s['min_credits']) ?></td>
          <td class="small"><?= $s['max_income'] !== null ? h($s['max_income']) . '분위' : '무관' ?></td>
          <td class="small"><?php if ($s['min_volunteer_hours'] !== null): ?><?= h($s['min_volunteer_hours']) ?>h<?php if ($s['volunteer_exempt_level']): ?> (<?= h($s['volunteer_exempt_level']) ?>학년↑ 면제)<?php endif; ?><?php else: ?>-<?php endif; ?></td>
          <td class="text-center"><?= h($s['eligible_count']) ?>명</td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= h(url('admin/scholarship', ['id' => $s['sch_id']])) ?>">수정</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-muted">등록된 장학금이 없습니다.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card p-4">
      <div class="card-title-sm"><?= $sch ? '장학금 수정' : '새 장학금 등록' ?></div>
      <form method="post" action="<?= h($sch ? url('admin/scholarship', ['id' => $sch['sch_id']]) : url('admin/scholarships')) ?>">
        <?= csrf_field() ?>
        <div class="row g-2 mb-2">
          <div class="col-7"><label class="form-label small">장학명</label>
            <input class="form-control" name="sch_name" value="<?= h($sch['sch_name'] ?? '') ?>" required></div>
          <div class="col-5"><label class="form-label small">구분</label>
            <select class="form-select" name="category">
              <?php foreach (['성적우수', '가계지원', '학과'] as $c): ?><option <?= $sch && $sch['category'] === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="mb-2"><label class="form-label small">지급액 (예: 등록금 80%, 90만원)</label>
          <input class="form-control" name="amount_desc" value="<?= h($sch['amount_desc'] ?? '') ?>"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small">최소 직전학기 평점</label>
            <input class="form-control" name="min_gpa" type="number" step="0.01" min="0" max="4.5" value="<?= $sch ? gpa($sch['min_gpa']) : '3.00' ?>" required></div>
          <div class="col-6"><label class="form-label small">최소 취득학점</label>
            <input class="form-control" name="min_credits" type="number" min="0" value="<?= h($sch['min_credits'] ?? 15) ?>" required></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-4"><label class="form-label small">소득분위 상한</label>
            <input class="form-control" name="max_income" type="number" min="0" max="10" value="<?= h($sch['max_income'] ?? '') ?>"></div>
          <div class="col-4"><label class="form-label small">봉사시간</label>
            <input class="form-control" name="min_volunteer_hours" type="number" min="0" value="<?= h($sch['min_volunteer_hours'] ?? '') ?>"></div>
          <div class="col-4"><label class="form-label small">봉사 면제 학년</label>
            <input class="form-control" name="volunteer_exempt_level" type="number" min="1" max="5" value="<?= h($sch['volunteer_exempt_level'] ?? '') ?>"></div>
        </div>
        <p class="small text-muted">비워 둔 칸은 조건 없음으로 봅니다.</p>
        <div class="mb-3"><label class="form-label small">대상</label>
          <select class="form-select" name="scope">
            <option value="all" <?= $sch && !$sch['dept_id'] ? 'selected' : '' ?>>전체 학과</option>
            <option value="dept" <?= $sch && $sch['dept_id'] ? 'selected' : '' ?>><?= h(admin_dept_name()) ?>만</option>
          </select></div>
        <button class="btn btn-primary" name="action" value="save"><?= $sch ? '수정' : '등록' ?></button>
        <?php if ($sch): ?>
          <button class="btn btn-outline-danger ms-1" name="action" value="delete" formnovalidate onclick="return confirm('삭제할까요?')">삭제</button>
          <a class="btn btn-link" href="<?= h(url('admin/scholarships')) ?>">취소</a>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>
