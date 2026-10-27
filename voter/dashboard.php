<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/dashboard.php
 *
 * The voter's home screen (2026 Edition):
 *   - Personal Voter Identity & Voter Code Card
 *   - Active Elections open for voting
 *   - Cast Ballots History with Verification Tokens
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';
require_once __DIR__ . '/../includes/engagement.php';

$voter  = require_voter();
$pdo    = db();
$userId = (int) $voter['id'];

// Load elections and this voter's ballot submissions
$stmt = $pdo->prepare(
    "SELECT e.*,
            ve.id AS eligibility_id,
            b.id AS ballot_id,
            b.ballot_reference,
            b.submitted_at AS ballot_submitted_at,
            (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.status = 'active') AS candidate_count
       FROM elections e
       LEFT JOIN voter_eligibility ve ON ve.election_id = e.id AND ve.user_id = :eligibility_user_id
       LEFT JOIN ballots b ON b.election_id = e.id AND b.voter_id = :user_id AND b.status = 'submitted'
      WHERE e.status IN ('open', 'scheduled', 'closed', 'archived')
      ORDER BY FIELD(e.status, 'open', 'scheduled', 'closed', 'archived'), e.start_datetime DESC"
);
$stmt->execute([':eligibility_user_id' => $userId, ':user_id' => $userId]);
$elections = $stmt->fetchAll();

$awaiting = [];
$voted    = [];
$assignedElectionCount = 0;

foreach ($elections as $election) {
    if (!empty($election['eligibility_id'])) {
        $assignedElectionCount++;
    }
    $status = election_effective_status($election);
    $hasVoted = !empty($election['ballot_reference']);

    if ($hasVoted) {
        $voted[] = $election;
    } elseif ($status === 'open' && !empty($election['eligibility_id'])) {
        $awaiting[] = $election;
    }
}

$pageTitle  = 'Your dashboard';
$activeNav  = 'dashboard.php';
$breadcrumb = [['label' => 'Dashboard', 'href' => 'voter/dashboard.php']];

$reminderCount=count(sv_reminders(sv_engagement_db(),$userId));
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-resources" aria-labelledby="dashboard-resources-title">
  <div class="dashboard-resources__intro">
    <h2 id="dashboard-resources-title"><?= e(sv_t("Voting resources")) ?></h2>
    <p><?= e(sv_t("Read the election guide or check your reminders before voting.")) ?></p>
  </div>
  <nav class="dashboard-resources__links" aria-label="<?= e(sv_t("Voting resources")) ?>">
    <a class="btn btn--secondary dashboard-resource" href="<?= e(url('guide.php')) ?>"><?= sv_icon('file', 18) ?><span><?= e(sv_t("Election guide")) ?></span></a>
    <a class="btn btn--secondary dashboard-resource" href="<?= e(url('voter/reminders.php')) ?>"><?= sv_icon('bell', 18) ?><span><?= e(sv_t("Reminders")) ?></span><span class="dashboard-resource__count" aria-label="<?= sprintf(sv_t('%d reminders'), (int) $reminderCount) ?>"><?= (int) $reminderCount ?></span></a>
  </nav>
</section>
<?php
?>

<section class="voter-welcome" aria-labelledby="voter-welcome-title">
  <div class="voter-welcome__main">
    <h2 id="voter-welcome-title"><?= sprintf(sv_t("Hi, %s!"), e((string) $voter['first_name'])) ?> <span><?= e(sv_t("Your vote starts here.")) ?></span></h2>
    <p>
      <?php if ($awaiting !== []): ?>
        <?= e(sprintf(count($awaiting) === 1 ? sv_t('%d election is ready for your vote. Take a look and choose when you are ready.') : sv_t('%d elections are ready for your vote. Take a look and choose when you are ready.'), count($awaiting))) ?>
      <?php elseif ($assignedElectionCount === 0): ?>
        <?= e(sv_t("Your account is ready. An election administrator will assign the elections you can vote in.")) ?>
      <?php else: ?>
        <?= e(sv_t("You are all caught up for now. Explore elections or check the receipts from ballots you have submitted.")) ?>
      <?php endif; ?>
    </p>
    <div class="voter-welcome__actions">
      <?php if ($awaiting !== []): ?>
        <a class="btn btn--primary" href="#ready-to-vote"><?= e(sv_t("See open elections")) ?> <?= sv_icon('arrow-right', 16) ?></a>
      <?php else: ?>
        <a class="btn btn--primary" href="<?= e(url('voter/elections.php')) ?>"><?= e(sv_t("Explore elections")) ?> <?= sv_icon('arrow-right', 16) ?></a>
      <?php endif; ?>
      <a class="btn btn--secondary" href="<?= e(url('voter/profile.php')) ?>"><?= e(sv_t("My profile")) ?></a>
    </div>
  </div>
  <div class="voter-pass">
    <div class="voter-pass__top">
      <span><?= e(sv_t("Voter pass")) ?></span>
      <?= sv_logo_mark(28) ?>
    </div>
    <div class="voter-pass__identity">
      <span class="avatar avatar--lg" aria-hidden="true"><?= e(initials($voter['first_name'], $voter['last_name'])) ?></span>
      <div><strong><?= e(user_full_name($voter)) ?></strong><small>@<?= e($voter['username']) ?></small></div>
    </div>
    <div class="voter-pass__code">
      <div><small><?= e(sv_t("Your voter code")) ?></small><strong class="mono" id="my-voter-code"><?= e((string) ($voter['voter_code'] ?? '')) ?></strong></div>
      <button class="icon-btn" type="button" data-copy="#my-voter-code" aria-label="<?= e(sv_t("Copy voter code")) ?>"><?= sv_icon('copy', 17) ?></button>
    </div>
    <span class="voter-pass__verified"><?= sv_icon('check-circle', 15) ?> <?= e(sv_t("Active account")) ?></span>
  </div>
</section>

<div class="voter-snapshot" aria-label="<?= e(sv_t("Your voting summary")) ?>">
  <div><strong><?= e((string) count($awaiting)) ?></strong><span><?= e(sv_t("Ready for your vote")) ?></span></div>
  <div><strong><?= e((string) count($voted)) ?></strong><span><?= e(sv_t("Ballots submitted")) ?></span></div>
  <div><strong><?= e((string) count($elections)) ?></strong><span><?= e(sv_t("Elections on the registry")) ?></span></div>
</div>

<section class="student-section" id="ready-to-vote" aria-labelledby="ready-to-vote-heading">
  <div class="student-section__heading">
    <div>
      <h2 id="ready-to-vote-heading"><?= e(sv_t("Ready for your vote")) ?></h2>
      <p><?= e(sv_t("See what is open and cast your ballot when you are ready.")) ?></p>
    </div>
    <?php if ($awaiting !== []): ?><span class="badge badge--success"><?= sprintf(sv_t('%d open'), count($awaiting)) ?></span><?php endif; ?>
  </div>
  <?php if ($awaiting === []): ?>
    <div class="student-empty">
      <span aria-hidden="true"><?= sv_icon('check-circle', 28) ?></span>
      <div>
        <h3><?= e($assignedElectionCount === 0 ? sv_t("No elections assigned yet") : sv_t("Nothing waiting for your vote")) ?></h3>
        <p><?= e($assignedElectionCount === 0
            ? sv_t("An election administrator will assign the elections you can vote in.")
            : sv_t("New elections will appear here when they open.")) ?></p>
      </div>
      <a class="btn btn--secondary" href="<?= e(url('voter/elections.php')) ?>"><?= e(sv_t("All elections")) ?></a>
    </div>
  <?php else: ?>
    <div class="voter-election-list">
      <?php foreach ($awaiting as $election): ?>
        <article class="voter-election">
          <div class="voter-election__body">
            <span class="badge badge--success"><?= sv_icon('vote', 13) ?> <?= e(sv_t("Voting open")) ?></span>
            <h3><?= e($election['title']) ?></h3>
            <p><?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?></p>
            <span class="voter-election__meta"><?= sprintf(sv_t('%d positions'), (int) $election['position_count']) ?> &middot; <?= e(countdown_text($election)) ?></span>
          </div>
          <div class="voter-election__actions">
            <a class="btn btn--primary" href="<?= e(url('voter/ballot.php?election_id=' . (int) $election['id'])) ?>"><?= e(sv_t("Open ballot")) ?> <?= sv_icon('arrow-right', 16) ?></a>
            <a class="btn btn--ghost" href="<?= e(url('voter/elections.php?election_id=' . (int) $election['id'])) ?>"><?= e(sv_t("Election details")) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($voted !== []): ?>
<section class="student-section" aria-labelledby="voted-heading">
  <div class="student-section__heading"><div><h2 id="voted-heading"><?= e(sv_t("Your past votes")) ?></h2><p><?= e(sv_t("Keep these receipt codes for your records.")) ?></p></div></div>
  <div class="receipt-list">
    <?php foreach ($voted as $election): ?>
      <article class="receipt-item">
        <span class="receipt-item__icon" aria-hidden="true"><?= sv_icon('check-circle', 22) ?></span>
        <div class="receipt-item__main">
          <h3><?= e($election['title']) ?></h3>
          <p><?= sprintf(sv_t('Submitted %s'), e(fmt_datetime((string) $election['ballot_submitted_at']))) ?></p>
          <code><?= e((string) $election['ballot_reference']) ?></code><a class="link" href="<?= e(url('voter/confirmation.php?election_id=' . (int)$election['id'])) ?>"><?= e(sv_t('View receipt')) ?></a>
        </div>
        <a class="btn btn--secondary btn--sm" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>"><?= e(sv_t("View results")) ?></a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
