<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/error-page.php
 *
 * Template used by sv_error_page() for 403, 404 and 500 responses.
 * Standalone, resilient, branded 2026 error surface.
 */

$code    = (int) ($code ?? 500);
$title   = (string) ($title ?? 'Something went wrong');
$message = (string) ($message ?? 'The request could not be completed.');
$links   = is_array($links ?? null) ? $links : [];

$badgeIcon = match ($code) {
    403 => 'lock',
    404 => 'info',
    default => 'alert-triangle',
};
$badgeClass = match ($code) {
    403 => 'badge badge--warning',
    404 => 'badge badge--info',
    default => 'badge badge--danger',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> &middot; <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('images/favicon-32.png')) ?>" type="image/png" sizes="32x32">
<link rel="shortcut icon" href="<?= e(url('favicon.ico')) ?>" type="image/x-icon">
<link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>" sizes="180x180">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/redesign.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
<script src="<?= e(asset('js/theme.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="app-shell app-shell--auth page-error">
  <div class="auth-layout">
    <main class="auth-card" role="alert" aria-labelledby="error-title" style="text-align: center; display: flex; flex-direction: column; align-items: center;">
      <a class="brand brand--lg" href="<?= e(url('index.php')) ?>" style="margin-bottom: var(--sp-4);">
        <?= sv_logo_mark(40) ?>
        <span class="brand__word">Secure<span>Vote</span></span>
      </a>

      <div class="<?= e($badgeClass) ?>" style="margin-bottom: var(--sp-3);" aria-hidden="true">
        <?= sv_icon($badgeIcon, 14) ?>
        <span class="mono">HTTP <?= e((string) $code) ?></span>
      </div>

      <h1 class="auth-card__title" id="error-title" style="margin-bottom: var(--sp-2); font-size: 1.6rem;">
        <?= e($title) ?>
      </h1>
      <p class="auth-card__text" style="margin-bottom: var(--sp-6); font-size: 0.95rem; line-height: 1.6;">
        <?= e($message) ?>
      </p>

      <div style="display: flex; flex-wrap: wrap; gap: var(--sp-3); justify-content: center; width: 100%; margin-bottom: var(--sp-4);">
        <?php foreach ($links as $index => $link): ?>
          <a class="btn <?= $index === 0 ? 'btn--primary' : 'btn--secondary' ?>"
             href="<?= e((string) $link['href']) ?>">
            <?= $index === 0 ? sv_icon('arrow-left', 16, 'btn__icon') : '' ?>
            <span><?= e((string) $link['label']) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if ($links === []): ?>
          <a class="btn btn--primary" href="<?= e(url('index.php')) ?>">
            <?= sv_icon('arrow-left', 16, 'btn__icon') ?>
            <span>Return to Home</span>
          </a>
        <?php endif; ?>
      </div>

      <div class="divider" role="presentation" style="width: 100%;"></div>

      <p class="error-assurance">
        <?= sv_icon('shield-check', 22) ?>
        <span>Confidentiality Protected &middot; No sensitive internal paths or stack traces disclosed.</span>
      </p>
    </main>
  </div>
</body>
</html>
