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
    $pageTitle  = sv_t('Results');
    $activeNav  = 'results.php';
    $breadcrumb   = [['label' => sv_t('Results'), 'href' => 'admin/results.php']];

    require __DIR__ . '/../includes/header.php';
    ?>
    <section class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('results', 26) ?></span>
        <p class="empty-state__title"><?= e(sv_t("There is nothing to count yet")) ?></p>
        <p class="empty-state__text">
          <?= e(sv_t("Results appear here as soon as an election exists and voters start submitting ballots.")) ?>
        </p>
        <a class="btn btn--accent" href="<?= e(url('admin/elections.php?action=create')) ?>">
          <?= sv_icon('plus', 16, 'btn__icon') ?><span><?= e(sv_t("Create an election")) ?></span>
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


/* =====================================================================
 | Build the tally
 |
 | sv_tally_positions() lives in includes/voting.php, so the
 | administrator's results page and the voter's results page are counted
 | by exactly the same code and can never disagree.
 * =================================================================== */

require_once __DIR__ . '/../includes/voting.php';

$positions = sv_tally_positions($electionId);
$isFinal = in_array($effectiveStatus, ['closed', 'archived'], true);
$finalSummary = $isFinal ? sv_final_result_summary($positions) : [];

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
    if (str_contains((string) ($election['description'] ?? ''), '[Demo results]')) {
        fputcsv($out, ['Data notice', 'Simulated demo ballots and results; not an official election outcome.']);
    }
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

$pageTitle   = sv_t('Results');
$activeNav   = 'results.php';
$pageActions = '<details class="display-settings"><summary>'.e(sv_t('Export results')).'</summary><div class="display-settings__panel"><a class="link" href="'.e(url('results-report.php?election_id='.$electionId)).'">'.e(sv_t('Download PDF report')).'</a><a class="link" href="'.e(url('admin/results.php?election_id='.$electionId.'&export=csv')).'">'.e(sv_t('Download CSV data')).'</a></div></details>';
$breadcrumb = [
    ['label' => sv_t('Results'), 'href' => 'admin/results.php'],
    ['label' => (string) $election['title']],
];

require __DIR__ . '/../includes/header.php';
?>

<?php if (str_contains((string) ($election['description'] ?? ''), '[Demo results]')): ?>
<div class="alert alert--warning" role="note">
  <span class="alert__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span>
  <p class="alert__text">Demo results: these ballots and tallies are simulated for testing and are not an official election outcome.</p>
</div>
<?php endif; ?>

<section class="card">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title"><?= e(sv_t("Election")) ?></h2>
      <p class="card__subtitle"><?= e(sv_t("Choose which election to count.")) ?></p>
    </div>
    <?= election_badge($effectiveStatus) ?>
  </div>

  <div class="card__body">
    <form class="row" method="get" action="<?= e(url('admin/results.php')) ?>">
      <label class="label" for="election_id"><?= e(sv_t("Election")) ?></label>
      <select class="select" id="election_id" name="election_id" data-auto-submit>
        <?php foreach ($elections as $option): ?>
          <option value="<?= e((string) $option['id']) ?>"
            <?= (int) $option['id'] === $electionId ? 'selected' : '' ?>>
            <?= e($option['title']) ?> — <?= e(sv_t(ucfirst(election_effective_status($option)))) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--secondary btn--sm" type="submit"><?= e(sv_t("Show")) ?></button>
      <a class="btn btn--ghost btn--sm"
         href="<?= e(url('admin/elections.php?action=edit&id=' . $electionId)) ?>">
        <?= sv_icon('edit', 14, 'btn__icon') ?><span><?= e(sv_t("Edit this election")) ?></span>
      </a>
    </form>

    <p class="help"><?= e(sv_t("Voters see aggregate live totals after submitting their ballot. Final outcomes publish when voting closes.")) ?></p>
  </div>
</section>

<section class="stat-grid" aria-label="<?= e(sv_t('Turnout summary')) ?>">
  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('vote', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label"><?= e(sv_t("Ballots submitted")) ?></p>
      <p class="stat-card__value"><?= e((string) $ballots) ?></p>
      <p class="stat-card__meta"><?= e(sprintf(sv_t('of %d eligible voters'), $eligible)) ?></p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--teal"><?= sv_icon('percent', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label"><?= e(sv_t("Turnout")) ?></p>
      <p class="stat-card__value"><?= e((string) $turnout) ?>%</p>
      <div class="progress" role="img"
           aria-label="<?= e(sprintf(sv_t('%s%% turnout'), $turnout)) ?>" style="margin-top:6px">
        <span class="progress__bar progress__bar--winner"
              style="width: <?= e((string) min(100, $turnout)) ?>%"></span>
      </div>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--success"><?= sv_icon('positions', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label"><?= e(sv_t("Positions counted")) ?></p>
      <p class="stat-card__value"><?= e((string) count($positions)) ?></p>
      <p class="stat-card__meta"><?= e(sprintf(sv_t('Voting window %s'), fmt_range($election['start_datetime'], $election['end_datetime']))) ?></p>
    </div>
  </div>
</section>

<?php
  $chartElectionId = $electionId;
  $chartEligible = $eligible;
  $chartBallots = $ballots;
  $chartPositions = $positions;
  require __DIR__ . '/../includes/results-charts.php';
?>

<?php if ($isFinal): ?>
  <section class="card" aria-labelledby="final-results-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="final-results-heading"><?= e(sv_t("Final election summary")) ?></h2>
        <p class="card__subtitle"><?= e(sprintf(sv_t('Voting closed %s. Outcomes use submitted ballots only.'), fmt_datetime((string) $election['end_datetime']))) ?></p>
      </div>
    </div>
    <div class="card__body stack-sm">
      <?php if ($finalSummary === []): ?>
        <p class="text-muted"><?= e(sv_t("No positions were configured for this election.")) ?></p>
      <?php else: ?>
        <?php foreach ($finalSummary as $item): ?>
          <p><strong><?= e($item['position']) ?> — <?= e($item['outcome']) ?>:</strong> <?= e($item['detail']) ?></p>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($positions === []): ?>
  <section class="card">
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('positions', 26) ?></span>
      <p class="empty-state__title"><?= e(sv_t("This election has no positions")) ?></p>
      <p class="empty-state__text"><?= e(sv_t("Add positions and candidates to produce a tally.")) ?></p>
      <a class="btn btn--accent" href="<?= e(url('admin/positions.php?action=create&election_id=' . $electionId)) ?>">
        <?= sv_icon('plus', 16, 'btn__icon') ?><span><?= e(sv_t("Add a position")) ?></span>
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
                ? e(sv_t('Single choice — the highest total wins'))
                : e(sprintf(sv_t('Choose up to %d — the top %d are elected'), (int) $position['max_selections'], (int) $position['max_selections'])) ?>
          </p>
        </div>
        <?php if ($hasAnyVote): ?>
          <span class="badge badge--neutral">
            <?= sv_icon('results', 14) ?>
            <?= e(sprintf(sv_t('%d selection(s) recorded'), $total)) ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="card__body stack">
        <?php if (!$hasAnyVote): ?>
          <div class="empty-state empty-state--inline">
            <span class="empty-state__icon"><?= sv_icon('clock', 24) ?></span>
            <p class="empty-state__title"><?= e(sprintf(sv_t('No votes recorded for this position%s'), $isFinal ? '' : sv_t(' yet'))) ?></p>
            <p class="empty-state__text">
              <?= $isFinal ? e(sv_t('This election closed without a vote for this position.')) : e(sv_t('Counts will appear here as soon as ballots are submitted.')) ?>
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
                      <img class="avatar avatar--sm" alt="" width="32" height="32" decoding="async"
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
                        <?= e($row['party'] ?: sv_t('No party listed')) ?>
                        <?php if ($row['status'] !== 'active'): ?>
                          &middot; <?= candidate_badge((string) $row['status']) ?>
                        <?php endif; ?>
                      </span>
                    </span>
                  </div>

                  <span class="result-row__count">
                    <?= e(sprintf(sv_t('%d vote(s)'), $count)) ?>
                    <span class="result-row__meta">(<?= e((string) $share) ?>%)</span>
                    <?php if ($isLeading): ?>
                      <span class="badge badge--teal"><?= sv_icon('trophy', 13) ?><?= e(sv_result_badge_label($entry, $row, $isFinal)) ?></span>
                    <?php endif; ?>
                  </span>
                </div>

                <div class="progress" role="img"
                     aria-label="<?= e(sprintf(sv_t('%s: %d votes, %s%%'), $row['first_name'] . ' ' . $row['last_name'], $count, $share)) ?>">
                  <span class="progress__bar<?= $isLeading ? ' progress__bar--winner' : '' ?>"
                        style="width: <?= e((string) min(100, max($share, $count > 0 ? 1 : 0))) ?>%"></span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <p class="progress__label">
            <?= e(sprintf(sv_t('%d of %d ballots carried a choice for this position%s. Percentages are of the %d selection(s) recorded for this position.'), $withChoice, $ballots, $blank > 0 ? sprintf(sv_t(', %d left it blank'), $blank) : '', $total)) ?>
          </p>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
