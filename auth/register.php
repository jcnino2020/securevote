<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * auth/register.php
 *
 * Public voter registration form and processing (2026 Edition).
 *
 * Supports:
 *   1. Google OAuth 2.0 1-Click Registration
 *   2. Standard registration with auto-generated personal Voter Code
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_guest();

$pdo = db();

$errors = [];
$form   = [
    'first_name' => '',
    'last_name'  => '',
    'username'   => '',
    'email'      => '',
];

if (is_post()) {
    require_csrf();

    $form = [
        'first_name' => trim((string) input('first_name', '')),
        'last_name'  => trim((string) input('last_name', '')),
        'username'   => strtolower(trim((string) input('username', ''))),
        'email'      => strtolower(trim((string) input('email', ''))),
    ];

    $password        = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    // Validation
    if (mb_strlen($form['first_name']) < 1 || mb_strlen($form['first_name']) > 80) {
        $errors['first_name'] = sv_t('Enter your first name (1 to 80 characters).');
    }
    if (mb_strlen($form['last_name']) < 1 || mb_strlen($form['last_name']) > 80) {
        $errors['last_name'] = sv_t('Enter your last name (1 to 80 characters).');
    }
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $form['username'])) {
        $errors['username'] = sv_t('Use 3 to 50 characters: letters, numbers, dot, underscore or hyphen.');
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 150) {
        $errors['email'] = sv_t('Enter a valid email address.');
    }

    if (mb_strlen($password) < 8) {
        $errors['password'] = sv_t('Use at least 8 characters.');
    } elseif (mb_strlen($password) > 200) {
        $errors['password'] = sv_t('Keep the password under 200 characters.');
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = sv_t('Include at least one letter and one number.');
    }

    if ($password !== $passwordConfirm) {
        $errors['password_confirm'] = sv_t('The two passwords do not match.');
    }

    // Unique username check
    if (!isset($errors['username'])) {
        $dupeUser = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $dupeUser->execute([$form['username']]);
        if ($dupeUser->fetchColumn() !== false) {
            $errors['username'] = sv_t('That username is already taken.');
        }
    }

    // Unique email check
    if (!isset($errors['email'])) {
        $dupeEmail = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $dupeEmail->execute([$form['email']]);
        if ($dupeEmail->fetchColumn() !== false) {
            $errors['email'] = sv_t('That email address is already registered.');
        }
    }

    if ($errors === []) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (sv_registration_otp_enabled()) {
            if (!sv_registration_mail_ready()) {
                $errors['general'] = sv_t('Email verification is temporarily unavailable. Please try again later.');
            } elseif (!sv_feature_allow('registration-otp', 8, 3600)
                || !sv_feature_allow('registration-otp-email:' . hash('sha256', $form['email']), 3, 3600)) {
                $errors['general'] = sv_t('Too many verification emails requested. Please try again in an hour.');
            } elseif (!sv_registration_otp_begin($form, $hash)) {
                $errors['general'] = sv_t('We could not send the verification code. Please try again.');
            } else {
                redirect('auth/verify-registration.php');
            }
        } else {
            $created = sv_create_registered_voter($form, $hash);
            if (!$created['ok']) {
                $errors['general'] = sv_t($created['error']);
            } elseif ($created['user']) {
                login_user($created['user']);
                flash_success(sprintf(sv_t('Welcome to SecureVote, %s! Your account is ready. An election administrator will assign the elections you can vote in. Your personal Voter Code is: %s'), $form['first_name'], $created['voter_code']));
                redirect('voter/dashboard.php');
            } else {
                flash_success(sv_t('Registration successful! Please sign in to your new voter account.'));
                redirect('auth/login.php');
            }
        }
    }
}

$layout    = 'auth';
$pageTitle = 'Register to vote';
$bodyClass = 'page-register';
require __DIR__ . '/../includes/header.php';
?>

<section class="auth-story" aria-labelledby="auth-story-title">
  <a class="brand auth-story__brand" href="<?= e(url('index.php')) ?>"><?= sv_logo_mark(34) ?><span class="brand__word">Secure<span>Vote</span></span></a>
  <div class="auth-story__content">
    <h2 id="auth-story-title"><?= e(sv_t("Be part of what comes next.")) ?></h2>
    <p><?= e(sv_registration_otp_enabled() ? sv_t('Create your account and verify your email before opening your dashboard.') : sv_t("Create your account, then an election administrator will assign the elections you can vote in.")) ?></p>
    <div class="auth-story__steps"><?php if (sv_registration_otp_enabled()): ?><span><?= e(sv_t('Create account')) ?></span><span><?= e(sv_t('Verify email')) ?></span><span><?= e(sv_t('View dashboard')) ?></span><?php else: ?><span><?= e(sv_t("Explore elections")) ?></span><span><?= e(sv_t("Choose candidates")) ?></span><span><?= e(sv_t("Keep your receipt")) ?></span><?php endif; ?></div>
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
      <h1 class="auth-card__title"><?= e(sv_t("Join SecureVote")) ?></h1>
      <p class="auth-card__text">
        <?= e(sv_registration_otp_enabled() ? sv_t('Use Google or email to create an account. Email registration needs a verification code.') : sv_t("Create an account with Google or email. An administrator assigns your election access.")) ?>
      </p>
    </div>

    <?php if ($errors !== []): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
        <div class="alert__text"><?= e($errors['general'] ?? sv_t("Please check the highlighted fields below to continue.")) ?></div>
      </div>
      <div class="divider" role="presentation"></div>
    <?php endif; ?>

    <!-- Google OAuth 2.0 Sign Up Button -->
    <div class="stack-sm">
      <a href="<?= e(url('auth/google-login.php')) ?>" class="btn btn--google btn--lg btn--block" id="btn-google-signup">
        <?= sv_icon('google', 18, 'btn__icon') ?>
        <span><?= e(sv_t("Sign up with Google")) ?></span>
      </a>
    </div>

    <!-- Divider -->
    <div class="auth-separator">
      <span><?= e(sv_t("or register with email")) ?></span>
    </div>

    <form class="form" method="post" action="<?= e(post_url('auth/register.php')) ?>" novalidate>
      <?= csrf_field() ?>

      <div class="form-grid">
        <div class="form-field">
          <label class="label" for="first_name">
            <?= e(sv_t("First name")) ?> <span class="label__required" aria-hidden="true">*</span>
          </label>
          <input
            class="input<?= isset($errors['first_name']) ? ' is-invalid' : '' ?>"
            type="text"
            id="first_name"
            name="first_name"
            maxlength="80"
            value="<?= e($form['first_name']) ?>"
            required
            aria-required="true"
            autocomplete="given-name"
            placeholder="Juan"
            <?= isset($errors['first_name']) ? 'aria-describedby="first_name-error" aria-invalid="true"' : '' ?>>
          <?php if (isset($errors['first_name'])): ?>
            <p class="field-error" id="first_name-error">
              <?= sv_icon('alert-triangle', 14) ?>
              <span><?= e($errors['first_name']) ?></span>
            </p>
          <?php endif; ?>
        </div>

        <div class="form-field">
          <label class="label" for="last_name">
            <?= e(sv_t("Last name")) ?> <span class="label__required" aria-hidden="true">*</span>
          </label>
          <input
            class="input<?= isset($errors['last_name']) ? ' is-invalid' : '' ?>"
            type="text"
            id="last_name"
            name="last_name"
            maxlength="80"
            value="<?= e($form['last_name']) ?>"
            required
            aria-required="true"
            autocomplete="family-name"
            placeholder="Dela Cruz"
            <?= isset($errors['last_name']) ? 'aria-describedby="last_name-error" aria-invalid="true"' : '' ?>>
          <?php if (isset($errors['last_name'])): ?>
            <p class="field-error" id="last_name-error">
              <?= sv_icon('alert-triangle', 14) ?>
              <span><?= e($errors['last_name']) ?></span>
            </p>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-field">
        <label class="label" for="username">
          <?= e(sv_t("Username")) ?> <span class="label__required" aria-hidden="true">*</span>
        </label>
        <input
          class="input<?= isset($errors['username']) ? ' is-invalid' : '' ?>"
          type="text"
          id="username"
          name="username"
          maxlength="50"
          value="<?= e($form['username']) ?>"
          autocomplete="username"
          spellcheck="false"
          autocapitalize="none"
          required
          aria-required="true"
          placeholder="e.g. juandelacruz"
          <?= isset($errors['username']) ? 'aria-describedby="username-error" aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['username'])): ?>
          <p class="field-error" id="username-error">
            <?= sv_icon('alert-triangle', 14) ?>
            <span><?= e($errors['username']) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="form-field">
        <label class="label" for="email">
          <?= e(sv_t("Email address")) ?> <span class="label__required" aria-hidden="true">*</span>
        </label>
        <input
          class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
          type="email"
          id="email"
          name="email"
          maxlength="150"
          value="<?= e($form['email']) ?>"
          autocomplete="email"
          spellcheck="false"
          required
          aria-required="true"
          placeholder="e.g. juan@university.edu.ph"
          <?= isset($errors['email']) ? 'aria-describedby="email-error" aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['email'])): ?>
          <p class="field-error" id="email-error">
            <?= sv_icon('alert-triangle', 14) ?>
            <span><?= e($errors['email']) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="form-grid">
        <div class="form-field">
          <label class="label" for="password">
            <?= e(sv_t("Password")) ?> <span class="label__required" aria-hidden="true">*</span>
          </label>
          <div class="input-password-wrap">
            <input
              class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
              type="password"
              id="password"
              name="password"
              autocomplete="new-password"
              required
              aria-required="true"
              placeholder="<?= e(sv_t("Min. 8 characters")) ?>"
              aria-describedby="<?= isset($errors['password']) ? 'password-error ' : '' ?>password-rules"
              <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="#password" aria-label="<?= e(sv_t("Show password")) ?>" aria-pressed="false" title="<?= e(sv_t("Show password")) ?>">
              <span class="icon-eye"><?= sv_icon('eye', 18) ?></span>
              <span class="icon-eye-off" hidden><?= sv_icon('eye-off', 18) ?></span>
            </button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <p class="field-error" id="password-error">
              <?= sv_icon('alert-triangle', 14) ?>
              <span><?= e($errors['password']) ?></span>
            </p>
          <?php endif; ?>
          <ul class="password-checks" id="password-rules" aria-label="<?= e(sv_t('Password requirements')) ?>">
            <li data-password-rule="length" data-state="pending"><span class="password-checks__icon" aria-hidden="true">○</span><?= e(sv_t('8 to 200 characters')) ?></li>
            <li data-password-rule="letter" data-state="pending"><span class="password-checks__icon" aria-hidden="true">○</span><?= e(sv_t('At least one letter')) ?></li>
            <li data-password-rule="number" data-state="pending"><span class="password-checks__icon" aria-hidden="true">○</span><?= e(sv_t('At least one number')) ?></li>
          </ul>
        </div>

        <div class="form-field">
          <label class="label" for="password_confirm">
            <?= e(sv_t("Confirm password")) ?> <span class="label__required" aria-hidden="true">*</span>
          </label>
          <div class="input-password-wrap">
            <input
              class="input<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
              type="password"
              id="password_confirm"
              name="password_confirm"
              autocomplete="new-password"
              required
              aria-required="true"
              placeholder="<?= e(sv_t("Repeat password")) ?>"
              aria-describedby="<?= isset($errors['password_confirm']) ? 'password_confirm-error ' : '' ?>password-match"
              <?= isset($errors['password_confirm']) ? 'aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="#password_confirm" aria-label="<?= e(sv_t("Show password confirmation")) ?>" aria-pressed="false" title="<?= e(sv_t("Show password")) ?>">
              <span class="icon-eye"><?= sv_icon('eye', 18) ?></span>
              <span class="icon-eye-off" hidden><?= sv_icon('eye-off', 18) ?></span>
            </button>
          </div>
          <?php if (isset($errors['password_confirm'])): ?>
            <p class="field-error" id="password_confirm-error">
              <?= sv_icon('alert-triangle', 14) ?>
              <span><?= e($errors['password_confirm']) ?></span>
            </p>
          <?php endif; ?>
          <p class="password-checks__match" id="password-match" data-password-match data-state="pending"><span class="password-checks__icon" aria-hidden="true">○</span><?= e(sv_t('Passwords match')) ?></p>
        </div>
      </div>
      <p class="visually-hidden" data-password-status role="status" aria-live="polite" aria-atomic="true"
         data-valid-message="<?= e(sv_t('Password requirements met')) ?>"
         data-invalid-message="<?= e(sv_t('Password requirements not yet met')) ?>"
         data-match-message="<?= e(sv_t('Passwords match')) ?>"
         data-mismatch-message="<?= e(sv_t('Passwords do not match')) ?>"></p>

      <div class="form-actions">
        <button class="btn btn--primary btn--lg btn--block" type="submit" data-submit-once
                data-submitting-label="<?= e(sv_registration_otp_enabled() ? sv_t('Sending code…') : sv_t("Creating account…")) ?>">
          <?= sv_icon(sv_registration_otp_enabled() ? 'mail' : 'user-plus', 18, 'btn__icon') ?>
          <span><?= e(sv_registration_otp_enabled() ? sv_t('Send verification code') : sv_t("Create Voter Account")) ?></span>
        </button>
      </div>
    </form>

    <div class="divider" role="presentation"></div>

    <div class="text-center stack-xs">
      <p class="text-sm text-muted"><?= e(sv_t("Already registered?")) ?></p>
      <a class="btn btn--secondary btn--block" href="<?= e(url('auth/login.php')) ?>">
        <?= sv_icon('lock', 16, 'btn__icon') ?>
        <span><?= e(sv_t("Sign in to your account")) ?></span>
      </a>
    </div>
  </div>

  <p class="text-muted text-xs text-center auth-footnote">
    <?= sv_icon('shield', 14) ?>
    <span><?= e(sv_t("Protected by bcrypt password hashing and TLS/HTTPS session binding.")) ?></span>
  </p>
  <p class="text-muted text-xs text-center auth-footnote-sub">
    <?= e(sv_t("By registering, you agree to our")) ?> 
    <a href="<?= e(url('terms.php')) ?>" class="link" target="_blank" rel="noopener noreferrer"><?= e(sv_t("Terms of Service")) ?></a> <?= e(sv_t("and")) ?> 
    <a href="<?= e(url('privacy.php')) ?>" class="link" target="_blank" rel="noopener noreferrer"><?= e(sv_t("Privacy Policy")) ?></a>.
  </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
