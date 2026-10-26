<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/ballot.php
 *
 * The voter's ballot interface:
 *   - Authenticated voter with verified 1-person-1-vote eligibility
 *   - Candidate selection per position with strict limits
 *   - Clean 2026 responsive UI with instant feedback
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter  = require_voter();
$pdo    = db();
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

$alreadyVoted = has_voted($electionId, $userId);
$effective    = election_effective_status($election);

// Already voted: send them to their receipt / dashboard
if ($alreadyVoted) {
    flash_warning('You have already submitted your ballot for "' . $election['title'] . '". Each voter may submit exactly one ballot.');
    redirect('voter/dashboard.php');
}

// Check if open
if ($effective !== 'open') {
    [$denialTitle, $denialMessage] = election_state_denial($effective, $election);
    sv_error_page(403, $denialTitle, $denialMessage, [['label' => 'Back to my elections', 'href' => url('voter/elections.php')]]);
}

/* =====================================================================
 | 2. The ballot structure & POST validation
 * =================================================================== */

$ballot  = sv_build_ballot($electionId);
$errors  = [];
$posted  = false;

if (is_post()) {
    require_csrf();
    $posted = true;

    $result = sv_validate_submission($ballot, $_POST['votes'] ?? []);

    if (!$result['ok']) {
        $errors = $result['errors'];
        audit_log('ballot_validation_failed', 'Ballot for election #' . $electionId
            . ' rejected: ' . implode(' ', array_slice($result['errors'], 0, 3)), $userId);
    } else {
        sv_store_pending_ballot($electionId, $result['plan']);

        audit_log('ballot_reviewed', 'Ballot for election #' . $electionId . ' prepared for review.', $userId);
        redirect('voter/review.php?election_id=' . $electionId);
    }
}

$selected = [];
if ($posted && $errors !== [] && isset($result)) {
    $selected = $result['plan'] ?? [];
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
 | Header
 * =================================================================== */

$pageTitle   = 'Your ballot';
$activeNav   = 'elections.php';
$bodyClass   = 'page-voter-ballot';
$pageActions  = '<a class="btn btn--secondary btn--sm" href="' . e(url('voter/elections.php')) . '">'
    . sv_icon('arrow-left', 16, 'btn__icon') . '<span>' . e(sv_t('Leave without voting')) . '</span></a>';
$breadcrumb   = [
    ['label' => 'Elections', 'href' => 'voter/elections.php'],
    ['label' => 'Ballot'],
];

require __DIR__ . '/../includes/header.php';
echo '<aside class="notice"><div><strong>'.e(sv_t('Voting instructions')).'</strong><p>'.e(sv_t('Choose your candidates, review your ballot, then submit once. Your final submission cannot be changed.')).'</p></div></aside>';
?>

<a class="ballot-mobile-exit" href="<?= e(url('voter/elections.php')) ?>">
  <?= sv_icon('arrow-left', 18) ?><span><?= e(sv_t("Leave ballot")) ?></span>
</a>

<?php if ($errors !== []): ?>
  <div class="alert alert--danger" role="alert">
    <span class="alert__icon" aria-hidden="true"><?= sv_icon('x-circle', 18) ?></span>
    <div>
      <p class="alert__text"><?= e(sv_t("Your selections need correction. Nothing has been recorded yet.")) ?></p>
      <ul class="stack-sm" style="margin-top:var(--sp-2)">
        <?php foreach ($errors as $message): ?>
          <li class="text-sm">&bull; <?= e($message) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<nav class="vote-progress" aria-label="Voting progress">
  <ol>
    <li class="is-current" aria-current="step"><span>1</span> <?= e(sv_t("Choose")) ?></li>
    <li><span>2</span> <?= e(sv_t("Review")) ?></li>
    <li><span>3</span> <?= e(sv_t("Receipt")) ?></li>
  </ol>
</nav>

<section class="vote-intro" aria-labelledby="vote-intro-title">
  <div>
    <h2 id="vote-intro-title"><?= e(sv_t("Choose your candidates")) ?></h2>
    <p><?= e($election['title']) ?>. <?= e(sv_t('Select up to the number allowed for each position. You can review everything before you submit.')) ?></p>
  </div>
  <div class="vote-intro__time"><?= sv_icon('clock', 18) ?><span><?= e(sv_t("Voting closes")) ?><br><strong><?= e(fmt_datetime($election['end_datetime'])) ?></strong></span></div>
</section>

<?php if ($ballot === []): ?>
  <section class="card">
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('positions', 26) ?></span>
      <p class="empty-state__title"><?= e(sv_t("This election has no positions yet")) ?></p>
      <p class="empty-state__text">
        An administrator has not added active positions or candidates yet.
      </p>
      <a class="btn btn--secondary" href="<?= e(url('voter/elections.php')) ?>">Back to my elections</a>
    </div>
  </section>
<?php else: ?>
  <form class="form" method="post" action="<?= e(post_url('voter/ballot.php?election_id=' . $electionId)) ?>" id="ballot-form">
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
                 aria-labelledby="position-<?= e((string) $positionId) ?>"
                 style="border-radius: 12px; margin-bottom: var(--sp-6);">
          <div class="ballot__position-head">
            <div>
              <h2 class="ballot__position-title" id="position-<?= e((string) $positionId) ?>">
                <?= e($position['name']) ?>
              </h2>
              <p class="ballot__hint">
                <?= $single
                    ? e(sv_t('Select 1 candidate.'))
                    : e(sprintf(sv_t('Select up to %d candidates.'), $max)) ?>
                <?php if (($position['description'] ?? '') !== ''): ?>
                  &middot; <?= e($position['description']) ?>
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
              <p class="empty-state__title"><?= e(sv_t("No active candidates for this position")) ?></p>
              <p class="empty-state__text">
                <?= e(sv_t("You can leave this position blank and still submit your other votes.")) ?>
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
                    <span class="choice__name"><?= e(sv_t("Skip this position")) ?></span>
                    <span class="choice__bio">
                      <?= e(sv_t("You can leave this one blank and still vote for other positions.")) ?>
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
                    <?php
                      $hasBio = !empty($candidate['biography']);
                      $hasPlatform = !empty($candidate['platform']);
                      $bioId = 'bio-' . $positionId . '-' . $candidateId;
                    ?>
                    <?php if ($hasBio || $hasPlatform): ?>
                      <span class="choice__bio">
                        <?php if ($hasBio): ?>
                          <?= e(str_limit((string) $candidate['biography'], 120)) ?>
                        <?php elseif ($hasPlatform): ?>
                          <strong>Platform:</strong> <?= e(str_limit((string) $candidate['platform'], 120)) ?>
                        <?php endif; ?>
                      </span>
                      <?php if (strlen((string) ($candidate['biography'] . $candidate['platform'])) > 120): ?>
                        <button type="button" class="bio-toggle-btn" data-toggle-bio aria-expanded="false" aria-controls="<?= e($bioId) ?>">
                          <?= sv_icon('info', 14) ?>
                          <span class="bio-toggle-label"><?= e(sv_t("Read platform & bio")) ?></span>
                        </button>
                        <div id="<?= e($bioId) ?>" class="candidate-bio-collapse" hidden>
                          <?php if ($hasBio): ?>
                            <p class="text-sm"><strong><?= e(sv_t("Biography:")) ?></strong> <?= e($candidate['biography']) ?></p>
                          <?php endif; ?>
                          <?php if ($hasPlatform): ?>
                            <p class="text-sm" style="margin-top:var(--sp-1);"><strong><?= e(sv_t("Full Platform:")) ?></strong> <?= e($candidate['platform']) ?></p>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
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

    <!-- Review controls follow the ballot on narrow screens. -->
    <div class="ballot__foot" style="background:var(--bg-card); border-radius:12px; box-shadow:var(--shadow-md); border:1px solid var(--border-color); padding:var(--sp-4);">
      <p class="ballot__foot-note">
        <?= sv_icon('info', 14) ?>
        The next screen shows a review of these choices before anything is submitted.
      </p>
      <div class="btn-group">
        <a class="btn btn--secondary" href="<?= e(url('voter/elections.php')) ?>">Cancel</a>
        <button class="btn btn--primary btn--lg" type="submit" data-submit-once
                data-submitting-label="Checking your selections…">
          <?= sv_icon('check-circle', 18, 'btn__icon') ?>
          <span><?= e(sv_t("Review my ballot")) ?></span>
        </button>
      </div>
    </div>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
