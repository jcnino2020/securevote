<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/error-page.php
 *
 * Template used by sv_error_page() for 403, 404 and 500 responses.
 * It is deliberately standalone: it never queries the database and never
 * prints a path, query or stack trace, because this page is exactly where
 * "information disclosure" bugs show up.
 *
 * Expects in scope: $code (int), $title (string), $message (string),
 *                   $links (array of ['label' => ..., 'href' => ...]).
 */

$code    = (int) ($code ?? 500);
$title   = (string) ($title ?? 'Something went wrong');
$message = (string) ($message ?? 'The request could not be completed.');
$links   = is_array($links ?? null) ? $links : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &middot; <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="app-auth">
  <main class="error-card" role="alert" aria-labelledby="error-title">
    <a class="brand brand--lg" href="<?= e(url('index.php')) ?>">
      <?= sv_logo_mark(40) ?>
      <span class="brand__word">Secure<span>Vote</span></span>
    </a>

    <p class="error-card__code" aria-hidden="true"><?= e((string) $code) ?></p>
    <h1 class="error-card__title" id="error-title"><?= e($title) ?></h1>
    <p class="error-card__message"><?= e($message) ?></p>

    <div class="error-card__actions">
      <?php foreach ($links as $index => $link): ?>
        <a class="btn <?= $index === 0 ? 'btn--primary' : 'btn--secondary' ?>"
           href="<?= e((string) $link['href']) ?>"><?= e((string) $link['label']) ?></a>
      <?php endforeach; ?>
      <?php if ($links === []): ?>
        <a class="btn btn--primary" href="<?= e(url('index.php')) ?>">Back to start</a>
      <?php endif; ?>
    </div>

    <p class="error-card__note text-muted text-sm">
      <?= sv_icon('shield-check', 14) ?>
      No sensitive details are shown on this page.
    </p>
  </main>
</body>
</html>
