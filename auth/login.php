<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * auth/login.php
 *
 * Multi-Method Sign-In Portal (2026 Edition):
 *   - Google OAuth 2.0 (1-Click Instant Sign In)
 *   - Database Email / Username + Password
 *   - Personal Voter Code (e.g. VC-2026-XXXX)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_guest();

// Messages left by authenticate.php (cleared as soon as they are read).
$loginError      = $_SESSION['_login_error'] ?? null;
$loginIdentifier = (string) ($_SESSION['_login_identifier'] ?? '');
$formMessage     = is_array($loginError) ? (string) ($loginError['message'] ?? '') : '';
$formFields      = is_array($loginError) ? ($loginError['fields'] ?? []) : [];
unset($_SESSION['_login_error'], $_SESSION['_login_identifier']);

$layout       = 'auth';
$pageTitle    = 'Sign in';
$bodyClass    = 'page-login';
require __DIR__ . '/../includes/header.php';
?>

<section class="auth-story" aria-labelledby="auth-story-title">
  <a class="brand auth-story__brand" href="<?= e(url('index.php')) ?>"><?= sv_logo_mark(34) ?><span class="brand__word">Secure<span>Vote</span></span></a>
  <div class="auth-story__content">
    <h2 id="auth-story-title">Your voice belongs here.</h2>
    <p>Sign in, see the elections open to you, and make your choices with confidence.</p>
    <div class="auth-story__steps"><span>Explore elections</span><span>Choose candidates</span><span>Keep your receipt</span></div>
  </div>
  <div class="auth-story__mark" aria-hidden="true"><?= sv_icon('vote', 86) ?></div>
</section>

<div class="auth-main">
  <div class="auth-card">
    <div class="auth-card__head">
      <a class="brand" href="<?= e(url('index.php')) ?>">
        <?= sv_logo_mark(32) ?>
        <span class="brand__word">Secure<span>Vote</span></span>
      </a>
      <h1 class="auth-card__title">Welcome back</h1>
      <p class="auth-card__text">
        Sign in to see your elections and get your ballot.
      </p>
    </div>

    <?php if ($formMessage !== ''): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
        <div class="alert__text"><?= e($formMessage) ?></div>
      </div>
      <div class="divider" role="presentation"></div>
    <?php endif; ?>

    <!-- Google OAuth 2.0 Sign In Button -->
    <div class="stack-sm">
      <a href="<?= e(url('auth/google-login.php')) ?>" class="btn btn--google btn--lg btn--block" id="btn-google-login">
        <?= sv_icon('google', 18, 'btn__icon') ?>
        <span>Continue with Google</span>
      </a>
    </div>

    <!-- Divider -->
    <div class="auth-separator">
      <span>or sign in with credentials</span>
    </div>

    <!-- Credentials Form -->
    <form class="form" method="post" action="<?= e(post_url('auth/authenticate.php')) ?>" novalidate>
      <?= csrf_field() ?>

      <div class="form-field">
        <label class="label" for="identifier">
          Email, username, or voter code
          <span class="label__required" aria-hidden="true">*</span>
        </label>
        <div class="input-with-icon">
          <input
            class="input<?= isset($formFields['identifier']) ? ' is-invalid' : '' ?>"
            type="text"
            id="identifier"
            name="identifier"
            value="<?= e($loginIdentifier) ?>"
            placeholder="Email, username, or voter code"
            autocomplete="username"
            spellcheck="false"
            autocapitalize="none"
            required
            aria-required="true"
            <?= isset($formFields['identifier'])
                ? 'aria-describedby="identifier-error" aria-invalid="true"'
                : '' ?>>
        </div>
        <?php if (isset($formFields['identifier'])): ?>
          <p class="field-error" id="identifier-error">
            <?= sv_icon('alert-triangle', 14) ?>
            <span><?= e((string) $formFields['identifier']) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="form-field">
        <label class="label" for="password">
          Password
          <span class="label__required" aria-hidden="true">*</span>
        </label>
        <div class="input-password-wrap">
          <input
            class="input<?= isset($formFields['password']) ? ' is-invalid' : '' ?>"
            type="password"
            id="password"
            name="password"
            placeholder="••••••••"
            autocomplete="current-password"
            required
            aria-required="true"
            <?= isset($formFields['password'])
                ? 'aria-describedby="password-error" aria-invalid="true"'
                : '' ?>>
          <button type="button" class="btn-password-toggle" data-toggle-password="#password" aria-label="Show password" aria-pressed="false" title="Show password">
            <span class="icon-eye"><?= sv_icon('eye', 18) ?></span>
            <span class="icon-eye-off" hidden><?= sv_icon('eye-off', 18) ?></span>
          </button>
        </div>
        <?php if (isset($formFields['password'])): ?>
          <p class="field-error" id="password-error">
            <?= sv_icon('alert-triangle', 14) ?>
            <span><?= e((string) $formFields['password']) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button class="btn btn--primary btn--lg btn--block" type="submit" data-submit-once
                data-submitting-label="Signing in…">
          <?= sv_icon('lock', 18, 'btn__icon') ?>
          <span>Sign In</span>
        </button>
      </div>
    </form>

    <div class="divider" role="presentation"></div>

    <div class="text-center stack-xs">
      <p class="text-sm text-muted">Don't have a voter account yet?</p>
      <a class="btn btn--secondary btn--block" href="<?= e(url('auth/register.php')) ?>">
        <?= sv_icon('user-plus', 16, 'btn__icon') ?>
        <span>Register New Voter Account</span>
      </a>
    </div>
  </div>

  <p class="text-muted text-xs text-center auth-footnote">
    <?= sv_icon('shield', 14) ?>
    <span>Protected by bcrypt password hashing and TLS/HTTPS session binding.</span>
  </p>
  <p class="text-muted text-xs text-center auth-footnote-sub">
    By signing in, you agree to our 
    <a href="<?= e(url('terms.php')) ?>" class="link" target="_blank" rel="noopener noreferrer">Terms of Service</a> and 
    <a href="<?= e(url('privacy.php')) ?>" class="link" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
  </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
