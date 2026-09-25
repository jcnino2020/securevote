<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/confirmation.php
 *
 * Official Digital Voting Receipt (2026 Edition):
 *   - Displays cryptographic verification reference token (e.g. SV-2026-XXXXXX)
 *   - Confirms voter participation and 1-person-1-vote recording
 *   - Provides print receipt, token copy, and tallies link
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$pdo  = db();
$user = current_user();

$reqElectionId = int_input('election_id', 0);
$receipt       = null;

if ($reqElectionId > 0 && $user !== null) {
    $stmt = $pdo->prepare(
        'SELECT b.ballot_reference, b.submitted_at, e.title AS election_title, e.id AS election_id,
                u.first_name, u.last_name, u.voter_code, u.username
           FROM ballots b
           JOIN elections e ON e.id = b.election_id
           JOIN users u ON u.id = b.voter_id
          WHERE b.voter_id = ? AND b.election_id = ? AND b.status = \'submitted\'
          ORDER BY b.submitted_at DESC, b.id DESC
          LIMIT 1'
    );
    $stmt->execute([(int) $user['id'], $reqElectionId]);
    $ballot = $stmt->fetch();

    if ($ballot) {
        $receipt = [
            'election_id'    => (int) $ballot['election_id'],
            'election_title' => (string) $ballot['election_title'],
            'reference'      => (string) $ballot['ballot_reference'],
            'voter_name'     => user_full_name($ballot),
            'voter_code'     => (string) ($ballot['voter_code'] ?? ''),
            'submitted_at'   => (string) $ballot['submitted_at'],
        ];
    }
}

if ($receipt === null) {
    $receipt = $_SESSION['submitted_ballot'] ?? null;
}

if (!is_array($receipt) || !isset($receipt['reference'])) {
    if ($user !== null) {
        // Fallback for signed-in voter looking at their last submission
        $stmt = $pdo->prepare(
            'SELECT b.ballot_reference, b.submitted_at, e.title AS election_title, e.id AS election_id,
                    u.first_name, u.last_name, u.voter_code, u.username
               FROM ballots b
               JOIN elections e ON e.id = b.election_id
               JOIN users u ON u.id = b.voter_id
              WHERE b.voter_id = ? AND b.status = \'submitted\'
              ORDER BY b.submitted_at DESC, b.id DESC
              LIMIT 1'
        );
        $stmt->execute([(int) $user['id']]);
        $latest = $stmt->fetch();

        if ($latest) {
            $receipt = [
                'election_id'    => (int) $latest['election_id'],
                'election_title' => (string) $latest['election_title'],
                'reference'      => (string) $latest['ballot_reference'],
                'voter_name'     => user_full_name($latest),
                'voter_code'     => (string) ($latest['voter_code'] ?? ''),
                'submitted_at'   => (string) $latest['submitted_at'],
            ];
        }
    }

    if ($receipt === null) {
        flash_info('No recent ballot submission was found.');
        redirect('voter/dashboard.php');
    }
}

$electionId    = (int) ($receipt['election_id'] ?? 0);
$electionTitle = (string) ($receipt['election_title'] ?? '');

if ($electionTitle === '' && $electionId > 0) {
    $stmt = $pdo->prepare('SELECT title FROM elections WHERE id = ? LIMIT 1');
    $stmt->execute([$electionId]);
    $electionTitle = (string) ($stmt->fetchColumn() ?: 'University Election');
}

$pageTitle    = 'Ballot Recorded';
$pageSubtitle = $electionTitle;
$activeNav    = 'elections.php';
$breadcrumb   = [
    ['label' => 'Dashboard', 'href' => 'voter/dashboard.php'],
    ['label' => 'Elections', 'href' => 'voter/elections.php'],
    ['label' => 'Receipt'],
];

require __DIR__ . '/../includes/header.php';
?>

<section class="confirmation" aria-labelledby="confirmation-title" style="max-width: 600px; margin: 0 auto; text-align: center;">
  <div class="confirmation__icon">
    <?= sv_icon('check-circle', 36) ?>
  </div>

  <h1 class="confirmation__title" id="confirmation-title">
    Ballot Recorded Successfully
  </h1>
  <p class="confirmation__text">
    Your ballot has been officially recorded in the digital ballot box and verified for 1-person-1-vote compliance.
  </p>

  <div class="card" style="text-align: left; width: 100%; margin-bottom: var(--sp-6);">
    <div class="card__header">
      <div class="card__headings">
        <h3 class="card__title" style="font-size: 1.05rem;"><?= e($electionTitle) ?></h3>
        <p class="card__subtitle">Official Digital Voting Receipt</p>
      </div>
      <span class="badge badge--success"><?= sv_icon('shield-check', 14) ?> 1-Vote Verified</span>
    </div>

    <div class="card__body">
      <dl class="kv">
        <div class="kv__row">
          <dt class="kv__key">Verification Token</dt>
          <dd class="kv__value">
            <span class="reference-code" id="ballot-reference">
              <?= e((string) $receipt['reference']) ?>
            </span>
          </dd>
        </div>

        <?php if (!empty($receipt['voter_name'])): ?>
          <div class="kv__row">
            <dt class="kv__key">Voter Identity</dt>
            <dd class="kv__value" style="font-weight: 600;">
              <?= e((string) $receipt['voter_name']) ?>
            </dd>
          </div>
        <?php endif; ?>

        <?php if (!empty($receipt['voter_code'])): ?>
          <div class="kv__row">
            <dt class="kv__key">Personal Voter Code</dt>
            <dd class="kv__value">
              <span class="voter-code-badge"><?= e((string) $receipt['voter_code']) ?></span>
            </dd>
          </div>
        <?php endif; ?>

        <div class="kv__row">
          <dt class="kv__key">Submission Timestamp</dt>
          <dd class="kv__value mono">
            <?= e(fmt_datetime((string) $receipt['submitted_at'])) ?>
          </dd>
        </div>

        <div class="kv__row">
          <dt class="kv__key">Integrity Status</dt>
          <dd class="kv__value" style="color: var(--success); font-weight: 600; display: flex; align-items: center; gap: 6px;">
            <?= sv_icon('shield-check', 16) ?> Certified 1-Person-1-Vote Record
          </dd>
        </div>
      </dl>
    </div>
  </div>

  <div class="security-note" style="text-align: left; margin-bottom: var(--sp-6); width: 100%;">
    <?= sv_icon('shield-check', 18) ?>
    <span>
      <strong>Verification Guarantee:</strong> Your verification token serves as cryptographic proof that your ballot was accepted into the tally. Keep this token for your records.
    </span>
  </div>

  <div class="row row--center" style="gap: var(--sp-3); width: 100%;">
    <button class="btn btn--secondary" type="button" data-copy="#ballot-reference">
      <?= sv_icon('copy', 16, 'btn__icon') ?><span data-copy-label>Copy Token</span>
    </button>
    <button class="btn btn--secondary" type="button" onclick="window.print()">
      <?= sv_icon('results', 16, 'btn__icon') ?><span>Print Receipt</span>
    </button>
    <?php if ($electionId > 0): ?>
      <a class="btn btn--primary" href="<?= e(url('voter/results.php?election_id=' . $electionId)) ?>">
        <?= sv_icon('results', 16, 'btn__icon') ?><span>View Results Tally</span>
      </a>
    <?php endif; ?>
    <a class="btn btn--secondary" href="<?= e(url('voter/dashboard.php')) ?>">
      <?= sv_icon('dashboard', 16, 'btn__icon') ?><span>Return to Dashboard</span>
    </a>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
