<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/candidates.php
 *
 * Candidate management, including the optional profile photo upload.
 *
 * Integrity rules enforced on the server (never trusting the form):
 *   - the chosen position must belong to the chosen election. The FK only
 *     proves the position exists; this check proves the relationship
 *   - a new candidate cannot be added to an election that is already open,
 *     because that would change the ballot for later voters
 *   - a candidate who already has recorded votes cannot be deleted
 *   - two candidates with the same name cannot sit in the same position
 *
 * Upload safety (the classic "unrestricted file upload" defence):
 *   - the real image type is detected with getimagesize(), not the filename
 *   - only JPEG, PNG and WebP are accepted
 *   - the stored name is random, so a caller can never choose a path
 *   - size is capped at 2 MB and the file must be a genuine upload
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();
require_once __DIR__.'/../includes/engagement.php'; sv_engagement_db(); sv_seed_change_baselines();

$errors = [];
$form   = [
    'id'          => 0,
    'election_id' => int_input('election_id', 0),
    'position_id' => int_input('position_id', 0),
    'first_name'  => '',
    'last_name'   => '',
    'party'       => '',
    'biography'   => '',
    'platform'    => '',
    'status'      => 'active',
    'photo'       => null,
];

$statusOptions = [
    'active'       => sv_t('Active — appears on the ballot'),
    'inactive'     => sv_t('Inactive — hidden from the ballot'),
    'disqualified' => sv_t('Disqualified — kept for the record, hidden from the ballot'),
];

/**
 * Validate and store an uploaded photo.
 *
 * @param array       $file  one entry from $_FILES
 * @param string|null $error filled with a message when something is wrong
 * @return string|null the stored file name, or null when there is no photo
 */
function sv_store_candidate_photo(array $file, ?string &$error): ?string
{
    $error = null;

    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        $error = sv_t('The photo could not be uploaded (upload error code %d).', (int) $file['error']);

        return null;
    }
    if (!is_uploaded_file((string) $file['tmp_name'])) {
        $error = sv_t('That upload did not come from a form on this site.');

        return null;
    }
    if ((int) $file['size'] > 2 * 1024 * 1024) {
        $error = sv_t('The photo must be 2 MB or smaller.');

        return null;
    }

    // Trust the file contents, never the supplied name or extension.
    $info = @getimagesize((string) $file['tmp_name']);
    if ($info === false) {
        $error = sv_t('That file is not a readable image.');

        return null;
    }

    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!isset($allowed[(int) $info[2]])) {
        $error = sv_t('Use a JPEG, PNG or WebP image.');

        return null;
    }

    $directory = APP_ROOT . '/assets/images/candidates';
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        $error = sv_t('The photo folder is missing and could not be created.');

        return null;
    }
    if (!is_writable($directory)) {
        $error = sv_t('The photo folder is not writable by the web server.');

        return null;
    }

    $name = bin2hex(random_bytes(8)) . '.' . $allowed[(int) $info[2]];

    if (!@move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $name)) {
        $error = sv_t('The photo could not be saved on the server.');

        return null;
    }

    return $name;
}

/** Remove a previously stored photo, refusing to touch anything outside the folder. */
function sv_delete_candidate_photo(?string $name): void
{
    if ($name === null || $name === '') {
        return;
    }

    $safe = basename($name);
    $path = APP_ROOT . '/assets/images/candidates/' . $safe;

    if (is_file($path)) {
        @unlink($path);
    }
}

/* =====================================================================
 | Reference data
 * =================================================================== */

/** Elections for the selects. */
$elections = $pdo->query('SELECT id, title, status FROM elections ORDER BY start_datetime DESC')->fetchAll();

/** Every position with its election, used to filter the second select. */
$allPositions = $pdo->query(
    'SELECT p.id, p.election_id, p.name, p.display_order, e.title AS election_title
       FROM positions p
       JOIN elections e ON e.id = p.election_id
      ORDER BY e.start_datetime DESC, p.display_order ASC, p.name ASC'
)->fetchAll();

/** position id => election id, for the cross-check. */
$positionElection = [];
foreach ($allPositions as $position) {
    $positionElection[(int) $position['id']] = (int) $position['election_id'];
}

/* =====================================================================
 | POST handlers
 * =================================================================== */

if (is_post()) {
    require_csrf();
    $postAction = (string) input('action', '');

    if ($postAction === 'save') {
        $form = [
            'id'          => int_input('id', 0),
            'election_id' => int_input('election_id', 0),
            'position_id' => int_input('position_id', 0),
            'first_name'  => (string) input('first_name', ''),
            'last_name'   => (string) input('last_name', ''),
            'party'       => (string) input('party', ''),
            'biography'   => (string) input('biography', ''),
            'platform'    => (string) input('platform', ''),
            'status'      => (string) input('status', 'active'),
            'photo'       => null,
        ];

        // Keep the existing photo unless a new one is uploaded or removal is asked for.
        $existingPhoto = null;
        if ($form['id'] > 0) {
            $photoStmt = $pdo->prepare('SELECT photo FROM candidates WHERE id = ? LIMIT 1');
            $photoStmt->execute([$form['id']]);
            $existingPhoto = $photoStmt->fetchColumn() ?: null;
        }
        // Reflected back into $form so a failed save still shows the current
        // photo and its "remove" checkbox.
        $form['photo'] = $existingPhoto;

        $firstName = trim($form['first_name']);
        $lastName  = trim($form['last_name']);

        // --- The election / position relationship, checked properly -------
        $electionStmt = $pdo->prepare('SELECT id, title, status FROM elections WHERE id = ? LIMIT 1');
        $electionStmt->execute([$form['election_id']]);
        $election = $electionStmt->fetch();

        if (!$election) {
            $errors['election_id'] = sv_t('Choose an election.');
        }

        $positionStmt = $pdo->prepare('SELECT id, name, election_id FROM positions WHERE id = ? LIMIT 1');
        $positionStmt->execute([$form['position_id']]);
        $position = $positionStmt->fetch();

        if (!$position) {
            $errors['position_id'] = sv_t('Choose a position.');
        } elseif ($election && (int) $position['election_id'] !== (int) $election['id']) {
            // The real integrity check: a position belongs to exactly one election.
            $errors['position_id'] = sv_t('That position belongs to a different election.');
        }

        if (mb_strlen($firstName) < 1 || mb_strlen($firstName) > 80) {
            $errors['first_name'] = sv_t('Enter the first name (1 to 80 characters).');
        }
        if (mb_strlen($lastName) < 1 || mb_strlen($lastName) > 80) {
            $errors['last_name'] = sv_t('Enter the last name (1 to 80 characters).');
        }
        if (mb_strlen($form['party']) > 100) {
            $errors['party'] = sv_t('Keep the party name under 100 characters.');
        }
        if (mb_strlen($form['biography']) > 3000) {
            $errors['biography'] = sv_t('Keep the biography under 3000 characters.');
        }
        if (mb_strlen($form['platform']) > 3000) {
            $errors['platform'] = sv_t('Keep the platform under 3000 characters.');
        }
        if (!isset($statusOptions[$form['status']])) {
            $errors['status'] = sv_t('Choose a valid candidate status.');
        }

        // Adding someone new to a running election would change the ballot
        // for everyone who has not voted yet.
        if ($election && $form['id'] === 0 && $election['status'] === 'open') {
            $errors['form'] = sv_t('This election is already open, so new candidates cannot be added. Close it first, or add the candidate to a draft election.');
        }

        // Two identical names in one position is almost always a mistake.
        if ($errors === [] && $position) {
            $dupe = $pdo->prepare(
                'SELECT id FROM candidates
                  WHERE position_id = ? AND first_name = ? AND last_name = ? AND id <> ? LIMIT 1'
            );
            $dupe->execute([$form['position_id'], $firstName, $lastName, $form['id']]);
            if ($dupe->fetchColumn() !== false) {
                $errors['form'] = sv_t('Another candidate with that exact name is already in this position.');
            }
        }

        // --- Photo --------------------------------------------------------
        $newPhoto = null;
        $removePhoto = checkbox_input('remove_photo');

        if (!empty($_FILES['photo']['name'])) {
            $photoError = null;
            $newPhoto   = sv_store_candidate_photo($_FILES['photo'], $photoError);
            if ($photoError !== null) {
                $errors['photo'] = $photoError;
            }
        }

        if ($errors === []) {
            $photoToStore = $existingPhoto;

            if ($newPhoto !== null) {
                $photoToStore = $newPhoto;
            } elseif ($removePhoto) {
                $photoToStore = null;
            }

            if ($form['id'] > 0) {
                $update = $pdo->prepare(
                    'UPDATE candidates
                        SET election_id = :election_id, position_id = :position_id,
                            first_name = :first_name, last_name = :last_name, party = :party,
                            biography = :biography, platform = :platform, status = :status,
                            photo = :photo
                      WHERE id = :id'
                );
                $update->execute([
                    ':election_id' => $form['election_id'],
                    ':position_id' => $form['position_id'],
                    ':first_name'  => $firstName,
                    ':last_name'   => $lastName,
                    ':party'       => $form['party'],
                    ':biography'   => $form['biography'],
                    ':platform'    => $form['platform'],
                    ':status'      => $form['status'],
                    ':photo'       => $photoToStore,
                    ':id'          => $form['id'],
                ]);

                // Only remove the old file once the row no longer points at it.
                if ($existingPhoto !== $photoToStore) {
                    sv_delete_candidate_photo($existingPhoto);
                }

                audit_log('candidate_updated', 'Updated candidate #' . $form['id'] . ' "'
                    . $firstName . ' ' . $lastName . '" in position #' . $form['position_id']
                    . ' (status ' . $form['status'] . ').');

                flash_success(sv_t('Candidate "%s" was updated.', $firstName . ' ' . $lastName));
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO candidates
                        (election_id, position_id, first_name, last_name, party, biography, platform, status, photo)
                     VALUES (:election_id, :position_id, :first_name, :last_name, :party, :biography, :platform, :status, :photo)'
                );
                $insert->execute([
                    ':election_id' => $form['election_id'],
                    ':position_id' => $form['position_id'],
                    ':first_name'  => $firstName,
                    ':last_name'   => $lastName,
                    ':party'       => $form['party'],
                    ':biography'   => $form['biography'],
                    ':platform'    => $form['platform'],
                    ':status'      => $form['status'],
                    ':photo'       => $photoToStore,
                ]);
                $newId = (int) $pdo->lastInsertId();

                audit_log('candidate_created', 'Created candidate #' . $newId . ' "'
                    . $firstName . ' ' . $lastName . '" in position #' . $form['position_id']
                    . ' of election #' . $form['election_id'] . '.');

                flash_success(sv_t('Candidate "%s" was added.', $firstName . ' ' . $lastName));
            }

            sv_record_election_change((int)$form['election_id']);
            redirect('admin/candidates.php?election_id=' . $form['election_id']);
        }

        // A failed save must not leave the uploaded file behind.
        if ($newPhoto !== null) {
            sv_delete_candidate_photo($newPhoto);
        }
    }

    if ($postAction === 'delete') {
        $id = int_input('id', 0);

        $stmt = $pdo->prepare(
            'SELECT c.id, c.first_name, c.last_name, c.election_id, c.photo,
                    (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id) AS vote_count
               FROM candidates c WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $candidate = $stmt->fetch();

        if (!$candidate) {
            flash_error(sv_t('That candidate no longer exists.'));
            redirect('admin/candidates.php');
        }

        // Votes reference candidates with ON DELETE RESTRICT: history is kept.
        if ((int) $candidate['vote_count'] > 0) {
            flash_error(sv_t(
                '"%s" already has %d recorded vote(s) and cannot be deleted. Set the status to "disqualified" or "inactive" instead.',
                $candidate['first_name'] . ' ' . $candidate['last_name'],
                (int) $candidate['vote_count']
            ));
            redirect('admin/candidates.php?election_id=' . (int) $candidate['election_id']);
        }

        $delete = $pdo->prepare('DELETE FROM candidates WHERE id = ?');
        $delete->execute([$id]);

        sv_delete_candidate_photo($candidate['photo'] !== null ? (string) $candidate['photo'] : null);

        audit_log('candidate_deleted', 'Deleted candidate #' . $id . ' "'
            . $candidate['first_name'] . ' ' . $candidate['last_name'] . '".');

        sv_record_election_change((int)$candidate['election_id']);
        flash_success(sv_t('Candidate was deleted.'));
        redirect('admin/candidates.php?election_id=' . (int) $candidate['election_id']);
    }
}

/* =====================================================================
 | Which view?
 * =================================================================== */

$viewAction = (string) input('action', 'list');

// A failed "save" POST must come back as the form, not the list, so the
// submitted values are not thrown away.
if (is_post() && $viewAction === 'save') {
    $viewAction = ((int) ($form['id'] ?? 0) > 0) ? 'edit' : 'create';
}

$filterElection = int_input('election_id', 0);
$filterPosition = int_input('position_id', 0);
$isForm     = ($viewAction === 'create' || $viewAction === 'edit');

if ($isForm) {
    $submitted = is_post() && (string) input('action', '') === 'save' && $errors !== [];

    if (!$submitted && $viewAction === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM candidates WHERE id = ? LIMIT 1');
        $stmt->execute([int_input('id', 0)]);
        $editing = $stmt->fetch();

        if (!$editing) {
            sv_error_page(
                404,
                sv_t('Candidate not found'),
                sv_t('That candidate does not exist, or it was deleted by another administrator.'),
                [['label' => sv_t('Back to candidates'), 'href' => url('admin/candidates.php')]]
            );
        }

        $form = [
            'id'          => (int) $editing['id'],
            'election_id' => (int) $editing['election_id'],
            'position_id' => (int) $editing['position_id'],
            'first_name'  => (string) $editing['first_name'],
            'last_name'   => (string) $editing['last_name'],
            'party'       => (string) $editing['party'],
            'biography'   => (string) $editing['biography'],
            'platform'    => (string) $editing['platform'],
            'status'      => (string) $editing['status'],
            'photo'       => $editing['photo'] !== null ? (string) $editing['photo'] : null,
        ];
        $filterElection = (int) $editing['election_id'];
    } elseif ($viewAction === 'create') {
        if ($form['election_id'] === 0 && $elections !== []) {
            $form['election_id'] = (int) $elections[0]['id'];
        }
        // Position may have been given directly in the query string.
        if ($form['position_id'] === 0 && $filterPosition > 0) {
            $form['position_id'] = $filterPosition;
            if (isset($positionElection[$filterPosition])) {
                $form['election_id'] = $positionElection[$filterPosition];
            }
        }
    }
}

/* =====================================================================
 | Header data
 * =================================================================== */

$isNew = (int) $form['id'] === 0;

if ($isForm) {
    $pageTitle   = $isNew ? sv_t('New candidate') : sv_t('Edit candidate');
    $pageActions = '<a class="btn btn--secondary btn--sm" href="'
        . e(url('admin/candidates.php' . ($filterElection > 0 ? '?election_id=' . $filterElection : ''))) . '">'
        . sv_icon('arrow-left', 16, 'btn__icon') . '<span>' . e(sv_t('Back to candidates')) . '</span></a>';
} else {
    $pageTitle   = sv_t('Candidates');
    $createHref  = 'admin/candidates.php?action=create'
        . ($filterElection > 0 ? '&election_id=' . $filterElection : '')
        . ($filterPosition > 0 ? '&position_id=' . $filterPosition : '');
    $pageActions = '<a class="btn btn--accent btn--sm" href="' . e(url($createHref)) . '">'
        . sv_icon('plus', 16, 'btn__icon') . '<span>' . e(sv_t('New candidate')) . '</span></a>';
}

$activeNav  = 'candidates.php';
$breadcrumb = $isForm
    ? [['label' => sv_t('Candidates'), 'href' => 'admin/candidates.php'], ['label' => $isNew ? sv_t('New') : sv_t('Edit')]]
    : [['label' => sv_t('Candidates'), 'href' => 'admin/candidates.php']];

require __DIR__ . '/../includes/header.php';

/* =====================================================================
 | View: form
 * =================================================================== */
if ($isForm):
    ?>
    <?php if (isset($errors['form'])): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
        <p class="alert__text"><?= e((string) $errors['form']) ?></p>
      </div>
    <?php elseif ($errors !== []): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span>
        <p class="alert__text"><?= e(sv_t('The candidate was not saved. Please correct the highlighted fields.')) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($elections === [] || $allPositions === []): ?>
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('positions', 26) ?></span>
        <p class="empty-state__title"><?= e(sv_t('Add a position first')) ?></p>
        <p class="empty-state__text">
          <?= e(sv_t('A candidate stands for one position inside one election, so at least one position must exist before candidates can be added.')) ?>
        </p>
        <a class="btn btn--accent" href="<?= e(url('admin/positions.php?action=create')) ?>">
          <?= sv_icon('plus', 16, 'btn__icon') ?><span><?= e(sv_t('New position')) ?></span>
        </a>
      </div>
    <?php else: ?>
      <section class="card">
        <div class="card__header">
          <div class="card__headings">
            <h2 class="card__title"><?= e($isNew ? sv_t('Candidate details') : sv_t('Edit candidate')) ?></h2>
            <p class="card__subtitle"><?= e(sv_t('Required fields are marked with an asterisk.')) ?></p>
          </div>
        </div>

        <div class="card__body">
          <form class="form" method="post" enctype="multipart/form-data"
                action="<?= e(post_url('admin/candidates.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">

            <div class="form-grid">
              <div class="form-field">
                <label class="label" for="election_id">
                  <?= e(sv_t('Election')) ?> <span class="label__required" aria-hidden="true">*</span>
                </label>
                <select class="select<?= isset($errors['election_id']) ? ' is-invalid' : '' ?>"
                        id="election_id" name="election_id" required aria-required="true">
                  <option value=""><?= e(sv_t('Select an election…')) ?></option>
                  <?php foreach ($elections as $election): ?>
                    <option value="<?= e((string) $election['id']) ?>"
                      <?= (int) $form['election_id'] === (int) $election['id'] ? 'selected' : '' ?>>
                      <?= e($election['title']) ?> (<?= e(sv_t(ucfirst((string) $election['status']))) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['election_id'])): ?>
                  <p class="field-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['election_id']) ?></span>
                  </p>
                <?php endif; ?>
              </div>

              <div class="form-field">
                <label class="label" for="position_id">
                  <?= e(sv_t('Position')) ?> <span class="label__required" aria-hidden="true">*</span>
                </label>
                <select class="select<?= isset($errors['position_id']) ? ' is-invalid' : '' ?>"
                        id="position_id" name="position_id" required aria-required="true">
                  <option value=""><?= e(sv_t('Select a position…')) ?></option>
                  <?php foreach ($allPositions as $position): ?>
                    <option value="<?= e((string) $position['id']) ?>"
                      <?= (int) $form['position_id'] === (int) $position['id'] ? 'selected' : '' ?>>
                      <?= e($position['election_title']) ?> — <?= e($position['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['position_id'])): ?>
                  <p class="field-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['position_id']) ?></span>
                  </p>
                <?php else: ?>
                  <p class="help"><?= e(sv_t('The server verifies the position belongs to the chosen election.')) ?></p>
                <?php endif; ?>
              </div>

              <div class="form-field">
                <label class="label" for="first_name">
                  <?= e(sv_t('First name')) ?> <span class="label__required" aria-hidden="true">*</span>
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
                  <?= e(sv_t('Last name')) ?> <span class="label__required" aria-hidden="true">*</span>
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
                <label class="label" for="party"><?= e(sv_t('Party or affiliation')) ?></label>
                <input class="input<?= isset($errors['party']) ? ' is-invalid' : '' ?>"
                        type="text" id="party" name="party" maxlength="100"
                        value="<?= e($form['party']) ?>" placeholder="<?= e(sv_t('e.g. Independent')) ?>"
                        <?= isset($errors['party']) ? 'aria-describedby="party-error" aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['party'])): ?>
                  <p class="field-error" id="party-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['party']) ?></span>
                  </p>
                <?php endif; ?>
              </div>

              <div class="form-field">
                <label class="label" for="status"><?= e(sv_t('Status')) ?></label>
                <select class="select<?= isset($errors['status']) ? ' is-invalid' : '' ?>"
                        id="status" name="status">
                  <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $form['status'] === $value ? 'selected' : '' ?>>
                      <?= e($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['status'])): ?>
                  <p class="field-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['status']) ?></span></p>
                <?php else: ?>
                  <p class="help"><?= e(sv_t('Only active candidates appear on the ballot and in the tally.')) ?></p>
                <?php endif; ?>
              </div>

              <div class="form-field form-field--full">
                <label class="label" for="biography"><?= e(sv_t('Biography')) ?></label>
                <textarea class="textarea<?= isset($errors['biography']) ? ' is-invalid' : '' ?>"
                          id="biography" name="biography" maxlength="3000"
                          <?= isset($errors['biography']) ? 'aria-describedby="bio-error" aria-invalid="true"' : '' ?>><?= e($form['biography']) ?></textarea>
                <?php if (isset($errors['biography'])): ?>
                  <p class="field-error" id="bio-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['biography']) ?></span>
                  </p>
                <?php else: ?>
                  <p class="help"><?= e(sv_t('Two or three sentences. Shown under the candidate\'s name on the ballot.')) ?></p>
                <?php endif; ?>
              </div>

              <div class="form-field form-field--full">
                <label class="label" for="platform"><?= e(sv_t('Platform')) ?></label>
                <textarea class="textarea<?= isset($errors['platform']) ? ' is-invalid' : '' ?>"
                          id="platform" name="platform" maxlength="3000"
                          <?= isset($errors['platform']) ? 'aria-describedby="platform-error" aria-invalid="true"' : '' ?>><?= e($form['platform']) ?></textarea>
                <?php if (isset($errors['platform'])): ?>
                  <p class="field-error" id="platform-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['platform']) ?></span>
                  </p>
                <?php endif; ?>
              </div>

              <div class="form-field form-field--full">
                <label class="label" for="photo"><?= e(sv_t('Profile photo')) ?></label>
                <input class="input<?= isset($errors['photo']) ? ' is-invalid' : '' ?>"
                        type="file" id="photo" name="photo"
                        accept="image/jpeg,image/png,image/webp"
                        <?= isset($errors['photo']) ? 'aria-describedby="photo-error" aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['photo'])): ?>
                  <p class="field-error" id="photo-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['photo']) ?></span>
                  </p>
                <?php endif; ?>
                <p class="help"><?= e(sv_t('Optional. JPEG, PNG or WebP, up to 2 MB. The file type is verified by content, not by name.')) ?></p>

                <?php if (($form['photo'] ?? null) !== null): ?>
                  <div class="row">
                    <img class="avatar avatar--lg" alt="" width="56" height="56" decoding="async"
                          src="<?= e(asset('images/candidates/' . basename((string) $form['photo']))) ?>">
                    <label class="check">
                      <input type="checkbox" name="remove_photo" value="1">
                      <span class="check__text"><?= e(sv_t('Remove the current photo')) ?></span>
                    </label>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="form-actions">
              <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="<?= e(sv_t('Saving…')) ?>">
                <?= sv_icon('check', 16, 'btn__icon') ?><span><?= e($isNew ? sv_t('Add candidate') : sv_t('Save changes')) ?></span>
              </button>
              <a class="btn btn--secondary"
                  href="<?= e(url('admin/candidates.php' . ($filterElection > 0 ? '?election_id=' . $filterElection : ''))) ?>"><?= e(sv_t('Cancel')) ?></a>
            </div>
          </form>
        </div>
      </section>

      <?php if (!$isNew): ?>
        <section class="card">
          <div class="card__header">
            <div class="card__headings">
              <h2 class="card__title"><?= e(sv_t('Remove this candidate')) ?></h2>
              <p class="card__subtitle">
                <?= e(sv_t('A candidate who already holds votes cannot be deleted, because the votes refer to them. Disqualify them instead to keep the record intact.')) ?>
              </p>
            </div>
          </div>
          <div class="card__body">
            <form method="post" action="<?= e(post_url('admin/candidates.php')) ?>"
                  data-confirm="<?= e(sv_t('Delete "%s"? This cannot be undone.', $form['first_name'] . ' ' . $form['last_name'])) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">
              <button class="btn btn--danger btn--sm" type="submit">
                <?= sv_icon('trash', 16, 'btn__icon') ?><span><?= e(sv_t('Delete candidate')) ?></span>
              </button>
            </form>
          </div>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
endif;

/* =====================================================================
 | View: list
 * =================================================================== */

$search        = (string) input('q', '');
$statusFilter  = (string) input('status', '');

$where  = [];
$params = [];

if ($filterElection > 0) {
    $where[]                = 'c.election_id = :election_id';
    $params[':election_id'] = $filterElection;
}
if ($filterPosition > 0) {
    $where[]                = 'c.position_id = :position_id';
    $params[':position_id'] = $filterPosition;
}
if ($search !== '') {
    $where[]              = '(c.first_name LIKE :q_first OR c.last_name LIKE :q_last OR c.party LIKE :q_party)';
    $params[':q_first']   = '%' . $search . '%';
    $params[':q_last']    = '%' . $search . '%';
    $params[':q_party']   = '%' . $search . '%';
}
if ($statusFilter !== '' && isset($statusOptions[$statusFilter])) {
    $where[]             = 'c.status = :status';
    $params[':status']   = $statusFilter;
}

$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM candidates c
       JOIN positions p ON p.id = c.position_id
       JOIN elections e ON e.id = c.election_id' . $whereSql
);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pageInfo = paginate($total, 15);

$listStmt = $pdo->prepare(
    'SELECT c.*, p.name AS position_name, p.display_order, e.title AS election_title,
            (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id) AS vote_count
       FROM candidates c
       JOIN positions p ON p.id = c.position_id
       JOIN elections e ON e.id = c.election_id'
    . $whereSql
    . ' ORDER BY e.start_datetime DESC, p.display_order ASC, c.last_name ASC, c.first_name ASC'
    . sprintf(' LIMIT %d OFFSET %d', $pageInfo['per_page'], $pageInfo['offset'])
);
$listStmt->execute($params);
$candidates = $listStmt->fetchAll();
?>

<section class="card">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title"><?= e(sv_t('Candidates')) ?></h2>
      <p class="card__subtitle"><?= e(sv_t($total === 1 ? '%d candidate found' : '%d candidates found', $total)) ?></p>
    </div>
  </div>

  <form class="table-toolbar" method="get" action="<?= e(url('admin/candidates.php')) ?>" role="search">
    <div class="search-field">
      <span class="search-field__icon" aria-hidden="true"><?= sv_icon('search', 16) ?></span>
      <label class="visually-hidden" for="q"><?= e(sv_t('Search candidates')) ?></label>
      <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>"
             placeholder="<?= e(sv_t('Search by name or party')) ?>">
    </div>

    <div class="row">
      <label class="visually-hidden" for="election_id"><?= e(sv_t('Filter by election')) ?></label>
      <select class="select" id="election_id" name="election_id" data-auto-submit>
        <option value=""><?= e(sv_t('All elections')) ?></option>
        <?php foreach ($elections as $election): ?>
          <option value="<?= e((string) $election['id']) ?>"
            <?= $filterElection === (int) $election['id'] ? 'selected' : '' ?>>
            <?= e($election['title']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if ($filterElection > 0): ?>
        <label class="visually-hidden" for="position_id"><?= e(sv_t('Filter by position')) ?></label>
        <select class="select" id="position_id" name="position_id" data-auto-submit>
          <option value=""><?= e(sv_t('All positions')) ?></option>
          <?php foreach ($allPositions as $position): ?>
            <?php if ((int) $position['election_id'] !== $filterElection) { continue; } ?>
            <option value="<?= e((string) $position['id']) ?>"
              <?= $filterPosition === (int) $position['id'] ? 'selected' : '' ?>>
              <?= e($position['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <label class="visually-hidden" for="status"><?= e(sv_t('Filter by status')) ?></label>
      <select class="select" id="status" name="status" data-auto-submit>
        <option value=""><?= e(sv_t('All statuses')) ?></option>
        <?php foreach ($statusOptions as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>>
            <?= e(sv_t(ucfirst($value))) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button class="btn btn--secondary btn--sm" type="submit"><?= sv_icon('filter', 16, 'btn__icon') ?><span><?= e(sv_t('Apply')) ?></span></button>
      <?php if ($search !== '' || $statusFilter !== '' || $filterElection > 0 || $filterPosition > 0): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/candidates.php')) ?>"><?= e(sv_t('Clear')) ?></a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($candidates === []): ?>
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('users', 26) ?></span>
      <p class="empty-state__title">
        <?= e($search !== '' || $statusFilter !== '' || $filterElection > 0 ? sv_t('No candidates match those filters') : sv_t('No candidates yet')) ?>
      </p>
      <p class="empty-state__text">
        <?= e(sv_t('Every position needs at least one active candidate before the election can be opened.')) ?>
      </p>
      <a class="btn btn--accent" href="<?= e(url($createHref)) ?>">
        <?= sv_icon('plus', 16, 'btn__icon') ?><span><?= e(sv_t('Add a candidate')) ?></span>
      </a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden"><?= e(sv_t('Candidates with their position, election and status')) ?></caption>
        <thead>
          <tr>
            <th scope="col"><?= e(sv_t('Candidate')) ?></th>
            <th scope="col"><?= e(sv_t('Position')) ?></th>
            <th scope="col"><?= e(sv_t('Election')) ?></th>
            <th scope="col"><?= e(sv_t('Status')) ?></th>
            <th scope="col"><?= e(sv_t('Votes')) ?></th>
            <th scope="col" class="table__actions-col"><?= e(sv_t('Actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($candidates as $candidate): ?>
            <tr>
              <td>
                <div class="row">
                  <?php if ($candidate['photo'] !== null): ?>
                    <img class="avatar" alt="" width="42" height="42" decoding="async"
                         src="<?= e(asset('images/candidates/' . basename((string) $candidate['photo']))) ?>">
                  <?php else: ?>
                    <span class="avatar" aria-hidden="true">
                      <?= e(initials($candidate['first_name'], $candidate['last_name'])) ?>
                    </span>
                  <?php endif; ?>
                  <div>
                    <span class="table__cell-strong" style="white-space:nowrap;">
                      <?= e($candidate['first_name'] . ' ' . $candidate['last_name']) ?>
                    </span>
                    <span class="table__cell-sub"><?= e($candidate['party'] ? $candidate['party'] : sv_t('No party listed')) ?></span>
                  </div>
                </div>
              </td>
              <td><span class="table__cell-strong"><?= e($candidate['position_name']) ?></span></td>
              <td><span><?= e(str_limit($candidate['election_title'], 40)) ?></span></td>
              <td><?= candidate_badge((string) $candidate['status']) ?></td>
              <td><span class="mono font-bold"><?= e((string) $candidate['vote_count']) ?></span></td>
              <td class="table__actions-col">
                <div class="table__actions">
                  <a class="btn btn--secondary btn--sm"
                     href="<?= e(url('admin/candidates.php?action=edit&id=' . (int) $candidate['id'])) ?>">
                    <?= sv_icon('edit', 14, 'btn__icon') ?><span><?= e(sv_t('Edit')) ?></span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?= pagination_links($pageInfo, 'candidates') ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
