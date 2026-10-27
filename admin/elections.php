<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/elections.php
 *
 * List, create, edit, open, close, archive and delete elections.
 *
 * Safety rules enforced here (and again in the database):
 *   - an election can only be opened when it has positions and every
 *     position has at least one active candidate
 *   - "Open now" moves the start time to the current moment, because an
 *     election can never be open before its own start time
 *   - an election that already holds ballots cannot be deleted, only
 *     archived, so a submitted vote is never silently destroyed
 *   - every change is written to the audit log
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();
require_once __DIR__.'/../includes/engagement.php'; sv_engagement_db(); sv_seed_change_baselines();

/* ---------------------------------------------------------------------
 | Local helpers for <input type="datetime-local">
 | The browser sends "2026-03-01T08:30"; the database stores DATETIME.
 * ------------------------------------------------------------------- */

/** DATETIME from the database -> value for a datetime-local input. */
function sv_datetime_local_value(?string $value): string
{
    $dt = to_datetime($value);

    return $dt === null ? '' : $dt->format('Y-m-d\TH:i');
}

/** datetime-local input value -> MySQL DATETIME, or null when invalid. */
function sv_parse_datetime_local(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone(APP_TIMEZONE));
        // The round-trip check rejects impossible dates such as 2026-02-31.
        if ($dt !== false && $dt->format($format) === $value) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    return null;
}

$statusOptions    = election_status_options();


$errors = [];
$form   = [
    'id'                 => 0,
    'title'              => '',
    'description'        => '',
    'start_datetime'     => '',
    'end_datetime'       => '',
    'status'             => 'draft',
    'results_visibility' => 'after_vote',
];

/* =====================================================================
 | POST handlers
 * =================================================================== */

if (is_post()) {
    require_csrf();

    $postAction = (string) input('action', '');

    /* ---------------------------------------------------------------
     | Create or update an election
     * ------------------------------------------------------------- */
    if ($postAction === 'save') {
        $form = [
            'id'                 => int_input('id', 0),
            'title'              => (string) input('title', ''),
            'description'        => (string) input('description', ''),
            'start_datetime'     => (string) input('start_datetime', ''),
            'end_datetime'       => (string) input('end_datetime', ''),
            'status'             => (string) input('status', 'draft'),
            'results_visibility' => 'after_vote',
        ];

        $title = trim($form['title']);

        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            $errors['title'] = sv_t('Enter a title between 3 and 150 characters.');
        }
        if (mb_strlen($form['description']) > 5000) {
            $errors['description'] = sv_t('Keep the description under 5000 characters.');
        }
        if (!isset($statusOptions[$form['status']])) {
            $errors['status'] = sv_t('Choose a valid status.');
        }
        if ($form['id'] === 0 && $form['status'] === 'open') {
            $errors['status'] = sv_t('Create the election first, then use Open for voting after adding positions and candidates.');
        }

        $start = sv_parse_datetime_local($form['start_datetime']);
        $end   = sv_parse_datetime_local($form['end_datetime']);

        if ($start === null) {
            $errors['start_datetime'] = sv_t('Enter a valid start date and time.');
        }
        if ($end === null) {
            $errors['end_datetime'] = sv_t('Enter a valid end date and time.');
        }
        if ($start !== null && $end !== null && $end <= $start) {
            $errors['end_datetime'] = sv_t('The end must be later than the start.');
        }

        if ($errors === []) {
            // Serialise the two datetimes into a small "start|end" signature so
            // the audit entry records exactly what was created or changed.
            $signature = $start . ' -> ' . $end;

            if ($form['id'] > 0) {
                $exists = $pdo->prepare('SELECT id, title, status FROM elections WHERE id = ? LIMIT 1');
                $exists->execute([$form['id']]);
                $current = $exists->fetch();

                if (!$current) {
                    $errors['form'] = sv_t('That election no longer exists.');
                } elseif ($form['status'] === 'open' && $current['status'] !== 'open') {
                    $errors['status'] = sv_t('Save the election first, then use Open for voting so readiness and voter assignment are checked.');
                } else {
                    $update = $pdo->prepare(
                        'UPDATE elections
                            SET title = :title, description = :description,
                                start_datetime = :start, end_datetime = :end,
                                status = :status, results_visibility = :visibility
                          WHERE id = :id'
                    );
                    $update->execute([
                        ':title'       => $title,
                        ':description' => $form['description'],
                        ':start'       => $start,
                        ':end'         => $end,
                        ':status'      => $form['status'],
                        ':visibility'  => $form['results_visibility'],
                        ':id'          => $form['id'],
                    ]);

                    audit_log('election_updated', 'Updated election #' . $form['id'] . ' "' . $title
                        . '" (' . $signature . ', status ' . $form['status'] . ').');

                    sv_record_election_change((int)$form['id']);
                    flash_success(sprintf(sv_t('Election "%s" was updated.'), $title));
                    redirect('admin/elections.php');
                }
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO elections
                        (title, description, start_datetime, end_datetime, status, results_visibility, created_by)
                     VALUES (:title, :description, :start, :end, :status, :visibility, :created_by)'
                );
                $insert->execute([
                    ':title'       => $title,
                    ':description' => $form['description'],
                    ':start'       => $start,
                    ':end'         => $end,
                    ':status'      => $form['status'],
                    ':visibility'  => $form['results_visibility'],
                    ':created_by'  => (int) $admin['id'],
                ]);
                $newId = (int) $pdo->lastInsertId();

                audit_log('election_created', 'Created election #' . $newId . ' "' . $title
                    . '" (' . $signature . ').');

                sv_record_election_change($newId);
                flash_success(sprintf(sv_t('Election "%s" was created. Add positions and candidates next.'), $title));
                redirect('admin/elections.php?action=edit&id=' . $newId);
            }
        }
    }

    /* ---------------------------------------------------------------
     | Change status: open / close / archive
     * ------------------------------------------------------------- */
    if ($postAction === 'set_status') {
        $id     = int_input('id', 0);
        $target = (string) input('target_status', '');

        $stmt = $pdo->prepare(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
                    (SELECT COUNT(*) FROM ballots b WHERE b.election_id = e.id) AS ballot_count
               FROM elections e WHERE e.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $election = $stmt->fetch();

        if (!$election) {
            flash_error(sv_t('That election no longer exists.'));
            redirect('admin/elections.php');
        }

        $allowed = ['open', 'closed', 'archived', 'draft', 'scheduled'];
        if (!in_array($target, $allowed, true)) {
            flash_error(sv_t('Unsupported status change.'));
            redirect('admin/elections.php');
        }

        $startNote = '';

        if ($target === 'open') {
            // Integrity gate: an election cannot be opened before it is ready.
            $blockers = [];

            if ((int) $election['position_count'] === 0) {
                $blockers[] = sv_t('it has no positions yet');
            }

            $emptyPositions = $pdo->prepare(
                'SELECT p.name
                   FROM positions p
                  WHERE p.election_id = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM candidates c
                         WHERE c.position_id = p.id AND c.status = \'active\'
                    )'
            );
            $emptyPositions->execute([$id]);
            $empty = $emptyPositions->fetchAll(PDO::FETCH_COLUMN);

            if ($empty !== []) {
                $blockers[] = sv_t('these positions have no active candidate: ') . implode(', ', $empty);
            }

            if ($blockers !== []) {
                flash_error(sprintf(sv_t('Cannot open this election because %s.'), implode('; ', $blockers)));
                redirect('admin/elections.php?action=edit&id=' . $id);
            }

            // An election can never be open before its own start time, so
            // "Open now" also corrects a future start time.
            $startDt = to_datetime($election['start_datetime']);
            $nowDt   = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));

            $endDt = to_datetime($election['end_datetime']);
            if ($endDt !== null && $endDt <= $nowDt) {
                flash_error(sv_t('The end date has already passed. Edit the election and set a future end date first.'));
                redirect('admin/elections.php?action=edit&id=' . $id);
            }

            if ($startDt !== null && $startDt > $nowDt) {
                $setStart = $pdo->prepare('UPDATE elections SET start_datetime = NOW() WHERE id = ?');
                $setStart->execute([$id]);
                $startNote = ' ' . sv_t('The start time was moved to now because it was still in the future.');
                audit_log('election_start_adjusted', 'Election #' . $id
                    . ' start time moved to now when it was opened.');
            }
        }

        $update = $pdo->prepare('UPDATE elections SET status = ? WHERE id = ?');
        $update->execute([$target, $id]);

        if ($target === 'open') {
            $autoEnroll = $pdo->prepare(
                "INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted, created_at)
                 SELECT ?, u.id, 0, NOW() FROM users u WHERE u.role = 'voter' AND u.status = 'active'"
            );
            $autoEnroll->execute([$id]);
        }

        $actionName = match ($target) {
            'open'     => 'election_activated',
            'closed'   => 'election_closed',
            'archived' => 'election_archived',
            default    => 'election_status_changed',
        };
        audit_log($actionName, 'Election #' . $id . ' "' . $election['title'] . '" set to ' . $target . '.');

        $message = match ($target) {
            'open'     => sv_t('Election is now open for voting.'),
            'closed'   => sv_t('Election closed. No further ballots can be submitted.'),
            'archived' => sv_t('Election archived.'),
            default    => sv_t('Election status updated.'),
        };

        sv_record_election_change($id);
        flash_success($message . $startNote);
        redirect('admin/elections.php');
    }

    /* ---------------------------------------------------------------
     | Delete
     * ------------------------------------------------------------- */
    if ($postAction === 'delete') {
        $id = int_input('id', 0);

        $stmt = $pdo->prepare(
            'SELECT e.id, e.title, e.status,
                    (SELECT COUNT(*) FROM ballots b WHERE b.election_id = e.id) AS ballot_count
               FROM elections e WHERE e.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $election = $stmt->fetch();

        if (!$election) {
            flash_error(sv_t('That election no longer exists.'));
            redirect('admin/elections.php');
        }

        // A submitted ballot is evidence. It is never destroyed by a click.
        if ((int) $election['ballot_count'] > 0) {
            flash_error(sprintf(
                sv_t('"%s" already holds %d submitted ballot(s), so it cannot be deleted. Archive it instead.'),
                $election['title'],
                (int) $election['ballot_count']
            ));
            redirect('admin/elections.php');
        }

        $delete = $pdo->prepare('DELETE FROM elections WHERE id = ?');
        $delete->execute([$id]);

        audit_log('election_deleted', 'Deleted election #' . $id . ' "' . $election['title']
            . '" (status was ' . $election['status'] . ', no ballots existed).');

        flash_success(sprintf(sv_t('Election "%s" was deleted.'), $election['title']));
        redirect('admin/elections.php');
    }
}

/* =====================================================================
 | Which view are we rendering?
 * =================================================================== */

$viewAction = (string) input('action', 'list');

// A failed "save" POST must come back as the form, not the list. Otherwise the
// visitor is bounced away from the screen and everything they typed is lost;
// the submitted values are already sitting in $form at this point.
if (is_post() && $viewAction === 'save') {
    $viewAction = ((int) ($form['id'] ?? 0) > 0) ? 'edit' : 'create';
}

$editing    = null;

if ($viewAction === 'create' || $viewAction === 'edit') {
    // On a failed POST the submitted values win, so nothing is lost.
    if (is_post() && (string) input('action', '') === 'save' && $errors !== []) {
        // $form already holds the submitted values.
    } elseif ($viewAction === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM elections WHERE id = ? LIMIT 1');
        $stmt->execute([int_input('id', 0)]);
        $editing = $stmt->fetch();

        if (!$editing) {
            sv_error_page(
                404,
                sv_t('Election not found'),
                sv_t('That election does not exist, or it was deleted by another administrator.'),
                [['label' => sv_t('Back to elections'), 'href' => url('admin/elections.php')]]
            );
        }

        $form = [
            'id'                 => (int) $editing['id'],
            'title'              => (string) $editing['title'],
            'description'        => (string) $editing['description'],
            'start_datetime'     => sv_datetime_local_value($editing['start_datetime']),
            'end_datetime'       => sv_datetime_local_value($editing['end_datetime']),
            'status'             => (string) $editing['status'],
            'results_visibility' => (string) $editing['results_visibility'],
        ];
    } elseif ($viewAction === 'create') {
        $form['start_datetime'] = date('Y-m-d\TH:i');
        $form['end_datetime']   = date('Y-m-d\TH:i', time() + 7 * 86400);
    }
}

/* =====================================================================
 | Header data
 * =================================================================== */

$isForm = $viewAction === 'create' || $viewAction === 'edit';

if ($isForm) {
    $isNew = ($form['id'] ?? 0) === 0;

    $pageTitle   = $isNew ? sv_t('New election') : sv_t('Edit election');
    $activeNav   = 'elections.php';
    $pageActions = '<a class="btn btn--secondary btn--sm" href="' . e(url('admin/elections.php')) . '">'
        . sv_icon('arrow-left', 16, 'btn__icon') . '<span>' . e(sv_t('Back to list')) . '</span></a>';
    $breadcrumb  = [
        ['label' => sv_t('Elections'), 'href' => 'admin/elections.php'],
        ['label' => $isNew ? sv_t('New') : sv_t('Edit')],
    ];
} else {
    $pageTitle   = sv_t('Elections');
    $activeNav   = 'elections.php';
    $pageActions = '<a class="btn btn--accent btn--sm" href="' . e(url('admin/elections.php?action=create')) . '">'
        . sv_icon('plus', 16, 'btn__icon') . '<span>' . e(sv_t('New election')) . '</span></a>';
    $breadcrumb  = [['label' => sv_t('Elections'), 'href' => 'admin/elections.php']];
}

require __DIR__ . '/../includes/header.php';
?>
<div class="feature-shortcuts"><a class="btn btn--secondary" href="<?= e(url('admin/readiness.php')) ?>"><?= e(sv_t('Election readiness checklist')) ?></a><a class="btn btn--secondary" href="<?= e(url('admin/announcements.php')) ?>"><?= e(sv_t('Manage announcements')) ?></a></div>
<?php

/* =====================================================================
 | View: create / edit form
 * =================================================================== */
if ($isForm):
    ?>
    <?php if (isset($errors['form'])): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
        <p class="alert__text"><?= e((string) $errors['form']) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($errors !== [] && !isset($errors['form'])): ?>
      <div class="alert alert--danger" role="alert">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span>
        <p class="alert__text"><?= e(sv_t('The election was not saved. Please correct the highlighted fields.')) ?></p>
      </div>
    <?php endif; ?>

    <section class="card">
      <div class="card__header">
        <div class="card__headings">
          <h2 class="card__title"><?= e($isNew ? sv_t('Election details') : sprintf(sv_t('Edit “%s”'), $form['title'])) ?></h2>
          <p class="card__subtitle"><?= e(sv_t('Required fields are marked with an asterisk.')) ?></p>
        </div>
      </div>

      <div class="card__body">
        <form class="form" method="post" action="<?= e(post_url('admin/elections.php')) ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">

          <div class="form-grid">
            <div class="form-field form-field--full">
              <label class="label" for="title">
                <?= e(sv_t('Election title')) ?> <span class="label__required" aria-hidden="true">*</span>
              </label>
              <input class="input<?= isset($errors['title']) ? ' is-invalid' : '' ?>"
                     type="text" id="title" name="title" maxlength="150"
                     value="<?= e($form['title']) ?>" required aria-required="true"
                     <?= isset($errors['title']) ? 'aria-describedby="title-error" aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['title'])): ?>
                <p class="field-error" id="title-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['title']) ?></span>
                </p>
              <?php endif; ?>
              <p class="help"><?= e(sv_t('Shown to voters on their dashboard and on the ballot.')) ?></p>
            </div>

            <div class="form-field form-field--full">
              <label class="label" for="description"><?= e(sv_t('Description')) ?></label>
              <textarea class="textarea<?= isset($errors['description']) ? ' is-invalid' : '' ?>"
                        id="description" name="description" maxlength="5000"
                        <?= isset($errors['description']) ? 'aria-describedby="description-error" aria-invalid="true"' : '' ?>><?= e($form['description']) ?></textarea>
              <?php if (isset($errors['description'])): ?>
                <p class="field-error" id="description-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['description']) ?></span>
                </p>
              <?php endif; ?>
              <p class="help"><?= e(sv_t('Optional. Explain what is being decided and who may vote.')) ?></p>
            </div>

            <div class="form-field">
              <label class="label" for="start_datetime">
                <?= e(sv_t('Voting opens')) ?> <span class="label__required" aria-hidden="true">*</span>
              </label>
              <input class="input<?= isset($errors['start_datetime']) ? ' is-invalid' : '' ?>"
                     type="datetime-local" id="start_datetime" name="start_datetime"
                     value="<?= e($form['start_datetime']) ?>" required aria-required="true"
                     <?= isset($errors['start_datetime']) ? 'aria-describedby="start-error" aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['start_datetime'])): ?>
                <p class="field-error" id="start-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['start_datetime']) ?></span>
                </p>
              <?php endif; ?>
            </div>

            <div class="form-field">
              <label class="label" for="end_datetime">
                <?= e(sv_t('Voting closes')) ?> <span class="label__required" aria-hidden="true">*</span>
              </label>
              <input class="input<?= isset($errors['end_datetime']) ? ' is-invalid' : '' ?>"
                     type="datetime-local" id="end_datetime" name="end_datetime"
                     value="<?= e($form['end_datetime']) ?>" required aria-required="true"
                     <?= isset($errors['end_datetime']) ? 'aria-describedby="end-error" aria-invalid="true"' : '' ?>>
              <?php if (isset($errors['end_datetime'])): ?>
                <p class="field-error" id="end-error">
                  <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['end_datetime']) ?></span>
                </p>
              <?php endif; ?>
              <p class="help"><?= e(sv_t('The server refuses votes outside this window, even if the status is open.')) ?></p>
            </div>

            <div class="form-field">
              <label class="label" for="status"><?= e(sv_t('Status')) ?></label>
              <select class="select<?= isset($errors['status']) ? ' is-invalid' : '' ?>"
                      id="status" name="status">
                <?php foreach ($statusOptions as $value => $label): ?>
                  <?php if ($value === 'open' && $form['status'] !== 'open') continue; ?>
                  <option value="<?= e($value) ?>" <?= $form['status'] === $value ? 'selected' : '' ?>>
                    <?= e(sv_t($label)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['status'])): ?>
                <p class="field-error"><?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['status']) ?></span></p>
              <?php endif; ?>
              <p class="help">
                <?= e(sv_t('Draft and scheduled elections are not votable. Save the election, then use Open for voting after adding positions and active candidates.')) ?>
              </p>
            </div>

            <div class="form-field">
              <p class="label"><?= e(sv_t('Results access')) ?></p>
              <p class="help"><?= e(sv_t('Students can see the live aggregate tally after submitting their ballot. Final outcomes appear when voting closes.')) ?></p>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="<?= e(sv_t('Saving…')) ?>">
              <?= sv_icon('check', 16, 'btn__icon') ?>
              <span><?= e($isNew ? sv_t('Create election') : sv_t('Save changes')) ?></span>
            </button>
            <a class="btn btn--secondary" href="<?= e(url('admin/elections.php')) ?>"><?= e(sv_t('Cancel')) ?></a>

            <?php if (!$isNew): ?>
              <span class="form-actions__spacer"></span>
              <a class="btn btn--secondary btn--sm"
                 href="<?= e(url('admin/positions.php?election_id=' . (int) $form['id'])) ?>">
                <?= sv_icon('positions', 16, 'btn__icon') ?><span><?= e(sv_t('Manage positions')) ?></span>
              </a>
              <a class="btn btn--secondary btn--sm"
                 href="<?= e(url('admin/candidates.php?election_id=' . (int) $form['id'])) ?>">
                <?= sv_icon('users', 16, 'btn__icon') ?><span><?= e(sv_t('Manage candidates')) ?></span>
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </section>

    <?php if (!$isNew): ?>
      <section class="card" aria-labelledby="danger-heading">
        <div class="card__header">
          <div class="card__headings">
            <h2 class="card__title" id="danger-heading"><?= e(sv_t('Status and deletion')) ?></h2>
            <p class="card__subtitle"><?= e(sv_t('These actions are recorded in the audit log.')) ?></p>
          </div>
        </div>
        <div class="card__body stack">
          <div class="row">
            <?php
            $currentStatus = (string) $form['status'];
            $transitions = [
                'open'     => ['label' => sv_t('Open for voting'), 'icon' => 'play', 'class' => 'btn--success', 'confirm' => sv_t('Open this election now? Voters will be able to submit ballots immediately.')],
                'closed'   => ['label' => sv_t('Close voting'), 'icon' => 'stop', 'class' => 'btn--secondary', 'confirm' => sv_t('Close this election? No further ballots will be accepted.')],
                'archived' => ['label' => sv_t('Archive'), 'icon' => 'archive', 'class' => 'btn--secondary', 'confirm' => sv_t('Archive this election? It stays readable but is clearly finished.')],
            ];
            ?>
            <?php foreach ($transitions as $target => $meta): ?>
              <?php if ($target === $currentStatus) { continue; } ?>
              <?php if ($target === 'archived' && $currentStatus !== 'closed') { continue; } ?>
              <?php if ($target === 'open' && !in_array($currentStatus, ['draft', 'scheduled', 'closed'], true)) { continue; } ?>
              <form method="post" action="<?= e(post_url('admin/elections.php')) ?>"
                    data-confirm="<?= e($meta['confirm']) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">
                <input type="hidden" name="target_status" value="<?= e($target) ?>">
                <button class="btn <?= e($meta['class']) ?> btn--sm" type="submit">
                  <?= sv_icon($meta['icon'], 16, 'btn__icon') ?><span><?= e($meta['label']) ?></span>
                </button>
              </form>
            <?php endforeach; ?>
          </div>

          <div class="divider" role="presentation"></div>

          <form method="post" action="<?= e(post_url('admin/elections.php')) ?>"
                data-confirm="<?= e(sv_t('Delete this election permanently? Positions, candidates and eligibility records are removed too. This cannot be undone.')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">
            <button class="btn btn--danger btn--sm" type="submit">
              <?= sv_icon('trash', 16, 'btn__icon') ?><span><?= e(sv_t('Delete election')) ?></span>
            </button>
          </form>
          <p class="text-sm text-muted">
            <?= e(sv_t('An election that already contains submitted ballots cannot be deleted, only archived, so a recorded vote can never be silently destroyed.')) ?>
          </p>
        </div>
      </section>
    <?php endif; ?>

    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
endif;

/* =====================================================================
 | View: list
 * =================================================================== */

$search       = (string) input('q', '');
$statusFilter = (string) input('status', '');

$where  = [];
$params = [];

if ($search !== '') {
    // Two distinct placeholders on purpose: a named parameter must not be
    // reused inside one statement when prepared statements are not emulated.
    $where[]            = '(e.title LIKE :q_title OR e.description LIKE :q_desc)';
    $params[':q_title'] = '%' . $search . '%';
    $params[':q_desc']  = '%' . $search . '%';
}
if ($statusFilter !== '' && isset($statusOptions[$statusFilter])) {
    // Stored open/scheduled states can age into closed without an admin click.
    $where[]            = "(CASE WHEN e.status IN ('draft', 'closed', 'archived') THEN e.status WHEN e.start_datetime > NOW() THEN 'scheduled' WHEN e.end_datetime <= NOW() THEN 'closed' ELSE 'open' END) = :status";
    $params[':status']  = $statusFilter;
}

$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM elections e' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pageInfo = paginate($total, 10);

// LIMIT and OFFSET are interpolated only after being cast to int by
// paginate(), which is safe; MySQL also refuses string-bound LIMIT values
// when prepared statements are not emulated.
$listSql = 'SELECT e.*,
                   (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
                   (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id) AS candidate_count,
                   (SELECT COUNT(*) FROM voter_eligibility ve WHERE ve.election_id = e.id) AS eligible_count,
                   (SELECT COUNT(*) FROM voter_eligibility ve WHERE ve.election_id = e.id AND ve.has_voted = 1) AS voted_count,
                   u.username AS creator_username
              FROM elections e
              LEFT JOIN users u ON u.id = e.created_by'
    . $whereSql
    . ' ORDER BY FIELD(e.status, \'open\', \'scheduled\', \'draft\', \'closed\', \'archived\'), e.start_datetime DESC'
    . sprintf(' LIMIT %d OFFSET %d', $pageInfo['per_page'], $pageInfo['offset']);

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$elections = $listStmt->fetchAll();
?>

<section class="card">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title"><?= e(sv_t('All elections')) ?></h2>
      <p class="card__subtitle"><?= e(sprintf(sv_t('%d election%s found'), $total, $total === 1 ? '' : 's')) ?></p>
    </div>
  </div>

  <form class="table-toolbar" method="get" action="<?= e(url('admin/elections.php')) ?>" role="search">
    <div class="search-field">
      <span class="search-field__icon" aria-hidden="true"><?= sv_icon('search', 16) ?></span>
      <label class="visually-hidden" for="q"><?= e(sv_t('Search elections')) ?></label>
      <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>"
             placeholder="<?= e(sv_t('Search by title or description')) ?>">
    </div>

    <div class="row">
      <label class="visually-hidden" for="status"><?= e(sv_t('Filter by status')) ?></label>
      <select class="select" id="status" name="status" data-auto-submit>
        <option value=""><?= e(sv_t('All statuses')) ?></option>
        <?php foreach ($statusOptions as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= e(sv_t($label)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--secondary btn--sm" type="submit"><?= sv_icon('filter', 16, 'btn__icon') ?><span><?= e(sv_t('Apply')) ?></span></button>
      <?php if ($search !== '' || $statusFilter !== ''): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/elections.php')) ?>"><?= e(sv_t('Clear')) ?></a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($elections === []): ?>
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('vote', 26) ?></span>
      <p class="empty-state__title">
        <?= e($search !== '' || $statusFilter !== '' ? sv_t('No elections match those filters') : sv_t('No elections yet')) ?>
      </p>
      <p class="empty-state__text">
        <?= e($search !== '' || $statusFilter !== ''
            ? sv_t('Try a different search term, or clear the filters.')
            : sv_t('An election needs at least one position and two candidates before it can be opened.')) ?>
      </p>
      <?php if ($search !== '' || $statusFilter !== ''): ?>
        <a class="btn btn--secondary" href="<?= e(url('admin/elections.php')) ?>"><?= e(sv_t('Clear filters')) ?></a>
      <?php else: ?>
        <a class="btn btn--accent" href="<?= e(url('admin/elections.php?action=create')) ?>">
          <?= sv_icon('plus', 16, 'btn__icon') ?><span><?= e(sv_t('Create an election')) ?></span>
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden"><?= e(sv_t('Elections with schedule, status and readiness')) ?></caption>
        <thead>
          <tr>
            <th scope="col"><?= e(sv_t('Election')) ?></th>
            <th scope="col"><?= e(sv_t('Voting window')) ?></th>
            <th scope="col"><?= e(sv_t('Status')) ?></th>
            <th scope="col"><?= e(sv_t('Setup')) ?></th>
            <th scope="col"><?= e(sv_t('Turnout')) ?></th>
            <th scope="col" class="table__actions-col"><?= e(sv_t('Actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($elections as $election): ?>
            <?php
              $effective = election_effective_status($election);
              $eligible  = (int) $election['eligible_count'];
              $voted     = (int) $election['voted_count'];
              $turnout   = percent($voted, $eligible);
            ?>
            <tr>
              <td>
                <span class="table__cell-strong"><?= e($election['title']) ?></span>
                <span class="table__cell-sub">
                  <?= e(str_limit($election['description'], 70) ?: sv_t('No description')) ?>
                </span>
              </td>
              <td>
                <span class="nowrap"><?= e(fmt_datetime($election['start_datetime'], 'M j, Y g:i A')) ?></span>
                <span class="table__cell-sub"><?= e(sv_t('to')) ?> <?= e(fmt_datetime($election['end_datetime'], 'M j, Y g:i A')) ?></span>
              </td>
              <td>
                <?= election_badge($effective) ?>
                <?php if ($effective !== $election['status']): ?>
                  <span class="table__cell-sub"><?= e(sprintf(sv_t('Stored as %s'), sv_t(ucfirst((string) $election['status'])))) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="table__cell-strong"><?= e(sprintf(sv_t('%d position%s'), (int) $election['position_count'], (int) $election['position_count'] === 1 ? '' : 's')) ?></span>
                <span class="table__cell-sub"><?= e(sprintf(sv_t('%d candidate%s'), (int) $election['candidate_count'], (int) $election['candidate_count'] === 1 ? '' : 's')) ?></span>
              </td>
              <td>
                <span class="table__cell-strong"><?= e((string) $voted) ?> / <?= e((string) $eligible) ?></span>
                <span class="table__cell-sub"><?= e((string) $turnout) ?>% &middot; <?= e(sv_t('Results after voting')) ?></span>
              </td>
              <td class="table__actions-col">
                <div class="table__actions">
                  <a class="btn btn--secondary btn--sm"
                     href="<?= e(url('admin/elections.php?action=edit&id=' . (int) $election['id'])) ?>">
                    <?= sv_icon('edit', 14, 'btn__icon') ?><span><?= e(sv_t('Edit')) ?></span>
                  </a>
                  <a class="btn btn--secondary btn--sm"
                     href="<?= e(url('admin/results.php?election_id=' . (int) $election['id'])) ?>">
                    <?= sv_icon('results', 14, 'btn__icon') ?><span><?= e(sv_t('Results')) ?></span>
                  </a>
                  <?php if (in_array($election['status'], ['draft', 'scheduled'], true)): ?>
                    <form method="post" action="<?= e(post_url('admin/elections.php')) ?>"
                          data-confirm="<?= e(sprintf(sv_t('Open “%s” for voting now?'), $election['title'])) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="set_status">
                      <input type="hidden" name="id" value="<?= e((string) $election['id']) ?>">
                      <input type="hidden" name="target_status" value="open">
                      <button class="btn btn--success btn--sm" type="submit">
                        <?= sv_icon('play', 14, 'btn__icon') ?><span><?= e(sv_t('Open')) ?></span>
                      </button>
                    </form>
                  <?php elseif ($election['status'] === 'open'): ?>
                    <form method="post" action="<?= e(post_url('admin/elections.php')) ?>"
                          data-confirm="<?= e(sprintf(sv_t('Close “%s”? No further ballots will be accepted.'), $election['title'])) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="set_status">
                      <input type="hidden" name="id" value="<?= e((string) $election['id']) ?>">
                      <input type="hidden" name="target_status" value="closed">
                      <button class="btn btn--secondary btn--sm" type="submit">
                        <?= sv_icon('stop', 14, 'btn__icon') ?><span><?= e(sv_t('Close')) ?></span>
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

    <?= pagination_links($pageInfo, 'elections') ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
