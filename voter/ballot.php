<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/ballot.php
 *
 * The ballot: one section per position, each candidate a labelled choice.
 *
 * Flow (Post/Redirect/Get at every step):
 *   GET  ballot.php?election_id=N   -> show the ballot
 *   POST ballot.php (votes[...])    -> validate on the server, keep the
 *                                      plan in the session, send the voter
 *                                      to review.php
 *
 * Nothing is written to the database on this page. The selections only
 * become a vote when they are confirmed on the review screen.
 *
 * Access is checked before anything is rendered: the election must be
 * open, the voter must be registered for it, and they must not have
 * voted already. Guessing the URL gets a 403 and an audit entry.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter = require_voter();
$pdo   = db();

/* =====================================================================
 | 1. Load and authorise the election
 * =================================================================== */

$electionId = int_input('election_id', 0);

$electionStmt = $pdo->prepare('SELECT * FROM elections WHERE id = ? LIMIT 1');
$electionStmt->execute([$electionId]);
$election = $electionStmt->fetch();

if (!$election) {
    sv_error_page(
        404,
        'Election not found',
        'That election does not exist, or it has been removed.',
        [['label' => 'Back to my elections', 'href' => url('voter/elections.php')]]
    );
}

$eligibility = voter_eligibility($electionId, (int) $voter['id']);
$alreadyVoted = has_voted($electionId, (int) $voter['id']);
$effective    = election_effective_status($election);

// Already voted: send them somewhere useful instead of showing a dead ballot.
if ($alreadyVoted) {
    audit_log('ballot_duplicate_attempt', 'Reached the ballot for election #' . $electionId
        . ' but a ballot already exists for this account.', (int) $voter['id']);

    flash_warning('You have already submitted your ballot for this election. '
        . 'Only one ballot can be recorded per voter.');
    redirect('voter/elections.php');
}

// Not registered for this election: an authorization failure, logged as such.
if ($eligibility === null) {
    audit_log('unauthorized_election_access', 'Voter "' . $voter['username']
        . '" is not registered for election #' . $electionId . '.', (int) $voter['id']);

    sv_error_page(
        403,
        'Not registered for this election',
        'Your account is not on the voter list for "' . $election['title']
        . '", so the ballot was not opened. The attempt has been recorded in the audit log. '
        . 'If you believe this is wrong, contact the election administrator.',
        [['label' => 'Back to my elections', 'href' => url('voter/elections.php')]]
    );
}

// Not open right now: also an authorization failure for a voting action.
if ($effective !== 'open') {
    audit_log('ballot_closed_attempt', 'Voter "' . $voter['username']
        . '" tried to open the ballot for election #' . $electionId
        . ' while it was ' . $effective . '.', (int) $voter['id']);

    [$denialTitle, $denialMessage] = election_state_denial($effective, $election);

    sv_error_page(
        403,
        $denialTitle,
        $denialMessage,
        [['label' => 'Back to my elections', 'href' => url('voter/elections.php')]]
    );
}

/* =====================================================================
 | 2. The ballot structure
 * =================================================================== */

$ballot = sv_build_ballot($electionId);

$errors  = [];
$posted  = false;

/* =====================================================================
 | 3. POST: validate and move on to the review screen
 * =================================================================== */

if (is_post()) {
    require_csrf();
    $posted = true;

    $result = sv_validate_submission($ballot, $_POST['votes'] ?? []);

    if (!$result['ok']) {
        $errors = $result['errors'];
        audit_log('ballot_validation_failed', 'Ballot for election #' . $electionId
            . ' rejected: ' . implode(' ', array_slice($result['errors'], 0, 3)), (int) $voter['id']);
    } else {
        // Keep the validated plan server-side and show it on the review page.
        sv_store_pending_ballot($electionId, $result['plan']);

        audit_log('ballot_reviewed', 'Ballot for election #' . $electionId
            . ' prepared for review.', (int) $voter['id']);

        redirect('voter/review.php?election_id=' . $electionId);
    }
}

/* =====================================================================
 | 4. What should be pre-ticked?
 |
 | Priority: what the voter just submitted (so nothing is lost when a
 | validation error is shown), then a plan they had already reviewed.
 * =================================================================== */

$selected = [];

if ($posted && $errors !== [] && isset($result)) {
    // Reuse the plan the validator already produced instead of validating twice.
    $selected = $result['plan'];
} else {
    $pending = sv_pending_ballot($electionId);
    if ($pending !== null) {
        $selected = $pending;
    }
}

/** Is this candidate selected in the plan? */
$isChosen = static function (array $plan, int $positionId, int $candidateId): bool {
    return in_array($candidateId, $plan[$positionId] ?? [], true);
};

$totalCandidates = 0;
foreach ($ballot as $entry) {
    $totalCandidates += count($entry['candidates']);
}

/* =====================================================================
 | 5. Header
 * =================================================================== */

$pageTitle    = $election['title'];
$pageSubtitle = 'Ballot — select your candidates, then review before submitting.';
$activeNav    = 'elections.php';
$pageActions  = '<a class="btn btn--secondary btn--sm" href="' . e(url('voter/elections.php')) . '">'
    . sv_icon('arrow-left', 16, 'btn__icon') . '<span>Leave without voting</span></a>';
$breadcrumb   = [
    ['label' => 'Elections', 'href' => 'voter/elections.php'],
    ['label' => 'Ballot'],
];

require __DIR__ . '/../includes/header.php';
?>

<?php if ($errors !== []): ?>
  <div class="alert alert--danger" role="alert">
    <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
    <div>
      <p class="alert__text">Your ballot was not accepted. Nothing has been recorded yet.</p>
      <ul class="stack-sm" style="margin-top:var(--sp-2)">
        <?php foreach ($errors as $message): ?>
          <li class="text-sm">&bull; <?= e($message) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<section class="card">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title">How to fill this ballot</h2>
      <p class="card__subtitle"><?= e($election['title']) ?></p>
    </div>
    <?= election_badge($effective) ?>
  </div>
  <div class="card__body stack-sm">
    <p class="text-sm text-muted">
      Voting is open from <?= e(fmt_datetime($election['start_datetime'])) ?>
      until <?= e(fmt_datetime($election['end_datetime'])) ?>.
    </p>
    <ul class="stack-sm">
      <li class="text-sm">
        <?= sv_icon('check', 14) ?>
        Each position tells you how many candidates you may select. You may select fewer,
        or none at all, but never more.
      </li>
      <li class="text-sm">
        <?= sv_icon('check', 14) ?>
        After this screen you will see a review of your choices. Nothing is recorded until you
        confirm on that review screen, and after that it cannot be changed.
      </li>
      <li class="text-sm">
        <?= sv_icon('check', 14) ?>
        Your ballot is secret. The system records <em>that</em> you voted, never <em>how</em> you
        voted in a way that can be traced back to you.
      </li>
    </ul>
    <div class="security-note">
      <?= sv_icon('lock', 16) ?>
      <span>
        Your selections are re-checked on the server when you submit the final ballot.
        <?= e((string) $totalCandidates) ?> candidates are on this ballot.
      </span>
    </div>
  </div>
</section>

<?php if ($ballot === []): ?>
  <section class="card">
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('positions', 26) ?></span>
      <p class="empty-state__title">This election has no positions yet</p>
      <p class="empty-state__text">
        An administrator still has to add positions and candidates before voting can take place.
      </p>
      <a class="btn btn--secondary" href="<?= e(url('voter/elections.php')) ?>">Back to my elections</a>
    </div>
  </section>
<?php else: ?>
  <form class="form" method="post" action="<?= e(url('voter/ballot.php')) ?>" id="ballot-form">
    <?= csrf_field() ?>
    <input type="hidden" name="election_id" value="<?= e((string) $electionId) ?>">

    <div class="ballot">
      <?php foreach ($ballot as $index => $entry): ?>
        <?php
          $position   = $entry['position'];
          $positionId = (int) $position['id'];
          $max        = max(1, (int) $position['max_selections']);
          $single     = $max === 1;
          $chosenHere = count($selected[$positionId] ?? []);
        ?>
        <section class="ballot__position" data-max="<?= e((string) $max) ?>"
                 aria-labelledby="position-<?= e((string) $positionId) ?>">
          <div class="ballot__position-head">
            <div>
              <h2 class="ballot__position-title" id="position-<?= e((string) $positionId) ?>">
                <?= e($position['name']) ?>
              </h2>
              <p class="ballot__hint">
                <?= $single
                    ? 'Select 1 candidate.'
                    : 'Select up to ' . $max . ' candidates.' ?>
                <?php if (($position['description'] ?? '') !== ''): ?>
                  <?= e($position['description']) ?>
                <?php endif; ?>
              </p>
            </div>
            <span class="ballot__counter<?= $chosenHere >= $max ? ' is-full' : '' ?>"
                  aria-live="polite">
              <?= e((string) $chosenHere) ?> of <?= e((string) $max) ?> selected
            </span>
          </div>

          <p class="ballot__hint" data-ballot-warning hidden></p>

          <?php if ($entry['candidates'] === []): ?>
            <div class="empty-state empty-state--inline">
              <span class="empty-state__icon"><?= sv_icon('users', 22) ?></span>
              <p class="empty-state__title">No active candidates for this position</p>
              <p class="empty-state__text">
                You can leave this position blank and still submit the rest of your ballot.
              </p>
            </div>
          <?php else: ?>
            <div class="ballot__candidates">
              <?php if ($single): ?>
                <?php $abstainId = 'abstain-' . $positionId; ?>
                <label class="choice" for="<?= e($abstainId) ?>">
                  <input class="choice__input" type="radio" id="<?= e($abstainId) ?>"
                         name="votes[<?= e((string) $positionId) ?>][]" value=""
                         <?= ($selected[$positionId] ?? []) === [] ? 'checked' : '' ?>>
                  <span class="choice__body">
                    <span class="choice__name">No selection (abstain)</span>
                    <span class="choice__bio">
                      Leave this position blank. Your other choices are still counted.
                    </span>
                  </span>
                  <span class="choice__tick" aria-hidden="true"><?= sv_icon('check', 14) ?></span>
                </label>
              <?php endif; ?>

              <?php foreach ($entry['candidates'] as $candidate): ?>
                <?php
                  $candidateId = (int) $candidate['id'];
                  $inputId     = 'c-' . $positionId . '-' . $candidateId;
                ?>
                <label class="choice" for="<?= e($inputId) ?>">
                  <input class="choice__input"
                         type="<?= $single ? 'radio' : 'checkbox' ?>"
                         id="<?= e($inputId) ?>"
                         name="votes[<?= e((string) $positionId) ?>][]"
                         value="<?= e((string) $candidateId) ?>"
                         <?= $isChosen($selected, $positionId, $candidateId) ? 'checked' : '' ?>>

                  <span class="choice__body">
                    <span class="choice__name">
                      <?= e($candidate['first_name'] . ' ' . $candidate['last_name']) ?>
                      <?php if (($candidate['party'] ?? '') !== ''): ?>
                        <span class="choice__party"><?= e($candidate['party']) ?></span>
                      <?php endif; ?>
                    </span>
                    <?php if (($candidate['biography'] ?? '') !== ''): ?>
                      <span class="choice__bio"><?= e(str_limit((string) $candidate['biography'], 180)) ?></span>
                    <?php endif; ?>
                    <?php if (($candidate['platform'] ?? '') !== ''): ?>
                      <span class="choice__bio">
                        <strong>Platform:</strong> <?= e(str_limit((string) $candidate['platform'], 180)) ?>
                      </span>
                    <?php endif; ?>
                  </span>

                  <span class="choice__tick" aria-hidden="true"><?= sv_icon('check', 14) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>

    <div class="ballot__foot">
      <p class="ballot__foot-note">
        <?= sv_icon('info', 14) ?>
        The next screen shows a review of these choices. Nothing is recorded until you
        confirm there.
      </p>
      <div class="btn-group">
        <a class="btn btn--secondary" href="<?= e(url('voter/elections.php')) ?>">Cancel</a>
        <button class="btn btn--primary btn--lg" type="submit" data-submit-once
                data-submitting-label="Checking your selections…">
          <?= sv_icon('check-circle', 18, 'btn__icon') ?>
          <span>Review my ballot</span>
        </button>
      </div>
    </div>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
