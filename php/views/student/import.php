<?php defined('APP_ROOT') || exit;
$title = '성적 가져오기';
ob_start(); ?>
<div class="eyebrow">STUDENT · IMPORT</div>
<h1 class="hero-title"><strong>클래스룸 성적</strong>을 가져와 바로 진단하기</h1>
<p class="hero-sub">클래스룸 전체 성적 조회 화면의 표를 복사해 붙여 넣거나, 엑셀에서 CSV 로 저장한 파일을 올리세요. 학교 계정 정보는 쓰지 않습니다.</p>
<div class="hero-badges">
  <span class="badge-soft"><?= h($info['name']) ?> · <?= h($info['student_id']) ?></span>
  <span class="badge-soft"><?= h($info['dept_name']) ?> <?= h($info['admission_year']) ?>학번</span>
  <span class="badge-soft">현재 저장된 성적 <?= h($current) ?>건</span>
</div>
<?php $hero_html = ob_get_clean();
if ($rows === null): ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card p-4 h-100">
      <div class="card-title-sm">이렇게 하면 돼요</div>
      <div class="d-flex mb-3"><span class="step-no">1</span><div class="small">클래스룸에 로그인해 <strong>졸업요건조회</strong>(추천) 또는 <strong>전체 성적 조회</strong> 화면을 엽니다.</div></div>
      <div class="d-flex mb-3"><span class="step-no">2</span><div class="small"><strong>"2026학년도 3학년 1학기" 같은 학기 제목 줄부터 표 끝까지</strong> 드래그해 선택하고 (여러 학기를 한 번에, <strong>졸업요건조회</strong> 화면은 맨 아래 요약표까지 통째로 선택해도 됩니다) <kbd>Ctrl</kbd>+<kbd>C</kbd> 로 복사합니다.</div></div>
      <div class="d-flex mb-3"><span class="step-no">3</span><div class="small">오른쪽 칸에 <kbd>Ctrl</kbd>+<kbd>V</kbd> 로 붙여 넣고 <strong>미리보기</strong>를 누릅니다.</div></div>
      <div class="d-flex"><span class="step-no">4</span><div class="small">읽은 결과를 확인한 뒤 <strong>저장</strong>하면 졸업·장학 판정이 바로 다시 계산됩니다.</div></div>
      <hr>
      <div class="small text-muted">
        클래스룸 졸업요건조회(이수구분 칸 포함)와 전체 성적 조회 형식을 그대로 읽습니다. 이수구분(교필·일교1~5·핵교6~8·M수학 등)과 *sw(심화) 표기도 반영하고, 요약표가 있으면 합계를 클래스룸과 맞춰 봅니다.
        년도·학기·이수구분 칸이 있는 엑셀/CSV 도 됩니다. DB 에 없는 과목은 학수번호 앞자리로 이수구분을 짐작해 새로 등록하니, 미리보기에서 확인하세요.
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <form class="card p-4" method="post" enctype="multipart/form-data" action="<?= h(url('me/import')) ?>">
      <?= csrf_field() ?>
      <div class="card-title-sm">성적표 붙여넣기</div>
      <textarea class="form-control paste-box mb-3" name="text" placeholder="예)
2026학년도 3학년 1학기
학수번호	과목명	영문과목명	학점	성적	재수강
704512	운영체제	OPERATING SYSTEM	3	A0
704826	알고리즘 및 실습	INTRODUCTION TO ALGORITHMS	4	A0
신청학점 7 신청평점 4.00"></textarea>
      <div class="row g-2 align-items-end">
        <div class="col-md-6">
          <label class="form-label small" for="file">또는 CSV 파일 올리기</label>
          <input class="form-control" type="file" id="file" name="file" accept=".csv,.txt,.tsv">
        </div>
        <div class="col-md-6 text-md-end">
          <button class="btn btn-primary px-4" name="action" value="preview">미리보기 →</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php else:
  $new_count = count(array_filter($rows, function ($x) { return $x['new']; })); ?>
<form method="post" action="<?= h(url('me/import')) ?>">
  <?= csrf_field() ?>
  <textarea name="text" hidden><?= h($text) ?></textarea>
  <?php if ($problems): ?>
    <div class="alert alert-warning rounded-4 small">
      <strong>읽지 못한 줄 <?= count($problems) ?>개</strong>
      <ul class="mb-0"><?php foreach (array_slice($problems, 0, 10) as $p): ?><li><?= h($p) ?></li><?php endforeach; ?><?php if (count($problems) > 10): ?><li>…</li><?php endif; ?></ul>
    </div>
  <?php endif; ?>
  <?php if ($rows): ?>
  <div class="card p-4 mb-3">
    <div class="card-title-sm">합계 확인 <span class="sub">재수강은 최근 성적 1건만, P/NP 는 평점 제외</span></div>
    <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th></th><th class="text-end">취득학점</th><th class="text-end">평점</th></tr></thead>
      <tbody>
        <tr><td>이 사이트 계산</td><td class="text-end fw-semibold"><?= h($check['credits']) ?></td>
          <td class="text-end fw-semibold"><?= gpa($check['gpa']) ?></td></tr>
        <?php if ($summary):
          $same = (!isset($summary['credits']) || $summary['credits'] == $check['credits'])
               && (!isset($summary['gpa']) || $check['gpa'] === null || abs($summary['gpa'] - $check['gpa']) < 0.015); ?>
        <tr><td>클래스룸 요약표</td><td class="text-end"><?= isset($summary['credits']) ? (int)$summary['credits'] : '-' ?></td>
          <td class="text-end"><?= isset($summary['gpa']) ? gpa($summary['gpa']) : '-' ?></td></tr>
        <tr><td colspan="3"><?php if ($same): ?><span class="pill pill-ok">클래스룸과 일치</span><?php else: ?><span class="pill pill-no">클래스룸과 다름</span> <span class="small text-muted">빠진 줄이 없는지 확인해 주세요.</span><?php endif; ?>
          <?php if ($check['retakes']): ?><span class="small text-muted ms-2">재수강 <?= h($check['retakes']) ?>건은 예전 성적을 빼고 셌습니다.</span><?php endif; ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endif; ?>
  <div class="card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <div class="card-title-sm mb-0">읽은 성적 <?= count($rows) ?>건
        <span class="sub">새 과목 <?= $new_count ?>개</span></div>
      <a class="small" href="<?= h(url('me/import')) ?>">← 다시 붙여넣기</a>
    </div>
    <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>학기</th><th>학수번호</th><th>과목명</th><th>이수구분</th><th class="text-end">학점</th><th class="text-center">등급</th><th>상태</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $x): ?>
        <tr>
          <td class="text-nowrap"><?= h($x['year']) ?>년 <?= term_label($x['term']) ?></td>
          <td class="small text-muted"><?= h($x['course_id']) ?></td>
          <td><?= h($x['course_name']) ?></td>
          <td class="small"><?= h($x['area_name']) ?></td>
          <td class="text-end"><?= h($x['credits']) ?></td>
          <td class="text-center fw-semibold <?= in_array($x['grade'], ['F', 'NP'], true) ? 'text-danger' : '' ?>"><?= h($x['grade']) ?></td>
          <td><?php if ($x['new']): ?><span class="pill pill-info">새 과목</span><?php else: ?><span class="pill pill-ok">기존 과목</span><?php endif; ?>
            <?php if (!empty($x['warn'])): ?><div class="small text-muted"><?= h($x['warn']) ?></div><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="text-muted">저장할 성적이 없습니다.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php if ($rows): ?>
  <div class="card p-4 d-flex flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="mode" id="m_replace" value="replace" <?= $mode !== 'merge' ? 'checked' : '' ?>>
        <label class="form-check-label" for="m_replace"><strong>내 성적을 이 내용으로 바꾸기</strong> <span class="small text-muted">(저장된 <?= h($current) ?>건을 지우고 새로 저장)</span></label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="mode" id="m_merge" value="merge" <?= $mode === 'merge' ? 'checked' : '' ?>>
        <label class="form-check-label" for="m_merge"><strong>기존 성적에 더하기</strong> <span class="small text-muted">(같은 학기·과목은 성적만 갱신)</span></label>
      </div>
    </div>
    <button class="btn btn-primary btn-lg px-4" name="action" value="save">저장하고 진단하기</button>
  </div>
  <?php endif; ?>
</form>
<?php endif; ?>
