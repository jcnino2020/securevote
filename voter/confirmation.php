<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/confirmation.php
 *
 * The receipt. Deliberately calm and uncluttered: the vote is in, and
 * there is nothing further to do.
 *
 * The receipt is read from the session. If the session entry is gone
 * (a bookmark, or a page reloaded much later) the voter's most recent
 * ballot is looked up instead, so the reference can always be recovered
 * without ever exposing another voter's data.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter = require_voter();
$pdo   = db();

$receipt = $_SESSION['submitted_ballot'] ?? null;

if (!is_array($receipt) || !isset($receipt['reference'])) {
    // Fall back to this voter's newest ballot. The query is scoped to the
    // signed-in voter, so it can only ever return their own record.
    $stmt = $pdo->prepare(
        'SELECT b.ballot_reference, b.submitted_at, e.title AS election_title, e.id AS election_id
           FROM ballots b
           JOIN elections e ON e.id = b.election_id
          WHERE b.voter_id = ?
          ORDER BY b.submitted_at DESC, b.id DESC
          LIMIT 1'
    );
    $stmt->execute([(int) $voter['id']]);
    $latest = $stmt->fetch();

    if (!$latest) {
        flash_info('No ballot has been submitted from your account yet.');
        redirect('voter/elections.php');
    }

    $receipt = [
        'election_id'  => (int) $latest['election_id'],
        'reference'    => (string) $latest['ballot_reference'],
        'submitted_at' => (string) $latest['submitted_at'],
    ];
    $_SESSION['submitted_ballot'] = $receipt;
}

$electionStmt = $pdo->prepare('SELECT * FROM elections WHERE id = ? LIMIT 1');
$electionStmt->execute([(int) $receipt['election_id']]);
$election = $electionStmt->fetch();

$pageTitle    = 'Ballot submitted';
$pageSubtitle = $election !== false && $election !== null ? (string) $election['title'] : '';
$activeNav    = 'elections.php';
$breadcrumb   = [
    ['label' => 'Elections', 'href' => 'voter/elections.php'],
    ['label' => 'Ballot submitted'],
];

require __DIR__ . '/../includes/header.php';
?>

<section class="confirmation" aria-labelledby="confirmation-title">
  <span class="confirmation__icon" aria-hidden="true"><?= sv_icon('check-circle', 34) ?></span>

  <h2 class="confirmation__title" id="confirmation-title">Ballot Submitted Successfully</h2>
  <p class="confirmation__text">Your ballot has been securely recorded.</p>

  <dl class="kv" style="width:100%; max-width:420px; text-align:left">
    <div class="kv__row">
      <dt class="kv__key">Election</dt>
      <dd class="kv__value">
        <?= e($election !== false && $election !== null ? (string) $election['title'] : 'Unknown election') ?>
      </dd>
    </div>
    <div class="kv__row">
      <dt class="kv__key">Reference</dt>
      <dd class="kv__value">
        <span class="reference-code" id="ballot-reference"><?= e((string) $receipt['reference']) ?></span>
      </dd>
    </div>
    <div class="kv__row">
      <dt class="kv__key">Recorded at</dt>
      <dd class="kv__value"><?= e(fmt_datetime((string) $receipt['submitted_at'])) ?></dd>
    </div>
    <div class="kv__row">
      <dt class="kv__key">Voter</dt>
      <dd class="kv__value"><?= e(user_full_name($voter)) ?></dd>
    </div>
  </dl>

  <p class="confirmation__text">
    You may now safely log out. Keep the reference above if you need to confirm
    your participation with the election committee.
  </p>

  <div class="btn-group">
    <button class="btn btn--secondary" type="button" data-copy="#ballot-reference">
      <?= sv_icon('copy', 16, 'btn__icon') ?><span data-copy-label>Copy reference</span>
    </button>
    <a class="btn btn--primary" href="<?= e(url('voter/dashboard.php')) ?>">
      <?= sv_icon('dashboard', 16, 'btn__icon') ?><span>Back to my dashboard</span>
    </a>
  </div>

  <div class="security-note" style="text-align:left">
    <?= sv_icon('shield-check', 16) ?>
    <span>
      Your ballot is final and cannot be changed. The system stores only that you voted,
      never who you voted for in a way that can be linked back to you.
    </span>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
