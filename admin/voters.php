<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/voters.php
 *
 * Voter account management and election eligibility.
 *
 * Authorization rules that matter here:
 *   - every mutating action first re-reads the target row and refuses to
 *     touch anything that is not a voter account, so this page can never
 *     be used to change an administrator
 *   - an account that already holds a ballot cannot be deleted, because
 *     ballots.voter_id is ON DELETE CASCADE: one careless click would
 *     destroy a recorded vote
 *   - eligibility cannot be withdrawn after the voter has voted
 *   - passwords are only ever written as bcrypt hashes
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();

$errors = [];
$form   = [
    'id'         => 0,
    'username'   => '',
    'email'      => '',
    'first_name' => '',
    'last_name'  => '',
    'status'     => 'active',
];

$statusOptions = [
    'active'   => 'Active — can sign in and vote',
    'inactive' => 'Inactive — cannot sign in',
    'blocked'  => 'Blocked — cannot sign in, kept for the record',
];

/** Load a voter account, or null when the id is not a voter. */
function sv_find_voter(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'voter' LIMIT 1");
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/* =====================================================================
 | POST handlers
 * =================================================================== */

if (is_post()) {
    require_csrf();
    $postAction = (string) input('action', '');

    /* ---------------------------------------------------------------
     | Create or update a voter account
     * ------------------------------------------------------------- */
    if ($postAction === 'save') {
        $form = [
            'id'         => int_input('id', 0),
            'username'   => (string) input('username', ''),
            'email'      => (string) input('email', ''),
            'first_name' => (string) input('first_name', ''),
            'last_name'  => (string) input('last_name', ''),
            'status'     => (string) input('status', 'active'),
        ];

        $password        = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        $username = strtolower(trim($form['username']));
        $email    = strtolower(trim($form['email']));
        $existing = $form['id'] > 0 ? sv_find_voter($pdo, $form['id']) : null;

        if ($form['id'] > 0 && $existing === null) {
            $errors['form'] = 'That voter account no longer exists, or it is not a voter.';
        }

        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
            $errors['username'] = 'Use 3 to 50 characters: letters, numbers, dot, underscore or hyphen.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (mb_strlen(trim($form['first_name'])) < 1 || mb_strlen(trim($form['first_name'])) > 80) {
            $errors['first_name'] = 'Enter the first name (1 to 80 characters).';
        }
        if (mb_strlen(trim($form['last_name'])) < 1 || mb_strlen(trim($form['last_name'])) > 80) {
            $errors['last_name'] = 'Enter the last name (1 to 80 characters).';
        }
        if (!isset($statusOptions[$form['status']])) {
            $errors['status'] = 'Choose a valid account status.';
        }

        // A password is required when creating, optional when editing.
        if ($form['id'] === 0 || $password !== '') {
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
        }

        // Unique username and email (mirrors the two unique keys).
        if ($errors === [] || !isset($errors['username'])) {
            $dupe = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $dupe->execute([$username, $form['id']]);
            if ($dupe->fetchColumn() !== false) {
                $errors['username'] = 'That username is already taken.';
            }
        }
        if ($errors === [] || !isset($errors['email'])) {
            $dupe = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $dupe->execute([$email, $form['id']]);
            if ($dupe->fetchColumn() !== false) {
                $errors['email'] = 'That email address is already registered.';
            }
        }

        if ($errors === []) {
            if ($form['id'] > 0) {
                $update = $pdo->prepare(
                    'UPDATE users
                        SET username = :username, email = :email, first_name = :first_name,
                            last_name = :last_name, status = :status
                      WHERE id = :id AND role = \'voter\''
                );
                $update->execute([
                    ':username'   => $username,
                    ':email'      => $email,
                    ':first_name' => trim($form['first_name']),
                    ':last_name'  => trim($form['last_name']),
                    ':status'     => $form['status'],
                    ':id'         => $form['id'],
                ]);

                audit_log('voter_updated', 'Updated voter #' . $form['id'] . ' "' . $username
                    . '" (status ' . $form['status'] . ').');

                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $reset = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'voter'");
                    $reset->execute([$hash, $form['id']]);

                    audit_log('voter_password_reset', 'Administrator reset the password for voter #'
                        . $form['id'] . ' "' . $username . '".');
                }

                flash_success('Voter account "' . $username . '" was updated.');
            } else {
                $hash      = password_hash($password, PASSWORD_DEFAULT);
                $voterCode = sv_generate_voter_code();

                // Ensure voter code uniqueness
                for ($attempt = 0; $attempt < 10; $attempt++) {
                    $chk = $pdo->prepare('SELECT id FROM users WHERE voter_code = ? LIMIT 1');
                    $chk->execute([$voterCode]);
                    if (!$chk->fetch()) {
                        break;
                    }
                    $voterCode = sv_generate_voter_code();
                }

                $insert = $pdo->prepare(
                    "INSERT INTO users (username, email, voter_code, password_hash, first_name, last_name, role, status)
                     VALUES (:username, :email, :voter_code, :hash, :first_name, :last_name, 'voter', :status)"
                );
                $insert->execute([
                    ':username'   => $username,
                    ':email'      => $email,
                    ':voter_code' => $voterCode,
                    ':hash'       => $hash,
                    ':first_name' => trim($form['first_name']),
                    ':last_name'  => trim($form['last_name']),
                    ':status'     => $form['status'],
                ]);
                $newId = (int) $pdo->lastInsertId();

                $autoEnroll = $pdo->prepare(
                    "INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted, created_at)
                     SELECT id, ?, 0, NOW() FROM elections WHERE status IN ('open', 'scheduled', 'draft')"
                );
                $autoEnroll->execute([$newId]);

                audit_log('voter_created', 'Created voter #' . $newId . ' "' . $username . '" (Voter Code: ' . $voterCode . ').');

                flash_success('Voter account "' . $username . '" was created (Voter Code: ' . $voterCode . ') and registered for active elections.');
            }

            redirect('admin/voters.php');
        }
    }

    /* ---------------------------------------------------------------
     | Change account status
     * ------------------------------------------------------------- */
    if ($postAction === 'set_status') {
        $target = sv_find_voter($pdo, int_input('id', 0));
        $status = (string) input('target_status', '');

        if ($target === null || !isset($statusOptions[$status])) {
            flash_error('That status change is not valid for this account.');
            redirect('admin/voters.php');
        }

        if ((int) $target['id'] === (int) $admin['id']) {
            flash_error('You cannot change the status of your own account from here.');
            redirect('admin/voters.php');
        }

        $update = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'voter'");
        $update->execute([$status, (int) $target['id']]);

        audit_log('voter_status_changed', 'Voter #' . (int) $target['id'] . ' "'
            . $target['username'] . '" status changed from ' . $target['status'] . ' to ' . $status . '.');

        flash_success('"' . $target['username'] . '" is now ' . $status . '.');
        redirect('admin/voters.php');
    }

    /* ---------------------------------------------------------------
     | Delete an account
     * ------------------------------------------------------------- */
    if ($postAction === 'delete') {
        $target = sv_find_voter($pdo, int_input('id', 0));

        if ($target === null) {
            flash_error('That voter account no longer exists.');
            redirect('admin/voters.php');
        }

        if ((int) $target['id'] === (int) $admin['id']) {
            flash_error('You cannot delete your own account.');
            redirect('admin/voters.php');
        }

        // In a secret ballot system, voter accounts that have already voted
        // are kept to preserve election integrity and turnout verification.
        $votedStmt = $pdo->prepare('SELECT COUNT(*) FROM voter_eligibility WHERE user_id = ? AND has_voted = 1');
        $votedStmt->execute([(int) $target['id']]);
        $votedCount = (int) $votedStmt->fetchColumn();

        if ($votedCount > 0) {
            flash_error('"' . $target['username'] . '" has already participated in ' . $votedCount
                . ' election(s). Their participation record is preserved for election integrity. '
                . 'Set the account status to "blocked" instead.');
            redirect('admin/voters.php');
        }

        $delete = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'voter'");
        $delete->execute([(int) $target['id']]);

        audit_log('voter_deleted', 'Deleted voter #' . (int) $target['id'] . ' "'
            . $target['username'] . '" (had not submitted ballots).');

        flash_success('Voter account "' . $target['username'] . '" was deleted.');
        redirect('admin/voters.php');
    }

    /* ---------------------------------------------------------------
     | Save election eligibility
     * ------------------------------------------------------------- */
    if ($postAction === 'save_eligibility') {
        $voter = sv_find_voter($pdo, int_input('id', 0));

        if ($voter === null) {
            flash_error('That voter account no longer exists.');
            redirect('admin/voters.php');
        }

        // Keys are election ids, so the raw array is used deliberately
        // (array_input() would discard them).
        $submitted = is_array($_POST['eligible'] ?? null) ? $_POST['eligible'] : [];
        $wanted    = [];
        foreach ($submitted as $key => $value) {
            $electionId = (int) $key;
            if ($value === '1' && $electionId > 0) {
                $wanted[$electionId] = true;
            }
        }

        $current = $pdo->prepare('SELECT election_id, has_voted FROM voter_eligibility WHERE user_id = ?');
        $current->execute([(int) $voter['id']]);
        $existing = [];
        foreach ($current->fetchAll() as $row) {
            $existing[(int) $row['election_id']] = (bool) $row['has_voted'];
        }

        $added     = 0;
        $removed   = 0;
        $protected = 0;

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted) VALUES (?, ?, 0)'
        );
        $delete = $pdo->prepare('DELETE FROM voter_eligibility WHERE election_id = ? AND user_id = ? AND has_voted = 0');

        foreach ($wanted as $electionId => $unused) {
            if (!isset($existing[$electionId])) {
                $insert->execute([$electionId, (int) $voter['id']]);
                $added++;
                audit_log('eligibility_added', 'Voter "' . $voter['username']
                    . '" registered for election #' . $electionId . '.');
            }
        }

        foreach ($existing as $electionId => $hasVoted) {
            if (isset($wanted[$electionId])) {
                continue;
            }
            if ($hasVoted) {
                // A voter who has already voted keeps their eligibility row:
                // it is the proof that their ballot was accepted.
                $protected++;
                continue;
            }
            $delete->execute([$electionId, (int) $voter['id']]);
            $removed++;
            audit_log('eligibility_removed', 'Voter "' . $voter['username']
                . '" removed from election #' . $electionId . '.');
        }

        $message = 'Eligibility updated: ' . $added . ' added, ' . $removed . ' removed.';
        if ($protected > 0) {
            $message .= ' ' . $protected . ' election(s) were kept because the voter has already voted.';
        }
        flash_success($message);
        redirect('admin/voters.php?action=eligibility&id=' . (int) $voter['id']);
    }

    /* ---------------------------------------------------------------
     | Register every active voter for one election
     * ------------------------------------------------------------- */
    if ($postAction === 'register_all') {
        $electionId = int_input('election_id', 0);

        $stmt = $pdo->prepare('SELECT id, title FROM elections WHERE id = ? LIMIT 1');
        $stmt->execute([$electionId]);
        $election = $stmt->fetch();

        if (!$election) {
            flash_error('That election no longer exists.');
            redirect('admin/voters.php');
        }

        $insert = $pdo->prepare(
            "INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted)
             SELECT ?, u.id, 0 FROM users u WHERE u.role = 'voter' AND u.status = 'active'"
        );
        $insert->execute([$electionId]);
        $added = $insert->rowCount();

        audit_log('eligibility_bulk_added', 'Registered ' . $added . ' active voter(s) for election #'
            . $electionId . ' "' . $election['title'] . '".');

        flash_success($added . ' voter(s) were registered for "' . $election['title'] . '". '
            . 'Those already registered were skipped.');
        redirect('admin/voters.php?action=eligibility&id=' . int_input('voter_id', 0));
    }
}

/* =====================================================================
 | Shared query data
 * =================================================================== */

$elections = $pdo->query('SELECT id, title, status FROM elections ORDER BY start_datetime DESC')->fetchAll();

/* =====================================================================
 | Which view?
 * =================================================================== */

$viewAction = (string) input('action', 'list');

// A failed "save" POST must come back as the form, not the list, so the
// submitted values are not thrown away.
if (is_post() && $viewAction === 'save') {
    $viewAction = ((int) ($form['id'] ?? 0) > 0) ? 'edit' : 'create';
}

if ($viewAction === 'eligibility') {
    $voter = sv_find_voter($pdo, int_input('id', 0));

    if ($voter === null) {
        sv_error_page(
            404,
            'Voter not found',
            'That voter account does not exist, or it was deleted by another administrator.',
            [['label' => 'Back to voters', 'href' => url('admin/voters.php')]]
        );
    }

    $eligibilityStmt = $pdo->prepare(
        'SELECT election_id, has_voted, voted_at FROM voter_eligibility WHERE user_id = ?'
    );
    $eligibilityStmt->execute([(int) $voter['id']]);
    $eligibility = [];
    foreach ($eligibilityStmt->fetchAll() as $row) {
        $eligibility[(int) $row['election_id']] = $row;
    }

    $pageTitle    = 'Election eligibility';
    $pageSubtitle = 'Choose which elections ' . user_full_name($voter) . ' may vote in.';
    $activeNav    = 'voters.php';
    $pageActions  = '<a class="btn btn--secondary btn--sm" href="' . e(url('admin/voters.php')) . '">'
        . sv_icon('arrow-left', 16, 'btn__icon') . '<span>Back to voters</span></a>';
    $breadcrumb   = [
        ['label' => 'Voters', 'href' => 'admin/voters.php'],
        ['label' => user_full_name($voter)],
    ];

    require __DIR__ . '/../includes/header.php';
    ?>

    <section class="card" aria-labelledby="eligibility-heading">
      <div class="card__header">
        <div class="card__headings">
          <h2 class="card__title" id="eligibility-heading">
            <?= e(user_full_name($voter)) ?>
            <span class="text-muted mono text-sm">(<?= e($voter['username']) ?>)</span>
          </h2>
          <p class="card__subtitle"><?= e($voter['email']) ?></p>
        </div>
        <?= account_badge((string) $voter['status']) ?>
      </div>

      <div class="card__body">
        <div class="security-note">
          <?= sv_icon('info', 16) ?>
          <span>
            A voter can only vote in an election they are registered for. Once a ballot has been
            submitted the registration is locked, because it is the record that the vote was accepted.
          </span>
        </div>

        <form class="form" method="post" action="<?= e(url('admin/voters.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_eligibility">
          <input type="hidden" name="id" value="<?= e((string) $voter['id']) ?>">

          <?php if ($elections === []): ?>
            <div class="empty-state empty-state--inline">
              <span class="empty-state__icon"><?= sv_icon('vote', 24) ?></span>
              <p class="empty-state__title">No elections exist yet</p>
              <p class="empty-state__text">Create an election before registering voters for one.</p>
            </div>
          <?php else: ?>
            <fieldset>
              <legend>Elections this voter may vote in</legend>

              <div class="stack-sm">
                <?php foreach ($elections as $election): ?>
                  <?php
                    $row       = $eligibility[(int) $election['id']] ?? null;
                    $isIn      = $row !== null;
                    $hasVoted  = $isIn && (int) $row['has_voted'] === 1;
                  ?>
                  <label class="check">
                    <input type="checkbox" name="eligible[<?= e((string) $election['id']) ?>]" value="1"
                           <?= $isIn ? 'checked' : '' ?>
                           <?= $hasVoted ? 'disabled' : '' ?>>
                    <span class="check__text">
                      <strong><?= e($election['title']) ?></strong>
                      <span class="text-muted text-sm">
                        &middot; <?= e(ucfirst(election_effective_status($election))) ?>
                        <?php if ($hasVoted): ?>
                          &middot; <span class="badge badge--teal"><?= sv_icon('check', 12) ?>Already voted</span>
                        <?php endif; ?>
                      </span>
                      <?php if ($hasVoted): ?>
                        <span class="help">
                          Registration locked: this voter submitted a ballot on
                          <?= e(fmt_datetime((string) $row['voted_at'])) ?>.
                        </span>
                      <?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endif; ?>

          <div class="form-actions">
            <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="Saving…">
              <?= sv_icon('check', 16, 'btn__icon') ?><span>Save eligibility</span>
            </button>
            <a class="btn btn--secondary" href="<?= e(url('admin/voters.php')) ?>">Cancel</a>
          </div>
        </form>
      </div>

      <?php if ($elections !== []): ?>
        <div class="card__footer">
          <form method="post" action="<?= e(url('admin/voters.php')) ?>"
                data-confirm="Register every active voter for the selected election?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="register_all">
            <input type="hidden" name="voter_id" value="<?= e((string) $voter['id']) ?>">
            <div class="row">
              <label class="label" for="bulk_election">Bulk register all active voters</label>
              <select class="select" id="bulk_election" name="election_id" required>
                <?php foreach ($elections as $election): ?>
                  <option value="<?= e((string) $election['id']) ?>"><?= e($election['title']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn--secondary btn--sm" type="submit">
                <?= sv_icon('users', 16, 'btn__icon') ?><span>Register everyone</span>
              </button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </section>

    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
}

/* =====================================================================
 | Form view
 * =================================================================== */

$isForm = ($viewAction === 'create' || $viewAction === 'edit');

if ($isForm) {
    $submitted = is_post() && (string) input('action', '') === 'save' && $errors !== [];

    if (!$submitted && $viewAction === 'edit') {
        $editing = sv_find_voter($pdo, int_input('id', 0));

        if ($editing === null) {
            sv_error_page(
                404,
                'Voter not found',
                'That voter account does not exist, or it was deleted by another administrator.',
                [['label' => 'Back to voters', 'href' => url('admin/voters.php')]]
            );
        }

        $form = [
            'id'         => (int) $editing['id'],
            'username'   => (string) $editing['username'],
            'email'      => (string) $editing['email'],
            'first_name' => (string) $editing['first_name'],
            'last_name'  => (string) $editing['last_name'],
            'status'     => (string) $editing['status'],
        ];
    }
}

$isNew = (int) $form['id'] === 0;

if ($isForm) {
    $pageTitle    = $isNew ? 'New voter account' : 'Edit voter account';
    $pageSubtitle = $isNew
        ? 'Create the account, then register the voter for an election.'
        : 'Update the details, change the status, or issue a new password.';
    $activeNav    = 'voters.php';
    $pageActions  = '<a class="btn btn--secondary btn--sm" href="' . e(url('admin/voters.php')) . '">'
        . sv_icon('arrow-left', 16, 'btn__icon') . '<span>Back to voters</span></a>';
    $breadcrumb   = [
        ['label' => 'Voters', 'href' => 'admin/voters.php'],
        ['label' => $isNew ? 'New' : 'Edit'],
    ];

    require __DIR__ . '/../includes/header.php';
    ?>

    <?php if (isset($errors['form'])): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
        <p class="alert__text"><?= e((string) $errors['form']) ?></p>
      </div>
    <?php elseif ($errors !== []): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span>
        <p class="alert__text">The account was not saved. Please correct the highlighted fields.</p>
      </div>
    <?php endif; ?>

    <section class="card">
      <div class="card__header">
        <div class="card__headings">
          <h2 class="card__title"><?= e($isNew ? 'Account details' : 'Edit account') ?></h2>
          <p class="card__subtitle">Required fields are marked with an asterisk.</p>
        </div>
      </div>

      <div class="card__body">
        <form class="form" method="post" action="<?= e(url('admin/voters.php')) ?>" novalidate autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">

          <div class="form-grid">
            <div class="form-field">
              <label class="label" for="first_name">
                First name <span class="label__required" aria-hidden="true">*</span>
              </label>
              <input class="input<?= isset($errors['first_name']) ? ' is-invalid' : '' ?>"
                     type="text" id="first_name" name="first_name" maxlength="80"
                     value="<?= e($form['first_name']) ?>" required aria-required="true"
                     <?= isset($errors['first_name']) ? 'aria-describedby="first-error" aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['first_name'])): ?>
                <p class="field-error" id="first-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['first_name']) ?></span>
                </p>
              <?php endif; ?>
            </div>

            <div class="form-field">
              <label class="label" for="last_name">
                Last name <span class="label__required" aria-hidden="true">*</span>
              </label>
              <input class="input<?= isset($errors['last_name']) ? ' is-invalid' : '' ?>"
                     type="text" id="last_name" name="last_name" maxlength="80"
                     value="<?= e($form['last_name']) ?>" required aria-required="true"
                     <?= isset($errors['last_name']) ? 'aria-describedby="last-error" aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['last_name'])): ?>
                <p class="field-error" id="last-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['last_name']) ?></span>
                </p>
              <?php endif; ?>
            </div>

            <div class="form-field">
              <label class="label" for="username">
                Username <span class="label__required" aria-hidden="true">*</span>
              </label>
              <input class="input<?= isset($errors['username']) ? ' is-invalid' : '' ?>"
                     type="text" id="username" name="username" maxlength="50"
                     value="<?= e($form['username']) ?>" required aria-required="true"
                     spellcheck="false" autocapitalize="none"
                     <?= isset($errors['username']) ? 'aria-describedby="username-error" aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['username'])): ?>
                <p class="field-error" id="username-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['username']) ?></span>
                </p>
              <?php else: ?>
                <p class="help">Letters, numbers, dot, underscore or hyphen. Stored in lower case.</p>
              <?php endif; ?>
            </div>

            <div class="form-field">
              <label class="label" for="email">
                Email address <span class="label__required" aria-hidden="true">*</span>
              </label>
              <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                     type="email" id="email" name="email" maxlength="150"
                     value="<?= e($form['email']) ?>" required aria-required="true"
                     spellcheck="false" autocapitalize="none"
                     <?= isset($errors['email']) ? 'aria-describedby="email-error" aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['email'])): ?>
                <p class="field-error" id="email-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['email']) ?></span>
                </p>
              <?php endif; ?>
            </div>

            <div class="form-field">
              <label class="label" for="status">Account status</label>
              <select class="select<?= isset($errors['status']) ? ' is-invalid' : '' ?>" id="status" name="status">
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?= e($value) ?>" <?= $form['status'] === $value ? 'selected' : '' ?>>
                    <?= e($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['status'])): ?>
                <p class="field-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['status']) ?></span></p>
              <?php else: ?>
                <p class="help">Only active accounts can sign in. Blocked accounts keep their history.</p>
              <?php endif; ?>
            </div>

            <div class="form-field">
              <span class="label">Role & Voter Passcode</span>
              <div class="panel" style="display:flex; flex-direction:column; gap:6px;">
                <div style="display:flex; align-items:center; gap:8px;">
                  <span class="badge badge--neutral"><?= sv_icon('user', 14) ?> Voter</span>
                  <?php if (!$isNew): ?>
                    <span class="voter-code-badge"><?= e((string) ($editing['voter_code'] ?? '—')) ?></span>
                    <?php if (!empty($editing['google_id'])): ?>
                      <span class="badge badge--primary"><?= sv_icon('google', 12) ?> Google Linked</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge badge--primary">Auto-generated Voter Code</span>
                  <?php endif; ?>
                </div>
                <span class="help">
                  <?= $isNew
                      ? 'A unique personal Voter Code (e.g. VC-2026-XXXX) will be automatically generated upon creation.'
                      : 'Personal Voter Code used by this student to sign in and cast ballots.' ?>
                </span>
              </div>
            </div>

            <div class="form-field">
              <label class="label" for="password">
                <?= e($isNew ? 'Password' : 'New password') ?>
                <?php if ($isNew): ?>
                  <span class="label__required" aria-hidden="true">*</span>
                <?php endif; ?>
              </label>
              <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                     type="password" id="password" name="password" autocomplete="new-password"
                     <?= $isNew ? 'required aria-required="true"' : '' ?>
                     <?= isset($errors['password']) ? 'aria-describedby="password-error" aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['password'])): ?>
                <p class="field-error" id="password-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['password']) ?></span>
                </p>
              <?php else: ?>
                <p class="help">
                  <?= e($isNew
                      ? 'At least 8 characters, with a letter and a number.'
                      : 'Leave blank to keep the current password.') ?>
                  Stored as a bcrypt hash, never as readable text.
                </p>
              <?php endif; ?>
            </div>

            <div class="form-field">
              <label class="label" for="password_confirm">Confirm password</label>
              <input class="input<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                     type="password" id="password_confirm" name="password_confirm" autocomplete="new-password"
                     <?= isset($errors['password_confirm']) ? 'aria-describedby="confirm-error" aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['password_confirm'])): ?>
                <p class="field-error" id="confirm-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['password_confirm']) ?></span>
                </p>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="Saving…">
              <?= sv_icon('check', 16, 'btn__icon') ?>
              <span><?= e($isNew ? 'Create account' : 'Save changes') ?></span>
            </button>
            <a class="btn btn--secondary" href="<?= e(url('admin/voters.php')) ?>">Cancel</a>

            <?php if (!$isNew): ?>
              <span class="form-actions__spacer"></span>
              <a class="btn btn--secondary btn--sm"
                 href="<?= e(url('admin/voters.php?action=eligibility&id=' . (int) $form['id'])) ?>">
                <?= sv_icon('vote', 16, 'btn__icon') ?><span>Election eligibility</span>
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </section>

    <?php if (!$isNew): ?>
      <section class="card">
        <div class="card__header">
          <div class="card__headings">
            <h2 class="card__title">Delete this account</h2>
            <p class="card__subtitle">
              Blocked accounts are usually the better choice: they keep the audit history intact.
            </p>
          </div>
        </div>
        <div class="card__body">
          <form method="post" action="<?= e(url('admin/voters.php')) ?>"
                data-confirm="Delete the account “<?= e($form['username']) ?>”? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">
            <button class="btn btn--danger btn--sm" type="submit">
              <?= sv_icon('trash', 16, 'btn__icon') ?><span>Delete account</span>
            </button>
          </form>
          <p class="text-sm text-muted">
            An account that has already submitted a ballot cannot be deleted, because deleting it
            would take the recorded ballot with it.
          </p>
        </div>
      </section>
    <?php endif; ?>

    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
}

/* =====================================================================
 | List view
 * =================================================================== */

$search       = (string) input('q', '');
$statusFilter = (string) input('status', '');

$where  = ["u.role = 'voter'"];
$params = [];

if ($search !== '') {
    // Distinct placeholders: a named parameter must not be reused in one
    // statement when prepared statements are not emulated.
    $where[]           = '(u.username LIKE :q_username OR u.email LIKE :q_email OR u.first_name LIKE :q_first OR u.last_name LIKE :q_last)';
    $params[':q_username'] = '%' . $search . '%';
    $params[':q_email']    = '%' . $search . '%';
    $params[':q_first']    = '%' . $search . '%';
    $params[':q_last']     = '%' . $search . '%';
}
if ($statusFilter !== '' && isset($statusOptions[$statusFilter])) {
    $where[]           = 'u.status = :status';
    $params[':status'] = $statusFilter;
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM users u' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pageInfo = paginate($total, 15);

$listStmt = $pdo->prepare(
    'SELECT u.*,
            (SELECT COUNT(*) FROM voter_eligibility ve WHERE ve.user_id = u.id) AS eligible_count,
            (SELECT COUNT(*) FROM voter_eligibility ve WHERE ve.user_id = u.id AND ve.has_voted = 1) AS ballot_count
       FROM users u'
    . $whereSql
    . ' ORDER BY u.last_name ASC, u.first_name ASC'
    . sprintf(' LIMIT %d OFFSET %d', $pageInfo['per_page'], $pageInfo['offset'])
);
$listStmt->execute($params);
$voters = $listStmt->fetchAll();

$pageTitle    = 'Voters';
$pageSubtitle = 'Accounts that may sign in, and which elections each one may vote in.';
$activeNav    = 'voters.php';
$pageActions  = '<a class="btn btn--accent btn--sm" href="' . e(url('admin/voters.php?action=create')) . '">'
    . sv_icon('plus', 16, 'btn__icon') . '<span>New voter</span></a>';
$breadcrumb   = [['label' => 'Voters', 'href' => 'admin/voters.php']];

require __DIR__ . '/../includes/header.php';
?>

<section class="card">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title">Voter accounts</h2>
      <p class="card__subtitle"><?= e((string) $total) ?> account<?= $total === 1 ? '' : 's' ?> found</p>
    </div>
  </div>

  <form class="table-toolbar" method="get" action="<?= e(url('admin/voters.php')) ?>" role="search">
    <div class="search-field">
      <span class="search-field__icon" aria-hidden="true"><?= sv_icon('search', 16) ?></span>
      <label class="visually-hidden" for="q">Search voters</label>
      <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>"
             placeholder="Search by name, username or email">
    </div>

    <div class="row">
      <label class="visually-hidden" for="status">Filter by status</label>
      <select class="select" id="status" name="status" data-auto-submit>
        <option value="">All statuses</option>
        <?php foreach ($statusOptions as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>>
            <?= e(ucfirst($value)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--secondary btn--sm" type="submit"><?= sv_icon('filter', 16, 'btn__icon') ?><span>Apply</span></button>
      <?php if ($search !== '' || $statusFilter !== ''): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/voters.php')) ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($voters === []): ?>
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('users', 26) ?></span>
      <p class="empty-state__title">
        <?= e($search !== '' || $statusFilter !== '' ? 'No voters match those filters' : 'No voter accounts yet') ?>
      </p>
      <p class="empty-state__text">
        Create an account for each eligible voter, then register them for the election.
      </p>
      <a class="btn btn--accent" href="<?= e(url('admin/voters.php?action=create')) ?>">
        <?= sv_icon('plus', 16, 'btn__icon') ?><span>Create a voter account</span>
      </a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden">Voter accounts with status, last sign-in and election registrations</caption>
        <thead>
          <tr>
            <th scope="col">Voter</th>
            <th scope="col">Voter Code</th>
            <th scope="col">Email & Sign-In</th>
            <th scope="col">Status</th>
            <th scope="col">Elections</th>
            <th scope="col">Last sign-in</th>
            <th scope="col" class="table__actions-col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($voters as $voter): ?>
            <tr>
              <td>
                <div class="row">
                  <span class="avatar avatar--sm" aria-hidden="true">
                    <?= e(initials($voter['first_name'], $voter['last_name'])) ?>
                  </span>
                  <span>
                    <span class="table__cell-strong"><?= e(user_full_name($voter)) ?></span>
                    <span class="table__cell-sub mono">@<?= e($voter['username']) ?></span>
                  </span>
                </div>
              </td>
              <td>
                <span class="voter-code-badge"><?= e((string) ($voter['voter_code'] ?? '—')) ?></span>
              </td>
              <td>
                <span><?= e($voter['email']) ?></span>
                <?php if (!empty($voter['google_id'])): ?>
                  <span class="badge badge--primary" style="margin-top:2px; display:inline-flex; font-size:0.7rem; padding:2px 6px;">
                    <?= sv_icon('google', 10) ?> Google Linked
                  </span>
                <?php endif; ?>
              </td>
              <td><?= account_badge((string) $voter['status']) ?></td>
              <td>
                <?= e((string) $voter['eligible_count']) ?> registered
                <span class="table__cell-sub">
                  <?= e((string) $voter['ballot_count']) ?> ballot<?= (int) $voter['ballot_count'] === 1 ? '' : 's' ?> submitted
                </span>
              </td>
              <td>
                <?= e($voter['last_login'] !== null ? relative_time((string) $voter['last_login']) : 'Never') ?>
              </td>
              <td class="table__actions-col">
                <div class="table__actions">
                  <a class="btn btn--secondary btn--sm"
                     href="<?= e(url('admin/voters.php?action=edit&id=' . (int) $voter['id'])) ?>">
                    <?= sv_icon('edit', 14, 'btn__icon') ?><span>Edit</span>
                  </a>
                  <a class="btn btn--secondary btn--sm"
                     href="<?= e(url('admin/voters.php?action=eligibility&id=' . (int) $voter['id'])) ?>">
                    <?= sv_icon('vote', 14, 'btn__icon') ?><span>Elections</span>
                  </a>

                  <?php if ($voter['status'] === 'active'): ?>
                    <form method="post" action="<?= e(url('admin/voters.php')) ?>"
                          data-confirm="Block “<?= e($voter['username']) ?>”? They will be signed out and cannot sign in again until reactivated.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="set_status">
                      <input type="hidden" name="id" value="<?= e((string) $voter['id']) ?>">
                      <input type="hidden" name="target_status" value="blocked">
                      <button class="btn btn--danger btn--sm" type="submit">
                        <?= sv_icon('ban', 14, 'btn__icon') ?><span>Block</span>
                      </button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="<?= e(url('admin/voters.php')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="set_status">
                      <input type="hidden" name="id" value="<?= e((string) $voter['id']) ?>">
                      <input type="hidden" name="target_status" value="active">
                      <button class="btn btn--success btn--sm" type="submit">
                        <?= sv_icon('check', 14, 'btn__icon') ?><span>Activate</span>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?= pagination_links($pageInfo, 'voters') ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
