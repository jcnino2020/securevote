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

$user = require_voter();
$pdo = db();
$reqElectionId = int_input('election_id', 0);
$sql = "SELECT b.ballot_reference AS reference, b.submitted_at, e.title AS election_title, e.id AS election_id
 FROM ballots b JOIN elections e ON e.id=b.election_id
 WHERE b.voter_id=? AND b.status='submitted'";
$params=[(int)$user['id']];
if ($reqElectionId>0) { $sql.=' AND b.election_id=?'; $params[]=$reqElectionId; }
$sql.=' ORDER BY b.submitted_at DESC,b.id DESC LIMIT 1';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $receipt=$stmt->fetch();
if (!$receipt) sv_error_page(404, sv_t('Receipt unavailable'), sv_t('No submitted ballot was found for your account in this election.'), [['label'=>sv_t('Back to elections'),'href'=>url('voter/elections.php')]]);
$receipt['voter_name']=user_full_name($user);
$electionId=(int)$receipt['election_id']; $electionTitle=(string)$receipt['election_title'];

$pageTitle = sv_t('Ballot Recorded');
$activeNav = 'elections.php';
$breadcrumb   = [
    ['label' => sv_t('Dashboard'), 'href' => 'voter/dashboard.php'],
    ['label' => sv_t('Elections'), 'href' => 'voter/elections.php'],
    ['label' => sv_t('Receipt')],
];

require __DIR__ . '/../includes/header.php';
?>

<nav class="vote-progress" aria-label="<?= e(sv_t('Voting progress')) ?>">
  <ol>
    <li class="is-complete"><span><?= sv_icon('check', 15) ?></span> <?= e(sv_t("Choose")) ?></li>
    <li class="is-complete"><span><?= sv_icon('check', 15) ?></span> <?= e(sv_t("Review")) ?></li>
    <li class="is-current" aria-current="step"><span>3</span> <?= e(sv_t("Receipt")) ?></li>
  </ol>
</nav>

<section class="confirmation receipt-print" aria-labelledby="confirmation-title" style="max-width: 600px; margin: 0 auto; text-align: center;">
  <div class="confirmation__icon">
    <?= sv_icon('check-circle', 36) ?>
  </div>

  <h1 class="confirmation__title" id="confirmation-title">
    <?= e(sv_t("Your vote is in!")) ?>
  </h1>
  <p class="confirmation__text">
    <?= e(sv_t("Your ballot has been recorded. Save the receipt code below for your records.")) ?> <a class="link" href="<?= e(url('verify.php')) ?>"><?= e(sv_t("Check a receipt")) ?></a>
  </p>

  <div class="card" style="text-align: left; width: 100%; margin-bottom: var(--sp-6);">
    <div class="card__header">
      <div class="card__headings">
        <h3 class="card__title" style="font-size: 1.05rem;"><?= e($electionTitle) ?></h3>
        <p class="card__subtitle"><?= e(sv_t("Official Digital Voting Receipt")) ?></p>
      </div>
      <span class="badge badge--success"><?= sv_icon('shield-check', 14) ?> <?= e(sv_t("1-Vote Verified")) ?></span>
    </div>

    <div class="card__body">
      <dl class="kv">
        <div class="kv__row">
          <dt class="kv__key"><?= e(sv_t("Verification Token")) ?></dt>
          <dd class="kv__value">
            <span class="reference-code" id="ballot-reference">
              <?= e((string) $receipt['reference']) ?>
            </span>
          </dd>
        </div>

        <?php if (!empty($receipt['voter_name'])): ?>
          <div class="kv__row">
            <dt class="kv__key"><?= e(sv_t("Voter Identity")) ?></dt>
            <dd class="kv__value" style="font-weight: 600;">
              <?= e((string) $receipt['voter_name']) ?>
            </dd>
          </div>
        <?php endif; ?>



        <div class="kv__row">
          <dt class="kv__key"><?= e(sv_t("Submission Timestamp")) ?></dt>
          <dd class="kv__value mono">
            <?= e(fmt_datetime((string) $receipt['submitted_at'])) ?>
          </dd>
        </div>

        <div class="kv__row">
          <dt class="kv__key"><?= e(sv_t("Integrity Status")) ?></dt>
          <dd class="kv__value" style="color: var(--success); font-weight: 600; display: flex; align-items: center; gap: 6px;">
            <?= sv_icon('shield-check', 16) ?> <?= e(sv_t("Certified 1-Person-1-Vote Record")) ?>
          </dd>
        </div>
      </dl>
    </div>
  </div>

  <p class="receipt-print-help"><?= e(sv_t("Print or choose Save as PDF in your browser. This receipt confirms submission and does not include your candidate choices.")) ?></p>
  <div class="row row--center" style="gap: var(--sp-3); width: 100%;">
    <button class="btn btn--secondary" type="button" data-copy="#ballot-reference">
      <?= sv_icon('copy', 16, 'btn__icon') ?><span data-copy-label><?= e(sv_t("Copy Token")) ?></span>
    </button>
    <button class="btn btn--secondary" type="button" data-print-receipt>
      <?= sv_icon('results', 16, 'btn__icon') ?><span><?= e(sv_t("Print / Save PDF")) ?></span>
    </button>
    <?php if ($electionId > 0): ?>
      <a class="btn btn--primary" href="<?= e(url('voter/results.php?election_id=' . $electionId)) ?>">
        <?= sv_icon('results', 16, 'btn__icon') ?><span><?= e(sv_t("View Results Tally")) ?></span>
      </a>
    <?php endif; ?>
    <a class="btn btn--secondary" href="<?= e(url('voter/dashboard.php')) ?>">
      <?= sv_icon('dashboard', 16, 'btn__icon') ?><span><?= e(sv_t("Return to Dashboard")) ?></span>
    </a>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
