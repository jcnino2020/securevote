<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/review.php
 *
 * The review screen. This is the only place a ballot can be submitted.
 *
 * The selections shown here come from the session copy that ballot.php
 * validated, not from the form, so what the voter sees is exactly what
 * the server is prepared to store. Re-posting a doctored form cannot
 * change this screen, and submitting does not trust the page either:
 * sv_submit_ballot() re-checks everything inside a transaction.
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

if (voter_eligibility($electionId, (int) $voter['id']) === null) {
    audit_log('unauthorized_election_access', 'Voter "' . $voter['username']
        . '" opened the review screen for election #' . $electionId
        . ' without being registered.', (int) $voter['id']);

    sv_error_page(
        403,
        'Not registered for this election',
        'Your account is not on the voter list for "' . $election['title'] . '".',
        [['label' => 'Back to my elections', 'href' => url('voter/elections.php')]]
    );
}

if (has_voted($electionId, (int) $voter['id'])) {
    flash_warning('A ballot for "' . $election['title'] . '" has already been recorded on your account.');
    redirect('voter/elections.php');
}

/* =====================================================================
 | 2. The plan to review
 * =================================================================== */

$plan = sv_pending_ballot($electionId);

if ($plan === null) {
    flash_error('There is nothing to review yet. Please fill in the ballot first.');
    redirect('voter/ballot.php?election_id=' . $electionId);
}

// Read the names back from the database: the review screen never renders
// values that came straight from the browser.
$describe = sv_describe_plan($electionId, $plan);

$chosenTotal = 0;
foreach ($plan as $ids) {
    $chosenTotal += is_array($ids) ? count($ids) : 0;
}

/* =====================================================================
 | 3. POST: submit the final ballot
 * =================================================================== */

if (is_post()) {
    require_csrf();

    $result = sv_submit_ballot($electionId, $plan, (int) $voter['id']);

    if (($result['ok'] ?? false) === true) {
        sv_clear_pending_ballot($electionId);

        // Hand the receipt to the confirmation page through the session so
        // the reference is never trusted from a URL parameter.
        $_SESSION['submitted_ballot'] = [
            'election_id' => $electionId,
            'reference'   => (string) $result['reference'],
            'submitted_at' => now(),
        ];

        redirect('voter/confirmation.php');
    }

    // The submission was refused. Log it and explain, without pretending
    // the vote went through.
    audit_log('ballot_rejected', 'Ballot for election #' . $electionId . ' refused: '
        . (string) ($result['error'] ?? 'unknown reason'), (int) $voter['id']);

    flash_error((string) ($result['error'] ?? 'The ballot could not be recorded.'));

    // If the election closed or the voter already voted, reviewing again is
    // pointless; send them back to their list.
    if (in_array($result['code'] ?? '', ['already_voted', 'not_open'], true)) {
        sv_clear_pending_ballot($electionId);
        redirect('voter/elections.php');
    }

    redirect('voter/review.php?election_id=' . $electionId);
}

/* =====================================================================
 | 4. Header
 * =================================================================== */

$pageTitle    = 'Review your ballot';
$pageSubtitle = (string) $election['title'];
$activeNav    = 'elections.php';
$breadcrumb   = [
    ['label' => 'Elections', 'href' => 'voter/elections.php'],
    ['label' => 'Ballot', 'href' => 'voter/ballot.php?election_id=' . $electionId],
    ['label' => 'Review'],
];

require __DIR__ . '/../includes/header.php';
?>

<section class="card">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title">Review your selections carefully before submitting.</h2>
      <p class="card__subtitle">
        <?= e($election['title']) ?> &middot;
        closes <?= e(fmt_datetime($election['end_datetime'])) ?>
      </p>
    </div>
    <?= election_badge(election_effective_status($election)) ?>
  </div>

  <div class="card__body stack">
    <div class="alert alert--warning">
      <span class="alert__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span>
      <p class="alert__text">
        A submitted ballot cannot be changed, cancelled or re-cast. Check every position below
        before you continue.
      </p>
    </div>

    <?php if ($chosenTotal === 0): ?>
      <div class="alert alert--danger">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span>
        <p class="alert__text">
          You have not selected any candidate. Submitting now records a completely blank ballot.
          If that is not what you want, go back to the ballot and make your selections.
        </p>
      </div>
    <?php endif; ?>

    <div class="review-list">
      <?php foreach ($describe as $entry): ?>
        <?php
          $position   = $entry['position'];
          $candidates = $entry['candidates'];
        ?>
        <article class="review-item">
          <span class="review-item__position"><?= e($position['name']) ?></span>

          <?php if ($candidates === []): ?>
            <span class="review-empty">
              <?= sv_icon('alert-triangle', 14) ?>
              No selection — this position will be left blank
            </span>
          <?php else: ?>
            <?php foreach ($candidates as $candidate): ?>
              <span class="review-item__choice">
                <?= e($candidate['first_name'] . ' ' . $candidate['last_name']) ?>
              </span>
              <span class="review-item__meta">
                <?= e($candidate['party'] ?: 'No party listed') ?>
              </span>
            <?php endforeach; ?>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <p class="text-sm text-muted">
      <?= sv_icon('info', 14) ?>
      Positions are shown in the order they appeared on the ballot. Blank positions stay blank.
    </p>
  </div>
</section>

<section class="card">
  <div class="card__body">
    <form method="post" action="<?= e(url('voter/review.php?election_id=' . $electionId)) ?>"
          data-confirm="Submit your final ballot now? It cannot be changed afterwards.">
      <?= csrf_field() ?>
      <input type="hidden" name="election_id" value="<?= e((string) $electionId) ?>">

      <div class="row row--between">
        <div>
          <p class="table__cell-strong">Ready to submit?</p>
          <p class="text-sm text-muted">
            Your ballot is written to the database inside a single transaction, so it is either
            stored completely or not at all.
          </p>
        </div>

        <div class="btn-group">
          <a class="btn btn--secondary btn--lg"
             href="<?= e(url('voter/ballot.php?election_id=' . $electionId)) ?>">
            <?= sv_icon('arrow-left', 18, 'btn__icon') ?><span>Back to ballot</span>
          </a>
          <button class="btn btn--success btn--lg" type="submit" data-submit-once
                  data-submitting-label="Recording your ballot…">
            <?= sv_icon('send', 18, 'btn__icon') ?><span>Submit final ballot</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
