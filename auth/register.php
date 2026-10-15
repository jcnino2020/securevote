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
        $errors['first_name'] = 'Enter your first name (1 to 80 characters).';
    }
    if (mb_strlen($form['last_name']) < 1 || mb_strlen($form['last_name']) > 80) {
        $errors['last_name'] = 'Enter your last name (1 to 80 characters).';
    }
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $form['username'])) {
        $errors['username'] = 'Use 3 to 50 characters: letters, numbers, dot, underscore or hyphen.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 150) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (mb_strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif (mb_strlen($password) > 200) {
        $errors['password'] = 'Keep the password under 200 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Include at least one letter and one number.';
    }

    if ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    // Unique username check
    if (!isset($errors['username'])) {
        $dupeUser = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $dupeUser->execute([$form['username']]);
        if ($dupeUser->fetchColumn() !== false) {
            $errors['username'] = 'That username is already taken.';
        }
    }

    // Unique email check
    if (!isset($errors['email'])) {
        $dupeEmail = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $dupeEmail->execute([$form['email']]);
        if ($dupeEmail->fetchColumn() !== false) {
            $errors['email'] = 'That email address is already registered.';
        }
    }

    if ($errors === []) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $voterCode = sv_generate_voter_code();

        // Ensure voter_code uniqueness
        for ($i = 1; $i <= 20; $i++) {
            $check = $pdo->prepare('SELECT id FROM users WHERE voter_code = ? LIMIT 1');
            $check->execute([$voterCode]);
            if (!$check->fetch()) {
                break;
            }
            $voterCode = sv_generate_voter_code();
        }

        $insert = $pdo->prepare(
            "INSERT INTO users (username, email, voter_code, password_hash, first_name, last_name, role, status, created_at)
             VALUES (:username, :email, :voter_code, :hash, :first_name, :last_name, 'voter', 'active', NOW())"
        );
        $insert->execute([
            ':username'   => $form['username'],
            ':email'      => $form['email'],
            ':voter_code' => $voterCode,
            ':hash'       => $hash,
            ':first_name' => $form['first_name'],
            ':last_name'  => $form['last_name'],
        ]);

        $newUserId = (int) $pdo->lastInsertId();

        // Automatically register the new voter for open and scheduled elections
        $autoEnroll = $pdo->prepare(
            "INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted, created_at)
             SELECT id, ?, 0, NOW() FROM elections WHERE status IN ('open', 'scheduled', 'draft')"
        );
        $autoEnroll->execute([$newUserId]);

        audit_log('voter_registered', 'New voter account self-registered: "' . $form['username'] . '" (Voter Code: ' . $voterCode . ').', $newUserId);

        // Fetch user data and log in
        $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$newUserId]);
        $newUser = $userStmt->fetch();

        if ($newUser) {
            login_user($newUser);
            flash_success('Welcome to SecureVote, ' . $form['first_name'] . '! Your voter account is active. Your personal Voter Code is: ' . $voterCode);
            redirect('voter/dashboard.php');
        } else {
            flash_success('Registration successful! Please sign in to your new voter account.');
            redirect('auth/login.php');
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
    <h2 id="auth-story-title">Be part of what comes next.</h2>
    <p>Create your account so you are ready when an election opens on campus.</p>
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
      <h1 class="auth-card__title">Join SecureVote</h1>
      <p class="auth-card__text">
        Use Google or fill in your details to create a voter account.
      </p>
    </div>

    <?php if ($errors !== []): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
        <div class="alert__text">Please check the highlighted fields below to continue.</div>
      </div>
      <div class="divider" role="presentation"></div>
    <?php endif; ?>

    <!-- Google OAuth 2.0 Sign Up Button -->
    <div class="stack-sm">
      <a href="<?= e(url('auth/google-login.php')) ?>" class="btn btn--google btn--lg btn--block" id="btn-google-signup">
        <?= sv_icon('google', 18, 'btn__icon') ?>
        <span>Sign up with Google</span>
      </a>
    </div>

    <!-- Divider -->
    <div class="auth-separator">
      <span>or register with email</span>
    </div>

    <form class="form" method="post" action="<?= e(post_url('auth/register.php')) ?>" novalidate>
      <?= csrf_field() ?>

      <div class="form-grid">
        <div class="form-field">
          <label class="label" for="first_name">
            First name <span class="label__required" aria-hidden="true">*</span>
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
            Last name <span class="label__required" aria-hidden="true">*</span>
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
          Username <span class="label__required" aria-hidden="true">*</span>
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
          Email address <span class="label__required" aria-hidden="true">*</span>
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
            Password <span class="label__required" aria-hidden="true">*</span>
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
              placeholder="Min. 8 characters"
              <?= isset($errors['password']) ? 'aria-describedby="password-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="#password" aria-label="Show password" aria-pressed="false" title="Show password">
              <span class="icon-eye"><?= sv_icon('eye', 18) ?></span>
              <span class="icon-eye-off" hidden><?= sv_icon('eye-off', 18) ?></span>
            </button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <p class="field-error" id="password-error">
              <?= sv_icon('alert-triangle', 14) ?>
              <span><?= e($errors['password']) ?></span>
            </p>
          <?php else: ?>
            <p class="help">At least 8 characters with 1 letter & 1 number.</p>
          <?php endif; ?>
        </div>

        <div class="form-field">
          <label class="label" for="password_confirm">
            Confirm password <span class="label__required" aria-hidden="true">*</span>
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
              placeholder="Repeat password"
              <?= isset($errors['password_confirm']) ? 'aria-describedby="password_confirm-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="#password_confirm" aria-label="Show password confirmation" aria-pressed="false" title="Show password">
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
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn--primary btn--lg btn--block" type="submit" data-submit-once
                data-submitting-label="Creating account…">
          <?= sv_icon('user-plus', 18, 'btn__icon') ?>
          <span>Create Voter Account</span>
        </button>
      </div>
    </form>

    <div class="divider" role="presentation"></div>

    <div class="text-center stack-xs">
      <p class="text-sm text-muted">Already registered?</p>
      <a class="btn btn--secondary btn--block" href="<?= e(url('auth/login.php')) ?>">
        <?= sv_icon('lock', 16, 'btn__icon') ?>
        <span>Sign in to your account</span>
      </a>
    </div>
  </div>

  <p class="text-muted text-xs text-center auth-footnote">
    <?= sv_icon('shield', 14) ?>
    <span>Protected by bcrypt password hashing and TLS/HTTPS session binding.</span>
  </p>
  <p class="text-muted text-xs text-center auth-footnote-sub">
    By registering, you agree to our 
    <a href="<?= e(url('terms.php')) ?>" class="link" target="_blank" rel="noopener noreferrer">Terms of Service</a> and 
    <a href="<?= e(url('privacy.php')) ?>" class="link" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
  </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
