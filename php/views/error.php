<?php defined('APP_ROOT') || exit;
$title = '오류';
ob_start(); ?>
<div class="eyebrow">ERROR <?= (int)$code ?></div>
<h1 class="hero-title"><strong><?= h($msg) ?></strong></h1>
<?php $hero_html = ob_get_clean(); ?>
<div class="card p-4">
  <p class="mb-2"><?= h($msg) ?></p>
  <?php if (!empty($detail)): ?><pre class="small text-muted mb-2" style="white-space:pre-wrap"><?= h($detail) ?></pre><?php endif; ?>
  <a href="<?= h(url()) ?>">← 처음 화면으로</a>
</div>
