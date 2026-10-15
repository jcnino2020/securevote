<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/review.php
 *
 * The review screen. This is the only place a ballot can be submitted.
 *
 * The selections shown here come from the session copy that ballot.php
 * validated. Submitting executes sv_submit_ballot() in a single transaction.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter = require_voter();
$pdo   = db();
$userId = (int) $voter['id'];

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

if (has_voted($electionId, $userId)) {
    flash_warning('A ballot for "' . $election['title'] . '" has already been recorded on your account.');
    redirect('voter/dashboard.php');
}

/* =====================================================================
 | 2. The plan to review
 * =================================================================== */

$plan = sv_pending_ballot($electionId);

if ($plan === null) {
    flash_error('There is nothing to review yet. Please fill in the ballot first.');
    redirect('voter/ballot.php?election_id=' . $electionId);
}

// Read the candidate details back from the database
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

    $result = sv_submit_ballot($electionId, $plan, $userId);

    if (($result['ok'] ?? false) === true) {
        sv_clear_pending_ballot($electionId);

        // Hand the receipt to the confirmation page through the session
        $_SESSION['submitted_ballot'] = [
            'election_id'    => $electionId,
            'election_title' => $election['title'],
            'reference'      => (string) $result['reference'],
            'voter_name'     => user_full_name($voter),
            'voter_code'     => (string) ($voter['voter_code'] ?? ''),
            'submitted_at'   => now(),
        ];

        redirect('voter/confirmation.php');
    }

    // Submission failed
    audit_log(
        'ballot_rejected',
        'Ballot for election #' . $electionId . ' refused: ' . (string) ($result['error'] ?? 'unknown reason'),
        $userId
    );

    flash_error((string) ($result['error'] ?? 'The ballot could not be recorded.'));

    if (in_array($result['code'] ?? '', ['already_voted', 'not_open'], true)) {
        sv_clear_pending_ballot($electionId);
        redirect('voter/elections.php');
    }

    redirect('voter/review.php?election_id=' . $electionId);
}

/* =====================================================================
 | 4. Header
 * =================================================================== */

$pageTitle = 'Review your ballot';
$activeNav = 'elections.php';
$breadcrumb   = [
    ['label' => 'Elections', 'href' => 'voter/elections.php'],
    ['label' => 'Ballot', 'href' => 'voter/ballot.php?election_id=' . $electionId],
    ['label' => 'Review'],
];

require __DIR__ . '/../includes/header.php';
?>

<nav class="vote-progress" aria-label="Voting progress">
  <ol>
    <li class="is-complete"><span><?= sv_icon('check', 15) ?></span> Choose</li>
    <li class="is-current" aria-current="step"><span>2</span> Review</li>
    <li><span>3</span> Receipt</li>
  </ol>
</nav>

<section class="vote-intro" aria-labelledby="vote-intro-title">
  <div>
    <h2 id="vote-intro-title">Take one last look</h2>
    <p>Review your choices for <?= e($election['title']) ?>. You can go back and change them before submitting. After submission, your ballot cannot be changed.</p>
  </div>
  <span class="badge badge--success"><?= sv_icon('shield-check', 14) ?> Ready to submit</span>
</section>

<div class="review-summary" style="margin-bottom: var(--sp-6);">
  <div class="review-summary__metric">
    <span class="review-summary__metric-number"><?= e((string) count($describe)) ?></span>
    <span class="review-summary__metric-label">positions on ballot</span>
  </div>
  <div class="review-summary__metric">
    <span class="review-summary__metric-number"><?= e((string) $chosenTotal) ?></span>
    <span class="review-summary__metric-label">candidate(s) selected</span>
  </div>
  <div class="review-summary__metric">
    <span class="review-summary__metric-number">1</span>
    <span class="review-summary__metric-label">ballot allocated</span>
  </div>
</div>

<div class="stack-md" style="margin-bottom: var(--sp-6);">
  <?php foreach ($describe as $entry): ?>
    <?php
      $position = $entry['position'];
      $max      = max(1, (int) $position['max_selections']);
      $chosen   = $entry['candidates'];
    ?>
    <section class="card">
      <div class="card__header">
        <div class="card__headings">
          <h3 class="card__title"><?= e($position['name']) ?></h3>
          <p class="card__subtitle">
            <?= $max === 1 ? '1 candidate allowed' : 'Up to ' . $max . ' candidates allowed' ?>
          </p>
        </div>
        <span class="badge badge--<?= $chosen === [] ? 'warning' : 'primary' ?>">
          <?= $chosen === [] ? 'Abstained' : count($chosen) . ' selected' ?>
        </span>
      </div>

      <div class="card__body">
        <?php if ($chosen === []): ?>
          <p class="text-sm text-muted" style="font-style:italic">
            <?= sv_icon('info', 14) ?>
            No candidate selected for this position (abstained).
          </p>
        <?php else: ?>
          <div class="candidate-summary-list">
            <?php foreach ($chosen as $cand): ?>
              <div class="candidate-summary-item">
                <span class="avatar avatar--sm" aria-hidden="true">
                  <?= initials((string) $cand['first_name'], (string) $cand['last_name']) ?>
                </span>
                <div class="candidate-summary-item__details">
                  <span class="candidate-summary-item__name">
                    <?= e($cand['first_name'] . ' ' . $cand['last_name']) ?>
                  </span>
                  <?php if (($cand['party'] ?? '') !== ''): ?>
                    <span class="candidate-summary-item__party"><?= e($cand['party']) ?></span>
                  <?php endif; ?>
                </div>
                <span class="candidate-summary-item__badge">
                  <?= sv_icon('check', 14) ?> Selected
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card__body">
    <form class="form" method="post" action="<?= e(post_url('voter/review.php?election_id=' . $electionId)) ?>">
      <?= csrf_field() ?>

      <div class="flex flex--between flex--middle flex--wrap gap-md">
        <a class="btn btn--secondary" href="<?= e(url('voter/ballot.php?election_id=' . $electionId)) ?>">
          <?= sv_icon('arrow-left', 16, 'btn__icon') ?>
          <span>Change my choices</span>
        </a>

        <button class="btn btn--success btn--lg" type="submit" data-submit-once
                data-submitting-label="Recording your ballot…">
          <?= sv_icon('shield-check', 18, 'btn__icon') ?>
          <span>Confirm and submit my ballot</span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
