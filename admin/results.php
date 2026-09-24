<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/results.php
 *
 * The tally for one election, position by position.
 *
 * Confidentiality rule that shapes this whole file: results are counted
 * with aggregate queries only. Nothing here joins ballots back to the
 * voter's name, so the page physically cannot show which voter chose
 * which candidate. The only per-voter fact the system records is *that*
 * somebody voted, never *how*.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();

/* =====================================================================
 | Elections for the selector
 * =================================================================== */

$elections = $pdo->query(
    "SELECT e.*,
            (SELECT COUNT(*) FROM voter_eligibility ve WHERE ve.election_id = e.id) AS eligible_count,
            (SELECT COUNT(*) FROM voter_eligibility ve WHERE ve.election_id = e.id AND ve.has_voted = 1) AS voted_count,
            (SELECT COUNT(*) FROM ballots b WHERE b.election_id = e.id AND b.status = 'submitted') AS ballot_count
       FROM elections e
      ORDER BY FIELD(e.status, 'open', 'closed', 'scheduled', 'draft', 'archived'), e.start_datetime DESC"
)->fetchAll();

if ($elections === []) {
    $pageTitle    = 'Results';
    $pageSubtitle = 'No election exists yet.';
    $activeNav    = 'results.php';
    $breadcrumb   = [['label' => 'Results', 'href' => 'admin/results.php']];

    require __DIR__ . '/../includes/header.php';
    ?>
    <section class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('results', 26) ?></span>
        <p class="empty-state__title">There is nothing to count yet</p>
        <p class="empty-state__text">
          Results appear here as soon as an election exists and voters start submitting ballots.
        </p>
        <a class="btn btn--accent" href="<?= e(url('admin/elections.php?action=create')) ?>">
          <?= sv_icon('plus', 16, 'btn__icon') ?><span>Create an election</span>
        </a>
      </div>
    </section>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/* =====================================================================
 | Which election?
 * =================================================================== */

$electionId = int_input('election_id', 0);
$election   = null;

foreach ($elections as $candidateElection) {
    if ((int) $candidateElection['id'] === $electionId) {
        $election = $candidateElection;
        break;
    }
}

// Default to the first election in the list, which is the most relevant one.
if ($election === null) {
    $election   = $elections[0];
    $electionId = (int) $election['id'];
}

$effectiveStatus = election_effective_status($election);
$eligible        = (int) $election['eligible_count'];
$voted           = (int) $election['voted_count'];
$ballots         = (int) $election['ballot_count'];
$turnout         = percent($voted, $eligible);
$policyNotice    = results_policy_notice($election, false);

/* =====================================================================
 | Build the tally
 |
 | sv_tally_positions() lives in includes/voting.php, so the
 | administrator's results page and the voter's results page are counted
 | by exactly the same code and can never disagree.
 * =================================================================== */

require_once __DIR__ . '/../includes/voting.php';

$positions = sv_tally_positions($electionId);

/* =====================================================================
 | CSV export
 |
 | Sent before any HTML so the response is a clean download. Values are
 | prefixed when they start with = + - or @, which stops a spreadsheet
 | from treating a name as a formula (CSV injection).
 * =================================================================== */

if ((string) input('export', '') === 'csv') {
    $safeCell = static function (string $value): string {
        if ($value !== '' && str_contains('=+-@', $value[0])) {
            return "'" . $value;
        }

        return $value;
    };

    $filename = 'securevote-results-election-' . $electionId . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'wb');

    fputcsv($out, ['SecureVote results export']);
    fputcsv($out, ['Election', $election['title']]);
    fputcsv($out, ['Status', $effectiveStatus]);
    fputcsv($out, ['Voting window', $election['start_datetime'] . ' to ' . $election['end_datetime']]);
    fputcsv($out, ['Eligible voters', $eligible]);
    fputcsv($out, ['Ballots submitted', $ballots]);
    fputcsv($out, ['Turnout', $turnout . '%']);
    fputcsv($out, ['Generated', now()]);
    fputcsv($out, []);
    fputcsv($out, ['Position', 'Candidate', 'Party', 'Status', 'Votes', 'Share of selections', 'Leading']);

    foreach ($positions as $entry) {
        foreach ($entry['rows'] as $row) {
            $count = (int) $row['vote_count'];
            fputcsv($out, [
                $safeCell((string) $entry['position']['name']),
                $safeCell($row['first_name'] . ' ' . $row['last_name']),
                $safeCell((string) ($row['party'] ?? '')),
                (string) $row['status'],
                $count,
                ($entry['total_selections'] > 0 ? percent($count, $entry['total_selections']) : 0) . '%',
                isset($entry['leading'][(int) $row['id']]) ? 'yes' : 'no',
            ]);
        }
        fputcsv($out, []);
    }

    fclose($out);

    audit_log('results_exported', 'Exported results for election #' . $electionId
        . ' "' . $election['title'] . '" as CSV.');

    exit;
}

/* =====================================================================
 | Header
 * =================================================================== */

$pageTitle    = 'Results';
$pageSubtitle = (string) $election['title'];
$activeNav    = 'results.php';
$pageActions  = '<a class="btn btn--secondary btn--sm" href="'
    . e(url('admin/results.php?election_id=' . $electionId . '&export=csv')) . '">'
    . sv_icon('download', 16, 'btn__icon') . '<span>Export CSV</span></a>';
$breadcrumb   = [['label' => 'Results', 'href' => 'admin/results.php']];

require __DIR__ . '/../includes/header.php';
?>

<section class="card">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title">Election</h2>
      <p class="card__subtitle">Choose which election to count.</p>
    </div>
    <?= election_badge($effectiveStatus) ?>
  </div>

  <div class="card__body">
    <form class="row" method="get" action="<?= e(url('admin/results.php')) ?>">
      <label class="label" for="election_id">Election</label>
      <select class="select" id="election_id" name="election_id" data-auto-submit>
        <?php foreach ($elections as $option): ?>
          <option value="<?= e((string) $option['id']) ?>"
            <?= (int) $option['id'] === $electionId ? 'selected' : '' ?>>
            <?= e($option['title']) ?> — <?= e(ucfirst(election_effective_status($option))) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--secondary btn--sm" type="submit">Show</button>
      <a class="btn btn--ghost btn--sm"
         href="<?= e(url('admin/elections.php?action=edit&id=' . $electionId)) ?>">
        <?= sv_icon('edit', 14, 'btn__icon') ?><span>Edit this election</span>
      </a>
    </form>

    <?php if ($policyNotice !== null): ?>
      <div class="divider" role="presentation"></div>
      <div class="notice">
        <span class="notice__icon" aria-hidden="true"><?= sv_icon('eye', 18) ?></span>
        <span>
          <?= e($policyNotice) ?>
          Administrators can always see the tally; the results policy only controls when
          <em>voters</em> may see it. Current policy: <strong><?= e((string) $election['results_visibility']) ?></strong>.
        </span>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="stat-grid" aria-label="Turnout summary">
  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('vote', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Ballots submitted</p>
      <p class="stat-card__value"><?= e((string) $ballots) ?></p>
      <p class="stat-card__meta">of <?= e((string) $eligible) ?> eligible voters</p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--teal"><?= sv_icon('percent', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Turnout</p>
      <p class="stat-card__value"><?= e((string) $turnout) ?>%</p>
      <div class="progress" role="img"
           aria-label="<?= e($turnout . '% turnout') ?>" style="margin-top:6px">
        <span class="progress__bar progress__bar--winner"
              style="width: <?= e((string) min(100, $turnout)) ?>%"></span>
      </div>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--success"><?= sv_icon('positions', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Positions counted</p>
      <p class="stat-card__value"><?= e((string) count($positions)) ?></p>
      <p class="stat-card__meta">Voting window <?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?></p>
    </div>
  </div>
</section>

<div class="security-note">
  <?= sv_icon('shield-check', 16) ?>
  <span>
    These figures come from aggregate SQL only. SecureVote never stores or displays a link
    between a voter's identity and the candidates they chose.
  </span>
</div>

<?php if ($positions === []): ?>
  <section class="card">
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('positions', 26) ?></span>
      <p class="empty-state__title">This election has no positions</p>
      <p class="empty-state__text">Add positions and candidates to produce a tally.</p>
      <a class="btn btn--accent" href="<?= e(url('admin/positions.php?action=create&election_id=' . $electionId)) ?>">
        <?= sv_icon('plus', 16, 'btn__icon') ?><span>Add a position</span>
      </a>
    </div>
  </section>
<?php else: ?>
  <?php foreach ($positions as $entry): ?>
    <?php
      $position       = $entry['position'];
      $rows           = $entry['rows'];
      $total          = $entry['total_selections'];
      $withChoice     = $entry['ballots_with_choice'];
      $blank          = max(0, $ballots - $withChoice);
      $hasAnyVote     = $total > 0;
    ?>
    <section class="card" aria-labelledby="position-<?= e((string) $position['id']) ?>">
      <div class="card__header">
        <div class="card__headings">
          <h2 class="card__title" id="position-<?= e((string) $position['id']) ?>">
            <?= e($position['name']) ?>
          </h2>
          <p class="card__subtitle">
            <?= (int) $position['max_selections'] === 1
                ? 'Single choice — the highest total wins'
                : 'Choose up to ' . (int) $position['max_selections'] . ' — the top '
                    . (int) $position['max_selections'] . ' are elected' ?>
          </p>
        </div>
        <?php if ($hasAnyVote): ?>
          <span class="badge badge--neutral">
            <?= sv_icon('results', 14) ?>
            <?= e((string) $total) ?> selection<?= $total === 1 ? '' : 's' ?> recorded
          </span>
        <?php endif; ?>
      </div>

      <div class="card__body stack">
        <?php if (!$hasAnyVote): ?>
          <div class="empty-state empty-state--inline">
            <span class="empty-state__icon"><?= sv_icon('clock', 24) ?></span>
            <p class="empty-state__title">No votes recorded for this position yet</p>
            <p class="empty-state__text">
              Counts will appear here as soon as ballots are submitted.
            </p>
          </div>
        <?php else: ?>
          <div class="result-group">
            <?php foreach ($rows as $row): ?>
              <?php
                $count     = (int) $row['vote_count'];
                $share     = percent($count, $total);
                $isLeading = isset($entry['leading'][(int) $row['id']]);
              ?>
              <article class="result-row<?= $isLeading ? ' result-row--winner' : '' ?>">
                <div class="result-row__head">
                  <div class="row">
                    <?php if ($row['photo'] !== null): ?>
                      <img class="avatar avatar--sm" alt=""
                           src="<?= e(asset('images/candidates/' . basename((string) $row['photo']))) ?>">
                    <?php else: ?>
                      <span class="avatar avatar--sm" aria-hidden="true">
                        <?= e(initials($row['first_name'], $row['last_name'])) ?>
                      </span>
                    <?php endif; ?>
                    <span>
                      <span class="result-row__name">
                        <?= e($row['first_name'] . ' ' . $row['last_name']) ?>
                      </span>
                      <span class="result-row__meta">
                        <?= e($row['party'] ?: 'No party listed') ?>
                        <?php if ($row['status'] !== 'active'): ?>
                          &middot; <?= candidate_badge((string) $row['status']) ?>
                        <?php endif; ?>
                      </span>
                    </span>
                  </div>

                  <span class="result-row__count">
                    <?= e((string) $count) ?> vote<?= $count === 1 ? '' : 's' ?>
                    <span class="result-row__meta">(<?= e((string) $share) ?>%)</span>
                    <?php if ($isLeading): ?>
                      <span class="badge badge--teal"><?= sv_icon('trophy', 13) ?>Leading</span>
                    <?php endif; ?>
                  </span>
                </div>

                <div class="progress" role="img"
                     aria-label="<?= e($row['first_name'] . ' ' . $row['last_name'] . ': ' . $count . ' votes, ' . $share . '%') ?>">
                  <span class="progress__bar<?= $isLeading ? ' progress__bar--winner' : '' ?>"
                        style="width: <?= e((string) min(100, max($share, $count > 0 ? 1 : 0))) ?>%"></span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <p class="progress__label">
            <?= e((string) $withChoice) ?> of <?= e((string) $ballots) ?> ballots carried a choice for this
            position<?= $blank > 0 ? ', ' . e((string) $blank) . ' left it blank' : '' ?>.
            Percentages are of the <?= e((string) $total) ?> selection<?= $total === 1 ? '' : 's' ?>
            recorded for this position.
          </p>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
