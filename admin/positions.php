<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/positions.php
 *
 * Positions are the contests inside an election: President, Secretary,
 * Senator, and so on. Each one carries the number of selections a voter
 * may make (1 for a single-choice race, 3 for a senate-style race).
 *
 * Server-side rules enforced here:
 *   - a position name must be unique inside its election (also a
 *     database unique constraint)
 *   - max_selections must be between 1 and 20
 *   - a position that already holds recorded votes cannot be deleted,
 *     because the votes table references it with ON DELETE RESTRICT
 *   - an election that is already open cannot have new positions added,
 *     otherwise early voters would face a different ballot than late ones
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();

$errors = [];
$form   = [
    'id'             => 0,
    'election_id'    => int_input('election_id', 0),
    'name'           => '',
    'description'    => '',
    'max_selections' => 1,
    'display_order'  => 0,
];

/* =====================================================================
 | POST handlers
 * =================================================================== */

if (is_post()) {
    require_csrf();
    $postAction = (string) input('action', '');

    if ($postAction === 'save') {
        $form = [
            'id'             => int_input('id', 0),
            'election_id'    => int_input('election_id', 0),
            'name'           => (string) input('name', ''),
            'description'    => (string) input('description', ''),
            'max_selections' => int_input('max_selections', 1),
            'display_order'  => int_input('display_order', 0),
        ];

        $name = trim($form['name']);

        // The election must exist.
        $electionStmt = $pdo->prepare('SELECT id, title, status FROM elections WHERE id = ? LIMIT 1');
        $electionStmt->execute([$form['election_id']]);
        $election = $electionStmt->fetch();

        if (!$election) {
            $errors['election_id'] = 'Choose the election this position belongs to.';
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Enter a position name between 2 and 100 characters.';
        }
        if (mb_strlen($form['description']) > 255) {
            $errors['description'] = 'Keep the description under 255 characters.';
        }
        if ($form['max_selections'] < 1 || $form['max_selections'] > 20) {
            $errors['max_selections'] = 'Allow between 1 and 20 selections.';
        }
        if ($form['display_order'] < 0 || $form['display_order'] > 999) {
            $errors['display_order'] = 'Use a display order between 0 and 999.';
        }

        // Only draft or scheduled elections may still be reshaped.
        if ($election !== false && $election !== null && $election['status'] === 'open' && $form['id'] === 0) {
            $errors['form'] = 'This election is already open. Add positions before opening it.';
        }

        // Unique name inside the election (mirrors uq_positions_election_name).
        if ($errors === []) {
            $dupe = $pdo->prepare('SELECT id FROM positions WHERE election_id = ? AND name = ? AND id <> ? LIMIT 1');
            $dupe->execute([$form['election_id'], $name, $form['id']]);
            if ($dupe->fetchColumn() !== false) {
                $errors['name'] = 'That election already has a position with this name.';
            }
        }

        if ($errors === []) {
            if ($form['id'] > 0) {
                $update = $pdo->prepare(
                    'UPDATE positions
                        SET election_id = :election_id, name = :name, description = :description,
                            max_selections = :max_selections, display_order = :display_order
                      WHERE id = :id'
                );
                $update->execute([
                    ':election_id'    => $form['election_id'],
                    ':name'           => $name,
                    ':description'    => $form['description'],
                    ':max_selections' => $form['max_selections'],
                    ':display_order'  => $form['display_order'],
                    ':id'             => $form['id'],
                ]);

                audit_log('position_updated', 'Updated position #' . $form['id'] . ' "' . $name
                    . '" in election #' . $form['election_id'] . '.');

                flash_success('Position "' . $name . '" was updated.');
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO positions (election_id, name, description, max_selections, display_order)
                     VALUES (:election_id, :name, :description, :max_selections, :display_order)'
                );
                $insert->execute([
                    ':election_id'    => $form['election_id'],
                    ':name'           => $name,
                    ':description'    => $form['description'],
                    ':max_selections' => $form['max_selections'],
                    ':display_order'  => $form['display_order'],
                ]);
                $newId = (int) $pdo->lastInsertId();

                audit_log('position_created', 'Created position #' . $newId . ' "' . $name
                    . '" in election #' . $form['election_id'] . ' (up to '
                    . $form['max_selections'] . ' selection(s)).');

                flash_success('Position "' . $name . '" was created. Add its candidates next.');
            }

            redirect('admin/positions.php?election_id=' . $form['election_id']);
        }
    }

    if ($postAction === 'delete') {
        $id = int_input('id', 0);

        $stmt = $pdo->prepare(
            'SELECT p.id, p.name, p.election_id,
                    (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id) AS candidate_count,
                    (SELECT COUNT(*) FROM votes v WHERE v.position_id = p.id) AS vote_count
               FROM positions p WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $position = $stmt->fetch();

        if (!$position) {
            flash_error('That position no longer exists.');
            redirect('admin/positions.php');
        }

        // Recorded votes are kept: a position with votes is part of history.
        if ((int) $position['vote_count'] > 0) {
            flash_error('"' . $position['name'] . '" already holds ' . (int) $position['vote_count']
                . ' recorded vote(s) and cannot be deleted.');
            redirect('admin/positions.php?election_id=' . (int) $position['election_id']);
        }

        $delete = $pdo->prepare('DELETE FROM positions WHERE id = ?');
        $delete->execute([$id]);

        audit_log('position_deleted', 'Deleted position #' . $id . ' "' . $position['name']
            . '" (and its ' . (int) $position['candidate_count'] . ' candidate record(s)).');

        flash_success('Position "' . $position['name'] . '" was deleted.');
        redirect('admin/positions.php?election_id=' . (int) $position['election_id']);
    }
}

/* =====================================================================
 | Helpers for this page
 * =================================================================== */

/** Elections for the selects: id, title, status. */
$elections = $pdo->query('SELECT id, title, status FROM elections ORDER BY start_datetime DESC')->fetchAll();

/** Quick title lookup for messages and headings. */
$electionTitles = [];
foreach ($elections as $election) {
    $electionTitles[(int) $election['id']] = (string) $election['title'];
}

/* =====================================================================
 | Which view?
 * =================================================================== */

$viewAction  = (string) input('action', 'list');

// A failed "save" POST must come back as the form, not the list, so the
// submitted values are not thrown away.
if (is_post() && $viewAction === 'save') {
    $viewAction = ((int) ($form['id'] ?? 0) > 0) ? 'edit' : 'create';
}

$filterId    = int_input('election_id', 0);
$isForm      = ($viewAction === 'create' || $viewAction === 'edit');

if ($isForm) {
    $submitted = is_post() && (string) input('action', '') === 'save' && $errors !== [];

    if (!$submitted && $viewAction === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM positions WHERE id = ? LIMIT 1');
        $stmt->execute([int_input('id', 0)]);
        $editing = $stmt->fetch();

        if (!$editing) {
            sv_error_page(
                404,
                'Position not found',
                'That position does not exist, or it was deleted by another administrator.',
                [['label' => 'Back to positions', 'href' => url('admin/positions.php')]]
            );
        }

        $form = [
            'id'             => (int) $editing['id'],
            'election_id'    => (int) $editing['election_id'],
            'name'           => (string) $editing['name'],
            'description'    => (string) $editing['description'],
            'max_selections' => (int) $editing['max_selections'],
            'display_order'  => (int) $editing['display_order'],
        ];
        $filterId = (int) $editing['election_id'];
    } elseif ($viewAction === 'create') {
        // Suggested display order: append at the end of the chosen election.
        if ($form['election_id'] === 0 && $elections !== []) {
            $form['election_id'] = (int) $elections[0]['id'];
        }
        $nextStmt = $pdo->prepare('SELECT COALESCE(MAX(display_order), 0) + 1 FROM positions WHERE election_id = ?');
        $nextStmt->execute([$form['election_id']]);
        $form['display_order'] = (int) $nextStmt->fetchColumn();
    }
}

/* =====================================================================
 | Header data
 * =================================================================== */

$isNew = (int) $form['id'] === 0;

if ($isForm) {
    $pageTitle   = $isNew ? 'New position' : 'Edit position';
    $pageActions = '<a class="btn btn--secondary btn--sm" href="' . e(url('admin/positions.php?election_id=' . $filterId)) . '">'
        . sv_icon('arrow-left', 16, 'btn__icon') . '<span>Back to positions</span></a>';
} else {
    $pageTitle   = 'Positions';
    $pageActions = '<a class="btn btn--accent btn--sm" href="'
        . e(url('admin/positions.php?action=create' . ($filterId > 0 ? '&election_id=' . $filterId : ''))) . '">'
        . sv_icon('plus', 16, 'btn__icon') . '<span>New position</span></a>';
}

$activeNav  = 'positions.php';
$breadcrumb = $isForm
    ? [['label' => 'Positions', 'href' => 'admin/positions.php'], ['label' => $isNew ? 'New' : 'Edit']]
    : [['label' => 'Positions', 'href' => 'admin/positions.php']];

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
        <p class="alert__text">The position was not saved. Please correct the highlighted fields.</p>
      </div>
    <?php endif; ?>

    <?php if ($elections === []): ?>
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('vote', 26) ?></span>
        <p class="empty-state__title">Create an election first</p>
        <p class="empty-state__text">A position always belongs to an election.</p>
        <a class="btn btn--accent" href="<?= e(url('admin/elections.php?action=create')) ?>">
          <?= sv_icon('plus', 16, 'btn__icon') ?><span>New election</span>
        </a>
      </div>
    <?php else: ?>
      <section class="card">
        <div class="card__header">
          <div class="card__headings">
            <h2 class="card__title"><?= e($isNew ? 'Position details' : 'Edit position') ?></h2>
            <p class="card__subtitle">Required fields are marked with an asterisk.</p>
          </div>
        </div>

        <div class="card__body">
          <form class="form" method="post" action="<?= e(post_url('admin/positions.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">

            <div class="form-grid">
              <div class="form-field">
                <label class="label" for="election_id">
                  Election <span class="label__required" aria-hidden="true">*</span>
                </label>
                <select class="select<?= isset($errors['election_id']) ? ' is-invalid' : '' ?>"
                        id="election_id" name="election_id" required aria-required="true">
                  <option value="">Select an election…</option>
                  <?php foreach ($elections as $election): ?>
                    <option value="<?= e((string) $election['id']) ?>"
                      <?= (int) $form['election_id'] === (int) $election['id'] ? 'selected' : '' ?>>
                      <?= e($election['title']) ?> (<?= e((string) $election['status']) ?>)
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
                <label class="label" for="name">
                  Position name <span class="label__required" aria-hidden="true">*</span>
                </label>
                <input class="input<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
                       type="text" id="name" name="name" maxlength="100"
                       value="<?= e($form['name']) ?>" required aria-required="true"
                       placeholder="e.g. President"
                       <?= isset($errors['name']) ? 'aria-describedby="name-error" aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['name'])): ?>
                  <p class="field-error" id="name-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['name']) ?></span>
                  </p>
                <?php endif; ?>
                <p class="help">Must be unique inside the election.</p>
              </div>

              <div class="form-field form-field--full">
                <label class="label" for="description">Description</label>
                <input class="input<?= isset($errors['description']) ? ' is-invalid' : '' ?>"
                       type="text" id="description" name="description" maxlength="255"
                       value="<?= e($form['description']) ?>"
                       placeholder="Short note shown above the candidates"
                       <?= isset($errors['description']) ? 'aria-describedby="description-error" aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['description'])): ?>
                  <p class="field-error" id="description-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['description']) ?></span>
                  </p>
                <?php endif; ?>
              </div>

              <div class="form-field">
                <label class="label" for="max_selections">
                  Selections allowed <span class="label__required" aria-hidden="true">*</span>
                </label>
                <input class="input<?= isset($errors['max_selections']) ? ' is-invalid' : '' ?>"
                       type="number" id="max_selections" name="max_selections"
                       min="1" max="20" step="1" value="<?= e((string) $form['max_selections']) ?>"
                       required aria-required="true"
                       <?= isset($errors['max_selections']) ? 'aria-describedby="max-error" aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['max_selections'])): ?>
                  <p class="field-error" id="max-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['max_selections']) ?></span>
                  </p>
                <?php endif; ?>
                <p class="help">1 for a single-choice race. The server rejects a ballot that exceeds this.</p>
              </div>

              <div class="form-field">
                <label class="label" for="display_order">Display order</label>
                <input class="input<?= isset($errors['display_order']) ? ' is-invalid' : '' ?>"
                       type="number" id="display_order" name="display_order"
                       min="0" max="999" step="1" value="<?= e((string) $form['display_order']) ?>"
                       <?= isset($errors['display_order']) ? 'aria-describedby="order-error" aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['display_order'])): ?>
                  <p class="field-error" id="order-error">
                    <?= sv_icon('alert-triangle', 14) ?><span><?= e($errors['display_order']) ?></span>
                  </p>
                <?php endif; ?>
                <p class="help">Lower numbers appear first on the ballot.</p>
              </div>
            </div>

            <div class="form-actions">
              <button class="btn btn--primary" type="submit" data-submit-once data-submitting-label="Saving…">
                <?= sv_icon('check', 16, 'btn__icon') ?><span><?= e($isNew ? 'Create position' : 'Save changes') ?></span>
              </button>
              <a class="btn btn--secondary"
                 href="<?= e(url('admin/positions.php' . ($form['election_id'] > 0 ? '?election_id=' . (int) $form['election_id'] : ''))) ?>">Cancel</a>

              <?php if (!$isNew): ?>
                <span class="form-actions__spacer"></span>
                <a class="btn btn--secondary btn--sm"
                   href="<?= e(url('admin/candidates.php?position_id=' . (int) $form['id'])) ?>">
                  <?= sv_icon('users', 16, 'btn__icon') ?><span>Manage candidates</span>
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
              <h2 class="card__title">Delete this position</h2>
              <p class="card__subtitle">Only possible while no ballot has been recorded against it.</p>
            </div>
          </div>
          <div class="card__body">
            <form method="post" action="<?= e(post_url('admin/positions.php')) ?>"
                  data-confirm="Delete “<?= e($form['name']) ?>” and all of its candidates? This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">
              <button class="btn btn--danger btn--sm" type="submit">
                <?= sv_icon('trash', 16, 'btn__icon') ?><span>Delete position</span>
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

$where  = [];
$params = [];

if ($filterId > 0) {
    $where[]           = 'p.election_id = :election_id';
    $params[':election_id'] = $filterId;
}

$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$listStmt = $pdo->prepare(
    'SELECT p.*, e.title AS election_title, e.status AS election_status,
            (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id) AS candidate_count,
            (SELECT COUNT(*) FROM votes v WHERE v.position_id = p.id) AS vote_count
       FROM positions p
       JOIN elections e ON e.id = p.election_id'
    . $whereSql
    . ' ORDER BY e.start_datetime DESC, p.display_order ASC, p.name ASC'
);
$listStmt->execute($params);
$positions = $listStmt->fetchAll();

$headingElection = $filterId > 0 ? ($electionTitles[$filterId] ?? null) : null;
?>

<section class="card">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title">
        <?= e($headingElection !== null ? $headingElection : 'All positions') ?>
      </h2>
      <p class="card__subtitle">
        <?= e((string) count($positions)) ?> position<?= count($positions) === 1 ? '' : 's' ?>
        <?= e($headingElection !== null ? 'in this election' : 'across every election') ?>
      </p>
    </div>
  </div>

  <form class="table-toolbar" method="get" action="<?= e(url('admin/positions.php')) ?>">
    <div class="row">
      <label class="label" for="election_id">Election</label>
      <select class="select" id="election_id" name="election_id" data-auto-submit>
        <option value="">All elections</option>
        <?php foreach ($elections as $election): ?>
          <option value="<?= e((string) $election['id']) ?>"
            <?= $filterId === (int) $election['id'] ? 'selected' : '' ?>>
            <?= e($election['title']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--secondary btn--sm" type="submit">Apply</button>
      <?php if ($filterId > 0): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/positions.php')) ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($positions === []): ?>
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('positions', 26) ?></span>
      <p class="empty-state__title">
        <?= e($headingElection !== null ? 'This election has no positions yet' : 'No positions yet') ?>
      </p>
      <p class="empty-state__text">
        A position is one contest on the ballot, for example President or Senator.
        Each position needs at least two candidates before the election can be opened.
      </p>
      <a class="btn btn--accent"
         href="<?= e(url('admin/positions.php?action=create' . ($filterId > 0 ? '&election_id=' . $filterId : ''))) ?>">
        <?= sv_icon('plus', 16, 'btn__icon') ?><span>Create a position</span>
      </a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden">Positions with their election, selection limit and candidate count</caption>
        <thead>
          <tr>
            <th scope="col">Order</th>
            <th scope="col">Position</th>
            <th scope="col">Election</th>
            <th scope="col">Selections</th>
            <th scope="col">Candidates</th>
            <th scope="col" class="table__actions-col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($positions as $position): ?>
            <tr>
              <td class="mono"><?= e((string) $position['display_order']) ?></td>
              <td>
                <span class="table__cell-strong"><?= e($position['name']) ?></span>
                <span class="table__cell-sub"><?= e(str_limit($position['description'], 60) ?: 'No description') ?></span>
              </td>
              <td>
                <span class="table__cell-strong">
                  <a href="<?= e(url('admin/elections.php?action=edit&id=' . (int) $position['election_id'])) ?>">
                    <?= e(str_limit($position['election_title'], 40)) ?>
                  </a>
                </span>
                <span class="table__cell-sub"><?= e(ucfirst((string) $position['election_status'])) ?></span>
              </td>
              <td>
                <span class="table__cell-strong"><?= e((string) $position['max_selections']) ?></span>
                <span class="table__cell-sub">
                  <?= (int) $position['max_selections'] === 1 ? 'Single choice' : 'Choose up to ' . (int) $position['max_selections'] ?>
                </span>
              </td>
              <td>
                <span class="table__cell-strong"><?= e((string) $position['candidate_count']) ?> candidate<?= (int) $position['candidate_count'] === 1 ? '' : 's' ?></span>
                <?php if ((int) $position['vote_count'] > 0): ?>
                  <span class="table__cell-sub"><?= e((string) $position['vote_count']) ?> votes recorded</span>
                <?php endif; ?>
              </td>
              <td class="table__actions-col">
                <div class="table__actions">
                  <a class="btn btn--secondary btn--sm"
                     href="<?= e(url('admin/positions.php?action=edit&id=' . (int) $position['id'])) ?>">
                    <?= sv_icon('edit', 14, 'btn__icon') ?><span>Edit</span>
                  </a>
                  <a class="btn btn--secondary btn--sm"
                     href="<?= e(url('admin/candidates.php?position_id=' . (int) $position['id'])) ?>">
                    <?= sv_icon('users', 14, 'btn__icon') ?><span>Candidates</span>
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
