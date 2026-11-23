<?php defined('APP_ROOT') || exit;
$title = '비밀번호 변경';
ob_start(); ?>
<div class="eyebrow">ACCOUNT</div>
<h1 class="hero-title"><strong>비밀번호</strong> 변경</h1>
<p class="hero-sub"><?= h($_SESSION['login_id']) ?> 계정<?php if ($last && $last['last_login']): ?> · 마지막 로그인 <?= h(substr($last['last_login'], 0, 16)) ?><?php endif; ?></p>
<?php $hero_html = ob_get_clean(); ?>
<form method="post" class="card p-4" style="max-width: 460px">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label small" for="current">현재 비밀번호</label>
    <input class="form-control" id="current" name="current" type="password" required autocomplete="current-password"></div>
  <div class="mb-3"><label class="form-label small" for="new">새 비밀번호 (8자 이상)</label>
    <input class="form-control" id="new" name="new" type="password" minlength="8" required autocomplete="new-password"></div>
  <div class="mb-3"><label class="form-label small" for="confirm">새 비밀번호 확인</label>
    <input class="form-control" id="confirm" name="confirm" type="password" minlength="8" required autocomplete="new-password"></div>
  <button class="btn btn-primary">변경</button>
  <p class="small text-muted mt-3 mb-0">비밀번호는 원문이 아니라 bcrypt 해시(PHP password_hash)로만 USER_ACCOUNT 에 저장됩니다.</p>
</form>
