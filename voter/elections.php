<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/elections.php
 *
 * The voter's list of elections, plus the instructions for one election
 * when ?election_id= is given.
 *
 * Every row here comes from voter_eligibility, so a voter can never see
 * an election they are not registered for. The action column explains
 * *why* a ballot cannot be opened instead of silently hiding the button.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter  = require_voter();
$pdo    = db();
$userId = (int) $voter['id'];

$focusId = int_input('election_id', 0);

/* =====================================================================
 | Elections this voter is registered for
 * =================================================================== */

$stmt = $pdo->prepare(
    "SELECT e.*,
            ve.has_voted,
            ve.voted_at,
            b.ballot_reference,
            b.submitted_at AS ballot_submitted_at,
            (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.status = 'active') AS candidate_count
       FROM voter_eligibility ve
       JOIN elections e ON e.id = ve.election_id
       LEFT JOIN ballots b ON b.election_id = e.id AND b.voter_id = ve.user_id
      WHERE ve.user_id = :user_id
      ORDER BY FIELD(e.status, 'open', 'scheduled', 'draft', 'closed', 'archived'),
               e.start_datetime DESC"
);
$stmt->execute([':user_id' => $userId]);
$elections = $stmt->fetchAll();

$focus = null;
if ($focusId > 0) {
    foreach ($elections as $election) {
        if ((int) $election['id'] === $focusId) {
            $focus = $election;
            break;
        }
    }

    if ($focus === null) {
        // Not registered, or the id does not exist. Both get the same answer,
        // so this page cannot be used to discover other elections.
        audit_log('unauthorized_election_access', 'Voter "' . $voter['username']
            . '" requested instructions for election #' . $focusId
            . ' without being registered.', $userId);

        sv_error_page(
            403,
            'Not available to your account',
            'This election is not on your voter list, so its details were not shown.',
            [['label' => 'Back to my elections', 'href' => url('voter/elections.php')]]
        );
    }
}

/* =====================================================================
 | Instructions for one election
 * =================================================================== */

$focusBallot = null;
if ($focus !== null) {
    $focusBallot = sv_build_ballot((int) $focus['id']);
}

/* =====================================================================
 | Header
 * =================================================================== */

$pageTitle    = $focus !== null ? (string) $focus['title'] : 'Elections';
$pageSubtitle = $focus !== null
    ? 'Election instructions and your ballot status.'
    : 'Everything you are registered to vote in.';
$activeNav    = 'elections.php';
$breadcrumb   = $focus !== null
    ? [['label' => 'Elections', 'href' => 'voter/elections.php'], ['label' => 'Instructions']]
    : [['label' => 'Elections', 'href' => 'voter/elections.php']];

require __DIR__ . '/../includes/header.php';
?>

<?php if ($focus !== null): ?>
  <?php
    $focusStatus = election_effective_status($focus);
    $focusVoted  = (int) $focus['has_voted'] === 1;
    $blockReason = election_block_reason($focus, true, $focusVoted);
    $policyNote  = results_policy_notice($focus, $focusVoted);
  ?>
  <section class="card">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title"><?= e($focus['title']) ?></h2>
        <p class="card__subtitle"><?= e(fmt_range($focus['start_datetime'], $focus['end_datetime'])) ?></p>
      </div>
      <?= election_badge($focusStatus) ?>
    </div>

    <div class="card__body stack">
      <?php if (($focus['description'] ?? '') !== ''): ?>
        <p><?= e((string) $focus['description']) ?></p>
      <?php endif; ?>

      <dl class="kv">
        <div class="kv__row">
          <dt class="kv__key">Voting opens</dt>
          <dd class="kv__value"><?= e(fmt_datetime($focus['start_datetime'])) ?></dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Voting closes</dt>
          <dd class="kv__value">
            <?= e(fmt_datetime($focus['end_datetime'])) ?>
            <span class="text-muted text-sm">(<?= e(countdown_text($focus)) ?>)</span>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Your registration</dt>
          <dd class="kv__value">
            <span class="badge badge--success"><?= sv_icon('check', 13) ?>Registered to vote</span>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Your ballot</dt>
          <dd class="kv__value">
            <?php if ($focusVoted): ?>
              <span class="badge badge--teal">
                <?= sv_icon('check-circle', 13) ?>Submitted
              </span>
              <span class="text-muted text-sm">
                Reference <span class="mono"><?= e((string) $focus['ballot_reference']) ?></span>
                on <?= e(fmt_datetime((string) $focus['ballot_submitted_at'])) ?>
              </span>
            <?php else: ?>
              <span class="badge badge--neutral"><?= sv_icon('clock', 13) ?>Not submitted yet</span>
            <?php endif; ?>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">On the ballot</dt>
          <dd class="kv__value">
            <?= e((string) $focus['position_count']) ?> position<?= (int) $focus['position_count'] === 1 ? '' : 's' ?>
            &middot; <?= e((string) $focus['candidate_count']) ?> candidate<?= (int) $focus['candidate_count'] === 1 ? '' : 's' ?>
          </dd>
        </div>
      </dl>

      <?php if ($blockReason !== null): ?>
        <div class="notice">
          <span class="notice__icon" aria-hidden="true"><?= sv_icon('info', 18) ?></span>
          <span><?= e($blockReason) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($policyNote !== null): ?>
        <div class="notice">
          <span class="notice__icon" aria-hidden="true"><?= sv_icon('eye', 18) ?></span>
          <span><?= e($policyNote) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <div class="card__footer">
      <div class="row">
        <?php if ($focusStatus === 'open' && !$focusVoted): ?>
          <a class="btn btn--primary"
             href="<?= e(url('voter/ballot.php?election_id=' . (int) $focus['id'])) ?>">
            <?= sv_icon('vote', 16, 'btn__icon') ?><span>Open my ballot</span>
          </a>
        <?php elseif ($focusVoted): ?>
          <a class="btn btn--secondary" href="<?= e(url('voter/confirmation.php')) ?>">
            <?= sv_icon('file', 16, 'btn__icon') ?><span>View my receipt</span>
          </a>
        <?php endif; ?>

        <?php if (may_view_results($focus, $voter, $focusVoted)): ?>
          <a class="btn btn--secondary"
             href="<?= e(url('voter/results.php?election_id=' . (int) $focus['id'])) ?>">
            <?= sv_icon('results', 16, 'btn__icon') ?><span>View results</span>
          </a>
        <?php endif; ?>

        <a class="btn btn--ghost" href="<?= e(url('voter/elections.php')) ?>">Back to my elections</a>
      </div>
    </div>
  </section>

  <section class="card" aria-labelledby="positions-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="positions-heading">What is being decided</h2>
        <p class="card__subtitle">Candidate names are hidden here on purpose — they belong on the ballot.</p>
      </div>
    </div>
    <div class="card__body">
      <?php if ($focusBallot === []): ?>
        <div class="empty-state empty-state--inline">
          <span class="empty-state__icon"><?= sv_icon('positions', 24) ?></span>
          <p class="empty-state__title">No positions have been published yet</p>
          <p class="empty-state__text">
            An administrator still has to finish setting this election up.
          </p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <caption class="visually-hidden">Positions in this election and how many selections each allows</caption>
            <thead>
              <tr>
                <th scope="col">#</th>
                <th scope="col">Position</th>
                <th scope="col">What it is</th>
                <th scope="col">You may select</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($focusBallot as $index => $entry): ?>
                <tr>
                  <td class="mono"><?= e((string) $index + 1) ?></td>
                  <td class="table__cell-strong"><?= e($entry['position']['name']) ?></td>
                  <td><?= e($entry['position']['description'] ?: '—') ?></td>
                  <td>
                    <?= e((string) $entry['position']['max_selections']) ?>
                    <span class="table__cell-sub">
                      <?= (int) $entry['position']['max_selections'] === 1
                          ? 'Single choice'
                          : 'Choose up to ' . (int) $entry['position']['max_selections'] ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php else: ?>

  <?php if ($elections === []): ?>
    <section class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('vote', 26) ?></span>
        <p class="empty-state__title">No elections on your list</p>
        <p class="empty-state__text">
          The election administrator registers voters for each election. Please contact them if
          you expected to see one here.
        </p>
      </div>
    </section>
  <?php else: ?>
    <section class="card">
      <div class="card__header">
        <div class="card__headings">
          <h2 class="card__title">Your elections</h2>
          <p class="card__subtitle">
            <?= e((string) count($elections)) ?> election<?= count($elections) === 1 ? '' : 's' ?> on your voter list
          </p>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table">
          <caption class="visually-hidden">Elections you are registered for, with status and your ballot state</caption>
          <thead>
            <tr>
              <th scope="col">Election</th>
              <th scope="col">Voting window</th>
              <th scope="col">Status</th>
              <th scope="col">Your ballot</th>
              <th scope="col" class="table__actions-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($elections as $election): ?>
              <?php
                $status = election_effective_status($election);
                $voted  = (int) $election['has_voted'] === 1;
                $canVote = $status === 'open' && !$voted;
                $reason  = election_block_reason($election, true, $voted);
              ?>
              <tr>
                <td>
                  <span class="table__cell-strong"><?= e($election['title']) ?></span>
                  <span class="table__cell-sub">
                    <?= e((string) $election['position_count']) ?> positions &middot;
                    <?= e((string) $election['candidate_count']) ?> candidates
                  </span>
                </td>
                <td>
                  <span class="nowrap"><?= e(fmt_datetime($election['start_datetime'], 'M j, Y')) ?></span>
                  <span class="table__cell-sub">
                    to <?= e(fmt_datetime($election['end_datetime'], 'M j, Y g:i A')) ?>
                  </span>
                </td>
                <td>
                  <?= election_badge($status) ?>
                  <span class="table__cell-sub"><?= e(countdown_text($election)) ?></span>
                </td>
                <td>
                  <?php if ($voted): ?>
                    <span class="badge badge--teal"><?= sv_icon('check-circle', 13) ?>Submitted</span>
                    <span class="table__cell-sub mono"><?= e((string) $election['ballot_reference']) ?></span>
                  <?php elseif ($canVote): ?>
                    <span class="badge badge--warning"><?= sv_icon('clock', 13) ?>Waiting for you</span>
                  <?php else: ?>
                    <span class="badge badge--neutral"><?= sv_icon('ban', 13) ?>Not votable</span>
                    <?php if ($reason !== null): ?>
                      <span class="table__cell-sub"><?= e(str_limit($reason, 70)) ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td class="table__actions-col">
                  <div class="table__actions">
                    <a class="btn btn--secondary btn--sm"
                       href="<?= e(url('voter/elections.php?election_id=' . (int) $election['id'])) ?>">
                      <?= sv_icon('info', 14, 'btn__icon') ?><span>Details</span>
                    </a>
                    <?php if ($canVote): ?>
                      <a class="btn btn--primary btn--sm"
                         href="<?= e(url('voter/ballot.php?election_id=' . (int) $election['id'])) ?>">
                        <?= sv_icon('vote', 14, 'btn__icon') ?><span>Vote</span>
                      </a>
                    <?php elseif ($voted && may_view_results($election, $voter, true)): ?>
                      <a class="btn btn--secondary btn--sm"
                         href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>">
                        <?= sv_icon('results', 14, 'btn__icon') ?><span>Results</span>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <section class="card" aria-labelledby="how-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="how-heading">Before you vote</h2>
        <p class="card__subtitle">A few things worth knowing.</p>
      </div>
    </div>
    <div class="card__body">
      <ul class="stack-sm">
        <li class="text-sm"><?= sv_icon('check', 14) ?>You can only vote in elections your account is registered for.</li>
        <li class="text-sm"><?= sv_icon('check', 14) ?>You may submit exactly one ballot per election. It cannot be edited afterwards.</li>
        <li class="text-sm"><?= sv_icon('check', 14) ?>You can review your choices before submitting, and go back to change them.</li>
        <li class="text-sm"><?= sv_icon('check', 14) ?>Voting outside the published window is refused by the server, not just hidden in the interface.</li>
        <li class="text-sm"><?= sv_icon('check', 14) ?>If a ballot fails to submit, nothing is recorded; the whole attempt is rolled back.</li>
      </ul>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
