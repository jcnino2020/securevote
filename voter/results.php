<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/results.php
 *
 * Aggregate results available after a ballot is submitted or voting closes.
 *
 * This page asks may_view_results() before it runs a counting query.
 *
 * Counting is aggregate only. Nothing here can reveal how a particular
 * person voted, and the query never joins a ballot to the voter's name.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter  = require_voter();
$pdo    = db();
$userId = (int) $voter['id'];

/* =====================================================================
 | Load Elections
 * =================================================================== */

$stmt = $pdo->prepare(
    "SELECT e.*,
            (SELECT COUNT(*) FROM voter_eligibility vv WHERE vv.election_id = e.id) AS eligible_count,
            (SELECT COUNT(*) FROM ballots bb WHERE bb.election_id = e.id AND bb.status = 'submitted') AS ballot_count,
            b.id AS ballot_id,
            b.ballot_reference,
            COALESCE(b.submitted_at, ve.voted_at) AS ballot_submitted_at,
            (CASE WHEN b.id IS NOT NULL OR ve.has_voted = 1 THEN 1 ELSE 0 END) AS has_voted,
            (CASE WHEN ve.user_id IS NOT NULL THEN 1 ELSE 0 END) AS is_eligible
       FROM elections e
       LEFT JOIN ballots b ON b.election_id = e.id AND b.voter_id = :ballot_user_id AND b.status = 'submitted'
       LEFT JOIN voter_eligibility ve ON ve.election_id = e.id AND ve.user_id = :ve_user_id
      WHERE e.status != 'draft'
      ORDER BY FIELD(e.status, 'open', 'scheduled', 'closed', 'archived'), e.start_datetime DESC"
);
$stmt->execute([
    ':ballot_user_id' => $userId,
    ':ve_user_id'     => $userId,
]);
$elections = $stmt->fetchAll();

/* =====================================================================
 | Which election, and is the viewer entitled to see its numbers?
 * =================================================================== */

$electionId = int_input('election_id', 0);
$focus      = null;

if ($electionId > 0) {
    foreach ($elections as $election) {
        if ((int) $election['id'] === $electionId) {
            $focus = $election;
            break;
        }
    }

    if ($focus === null) {
        $stmtFocus = $pdo->prepare(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM voter_eligibility vv WHERE vv.election_id = e.id) AS eligible_count,
                    (SELECT COUNT(*) FROM ballots bb WHERE bb.election_id = e.id AND bb.status = 'submitted') AS ballot_count,
                    b.id AS ballot_id,
                    b.ballot_reference,
                    COALESCE(b.submitted_at, ve.voted_at) AS ballot_submitted_at,
                    (CASE WHEN b.id IS NOT NULL OR ve.has_voted = 1 THEN 1 ELSE 0 END) AS has_voted,
                    (CASE WHEN ve.user_id IS NOT NULL THEN 1 ELSE 0 END) AS is_eligible
               FROM elections e
               LEFT JOIN ballots b ON b.election_id = e.id AND b.voter_id = :ballot_user_id AND b.status = 'submitted'
               LEFT JOIN voter_eligibility ve ON ve.election_id = e.id AND ve.user_id = :ve_user_id
              WHERE e.id = :id LIMIT 1"
        );
        $stmtFocus->execute([
            ':ballot_user_id' => $userId,
            ':ve_user_id'     => $userId,
            ':id'             => $electionId,
        ]);
        $focus = $stmtFocus->fetch() ?: null;
    }

    if ($focus === null) {
        sv_error_page(
            404,
            sv_t('Election not found'),
            sv_t('That election does not exist, or it has been removed.'),
            [['label' => sv_t('Back to results'), 'href' => url('voter/results.php')]]
        );
    }
}

$focusVoted  = $focus !== null && ((int) ($focus['has_voted'] ?? 0) === 1 || !empty($focus['ballot_reference']));
$focusMaySee = $focus !== null && may_view_results($focus, $voter, $focusVoted);
$focusFinal  = $focus !== null && in_array(election_effective_status($focus), ['closed', 'archived'], true);

// The tally is only computed when it may be shown or summarised.
$tally = [];
if ($focus !== null && $focusMaySee) {
    $tally = sv_tally_positions((int) $focus['id']);
}
$finalSummary = $focusFinal && $focusMaySee ? sv_final_result_summary($tally) : [];


/* =====================================================================
 | Header
 * =================================================================== */

$pageTitle  = sv_t('Results');
$activeNav  = 'results.php';
$breadcrumb = $focus !== null
    ? [['label' => sv_t('Results'), 'href' => 'voter/results.php'], ['label' => (string) $focus['title']]]
    : [['label' => sv_t('Results'), 'href' => 'voter/results.php']];


require __DIR__ . '/../includes/header.php';
?>
<?php if ($focus !== null && str_contains((string) ($focus['description'] ?? ''), '[Demo results]')): ?>
<div class="alert alert--warning" role="note">
  <span class="alert__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span>
  <p class="alert__text">Demo results: these ballots and tallies are simulated for testing and are not an official election outcome.</p>
</div>
<?php endif; ?>
<?php if($focusMaySee): ?><p class="report-download"><a class="link" href="<?= e(url('results-report.php?election_id='.(int)$focus['id'])) ?>"><?= e(sv_t("Download PDF report")) ?></a></p><?php endif; ?>

<?php if ($elections === []): ?>
  <section class="card">
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('results', 26) ?></span>
      <p class="empty-state__title"><?= e(sv_t("No elections on your list")) ?></p>
      <p class="empty-state__text">
        <?= e(sv_t("Results appear here for the elections your account is registered for.")) ?>
      </p>
      <a class="btn btn--secondary" href="<?= e(url('voter/dashboard.php')) ?>"><?= e(sv_t("Back to my dashboard")) ?></a>
    </div>
  </section>
<?php elseif ($focus === null): ?>

  <section class="card">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title"><?= e(sv_t("Election results")) ?></h2>
        <p class="card__subtitle">
          <?= e(sv_t("See live totals after you vote. Completed elections show their final outcomes.")) ?>
        </p>
      </div>
    </div>

    <div class="table-wrap voter-results-list-wrap">
      <table class="table voter-results-list-table">
        <caption class="visually-hidden"><?= e(sv_t("Elections you are registered for and whether their results are published")) ?></caption>
        <thead>
          <tr>
            <th scope="col"><?= e(sv_t("Election")) ?></th>
            <th scope="col"><?= e(sv_t("Status")) ?></th>
            <th scope="col"><?= e(sv_t("Availability")) ?></th>
            <th scope="col" class="table__actions-col"><?= e(sv_t("Actions")) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($elections as $election): ?>
            <?php
              $status  = election_effective_status($election);
              $voted   = (int) ($election['has_voted'] ?? 0) === 1 || !empty($election['ballot_reference']);
              $maySee  = may_view_results($election, $voter, $voted);
              $canVote = $status === 'open' && !$voted && (int) ($election['is_eligible'] ?? 0) === 1;
              $note    = results_policy_notice($election, $voted);
            ?>
            <tr>
              <td data-label="<?= e(sv_t("Election")) ?>"><span class="table__cell-strong"><?= e($election['title']) ?></span></td>
              <td data-label="<?= e(sv_t("Status")) ?>"><?= election_badge($status) ?></td>
              <td data-label="<?= e(sv_t("Availability")) ?>">
                <?php if ($maySee): ?>
                  <span class="badge badge--teal"><?= sv_icon('check', 13) ?><?= e(sv_t("Available")) ?></span>
                <?php else: ?>
                  <?php if ($canVote): ?>
                    <span class="badge badge--neutral"><?= sv_icon('clock', 13) ?><?= e(sv_t("Vote to unlock")) ?></span>
                  <?php elseif ($status === 'scheduled'): ?>
                    <span class="badge badge--neutral"><?= e(sv_t("Not open yet")) ?></span>
                  <?php else: ?>
                    <span class="badge badge--neutral"><?= e(sv_t("Not on voter list")) ?></span>
                  <?php endif; ?>
                  <?php if ($canVote && $note !== null): ?>
                    <span class="table__cell-sub"><?= e($note) ?></span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td class="table__actions-col" data-label="<?= e(sv_t("Results")) ?>">
                <div class="table__actions">
                  <?php if ($maySee): ?>
                    <a class="btn btn--secondary btn--sm"
                       href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>">
                      <?= sv_icon('results', 14, 'btn__icon') ?><span><?= e(sv_t("View results")) ?></span>
                    </a>
                  <?php elseif ($canVote): ?>
                    <a class="btn btn--primary btn--sm"
                       href="<?= e(url('voter/ballot.php?election_id=' . (int) $election['id'])) ?>">
                      <?= sv_icon('vote', 14, 'btn__icon') ?><span><?= e(sv_t("Vote now")) ?></span>
                    </a>
                  <?php elseif ($status === 'scheduled'): ?>
                    <span class="text-sm text-muted"><?= e(sv_t("Not open yet")) ?></span>
                  <?php else: ?>
                    <span class="text-sm text-muted"><?= e(sv_t("Not on voter list")) ?></span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card__footer">
      <p class="text-sm text-muted"><?= e(sv_t("Live tallies become visible after your ballot is submitted. Final outcomes appear when voting closes.")) ?></p>
    </div>
  </section>

<?php elseif (!$focusMaySee): ?>

  <section class="card">
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('lock', 26) ?></span>
      <?php $focusCanVote = election_effective_status($focus) === 'open' && !$focusVoted && (int) ($focus['is_eligible'] ?? 0) === 1; ?>
      <p class="empty-state__title"><?= $focusCanVote ? e(sv_t('Vote to see live results')) : e(sv_t('Results are not available yet')) ?></p>
      <p class="empty-state__text">
        <?= e($focusCanVote ? (results_policy_notice($focus, $focusVoted) ?? sv_t('Submit your ballot to unlock the live tally.')) : (election_effective_status($focus) === 'scheduled' ? sv_t('Voting has not opened yet.') : sv_t('Results become available after an eligible ballot is submitted.'))) ?>
      </p>
      <div class="row" style="justify-content:center">
        <?php if ($focusCanVote): ?>
          <a class="btn btn--primary" href="<?= e(url('voter/ballot.php?election_id=' . (int) $focus['id'])) ?>"><?= e(sv_t("Vote now")) ?></a>
        <?php endif; ?>
        <a class="btn btn--secondary" href="<?= e(url('voter/results.php')) ?>"><?= e(sv_t("All elections")) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('voter/elections.php')) ?>"><?= e(sv_t("My elections")) ?></a>
      </div>
    </div>
  </section>

<?php else: ?>

  <section class="stat-grid" aria-label="<?= e(sv_t('Result summary')) ?>">
    <div class="stat-card">
      <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('vote', 20) ?></span>
      <div class="stat-card__body">
        <p class="stat-card__label"><?= e(sv_t("Ballots counted")) ?></p>
        <p class="stat-card__value"><?= e((string) $focus['ballot_count']) ?></p>
        <p class="stat-card__meta"><?= e(sv_t("Submitted ballots in this election")) ?></p>
      </div>
    </div>

    <div class="stat-card">
      <span class="stat-card__icon stat-card__icon--success"><?= sv_icon('positions', 20) ?></span>
      <div class="stat-card__body">
        <p class="stat-card__label"><?= e(sv_t("Positions")) ?></p>
        <p class="stat-card__value"><?= e((string) count($tally)) ?></p>
        <p class="stat-card__meta"><?= e(sv_t("Counted separately")) ?></p>
      </div>
    </div>

    <div class="stat-card">
      <span class="stat-card__icon stat-card__icon--teal"><?= sv_icon('check-circle', 20) ?></span>
      <div class="stat-card__body">
        <p class="stat-card__label"><?= e(sv_t("Your ballot")) ?></p>
        <p class="stat-card__value" style="font-size:1rem">
          <?= $focusVoted ? e((string) $focus['ballot_reference']) : e(sv_t('Not submitted')) ?>
        </p>
        <p class="stat-card__meta">
          <?= $focusVoted
              ? e(sprintf(sv_t('Recorded %s'), fmt_datetime((string) $focus['ballot_submitted_at'])))
              : e(sv_t('You did not vote in this election')) ?>
        </p>
      </div>
    </div>
  </section>

  <?php
    $chartElectionId = (int) $focus['id'];
    $chartEligible = (int) $focus['eligible_count'];
    $chartBallots = (int) $focus['ballot_count'];
    $chartPositions = $tally;
    require __DIR__ . '/../includes/results-charts.php';
  ?>

  <?php if ($focusFinal): ?>
    <section class="card" aria-labelledby="final-results-heading">
      <div class="card__header">
        <div class="card__headings">
          <h2 class="card__title" id="final-results-heading"><?= e(sv_t("Final election summary")) ?></h2>
          <p class="card__subtitle"><?= e(sprintf(sv_t('Voting closed %s. Outcomes below use recorded ballots only.'), fmt_datetime((string) $focus['end_datetime']))) ?></p>
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

  <?php if ($tally === []): ?>
    <section class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('positions', 26) ?></span>
        <p class="empty-state__title"><?= e(sv_t("Nothing to count yet")) ?></p>
        <p class="empty-state__text"><?= e(sv_t("This election has no positions.")) ?></p>
      </div>
    </section>
  <?php else: ?>
    <?php foreach ($tally as $entry): ?>
      <?php
        $position   = $entry['position'];
        $rows       = $entry['rows'];
        $total      = $entry['total_selections'];
        $withChoice = $entry['ballots_with_choice'];
        $blank      = max(0, (int) $focus['ballot_count'] - $withChoice);
      ?>
      <section class="card" aria-labelledby="result-<?= e((string) $position['id']) ?>">
        <div class="card__header">
          <div class="card__headings">
            <h2 class="card__title" id="result-<?= e((string) $position['id']) ?>">
              <?= e($position['name']) ?>
            </h2>
            <p class="card__subtitle">
              <?= (int) $position['max_selections'] === 1
                  ? e(sv_t('Single choice'))
                  : e(sprintf(sv_t('Up to %d selections per voter'), (int) $position['max_selections'])) ?>
            </p>
          </div>
          <span class="badge badge--neutral">
            <?= e(sprintf(sv_t('%d selection(s) recorded'), $total)) ?>
          </span>
        </div>

        <div class="card__body stack">
          <?php if ($total === 0): ?>
            <div class="empty-state empty-state--inline">
              <span class="empty-state__icon"><?= sv_icon('clock', 24) ?></span>
              <p class="empty-state__title"><?= e(sv_t("No votes recorded for this position")) ?></p>
              <p class="empty-state__text">
                <?= e(sv_t("Either nobody has voted yet, or every voter left this position blank.")) ?>
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
                    <div>
                      <span class="result-row__name">
                        <?= e($row['first_name'] . ' ' . $row['last_name']) ?>
                        <?php if ($isLeading): ?>
                          <span class="badge badge--teal"><?= sv_icon('trophy', 13) ?><?= e(sv_result_badge_label($entry, $row, $focusFinal)) ?></span>
                        <?php endif; ?>
                      </span>
                      <span class="result-row__meta"><?= e($row['party'] ?: sv_t('No party listed')) ?></span>
                    </div>
                    <span class="result-row__count">
                      <?= e(sprintf(sv_t('%d vote(s)'), $count)) ?>
                      <span class="result-row__meta">(<?= e((string) $share) ?>%)</span>
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
              <?= e(sprintf(sv_t('%d of %d ballots carried a choice for this position%s. Percentages are of the %d selections recorded for this position.'), $withChoice, $focus['ballot_count'], $blank > 0 ? sprintf(sv_t(', %d left it blank'), $blank) : '', $total)) ?>
            </p>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

  <section class="card">
    <div class="card__body">
      <div class="row row--between">
        <p class="text-sm text-muted">
          <?php if ($focusFinal): ?>
            <?= e(sv_t("Final results reflect submitted ballots. A tie at the cutoff needs an administrator's review before a winner can be declared.")) ?>
          <?php else: ?>
            <?= sv_t("<strong>Leading</strong> shows who is ahead at this moment. Counting continues while the election is open, so these numbers can change until it closes.") ?>
          <?php endif; ?>
        </p>
        <a class="btn btn--secondary btn--sm" href="<?= e(url('voter/results.php')) ?>">
          <?= sv_icon('arrow-left', 14, 'btn__icon') ?><span><?= e(sv_t("All elections")) ?></span>
        </a>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
