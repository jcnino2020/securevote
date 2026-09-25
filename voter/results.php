<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/results.php
 *
 * Results, but only where the election's configured policy allows them.
 *
 * The policy is not a cosmetic setting: this page asks may_view_results()
 * before it runs a single counting query, so a voter who is not yet
 * entitled to see a tally never receives those numbers at all.
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
            (SELECT COUNT(*) FROM ballots bb WHERE bb.election_id = e.id AND bb.status = 'submitted') AS ballot_count,
            b.id AS ballot_id,
            b.ballot_reference,
            COALESCE(b.submitted_at, ve.voted_at) AS ballot_submitted_at,
            (CASE WHEN b.id IS NOT NULL OR ve.has_voted = 1 THEN 1 ELSE 0 END) AS has_voted
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
                    (SELECT COUNT(*) FROM ballots bb WHERE bb.election_id = e.id AND bb.status = 'submitted') AS ballot_count,
                    b.id AS ballot_id,
                    b.ballot_reference,
                    COALESCE(b.submitted_at, ve.voted_at) AS ballot_submitted_at,
                    (CASE WHEN b.id IS NOT NULL OR ve.has_voted = 1 THEN 1 ELSE 0 END) AS has_voted
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
            'Election not found',
            'That election does not exist, or it has been removed.',
            [['label' => 'Back to results', 'href' => url('voter/results.php')]]
        );
    }
}

$focusVoted  = $focus !== null && ((int) ($focus['has_voted'] ?? 0) === 1 || !empty($focus['ballot_reference']));
$focusMaySee = $focus !== null && may_view_results($focus, $voter, $focusVoted);

// The tally is only computed when it may be shown or summarised.
$tally = [];
if ($focus !== null && $focusMaySee) {
    $tally = sv_tally_positions((int) $focus['id']);
}


/* =====================================================================
 | Header
 * =================================================================== */

$pageTitle    = $focus !== null ? 'Results' : 'Results';
$pageSubtitle = $focus !== null
    ? (string) $focus['title']
    : 'Results for the elections you took part in, when the policy allows it.';
$activeNav    = 'results.php';
$breadcrumb   = $focus !== null
    ? [['label' => 'Results', 'href' => 'voter/results.php'], ['label' => 'Election']]
    : [['label' => 'Results', 'href' => 'voter/results.php']];

require __DIR__ . '/../includes/header.php';
?>

<?php if ($elections === []): ?>
  <section class="card">
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('results', 26) ?></span>
      <p class="empty-state__title">No elections on your list</p>
      <p class="empty-state__text">
        Results appear here for the elections your account is registered for.
      </p>
      <a class="btn btn--secondary" href="<?= e(url('voter/dashboard.php')) ?>">Back to my dashboard</a>
    </div>
  </section>
<?php elseif ($focus === null): ?>

  <section class="card">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title">Which results you can see</h2>
        <p class="card__subtitle">
          Each election decides for itself when its tally becomes public.
        </p>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden">Elections you are registered for and whether their results are published</caption>
        <thead>
          <tr>
            <th scope="col">Election</th>
            <th scope="col">Status</th>
            <th scope="col">Results policy</th>
            <th scope="col">Availability</th>
            <th scope="col" class="table__actions-col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($elections as $election): ?>
            <?php
              $status  = election_effective_status($election);
              $voted   = (int) ($election['has_voted'] ?? 0) === 1 || !empty($election['ballot_reference']);
              $maySee  = may_view_results($election, $voter, $voted);
              $note    = results_policy_notice($election, $voted);
            ?>
            <tr>
              <td class="table__cell-strong"><?= e($election['title']) ?></td>
              <td><?= election_badge($status) ?></td>
              <td><?= e((string) $election['results_visibility']) ?></td>
              <td>
                <?php if ($maySee): ?>
                  <span class="badge badge--teal"><?= sv_icon('check', 13) ?>Available</span>
                <?php else: ?>
                  <span class="badge badge--neutral"><?= sv_icon('clock', 13) ?>Not yet published</span>
                  <?php if ($note !== null): ?>
                    <span class="table__cell-sub"><?= e($note) ?></span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td class="table__actions-col">
                <div class="table__actions">
                  <?php if ($maySee): ?>
                    <a class="btn btn--secondary btn--sm"
                       href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>">
                      <?= sv_icon('results', 14, 'btn__icon') ?><span>View results</span>
                    </a>
                  <?php else: ?>
                    <span class="text-sm text-muted">Withheld by policy</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card__footer">
      <p class="text-sm text-muted">
        <?= sv_icon('info', 14) ?>
        The three policies are: <strong>hidden</strong> (never published),
        <strong>after vote</strong> (published once you have voted) and
        <strong>after close</strong> (published when the election closes).
      </p>
    </div>
  </section>

<?php elseif (!$focusMaySee): ?>

  <section class="card">
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('lock', 26) ?></span>
      <p class="empty-state__title">These results are not public yet</p>
      <p class="empty-state__text">
        <?= e(results_policy_notice($focus, $focusVoted)
            ?? 'The election administrator has not published these results.') ?>
      </p>
      <div class="row" style="justify-content:center">
        <a class="btn btn--secondary" href="<?= e(url('voter/results.php')) ?>">All elections</a>
        <a class="btn btn--ghost" href="<?= e(url('voter/elections.php')) ?>">My elections</a>
      </div>
    </div>
  </section>

<?php else: ?>

  <section class="stat-grid" aria-label="Result summary">
    <div class="stat-card">
      <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('vote', 20) ?></span>
      <div class="stat-card__body">
        <p class="stat-card__label">Ballots counted</p>
        <p class="stat-card__value"><?= e((string) $focus['ballot_count']) ?></p>
        <p class="stat-card__meta">Submitted ballots in this election</p>
      </div>
    </div>

    <div class="stat-card">
      <span class="stat-card__icon stat-card__icon--success"><?= sv_icon('positions', 20) ?></span>
      <div class="stat-card__body">
        <p class="stat-card__label">Positions</p>
        <p class="stat-card__value"><?= e((string) count($tally)) ?></p>
        <p class="stat-card__meta">Counted separately</p>
      </div>
    </div>

    <div class="stat-card">
      <span class="stat-card__icon stat-card__icon--teal"><?= sv_icon('check-circle', 20) ?></span>
      <div class="stat-card__body">
        <p class="stat-card__label">Your ballot</p>
        <p class="stat-card__value" style="font-size:1rem">
          <?= $focusVoted ? e((string) $focus['ballot_reference']) : 'Not submitted' ?>
        </p>
        <p class="stat-card__meta">
          <?= $focusVoted
              ? e('Recorded ' . fmt_datetime((string) $focus['ballot_submitted_at']))
              : 'You did not vote in this election' ?>
        </p>
      </div>
    </div>
  </section>

  <div class="security-note">
    <?= sv_icon('shield-check', 16) ?>
    <span>
      Counts are produced with aggregate queries. SecureVote never publishes, and cannot
      reconstruct, which voter chose which candidate.
    </span>
  </div>

  <?php if ($tally === []): ?>
    <section class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('positions', 26) ?></span>
        <p class="empty-state__title">Nothing to count yet</p>
        <p class="empty-state__text">This election has no positions.</p>
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
                  ? 'Single choice'
                  : 'Up to ' . (int) $position['max_selections'] . ' selections per voter' ?>
            </p>
          </div>
          <span class="badge badge--neutral">
            <?= e((string) $total) ?> selection<?= $total === 1 ? '' : 's' ?> recorded
          </span>
        </div>

        <div class="card__body stack">
          <?php if ($total === 0): ?>
            <div class="empty-state empty-state--inline">
              <span class="empty-state__icon"><?= sv_icon('clock', 24) ?></span>
              <p class="empty-state__title">No votes recorded for this position</p>
              <p class="empty-state__text">
                Either nobody has voted yet, or every voter left this position blank.
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
                          <span class="badge badge--teal"><?= sv_icon('trophy', 13) ?>Leading</span>
                        <?php endif; ?>
                      </span>
                      <span class="result-row__meta"><?= e($row['party'] ?: 'No party listed') ?></span>
                    </div>
                    <span class="result-row__count">
                      <?= e((string) $count) ?> vote<?= $count === 1 ? '' : 's' ?>
                      <span class="result-row__meta">(<?= e((string) $share) ?>%)</span>
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
              <?= e((string) $withChoice) ?> of <?= e((string) $focus['ballot_count']) ?> ballots carried a
              choice for this position<?= $blank > 0 ? ', ' . e((string) $blank) . ' left it blank' : '' ?>.
              Percentages are of the <?= e((string) $total) ?> selections recorded for this position.
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
          <strong>Leading</strong> shows who is ahead at this moment. Counting continues while the
          election is open, so these numbers can change until it closes.
        </p>
        <a class="btn btn--secondary btn--sm" href="<?= e(url('voter/results.php')) ?>">
          <?= sv_icon('arrow-left', 14, 'btn__icon') ?><span>All elections</span>
        </a>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
