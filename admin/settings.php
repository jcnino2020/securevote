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
            $errors['first_name'] = sv_t('Enter the first name (1 to 80 characters).');
        }
        if (mb_strlen($account['last_name']) < 1 || mb_strlen($account['last_name']) > 80) {
            $errors['last_name'] = sv_t('Enter the last name (1 to 80 characters).');
        }
        if (!filter_var($account['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($account['email']) > 150) {
            $errors['email'] = sv_t('Enter a valid email address.');
        }

        if ($errors === []) {
            $dupe = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $dupe->execute([$account['email'], (int) $admin['id']]);
            if ($dupe->fetchColumn() !== false) {
                $errors['email'] = sv_t('Another account already uses that email address.');
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

            flash_success(sv_t('Your profile was updated.'));
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
            $errors['current_password'] = sv_t('That is not your current password.');
            audit_log('password_change_failed', 'Incorrect current password supplied on the settings page.');
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = sv_t('Use at least 8 characters.');
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = sv_t('Include at least one letter and one number.');
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = sv_t('The two passwords do not match.');
        }

        if ($errors === []) {
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $admin['id']]);
                $version = sv_rotate_security_version($pdo, (int) $admin['id']);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            $_SESSION['security_version'] = $version;
            csrf_rotate();

            audit_log('password_changed', 'Administrator changed their own password.');

            flash_success(sv_t('Your password was changed. Other sessions have been signed out.'));
            redirect('admin/settings.php');
        }
    }

    /* ---------------------------------------------------------------
     | Purge old audit entries
     * ------------------------------------------------------------- */
    if ($postAction === 'purge_audit') {
        $days = int_input('days', 0);

        if ($days < 30 || $days > 3650) {
            flash_error(sv_t('Choose a retention window between 30 and 3650 days.'));
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

        flash_success(sv_t(
            $removed === 1 ? '%d audit entry older than %d days was removed.' : '%d audit entries older than %d days were removed.',
            $removed,
            $days
        ));
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
    ['icon' => 'key',          'title' => sv_t('Password hashing'),        'detail' => sv_t('Passwords are stored with password_hash() as %s and checked with password_verify(). Plain text is never stored.', $hashLabel)],
    ['icon' => 'database',     'title' => sv_t('SQL injection'),           'detail' => sv_t('Every query is a prepared statement with PDO::ATTR_EMULATE_PREPARES disabled, so input is never parsed as SQL.')],
    ['icon' => 'shield-check', 'title' => sv_t('Cross-site request forgery'), 'detail' => sv_t('Every state-changing form posts a per-session token which is compared with hash_equals().')],
    ['icon' => 'eye',          'title' => sv_t('Cross-site scripting'),    'detail' => sv_t('All output is escaped with htmlspecialchars() through the e() helper, and the Content-Security-Policy forbids inline and remote scripts.')],
    ['icon' => 'lock',         'title' => sv_t('Session security'),        'detail' => sv_t('HttpOnly and SameSite=Lax cookie, strict mode, session id regenerated on login and logout, %d minute idle timeout and a %d hour maximum lifetime.', (int)(SESSION_IDLE_TIMEOUT / 60), (int)(SESSION_ABSOLUTE_TIMEOUT / 3600))],
    ['icon' => 'user-check',   'title' => sv_t('Role authorization'),       'detail' => sv_t('require_admin() and require_voter() run before any page body. A denied request is logged and answered with 403.')],
    ['icon' => 'check-circle', 'title' => sv_t('Vote integrity'),           'detail' => sv_t('Ballots are written inside a transaction with a unique constraint per voter per election, and every candidate choice is re-validated on the server.')],
    ['icon' => 'audit',        'title' => sv_t('Auditability'),             'detail' => sv_t('Sign-ins, failures, election changes and ballot submissions are appended to the audit log, which the interface cannot edit.')],
];

/* Database snapshot --------------------------------------------------- */
$tables = [
    'users'             => sv_t('Accounts (administrators and voters)'),
    'elections'         => sv_t('Elections'),
    'positions'         => sv_t('Positions'),
    'candidates'        => sv_t('Candidates'),
    'voter_eligibility' => sv_t('Voter registrations'),
    'ballots'           => sv_t('Submitted ballots'),
    'votes'             => sv_t('Recorded selections'),
    'audit_logs'        => sv_t('Audit entries'),
];

$snapshot = [];
foreach (array_keys($tables) as $table) {
    // The table name comes from this fixed list, never from user input.
    $snapshot[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}

/* =====================================================================
 | Header
 * =================================================================== */

$pageTitle = sv_t('Settings');
$activeNav = 'settings.php';
$breadcrumb   = [['label' => sv_t('Settings'), 'href' => 'admin/settings.php']];

require __DIR__ . '/../includes/header.php';
?>

<section class="stat-grid stat-grid--4" aria-label="<?= e(sv_t('Database snapshot')) ?>">
  <?php foreach (array_keys($tables) as $table): ?>
    <div class="stat-card">
      <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('server', 20) ?></span>
      <div class="stat-card__body">
        <p class="stat-card__label"><?= e(sv_t(ucfirst(str_replace('_', ' ', $table)))) ?></p>
        <p class="stat-card__value"><?= e((string) $snapshot[$table]) ?></p>
        <p class="stat-card__meta"><?= e($tables[$table]) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<section class="card" aria-labelledby="security-heading">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title" id="security-heading"><?= e(sv_t('Security controls in effect')) ?></h2>
      <p class="card__subtitle">
        <?= e(sv_t('Read from the running configuration, not from a specification document.')) ?>
      </p>
    </div>
    <span class="badge badge--teal"><?= sv_icon('shield-check', 14) ?><?= e(sv_t('Implemented')) ?></span>
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
      <?= e(sv_t('Not implemented, and deliberately not claimed: two-factor authentication, email delivery for password resets, HTTPS termination (configure it at the web server), and vote encryption at rest. Voters are created by an administrator; the system has no public self-registration.')) ?>
    </p>
  </div>
</section>

<div class="grid-2">
  <section class="card" aria-labelledby="profile-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="profile-heading"><?= e(sv_t('My profile')) ?></h2>
        <p class="card__subtitle"><?= e(sv_t('Your own administrator account.')) ?></p>
      </div>
      <span class="badge badge--info"><?= sv_icon('user', 14) ?><?= e(sv_t('Administrator')) ?></span>
    </div>
    <div class="card__body">
      <form class="form" method="post" action="<?= e(post_url('admin/settings.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_profile">

        <div class="form-grid">
          <div class="form-field">
            <label class="label" for="first_name"><?= e(sv_t('First name')) ?> <span class="label__required" aria-hidden="true">*</span></label>
            <input class="input<?= isset($errors['first_name']) ? ' is-invalid' : '' ?>"
                   type="text" id="first_name" name="first_name" maxlength="80"
                   value="<?= e($account['first_name']) ?>" required>
            <?php if (isset($errors['first_name'])): ?>
              <p class="field-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['first_name']) ?></span></p>
            <?php endif; ?>
          </div>

          <div class="form-field">
            <label class="label" for="last_name"><?= e(sv_t('Last name')) ?> <span class="label__required" aria-hidden="true">*</span></label>
            <input class="input<?= isset($errors['last_name']) ? ' is-invalid' : '' ?>"
                   type="text" id="last_name" name="last_name" maxlength="80"
                   value="<?= e($account['last_name']) ?>" required>
            <?php if (isset($errors['last_name'])): ?>
              <p class="field-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['last_name']) ?></span></p>
            <?php endif; ?>
          </div>

          <div class="form-field form-field--full">
            <label class="label" for="email"><?= e(sv_t('Email address')) ?> <span class="label__required" aria-hidden="true">*</span></label>
            <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                   type="email" id="email" name="email" maxlength="150"
                   value="<?= e($account['email']) ?>" required>
            <?php if (isset($errors['email'])): ?>
              <p class="field-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['email']) ?></span></p>
            <?php endif; ?>
          </div>

          <div class="form-field form-field--full">
            <span class="label"><?= e(sv_t('Account facts')) ?></span>
            <div class="panel">
              <dl class="kv">
                <div class="kv__row">
                  <dt class="kv__key"><?= e(sv_t('Username')) ?></dt>
                  <dd class="kv__value mono"><?= e((string) $admin['username']) ?></dd>
                </div>
                <div class="kv__row">
                  <dt class="kv__key"><?= e(sv_t('Account created')) ?></dt>
                  <dd class="kv__value"><?= e(fmt_datetime((string) $admin['created_at'])) ?></dd>
                </div>
                <div class="kv__row">
                  <dt class="kv__key"><?= e(sv_t('Last sign-in')) ?></dt>
                  <dd class="kv__value">
                    <?= e($admin['last_login'] !== null ? fmt_datetime((string) $admin['last_login']) : sv_t('This session')) ?>
                  </dd>
                </div>
              </dl>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="<?= e(sv_t('Saving…')) ?>">
            <?= sv_icon('check', 16, 'btn__icon') ?><span><?= e(sv_t('Save profile')) ?></span>
          </button>
        </div>
      </form>
    </div>
  </section>

  <section class="card" aria-labelledby="password-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="password-heading"><?= e(sv_t('Change my password')) ?></h2>
        <p class="card__subtitle"><?= e(sv_t('Your current password is required, even though you are signed in.')) ?></p>
      </div>
      <span class="badge badge--neutral"><?= sv_icon('key', 14) ?><?= e($hashLabel) ?></span>
    </div>
    <div class="card__body">
      <form class="form" method="post" action="<?= e(post_url('admin/settings.php')) ?>" novalidate autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="form-field">
          <label class="label" for="current_password"><?= e(sv_t('Current password')) ?> <span class="label__required" aria-hidden="true">*</span></label>
          <div class="input-password-wrap">
            <input class="input<?= isset($errors['current_password']) ? ' is-invalid' : '' ?>"
                   type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required
                   <?= isset($errors['current_password']) ? 'aria-describedby="current-password-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="current_password" aria-label="<?= e(sv_t('Show current password')) ?>" aria-pressed="false">
              <span class="icon-eye"><?= sv_icon('eye', 16) ?></span>
              <span class="icon-eye-off" style="display:none;"><?= sv_icon('eye-off', 16) ?></span>
            </button>
          </div>
          <?php if (isset($errors['current_password'])): ?>
            <p class="field-error" id="current-password-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['current_password']) ?></span></p>
          <?php endif; ?>
        </div>

        <div class="form-field">
          <label class="label" for="password"><?= e(sv_t('New password')) ?> <span class="label__required" aria-hidden="true">*</span></label>
          <div class="input-password-wrap">
            <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                   type="password" id="password" name="password" autocomplete="new-password" required
                   <?= isset($errors['password']) ? 'aria-describedby="password-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="password" aria-label="<?= e(sv_t('Show new password')) ?>" aria-pressed="false">
              <span class="icon-eye"><?= sv_icon('eye', 16) ?></span>
              <span class="icon-eye-off" style="display:none;"><?= sv_icon('eye-off', 16) ?></span>
            </button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <p class="field-error" id="password-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['password']) ?></span></p>
          <?php else: ?>
            <p class="help"><?= e(sv_t('At least 8 characters, with a letter and a number.')) ?></p>
          <?php endif; ?>
        </div>

        <div class="form-field">
          <label class="label" for="password_confirm"><?= e(sv_t('Confirm new password')) ?> <span class="label__required" aria-hidden="true">*</span></label>
          <div class="input-password-wrap">
            <input class="input<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                   type="password" id="password_confirm" name="password_confirm"
                   autocomplete="new-password" required
                   <?= isset($errors['password_confirm']) ? 'aria-describedby="confirm-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="password_confirm" aria-label="<?= e(sv_t('Show password confirmation')) ?>" aria-pressed="false">
              <span class="icon-eye"><?= sv_icon('eye', 16) ?></span>
              <span class="icon-eye-off" style="display:none;"><?= sv_icon('eye-off', 16) ?></span>
            </button>
          </div>
          <?php if (isset($errors['password_confirm'])): ?>
            <p class="field-error" id="confirm-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['password_confirm']) ?></span></p>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="<?= e(sv_t('Updating…')) ?>">
            <?= sv_icon('lock', 16, 'btn__icon') ?><span><?= e(sv_t('Change password')) ?></span>
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
        <h2 class="card__title" id="runtime-heading"><?= e(sv_t('Runtime environment')) ?></h2>
        <p class="card__subtitle"><?= e(sv_t('Useful when something does not behave as expected.')) ?></p>
      </div>
    </div>
    <div class="card__body">
      <dl class="kv">
        <?php foreach ($serverFacts as $label => $value): ?>
          <div class="kv__row">
            <dt class="kv__key"><?= e(sv_t($label)) ?></dt>
            <dd class="kv__value mono"><?= e($value) ?></dd>
          </div>
        <?php endforeach; ?>
        <div class="kv__row">
          <dt class="kv__key"><?= e(sv_t('Debug mode')) ?></dt>
          <dd class="kv__value">
            <span class="badge badge--<?= APP_DEBUG ? 'danger' : 'success' ?>">
              <?= e(sv_t(APP_DEBUG ? 'On — never use in production' : 'Off — errors are logged, not printed')) ?>
            </span>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key"><?= e(sv_t('Transport')) ?></dt>
          <dd class="kv__value">
            <?= e(sv_is_https() ? 'HTTPS' : sv_t('HTTP (development only)')) ?>
          </dd>
        </div>
      </dl>
    </div>
  </section>

  <section class="card" aria-labelledby="maintenance-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="maintenance-heading"><?= e(sv_t('Audit log retention')) ?></h2>
        <p class="card__subtitle"><?= e(sv_t('The only bulk operation available on the audit trail.')) ?></p>
      </div>
    </div>
    <div class="card__body stack">
      <div class="notice">
        <span class="notice__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span>
        <span>
          <?= e(sv_t('Entries older than the window you choose are permanently deleted. The purge itself is recorded in the log, so the removal can never happen silently. There are currently %d entries.', (int) $snapshot['audit_logs'])) ?>
        </span>
      </div>

      <form class="form" method="post" action="<?= e(post_url('admin/settings.php')) ?>"
            data-confirm="<?= e(sv_t('Permanently delete audit entries older than the chosen window?')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="purge_audit">

        <div class="form-field">
          <label class="label" for="days"><?= e(sv_t('Keep the most recent')) ?></label>
          <select class="select" id="days" name="days">
            <option value="30"><?= e(sv_t('30 days')) ?></option>
            <option value="90" selected><?= e(sv_t('90 days')) ?></option>
            <option value="180"><?= e(sv_t('180 days')) ?></option>
            <option value="365"><?= e(sv_t('1 year')) ?></option>
            <option value="1095"><?= e(sv_t('3 years')) ?></option>
          </select>
          <p class="help"><?= e(sv_t('Records inside the window are never touched.')) ?></p>
        </div>

        <div class="form-actions">
          <button class="btn btn--danger btn--sm" type="submit">
            <?= sv_icon('trash', 16, 'btn__icon') ?><span><?= e(sv_t('Purge old entries')) ?></span>
          </button>
        </div>
      </form>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
