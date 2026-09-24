<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * auth/register.php
 *
 * Public voter registration form and processing.
 *
 * Allows any voter to register for an account. Upon registration:
 *   1. Input is validated (first name, last name, username, email, password)
 *   2. Uniqueness of username and email is enforced
 *   3. Password is hashed securely with bcrypt (password_hash)
 *   4. Account is created with role='voter' and status='active'
 *   5. The voter is automatically made eligible for all active/upcoming elections
 *   6. Security audit trail records the registration event
 *   7. The voter is automatically signed in and redirected to their dashboard
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Already signed in? Go straight to the appropriate dashboard.
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

        $insert = $pdo->prepare(
            "INSERT INTO users (username, email, password_hash, first_name, last_name, role, status, created_at)
             VALUES (:username, :email, :hash, :first_name, :last_name, 'voter', 'active', NOW())"
        );
        $insert->execute([
            ':username'   => $form['username'],
            ':email'      => $form['email'],
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

        audit_log('voter_registered', 'New voter account self-registered: "' . $form['username'] . '".', $newUserId);

        // Fetch user data and log in
        $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$newUserId]);
        $newUser = $userStmt->fetch();

        if ($newUser) {
            login_user($newUser);
            flash_success('Welcome to SecureVote, ' . $form['first_name'] . '! Your voter account is active and you can now vote.');
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

<section class="auth-aside" aria-labelledby="auth-aside-heading">
  <div class="auth-aside__top">
    <span class="brand brand--lg">
      <?= sv_logo_mark(40) ?>
      <span class="brand__word">Secure<span>Vote</span></span>
    </span>

    <h2 class="auth-aside__heading" id="auth-aside-heading">
      Register to participate in verified online elections.
    </h2>
    <p class="auth-aside__text">
      Create your personal voter account to cast your ballot securely in active elections.
      Your vote is completely confidential and protected by cryptographic integrity.
    </p>

    <ul class="auth-facts">
      <li><?= sv_icon('user-check', 18) ?><span>Instant voter registration with verified unique credentials.</span></li>
      <li><?= sv_icon('shield-check', 18) ?><span>Secret ballot: your vote selections are never linked to your personal name.</span></li>
      <li><?= sv_icon('lock', 18) ?><span>Passwords are securely hashed with bcrypt and never stored in plain text.</span></li>
      <li><?= sv_icon('audit', 18) ?><span>All voting activities and administrative actions are logged in the audit trail.</span></li>
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
      <h1 class="auth-card__title">Create your voter account</h1>
      <p class="auth-card__text">
        Fill out the form below to register and start voting.
      </p>
    </div>

    <?php if ($errors !== []): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
        <p class="alert__text">Please check the highlighted fields below to continue.</p>
      </div>
      <div class="divider" role="presentation"></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('auth/register.php')) ?>" novalidate>
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
        <p class="help">3–50 characters: letters, numbers, dot, underscore, or hyphen.</p>
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
          placeholder="e.g. juan@example.com"
          <?= isset($errors['email']) ? 'aria-describedby="email-error" aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['email'])): ?>
          <p class="field-error" id="email-error">
            <?= sv_icon('alert-triangle', 14) ?>
            <span><?= e($errors['email']) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="form-field">
        <label class="label" for="password">
          Password <span class="label__required" aria-hidden="true">*</span>
        </label>
        <input
          class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
          type="password"
          id="password"
          name="password"
          autocomplete="new-password"
          required
          aria-required="true"
          <?= isset($errors['password']) ? 'aria-describedby="password-error" aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['password'])): ?>
          <p class="field-error" id="password-error">
            <?= sv_icon('alert-triangle', 14) ?>
            <span><?= e($errors['password']) ?></span>
          </p>
        <?php endif; ?>
        <p class="help">Use at least 8 characters with at least one letter and one number.</p>
      </div>

      <div class="form-field">
        <label class="label" for="password_confirm">
          Confirm password <span class="label__required" aria-hidden="true">*</span>
        </label>
        <input
          class="input<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
          type="password"
          id="password_confirm"
          name="password_confirm"
          autocomplete="new-password"
          required
          aria-required="true"
          <?= isset($errors['password_confirm']) ? 'aria-describedby="password_confirm-error" aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['password_confirm'])): ?>
          <p class="field-error" id="password_confirm-error">
            <?= sv_icon('alert-triangle', 14) ?>
            <span><?= e($errors['password_confirm']) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button class="btn btn--primary btn--lg btn--block" type="submit" data-submit-once
                data-submitting-label="Registering account…">
          <?= sv_icon('user-plus', 18, 'btn__icon') ?>
          <span>Register to vote</span>
        </button>
      </div>
    </form>

    <div class="divider" role="presentation"></div>

    <p class="text-sm text-center text-muted">
      Already have an account?
      <a class="link" href="<?= e(url('auth/login.php')) ?>"><strong>Sign in here</strong></a>
    </p>
  </div>

  <p class="text-muted text-xs text-center">
    <?= sv_icon('shield', 13) ?>
    All voter registrations and sign-ins are recorded securely in the audit log.
  </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
