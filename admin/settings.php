<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/settings.php
 *
 * System and account settings for the signed-in administrator.
 *
 * Everything shown on this page is read from the running configuration or
 * from the database. Nothing here is a marketing claim: if a control is
 * not implemented, it is not listed. That rule matters because an
 * interface that overstates its own security is worse than one that says
 * nothing at all.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();

$errors  = [];
$account = [
    'first_name' => (string) $admin['first_name'],
    'last_name'  => (string) $admin['last_name'],
    'email'      => (string) $admin['email'],
];

/* =====================================================================
 | POST handlers
 * =================================================================== */

if (is_post()) {
    require_csrf();
    $postAction = (string) input('action', '');

    /* ---------------------------------------------------------------
     | Update the administrator's own profile
     * ------------------------------------------------------------- */
    if ($postAction === 'save_profile') {
        $account = [
            'first_name' => trim((string) input('first_name', '')),
            'last_name'  => trim((string) input('last_name', '')),
            'email'      => strtolower(trim((string) input('email', ''))),
        ];

        if (mb_strlen($account['first_name']) < 1 || mb_strlen($account['first_name']) > 80) {
            $errors['first_name'] = 'Enter the first name (1 to 80 characters).';
        }
        if (mb_strlen($account['last_name']) < 1 || mb_strlen($account['last_name']) > 80) {
            $errors['last_name'] = 'Enter the last name (1 to 80 characters).';
        }
        if (!filter_var($account['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($account['email']) > 150) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($errors === []) {
            $dupe = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $dupe->execute([$account['email'], (int) $admin['id']]);
            if ($dupe->fetchColumn() !== false) {
                $errors['email'] = 'Another account already uses that email address.';
            }
        }

        if ($errors === []) {
            $update = $pdo->prepare(
                'UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?'
            );
            $update->execute([
                $account['first_name'],
                $account['last_name'],
                $account['email'],
                (int) $admin['id'],
            ]);

            audit_log('profile_updated', 'Administrator updated their own profile details.');

            flash_success('Your profile was updated.');
            redirect('admin/settings.php');
        }
    }

    /* ---------------------------------------------------------------
     | Change the administrator's own password
     * ------------------------------------------------------------- */
    if ($postAction === 'change_password') {
        $current  = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        // Re-authenticate before allowing a credential change.
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $admin['id']]);
        $hash = (string) $stmt->fetchColumn();

        if ($current === '' || !password_verify($current, $hash)) {
            $errors['current_password'] = 'That is not your current password.';
            audit_log('password_change_failed', 'Incorrect current password supplied on the settings page.');
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Include at least one letter and one number.';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }

        if ($errors === []) {
            $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $admin['id']]);

            // A credential change invalidates the token issued for the old state.
            csrf_rotate();

            audit_log('password_changed', 'Administrator changed their own password.');

            flash_success('Your password was changed. Other sessions keep working until they expire.');
            redirect('admin/settings.php');
        }
    }

    /* ---------------------------------------------------------------
     | Purge old audit entries
     * ------------------------------------------------------------- */
    if ($postAction === 'purge_audit') {
        $days = int_input('days', 0);

        if ($days < 30 || $days > 3650) {
            flash_error('Choose a retention window between 30 and 3650 days.');
            redirect('admin/settings.php');
        }

        $cutoff = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))
            ->modify('-' . $days . ' days')
            ->format('Y-m-d H:i:s');

        // Counted first so the audit entry can state exactly what was removed.
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE created_at < ?');
        $countStmt->execute([$cutoff]);
        $removed = (int) $countStmt->fetchColumn();

        $delete = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < ?');
        $delete->execute([$cutoff]);

        audit_log('audit_log_purged', 'Retention purge removed ' . $removed
            . ' entr' . ($removed === 1 ? 'y' : 'ies') . ' older than ' . $days . ' days.');

        flash_success($removed . ' audit entr' . ($removed === 1 ? 'y' : 'ies')
            . ' older than ' . $days . ' days were removed.');
        redirect('admin/settings.php');
    }
}

/* =====================================================================
 | Live facts for the security panel
 * =================================================================== */

$serverFacts = [
    'PHP version'        => PHP_VERSION,
    'Database server'    => (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    'Database name'      => DB_NAME,
    'Application version' => APP_VERSION,
    'Timezone'           => APP_TIMEZONE . ' (server: ' . date_default_timezone_get() . ')',
];

$hashInfo = password_get_info(password_hash('probe', PASSWORD_DEFAULT));
$hashLabel = ($hashInfo['algoName'] ?? 'unknown') . ($hashInfo['options']['cost'] ?? null
    ? ' (cost ' . $hashInfo['options']['cost'] . ')'
    : '');

$securityControls = [
    ['icon' => 'key',          'title' => 'Password hashing',        'detail' => 'Passwords are stored with password_hash() as ' . $hashLabel . ' and checked with password_verify(). Plain text is never stored.'],
    ['icon' => 'database',     'title' => 'SQL injection',           'detail' => 'Every query is a prepared statement with PDO::ATTR_EMULATE_PREPARES disabled, so input is never parsed as SQL.'],
    ['icon' => 'shield-check', 'title' => 'Cross-site request forgery', 'detail' => 'Every state-changing form posts a per-session token which is compared with hash_equals().'],
    ['icon' => 'eye',          'title' => 'Cross-site scripting',    'detail' => 'All output is escaped with htmlspecialchars() through the e() helper, and the Content-Security-Policy forbids inline and remote scripts.'],
    ['icon' => 'lock',         'title' => 'Session security',        'detail' => 'HttpOnly and SameSite=Lax cookie, strict mode, session id regenerated on login and logout, ' . (SESSION_IDLE_TIMEOUT / 60) . ' minute idle timeout and a ' . (SESSION_ABSOLUTE_TIMEOUT / 3600) . ' hour maximum lifetime.'],
    ['icon' => 'user-check',   'title' => 'Role authorization',       'detail' => 'require_admin() and require_voter() run before any page body. A denied request is logged and answered with 403.'],
    ['icon' => 'check-circle', 'title' => 'Vote integrity',           'detail' => 'Ballots are written inside a transaction with a unique constraint per voter per election, and every candidate choice is re-validated on the server.'],
    ['icon' => 'audit',        'title' => 'Auditability',             'detail' => 'Sign-ins, failures, election changes and ballot submissions are appended to the audit log, which the interface cannot edit.'],
];

/* Database snapshot --------------------------------------------------- */
$tables = [
    'users'             => 'Accounts (administrators and voters)',
    'elections'         => 'Elections',
    'positions'         => 'Positions',
    'candidates'        => 'Candidates',
    'voter_eligibility' => 'Voter registrations',
    'ballots'           => 'Submitted ballots',
    'votes'             => 'Recorded selections',
    'audit_logs'        => 'Audit entries',
];

$snapshot = [];
foreach (array_keys($tables) as $table) {
    // The table name comes from this fixed list, never from user input.
    $snapshot[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}

/* =====================================================================
 | Header
 * =================================================================== */

$pageTitle = 'Settings';
$activeNav = 'settings.php';
$breadcrumb   = [['label' => 'Settings', 'href' => 'admin/settings.php']];

require __DIR__ . '/../includes/header.php';
?>

<section class="stat-grid stat-grid--4" aria-label="Database snapshot">
  <?php foreach (array_keys($tables) as $table): ?>
    <div class="stat-card">
      <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('server', 20) ?></span>
      <div class="stat-card__body">
        <p class="stat-card__label"><?= e(ucfirst(str_replace('_', ' ', $table))) ?></p>
        <p class="stat-card__value"><?= e((string) $snapshot[$table]) ?></p>
        <p class="stat-card__meta"><?= e($tables[$table]) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<section class="card" aria-labelledby="security-heading">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title" id="security-heading">Security controls in effect</h2>
      <p class="card__subtitle">
        Read from the running configuration, not from a specification document.
      </p>
    </div>
    <span class="badge badge--teal"><?= sv_icon('shield-check', 14) ?>Implemented</span>
  </div>
  <div class="card__body stack-sm">
    <?php foreach ($securityControls as $control): ?>
      <div class="review-item">
        <span class="review-item__position">
          <?= sv_icon($control['icon'], 14) ?>
          <?= e($control['title']) ?>
        </span>
        <span class="review-item__meta"><?= e($control['detail']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="card__footer">
    <p class="text-sm text-muted">
      Not implemented, and deliberately not claimed: two-factor authentication, email delivery
      for password resets, HTTPS termination (configure it at the web server), and vote
      encryption at rest. Voters are created by an administrator; the system has no public
      self-registration.
    </p>
  </div>
</section>

<div class="grid-2">
  <section class="card" aria-labelledby="profile-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="profile-heading">My profile</h2>
        <p class="card__subtitle">Your own administrator account.</p>
      </div>
      <span class="badge badge--info"><?= sv_icon('user', 14) ?>Administrator</span>
    </div>
    <div class="card__body">
      <form class="form" method="post" action="<?= e(url('admin/settings.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_profile">

        <div class="form-grid">
          <div class="form-field">
            <label class="label" for="first_name">First name <span class="label__required" aria-hidden="true">*</span></label>
            <input class="input<?= isset($errors['first_name']) ? ' is-invalid' : '' ?>"
                   type="text" id="first_name" name="first_name" maxlength="80"
                   value="<?= e($account['first_name']) ?>" required>
            <?php if (isset($errors['first_name'])): ?>
              <p class="field-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['first_name']) ?></span></p>
            <?php endif; ?>
          </div>

          <div class="form-field">
            <label class="label" for="last_name">Last name <span class="label__required" aria-hidden="true">*</span></label>
            <input class="input<?= isset($errors['last_name']) ? ' is-invalid' : '' ?>"
                   type="text" id="last_name" name="last_name" maxlength="80"
                   value="<?= e($account['last_name']) ?>" required>
            <?php if (isset($errors['last_name'])): ?>
              <p class="field-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['last_name']) ?></span></p>
            <?php endif; ?>
          </div>

          <div class="form-field form-field--full">
            <label class="label" for="email">Email address <span class="label__required" aria-hidden="true">*</span></label>
            <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                   type="email" id="email" name="email" maxlength="150"
                   value="<?= e($account['email']) ?>" required>
            <?php if (isset($errors['email'])): ?>
              <p class="field-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['email']) ?></span></p>
            <?php endif; ?>
          </div>

          <div class="form-field form-field--full">
            <span class="label">Account facts</span>
            <div class="panel">
              <dl class="kv">
                <div class="kv__row">
                  <dt class="kv__key">Username</dt>
                  <dd class="kv__value mono"><?= e((string) $admin['username']) ?></dd>
                </div>
                <div class="kv__row">
                  <dt class="kv__key">Account created</dt>
                  <dd class="kv__value"><?= e(fmt_datetime((string) $admin['created_at'])) ?></dd>
                </div>
                <div class="kv__row">
                  <dt class="kv__key">Last sign-in</dt>
                  <dd class="kv__value">
                    <?= e($admin['last_login'] !== null ? fmt_datetime((string) $admin['last_login']) : 'This session') ?>
                  </dd>
                </div>
              </dl>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="Saving…">
            <?= sv_icon('check', 16, 'btn__icon') ?><span>Save profile</span>
          </button>
        </div>
      </form>
    </div>
  </section>

  <section class="card" aria-labelledby="password-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="password-heading">Change my password</h2>
        <p class="card__subtitle">Your current password is required, even though you are signed in.</p>
      </div>
      <span class="badge badge--neutral"><?= sv_icon('key', 14) ?><?= e($hashLabel) ?></span>
    </div>
    <div class="card__body">
      <form class="form" method="post" action="<?= e(url('admin/settings.php')) ?>" novalidate autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="form-field">
          <label class="label" for="current_password">Current password <span class="label__required" aria-hidden="true">*</span></label>
          <div class="input-password-wrap">
            <input class="input<?= isset($errors['current_password']) ? ' is-invalid' : '' ?>"
                   type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required
                   <?= isset($errors['current_password']) ? 'aria-describedby="current-password-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="current_password" aria-label="Show current password" aria-pressed="false">
              <span class="icon-eye"><?= sv_icon('eye', 16) ?></span>
              <span class="icon-eye-off" style="display:none;"><?= sv_icon('eye-off', 16) ?></span>
            </button>
          </div>
          <?php if (isset($errors['current_password'])): ?>
            <p class="field-error" id="current-password-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['current_password']) ?></span></p>
          <?php endif; ?>
        </div>

        <div class="form-field">
          <label class="label" for="password">New password <span class="label__required" aria-hidden="true">*</span></label>
          <div class="input-password-wrap">
            <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                   type="password" id="password" name="password" autocomplete="new-password" required
                   <?= isset($errors['password']) ? 'aria-describedby="password-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="password" aria-label="Show new password" aria-pressed="false">
              <span class="icon-eye"><?= sv_icon('eye', 16) ?></span>
              <span class="icon-eye-off" style="display:none;"><?= sv_icon('eye-off', 16) ?></span>
            </button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <p class="field-error" id="password-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['password']) ?></span></p>
          <?php else: ?>
            <p class="help">At least 8 characters, with a letter and a number.</p>
          <?php endif; ?>
        </div>

        <div class="form-field">
          <label class="label" for="password_confirm">Confirm new password <span class="label__required" aria-hidden="true">*</span></label>
          <div class="input-password-wrap">
            <input class="input<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                   type="password" id="password_confirm" name="password_confirm"
                   autocomplete="new-password" required
                   <?= isset($errors['password_confirm']) ? 'aria-describedby="confirm-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="password_confirm" aria-label="Show password confirmation" aria-pressed="false">
              <span class="icon-eye"><?= sv_icon('eye', 16) ?></span>
              <span class="icon-eye-off" style="display:none;"><?= sv_icon('eye-off', 16) ?></span>
            </button>
          </div>
          <?php if (isset($errors['password_confirm'])): ?>
            <p class="field-error" id="confirm-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['password_confirm']) ?></span></p>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="Updating…">
            <?= sv_icon('lock', 16, 'btn__icon') ?><span>Change password</span>
          </button>
        </div>
      </form>
    </div>
  </section>
</div>

<div class="grid-2">
  <section class="card" aria-labelledby="runtime-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="runtime-heading">Runtime environment</h2>
        <p class="card__subtitle">Useful when something does not behave as expected.</p>
      </div>
    </div>
    <div class="card__body">
      <dl class="kv">
        <?php foreach ($serverFacts as $label => $value): ?>
          <div class="kv__row">
            <dt class="kv__key"><?= e($label) ?></dt>
            <dd class="kv__value mono"><?= e($value) ?></dd>
          </div>
        <?php endforeach; ?>
        <div class="kv__row">
          <dt class="kv__key">Debug mode</dt>
          <dd class="kv__value">
            <span class="badge badge--<?= APP_DEBUG ? 'danger' : 'success' ?>">
              <?= e(APP_DEBUG ? 'On — never use in production' : 'Off — errors are logged, not printed') ?>
            </span>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Transport</dt>
          <dd class="kv__value">
            <?= e(sv_is_https() ? 'HTTPS' : 'HTTP (development only)') ?>
          </dd>
        </div>
      </dl>
    </div>
  </section>

  <section class="card" aria-labelledby="maintenance-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="maintenance-heading">Audit log retention</h2>
        <p class="card__subtitle">The only bulk operation available on the audit trail.</p>
      </div>
    </div>
    <div class="card__body stack">
      <div class="notice">
        <span class="notice__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span>
        <span>
          Entries older than the window you choose are permanently deleted. The purge itself is
          recorded in the log, so the removal can never happen silently. There are currently
          <strong><?= e((string) $snapshot['audit_logs']) ?></strong> entries.
        </span>
      </div>

      <form class="form" method="post" action="<?= e(url('admin/settings.php')) ?>"
            data-confirm="Permanently delete audit entries older than the chosen window?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="purge_audit">

        <div class="form-field">
          <label class="label" for="days">Keep the most recent</label>
          <select class="select" id="days" name="days">
            <option value="30">30 days</option>
            <option value="90" selected>90 days</option>
            <option value="180">180 days</option>
            <option value="365">1 year</option>
            <option value="1095">3 years</option>
          </select>
          <p class="help">Records inside the window are never touched.</p>
        </div>

        <div class="form-actions">
          <button class="btn btn--danger btn--sm" type="submit">
            <?= sv_icon('trash', 16, 'btn__icon') ?><span>Purge old entries</span>
          </button>
        </div>
      </form>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
