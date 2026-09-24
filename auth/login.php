<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * auth/login.php
 *
 * The sign-in form. This page only renders; the credentials are checked
 * by auth/authenticate.php, which then redirects back here on failure.
 * That redirect (the Post/Redirect/Get pattern) is what stops a browser
 * refresh from re-posting a login.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Already signed in? Go straight to the right dashboard.
require_guest();

/* ---------------------------------------------------------------------
 | Demo hints
 |
 | These two lines exist so the system can be demonstrated from a fresh
 | install. Set $showDemoHints to false, or delete the block that uses
 | it, before this is ever used for a real election.
 * ------------------------------------------------------------------- */
$showDemoHints = true;

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

<section class="auth-aside" aria-labelledby="auth-aside-heading">
  <div class="auth-aside__top">
    <span class="brand brand--lg">
      <?= sv_logo_mark(40) ?>
      <span class="brand__word">Secure<span>Vote</span></span>
    </span>

    <h2 class="auth-aside__heading" id="auth-aside-heading">
      Secure online voting for authorized participants.
    </h2>
    <p class="auth-aside__text">
      SecureVote records one ballot per registered voter, keeps every vote
      confidential, and writes every administrative action to an audit trail.
    </p>

    <ul class="auth-facts">
      <li><?= sv_icon('shield-check', 18) ?><span>One ballot per voter, guaranteed by a unique database constraint.</span></li>
      <li><?= sv_icon('lock', 18) ?><span>Passwords are stored only as bcrypt hashes, never as plain text.</span></li>
      <li><?= sv_icon('database', 18) ?><span>Every query uses a prepared statement, so input can never become SQL.</span></li>
      <li><?= sv_icon('audit', 18) ?><span>Sign-ins, failed attempts and result changes are all recorded.</span></li>
    </ul>
  </div>

  <p class="auth-aside__foot">
    <?= e(APP_NAME) ?> <?= e(APP_VERSION) ?> &middot; Academic endterm project.
    Not affiliated with any government body.
  </p>
</section>

<div class="auth-main">
  <div class="auth-card">
    <div class="auth-card__head">
      <span class="brand">
        <?= sv_logo_mark(32) ?>
        <span class="brand__word">Secure<span>Vote</span></span>
      </span>
      <h1 class="auth-card__title">Sign in to vote</h1>
      <p class="auth-card__text">
        Use the account issued to you by the election administrator.
      </p>
    </div>

    <?php if ($formMessage !== ''): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
        <p class="alert__text"><?= e($formMessage) ?></p>
      </div>
      <div class="divider" role="presentation"></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('auth/authenticate.php')) ?>" novalidate>
      <?= csrf_field() ?>

      <div class="form-field">
        <label class="label" for="identifier">
          Username or email
          <span class="label__required" aria-hidden="true">*</span>
        </label>
        <input
          class="input<?= isset($formFields['identifier']) ? ' is-invalid' : '' ?>"
          type="text"
          id="identifier"
          name="identifier"
          value="<?= e($loginIdentifier) ?>"
          autocomplete="username"
          spellcheck="false"
          autocapitalize="none"
          required
          aria-required="true"
          <?= isset($formFields['identifier'])
              ? 'aria-describedby="identifier-error" aria-invalid="true"'
              : '' ?>>
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
        <input
          class="input<?= isset($formFields['password']) ? ' is-invalid' : '' ?>"
          type="password"
          id="password"
          name="password"
          autocomplete="current-password"
          required
          aria-required="true"
          <?= isset($formFields['password'])
              ? 'aria-describedby="password-error" aria-invalid="true"'
              : '' ?>>
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
          <span>Sign in securely</span>
        </button>
      </div>
    </form>

    <?php if ($showDemoHints): ?>
      <div class="auth-demo">
        <strong>Demonstration accounts</strong>
        <p class="text-xs">
          Seeded with the sample dataset. Remove this block before real use.
        </p>
        <dl>
          <dt>Administrator</dt>
          <dd><span class="mono">admin</span> / <span class="mono">Admin@12345</span></dd>
          <dt>Voter, not voted yet</dt>
          <dd><span class="mono">voter009</span> / <span class="mono">Voter@12345</span></dd>
          <dt>Voter, already voted</dt>
          <dd><span class="mono">voter001</span> / <span class="mono">Voter@12345</span></dd>
          <dt>Blocked voter</dt>
          <dd><span class="mono">voter020</span> / <span class="mono">Voter@12345</span></dd>
        </dl>
        <p class="text-xs">Voters run from <span class="mono">voter001</span> to
          <span class="mono">voter020</span>, all with the same password.</p>
      </div>
    <?php endif; ?>

    <p class="auth-card__foot">
      <?= sv_icon('info', 14) ?>
      Forgot your password? Accounts are created and reset by the election
      administrator, so please contact them. SecureVote does not send email.
    </p>
  </div>

  <p class="text-muted text-xs text-center">
    <?= sv_icon('shield', 13) ?>
    Sign-in attempts, successful or not, are recorded in the audit log.
  </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
