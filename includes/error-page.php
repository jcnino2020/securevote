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

$badgeIcon = match ($code) {
    403 => 'lock',
    404 => 'info',
    default => 'alert-triangle',
};
$badgeClass = match ($code) {
    403 => 'error-card__badge error-card__badge--403',
    404 => 'error-card__badge error-card__badge--404',
    default => 'error-card__badge',
};
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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<style>
  /* Embedded resilient styling ensures the error page is immediately centered and styled */
  *, *::before, *::after { box-sizing: border-box; }
  body.app-auth, body.app-shell--auth {
    margin: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 32px 16px;
    font-family: Figtree, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background-color: #f8fafc;
    color: #0f172a;
    -webkit-font-smoothing: antialiased;
  }
  @media (prefers-color-scheme: dark) {
    body.app-auth, body.app-shell--auth {
      background-color: #0b1222;
      color: #f8fafc;
    }
  }
  .auth-main {
    width: 100%;
    max-width: 490px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    margin: 0 auto;
  }
  .error-card {
    width: 100%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
    padding: 40px 32px 32px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    overflow: hidden;
  }
  @media (prefers-color-scheme: dark) {
    .error-card {
      background: #152037;
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4);
    }
  }
  .error-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #ef4444, #f59e0b, #3b82f6);
  }
  .error-card .brand {
    margin-bottom: 24px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
  }
  .brand__mark { color: #2563eb; flex-shrink: 0; }
  .brand__word { font-size: 1.35rem; font-weight: 800; color: inherit; letter-spacing: -0.02em; }
  .brand__word span { color: #2563eb; }
  .error-card__badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-radius: 9999px;
    font-size: 0.8125rem;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    margin-bottom: 14px;
    background: rgba(220, 38, 38, 0.08);
    color: #dc2626;
    border: 1px solid #fecaca;
  }
  .error-card__badge--404 {
    background: rgba(2, 132, 199, 0.08);
    color: #0284c7;
    border-color: #bae6fd;
  }
  .error-card__badge--403 {
    background: rgba(217, 119, 6, 0.08);
    color: #d97706;
    border-color: #fde68a;
  }
  .error-card__title {
    font-size: 1.6rem;
    font-weight: 800;
    margin: 0 0 10px 0;
    letter-spacing: -0.025em;
    color: inherit;
    line-height: 1.25;
  }
  .error-card__message {
    font-size: 0.95rem;
    color: #64748b;
    margin: 0 0 28px 0;
    line-height: 1.6;
    max-width: 400px;
  }
  @media (prefers-color-scheme: dark) {
    .error-card__message { color: #94a3b8; }
  }
  .error-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
    width: 100%;
    margin-bottom: 24px;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 22px;
    font-size: 0.9375rem;
    font-weight: 600;
    border-radius: 10px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid transparent;
  }
  .btn--primary {
    background-color: #2563eb;
    color: #ffffff;
  }
  .btn--primary:hover {
    background-color: #1d4ed8;
    color: #ffffff;
  }
  .btn--secondary {
    background-color: #f1f5f9;
    color: #0f172a;
    border-color: #e2e8f0;
  }
  .btn--secondary:hover {
    background-color: #e2e8f0;
  }
  @media (prefers-color-scheme: dark) {
    .btn--secondary {
      background-color: rgba(255, 255, 255, 0.08);
      color: #f8fafc;
      border-color: rgba(255, 255, 255, 0.12);
    }
    .btn--secondary:hover {
      background-color: rgba(255, 255, 255, 0.12);
    }
  }
  .error-card__note {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding-top: 18px;
    border-top: 1px solid #f1f5f9;
    width: 100%;
    justify-content: center;
    font-size: 0.8125rem;
    color: #94a3b8;
    margin: 0;
  }
  @media (prefers-color-scheme: dark) {
    .error-card__note {
      border-color: rgba(255, 255, 255, 0.08);
      color: #64748b;
    }
  }
</style>
</head>
<body class="app-shell app-shell--auth app-auth">
  <div class="auth-main">
    <main class="auth-card error-card" role="alert" aria-labelledby="error-title">
      <a class="brand brand--lg" href="<?= e(url('index.php')) ?>">
        <?= sv_logo_mark(36) ?>
        <span class="brand__word">Secure<span>Vote</span></span>
      </a>

      <div class="<?= e($badgeClass) ?>" aria-hidden="true">
        <?= sv_icon($badgeIcon, 14) ?>
        <span>HTTP <?= e((string) $code) ?></span>
      </div>

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
        <span>No sensitive details are shown on this page.</span>
      </p>
    </main>
  </div>
</body>
</html>
