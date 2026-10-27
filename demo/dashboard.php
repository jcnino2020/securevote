<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$user = demo_require_user();
$pdo = demo_db();
$elections = demo_elections_for_user($pdo, (int) $user['id']);
$votedCount = count(array_filter($elections, static fn(array $election): bool => $election['ballot_id'] !== null));
demo_header('Dashboard', 'dashboard');
?>
<div class="dashboard">
  <div class="dashboard-intro"><div><span class="eyebrow">YOUR VOTING SPACE</span><h1>Hi, <?= demo_e($user['first_name']) ?>.</h1><p>Explore the sample elections below, review the candidates, and submit one ballot in each election.</p></div><span class="active-badge">● &nbsp; Active account</span></div>
  <div class="dashboard-summary" aria-label="Voting summary"><div><strong><?= count($elections) - $votedCount ?></strong><span>Ready to vote</span></div><div><strong><?= $votedCount ?></strong><span>Ballots submitted</span></div><div><strong><?= count($elections) ?></strong><span>Sample elections</span></div></div>
  <section class="elections-section" id="elections" aria-labelledby="elections-title"><div class="section-heading"><span class="eyebrow">AVAILABLE NOW</span><h2 id="elections-title">Sample elections</h2><p>Fictional candidates and tallies are used here. These ballots do not affect official SecureVote elections.</p></div><div class="elections-grid">
    <?php foreach ($elections as $election): ?>
      <article class="election-card"><div class="election-card-top"><span class="sample-badge">SAMPLE ELECTION</span><span class="<?= $election['ballot_id'] ? 'status-voted' : 'status-open' ?>"><?= $election['ballot_id'] ? 'Ballot submitted' : 'Voting open' ?></span></div><span class="election-category"><?= demo_e($election['category']) ?></span><h3><?= demo_e($election['title']) ?></h3><p><?= demo_e($election['description']) ?></p><div class="election-facts"><span><?= (int) $election['position_count'] ?> positions</span><span><?= (int) $election['candidate_count'] ?> candidates</span><span><?= (int) $election['baseline_ballots'] ?> sample baseline ballots</span></div><div class="election-card-actions"><?php if ($election['ballot_id']): ?><a class="btn btn-primary" href="results.php?election=<?= (int) $election['id'] ?>">View results →</a><span class="receipt-mini">Receipt <?= demo_e($election['receipt']) ?></span><?php else: ?><a class="btn btn-primary" href="ballot.php?election=<?= (int) $election['id'] ?>">Open ballot →</a><a class="text-link" href="ballot.php?election=<?= (int) $election['id'] ?>">Meet candidates</a><?php endif; ?></div></article>
    <?php endforeach; ?>
  </div></section>
  <section class="account-panel" aria-labelledby="account-title"><div><span class="eyebrow">YOUR ACCOUNT</span><h2 id="account-title"><?= demo_e($user['first_name'] . ' ' . $user['last_name']) ?></h2><p>@<?= demo_e($user['username']) ?> · <?= demo_e($user['email']) ?></p></div><span class="active-badge">Authenticated session</span></section>
</div>
<?php demo_footer(); ?>
