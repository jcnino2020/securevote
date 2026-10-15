<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/profile.php
 *
 * A voter's own account: their details, their participation history and
 * a way to change their own password.
 *
 * There is no code path here that can change a role, change an account
 * status, or read another voter's record: every query is scoped to the
 * signed-in user's id.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter  = require_voter();
$pdo    = db();
$userId = (int) $voter['id'];

$errors = [];

/* =====================================================================
 | POST: change own password
 * =================================================================== */

if (is_post()) {
    require_csrf();

    if ((string) input('action', '') === 'change_password') {
        $current  = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $hash = (string) $stmt->fetchColumn();

        if ($current === '' || !password_verify($current, $hash)) {
            $errors['current_password'] = 'That is not your current password.';
            audit_log('password_change_failed', 'Incorrect current password supplied on the voter profile page.', $userId);
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
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);

            csrf_rotate();
            audit_log('password_changed', 'Voter changed their own password.', $userId);

            flash_success('Your password was changed.');
            redirect('voter/profile.php');
        }
    }
}

/* =====================================================================
 | Participation history (this voter's own ballots only)
 * =================================================================== */

$stmt = $pdo->prepare(
    "SELECT b.ballot_reference,
            b.submitted_at,
            b.status,
            e.title AS election_title,
            e.id AS election_id,
            e.end_datetime,
            e.results_visibility
       FROM ballots b
       JOIN elections e ON e.id = b.election_id
      WHERE b.voter_id = ? AND b.status = 'submitted'
      ORDER BY b.submitted_at DESC, b.id DESC"
);
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

/* =====================================================================
 | Elections this voter is registered for
 * =================================================================== */

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM voter_eligibility WHERE user_id = ?'
);
$stmt->execute([$userId]);
$registrationCount = (int) $stmt->fetchColumn();

/* =====================================================================
 | Header
 * =================================================================== */

$pageTitle = 'My profile';
$activeNav = 'profile.php';
$breadcrumb   = [['label' => 'My profile', 'href' => 'voter/profile.php']];

require __DIR__ . '/../includes/header.php';
?>

<div class="grid-2">
  <section class="card" aria-labelledby="details-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="details-heading">Account details</h2>
        <p class="card__subtitle">Managed by the election administrator.</p>
      </div>
      <?= account_badge((string) $voter['status']) ?>
    </div>

    <div class="card__body">
      <div class="row" style="margin-bottom:var(--sp-4)">
        <span class="avatar avatar--lg" aria-hidden="true">
          <?= e(initials($voter['first_name'], $voter['last_name'])) ?>
        </span>
        <div>
          <p class="table__cell-strong"><?= e(user_full_name($voter)) ?></p>
          <p class="text-sm text-muted">
            <span class="badge badge--neutral"><?= sv_icon('user', 13) ?>Voter</span>
          </p>
        </div>
      </div>

      <dl class="kv">
        <div class="kv__row">
          <dt class="kv__key">Personal Voter Code</dt>
          <dd class="kv__value">
            <div style="display:flex; align-items:center; gap:8px;">
              <span class="voter-code-badge" id="profile-voter-code"><?= e((string) ($voter['voter_code'] ?? 'VC-2026-0000')) ?></span>
              <button class="btn btn--secondary btn--sm" type="button" data-copy="#profile-voter-code" style="padding:4px 8px;">
                <?= sv_icon('copy', 14) ?> <span data-copy-label>Copy</span>
              </button>
            </div>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Sign-In Methods</dt>
          <dd class="kv__value">
            <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
              <span class="badge badge--neutral"><?= sv_icon('key', 12) ?> Voter Code</span>
              <span class="badge badge--neutral"><?= sv_icon('lock', 12) ?> Password</span>
              <?php if (!empty($voter['google_id'])): ?>
                <span class="badge badge--primary"><?= sv_icon('google', 12) ?> Google Linked</span>
              <?php else: ?>
                <a href="<?= e(url('auth/google-login.php')) ?>" class="badge badge--neutral" style="text-decoration:none;">
                  <?= sv_icon('google', 12) ?> Link Google
                </a>
              <?php endif; ?>
            </div>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Username</dt>
          <dd class="kv__value mono">@<?= e((string) $voter['username']) ?></dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Email address</dt>
          <dd class="kv__value"><?= e((string) $voter['email']) ?></dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Account created</dt>
          <dd class="kv__value"><?= e(fmt_datetime((string) $voter['created_at'])) ?></dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Last sign-in</dt>
          <dd class="kv__value">
            <?= e($voter['last_login'] !== null ? fmt_datetime((string) $voter['last_login']) : 'This session') ?>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Elections registered for</dt>
          <dd class="kv__value"><?= e((string) $registrationCount) ?></dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Ballots submitted</dt>
          <dd class="kv__value"><?= e((string) count($history)) ?></dd>
        </div>
      </dl>

      <div class="notice" style="margin-top:var(--sp-4)">
        <span class="notice__icon" aria-hidden="true"><?= sv_icon('info', 18) ?></span>
        <span>
          To change your name, username, email or account status, please contact the election
          administrator. Those fields are deliberately not editable from a voter account.
        </span>
      </div>
    </div>
  </section>

  <section class="card" aria-labelledby="password-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="password-heading">Change my password</h2>
        <p class="card__subtitle">Your current password is required.</p>
      </div>
      <span class="badge badge--neutral"><?= sv_icon('key', 14) ?>bcrypt</span>
    </div>

    <div class="card__body">
      <form class="form" method="post" action="<?= e(post_url('voter/profile.php')) ?>" novalidate autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="form-field">
          <label class="label" for="current_password">
            Current password <span class="label__required" aria-hidden="true">*</span>
          </label>
          <div class="input-password-wrap">
            <input class="input<?= isset($errors['current_password']) ? ' is-invalid' : '' ?>"
                   type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required
                   <?= isset($errors['current_password']) ? 'aria-describedby="current_password-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="#current_password" aria-label="Show current password" aria-pressed="false" title="Show password">
              <span class="icon-eye"><?= sv_icon('eye', 18) ?></span>
              <span class="icon-eye-off" hidden><?= sv_icon('eye-off', 18) ?></span>
            </button>
          </div>
          <?php if (isset($errors['current_password'])): ?>
            <p class="field-error" id="current_password-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['current_password']) ?></span></p>
          <?php endif; ?>
        </div>

        <div class="form-field">
          <label class="label" for="password">
            New password <span class="label__required" aria-hidden="true">*</span>
          </label>
          <div class="input-password-wrap">
            <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                   type="password" id="password" name="password" autocomplete="new-password" required
                   <?= isset($errors['password']) ? 'aria-describedby="password-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="#password" aria-label="Show new password" aria-pressed="false" title="Show password">
              <span class="icon-eye"><?= sv_icon('eye', 18) ?></span>
              <span class="icon-eye-off" hidden><?= sv_icon('eye-off', 18) ?></span>
            </button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <p class="field-error" id="password-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['password']) ?></span></p>
          <?php else: ?>
            <p class="help">At least 8 characters, with a letter and a number.</p>
          <?php endif; ?>
        </div>

        <div class="form-field">
          <label class="label" for="password_confirm">
            Confirm new password <span class="label__required" aria-hidden="true">*</span>
          </label>
          <div class="input-password-wrap">
            <input class="input<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                   type="password" id="password_confirm" name="password_confirm"
                   autocomplete="new-password" required
                   <?= isset($errors['password_confirm']) ? 'aria-describedby="password_confirm-error" aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-password-toggle" data-toggle-password="#password_confirm" aria-label="Show confirm password" aria-pressed="false" title="Show password">
              <span class="icon-eye"><?= sv_icon('eye', 18) ?></span>
              <span class="icon-eye-off" hidden><?= sv_icon('eye-off', 18) ?></span>
            </button>
          </div>
          <?php if (isset($errors['password_confirm'])): ?>
            <p class="field-error" id="password_confirm-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['password_confirm']) ?></span></p>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="Updating…">
            <?= sv_icon('lock', 16, 'btn__icon') ?><span>Change password</span>
          </button>
        </div>
      </form>
    </div>

    <div class="card__footer">
      <p class="text-sm text-muted">
        Your password is stored as a bcrypt hash. Nobody, including the administrator, can read it.
      </p>
    </div>
  </section>
</div>

<section class="card" aria-labelledby="history-heading">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title" id="history-heading">Participation history</h2>
      <p class="card__subtitle">
        What you took part in and when. The candidates you chose are never listed here.
      </p>
    </div>
  </div>

  <?php if ($history === []): ?>
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('file', 26) ?></span>
      <p class="empty-state__title">No ballots submitted yet</p>
      <p class="empty-state__text">
        Once you submit a ballot, its reference and time appear here so you can prove you took part.
      </p>
      <a class="btn btn--secondary" href="<?= e(url('voter/elections.php')) ?>">
        <?= sv_icon('vote', 16, 'btn__icon') ?><span>See my elections</span>
      </a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden">Your submitted ballots with their public reference</caption>
        <thead>
          <tr>
            <th scope="col">Election</th>
            <th scope="col">Receipt code</th>
            <th scope="col">Submitted</th>
            <th scope="col">Ballot secrecy</th>
            <th scope="col">Status</th>
            <th scope="col" class="table__actions-col">Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $row): ?>
            <?php [$label, $variant] = ballot_status_meta((string) $row['status']); ?>
            <tr>
              <td class="table__cell-strong"><?= e($row['election_title']) ?></td>
              <td class="mono"><?= e((string) ($row['ballot_reference'] !== '' ? $row['ballot_reference'] : '—')) ?></td>
              <td>
                <?= e(fmt_datetime((string) $row['submitted_at'])) ?>
                <span class="table__cell-sub"><?= e(relative_time((string) $row['submitted_at'])) ?></span>
              </td>
              <td><span class="badge badge--navy"><?= sv_icon('shield-check', 13) ?>Secret ballot</span></td>
              <td><?= badge($label, $variant) ?></td>
              <td class="table__actions-col">
                <div class="table__actions">
                  <a class="btn btn--secondary btn--sm" href="<?= e(url('voter/confirmation.php?election_id=' . (int) $row['election_id'])) ?>">
                    <?= sv_icon('file', 14, 'btn__icon') ?><span>View</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
