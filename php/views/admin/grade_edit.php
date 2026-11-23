<?php defined('APP_ROOT') || exit;
$title = '성적 수정';
ob_start(); ?>
<div class="eyebrow">ADMIN · GRADE EDIT</div>
<h1 class="hero-title"><strong>성적 수정</strong></h1>
<p class="hero-sub"><?= h($e['name']) ?> (<?= h($e['student_id']) ?>) · <?= h($e['course_name']) ?></p>
<?php $hero_html = ob_get_clean(); ?>
<a class="small" href="<?= h(url('admin/student', ['id' => $e['student_id']])) ?>">← <?= h($e['name']) ?> 상세</a>
<div class="card p-4 mt-2" style="max-width: 560px">
  <h1 class="h5 mb-3">성적 수정</h1>
  <dl class="row small mb-3">
    <dt class="col-4">학생</dt><dd class="col-8"><?= h($e['name']) ?> (<?= h($e['student_id']) ?>)</dd>
    <dt class="col-4">과목</dt><dd class="col-8"><?= h($e['course_name']) ?> · <?= h($e['credits']) ?>학점</dd>
    <dt class="col-4">학기</dt><dd class="col-8"><?= h($e['year']) ?>년 <?= term_label($e['term']) ?></dd>
    <dt class="col-4">현재 성적</dt><dd class="col-8 fw-semibold"><?= h($e['grade']) ?></dd>
  </dl>
  <form method="post" action="<?= h(url('admin/grade-edit', ['id' => $e['enroll_id']])) ?>">
    <?= csrf_field() ?>
    <label class="form-label" for="grade">새 성적</label>
    <select class="form-select mb-3" id="grade" name="grade">
      <?php foreach ($grades as $g): ?>
        <option value="<?= h($g['grade']) ?>" <?= $g['grade'] === $e['grade'] ? 'selected' : '' ?>><?= h($g['grade']) ?><?php if ($g['grade_point'] !== null): ?> (<?= number_format($g['grade_point'], 1) ?>)<?php endif; ?></option>
      <?php endforeach; ?>
    </select>
    <p class="small text-muted">저장하면 ENROLLMENT 트리거가 이 학기의 평점(SEMESTER_RECORD)을 다시 계산하고, 졸업·장학 판정에 바로 반영됩니다.</p>
    <button class="btn btn-primary">저장</button>
  </form>
</div>
