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

<style>
  .receipt-paper { display: none; }
  @media print {
    @page { size: A4 portrait; margin: 16mm; }
    html, body, body:has(.receipt-print), body:has(.receipt-print) .shell,
    body:has(.receipt-print) .content {
      background: #fff !important;
      color: #1c285e !important;
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
      min-height: 0 !important;
      display: block !important;
    }
    .receipt-print, .vote-progress, .skip-link, #traffic-config { display: none !important; }
    .receipt-paper {
      display: block !important;
      width: 100%;
      max-width: 178mm;
      margin: 0 auto;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 10pt;
      line-height: 1.5;
      color: #25304d;
      break-inside: avoid;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .receipt-paper * { box-sizing: border-box; }
    .receipt-paper__header {
      border-top: 9px solid #1c285e;
      border-bottom: 1px solid #dbe0ed;
      padding: 22px 0 25px;
    }
    .receipt-paper__brand {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 20px;
      color: #1c285e;
      font-size: 12pt;
      font-weight: 800;
    }
    .receipt-paper__brand span:last-child {
      color: #59678b;
      font-size: 8pt;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
    }
    .receipt-paper h1 {
      margin: 32px 0 4px;
      color: #1c285e;
      font-size: 27pt;
      line-height: 1.15;
      letter-spacing: -.03em;
    }
    .receipt-paper__intro { margin: 0; color: #52607a; font-size: 10pt; }
    .receipt-paper__status {
      display: inline-block;
      margin-top: 18px;
      padding: 5px 10px;
      border: 1px solid #22836b;
      color: #176a57;
      font-size: 8pt;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    .receipt-paper__section { padding-top: 30px; }
    .receipt-paper__label {
      display: block;
      margin-bottom: 5px;
      color: #65718a;
      font-size: 8pt;
      font-weight: 700;
      letter-spacing: .09em;
      text-transform: uppercase;
    }
    .receipt-paper__election {
      margin: 0;
      color: #1c285e;
      font-size: 17pt;
      font-weight: 700;
      line-height: 1.3;
    }
    .receipt-paper__token {
      margin-top: 26px;
      padding: 20px 22px;
      border: 1px solid #bac5e2;
      border-left: 5px solid #3647b7;
    }
    .receipt-paper__token strong {
      display: block;
      color: #1c285e;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 19pt;
      letter-spacing: .045em;
      line-height: 1.25;
      overflow-wrap: anywhere;
    }
    .receipt-paper__details { margin: 25px 0 0; }
    .receipt-paper__details div {
      display: flex;
      justify-content: space-between;
      gap: 24px;
      padding: 11px 0;
      border-bottom: 1px solid #e2e6ef;
    }
    .receipt-paper__details dt { color: #65718a; }
    .receipt-paper__details dd {
      margin: 0;
      color: #25304d;
      font-weight: 700;
      text-align: right;
    }
    .receipt-paper__note {
      margin-top: 31px;
      padding-top: 16px;
      border-top: 1px solid #dbe0ed;
      color: #52607a;
      font-size: 9pt;
    }
    .receipt-paper__note strong { color: #1c285e; }
    .receipt-paper__footer {
      margin-top: 65px;
      padding-top: 11px;
      border-top: 1px solid #dbe0ed;
      color: #68738f;
      font-size: 8pt;
      font-weight: 700;
      letter-spacing: .07em;
      text-transform: uppercase;
    }
  }
</style>

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

<section class="receipt-paper" aria-label="<?= e(sv_t('Printable voting receipt')) ?>">
  <header class="receipt-paper__header">
    <div class="receipt-paper__brand">
      <span>SecureVote</span>
      <span><?= e(sv_t('Voting receipt')) ?></span>
    </div>
    <h1><?= e(sv_t('Ballot recorded')) ?></h1>
    <p class="receipt-paper__intro"><?= e(sv_t('Confirmation of your submitted ballot')) ?></p>
    <span class="receipt-paper__status"><?= e(sv_t('Submission verified')) ?></span>
  </header>

  <div class="receipt-paper__section">
    <span class="receipt-paper__label"><?= e(sv_t('Election')) ?></span>
    <p class="receipt-paper__election"><?= e($electionTitle) ?></p>
  </div>

  <div class="receipt-paper__token">
    <span class="receipt-paper__label"><?= e(sv_t('Verification code')) ?></span>
    <strong><?= e((string) $receipt['reference']) ?></strong>
  </div>

  <dl class="receipt-paper__details">
    <?php if (!empty($receipt['voter_name'])): ?>
      <div><dt><?= e(sv_t('Voter')) ?></dt><dd><?= e((string) $receipt['voter_name']) ?></dd></div>
    <?php endif; ?>
    <div><dt><?= e(sv_t('Submitted')) ?></dt><dd><?= e(fmt_datetime((string) $receipt['submitted_at'])) ?> (PHT)</dd></div>
    <div><dt><?= e(sv_t('Record status')) ?></dt><dd><?= e(sv_t('One submitted ballot for this election')) ?></dd></div>
  </dl>

  <p class="receipt-paper__note">
    <strong><?= e(sv_t('Keep this code for your records.')) ?></strong>
    <?= e(sv_t('Use Check a receipt in SecureVote to confirm that this ballot is recorded. This receipt does not show your candidate choices or certify the election result.')) ?>
  </p>
  <footer class="receipt-paper__footer">SecureVote / <?= e(sv_t('Ballot submission record')) ?></footer>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
