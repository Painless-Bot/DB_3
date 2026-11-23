<?php defined('APP_ROOT') || exit;
$title = '과목군 관리';
ob_start(); ?>
<div class="eyebrow">ADMIN · COURSE GROUPS</div>
<h1 class="hero-title"><strong>과목군</strong>과 필수과목 관리</h1>
<p class="hero-sub">과목군은 졸업요건에 쓰는 과목 묶음입니다. "n학점 이상(CREDITS)" 규칙은 택1·SW/데이터활용역량 같은 요건, "모두 이수(ALL)" 규칙은 전공필수 같은 필수과목 목록입니다. 한 번 정의해 여러 학번 구간 요건에 연결합니다.</p>
<?php $hero_html = ob_get_clean(); ?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card p-4">
    <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead><tr><th>과목군</th><th>규칙</th><th class="text-end">과목</th><th class="text-end">학점 합</th><th class="text-center">연결된 요건</th><th>소유</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $g): ?>
        <tr>
          <td><div class="fw-semibold"><?= h($g['group_name']) ?></div><div class="small text-muted"><?= h($g['group_code']) ?><?php if ($g['description']): ?> · <?= h(truncate_text($g['description'], 60)) ?><?php endif; ?></div></td>
          <td><?php if ($g['rule_type'] === 'ALL'): ?><span class="pill pill-bad">모두 이수</span><?php else: ?><span class="pill pill-info">n학점 이상</span><?php endif; ?></td>
          <td class="text-end"><?= h($g['member_count']) ?></td>
          <td class="text-end"><?= h($g['total_credits']) ?></td>
          <td class="text-center"><?= h($g['req_count']) ?>개</td>
          <td class="small"><?= $g['dept_id'] ? h(admin_dept_name()) : '공통' ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= h(url('admin/group', ['code' => $g['group_code']])) ?>"><?= $g['dept_id'] ? '수정' : '보기' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    </div>
  </div>
  <div class="col-xl-4">
    <form method="post" class="card p-4" action="<?= h(url('admin/groups')) ?>">
      <?= csrf_field() ?>
      <div class="card-title-sm">새 과목군</div>
      <div class="mb-2"><label class="form-label small" for="group_code">코드 (영문 대문자·숫자·_)</label>
        <input class="form-control" id="group_code" name="group_code" pattern="[A-Za-z0-9_]{2,20}" placeholder="예: MAJOR_REQ_CE" required></div>
      <div class="mb-2"><label class="form-label small" for="group_name">이름</label>
        <input class="form-control" id="group_name" name="group_name" maxlength="50" required></div>
      <div class="mb-2"><label class="form-label small">규칙 (만든 뒤에는 바꿀 수 없음)</label>
        <div class="form-check"><input class="form-check-input" type="radio" name="rule_type" value="CREDITS" id="r1" checked>
          <label class="form-check-label small" for="r1">n학점 이상 — 요건마다 기준 학점을 정함</label></div>
        <div class="form-check"><input class="form-check-input" type="radio" name="rule_type" value="ALL" id="r2">
          <label class="form-check-label small" for="r2">모두 이수 — 필수과목 목록</label></div></div>
      <div class="mb-3"><label class="form-label small" for="description">설명</label>
        <textarea class="form-control" id="description" name="description" rows="2" maxlength="300"></textarea></div>
      <button class="btn btn-primary">만들기</button>
      <p class="small text-muted mt-3 mb-0">공통 과목군(교양·SW/데이터 등)은 여러 학과가 같이 쓰므로 여기서는 볼 수만 있습니다.</p>
    </form>
  </div>
</div>
