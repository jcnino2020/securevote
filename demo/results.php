<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$user = demo_require_user();
$pdo = demo_db();
$electionId = (int) ($_GET['election'] ?? 0);
if ($electionId === 0) {
    $elections = demo_elections_for_user($pdo, (int) $user['id']);
    demo_header('Results', 'results');
    ?><div class="results-index"><span class="eyebrow">ELECTION RESULTS</span><h1>Results and receipts</h1><p>Detailed totals become available after you submit a ballot in that election.</p><div class="elections-grid"><?php foreach ($elections as $election): ?><article class="election-card"><span class="sample-badge">SAMPLE ELECTION</span><h2><?= demo_e($election['title']) ?></h2><p><?= demo_e($election['description']) ?></p><div class="election-card-actions"><?php if ($election['ballot_id']): ?><a class="btn btn-primary" href="results.php?election=<?= (int) $election['id'] ?>">View detailed results →</a><?php else: ?><a class="btn btn-outline" href="ballot.php?election=<?= (int) $election['id'] ?>">Vote to unlock results →</a><?php endif; ?></div></article><?php endforeach; ?></div></div><?php
    demo_footer();
    exit;
}
$election = demo_election($pdo, $electionId);
if (!$election) { http_response_code(404); exit('Election not found.'); }
$ballot = demo_owned_ballot($pdo, (int) $user['id'], $electionId);
if (!$ballot) demo_redirect('ballot.php?election=' . $electionId);
$submittedCountStmt = $pdo->prepare('SELECT COUNT(*) FROM demo_ballots WHERE election_id = ?');
$submittedCountStmt->execute([$electionId]);
$submittedCount = (int) $submittedCountStmt->fetchColumn();
$totalBallots = (int) $election['baseline_ballots'] + $submittedCount;
$positions = demo_positions($pdo, $electionId);
$tally = $pdo->prepare('SELECT c.*, c.baseline_votes + COUNT(bc.ballot_id) AS total_votes FROM demo_candidates c LEFT JOIN demo_ballot_choices bc ON bc.candidate_id = c.id WHERE c.position_id = ? GROUP BY c.id ORDER BY total_votes DESC, c.display_order');
demo_header('Results', 'results');
?>
<div class="results-page"><a class="back-link" href="dashboard.php#elections">← Back to elections</a>
  <?php if (isset($_GET['submitted'])): ?><div class="submitted-banner" role="status"><span>✓</span><div><strong>Your ballot was recorded.</strong><p>Save the receipt below. Your choices cannot be changed or submitted again.</p></div></div><?php endif; ?>
  <div class="election-page-head"><span class="sample-badge">SAMPLE RESULTS</span><h1><?= demo_e($election['title']) ?></h1><p>Aggregate candidate totals for this fictional election. Results include seeded sample ballots and ballots submitted on this separate site.</p></div>
  <div class="result-summary"><div><strong><?= $totalBallots ?></strong><span>Total sample ballots</span></div><div><strong><?= (int) $election['baseline_ballots'] ?></strong><span>Fictional baseline</span></div><div><strong><?= $submittedCount ?></strong><span>Submitted here</span></div></div>
  <section class="receipt-panel" aria-labelledby="receipt-title"><div><span class="eyebrow">YOUR VOTING RECEIPT</span><h2 id="receipt-title"><?= demo_e($ballot['receipt']) ?></h2><p>Recorded <?= demo_e(date('M j, Y · g:i A', strtotime($ballot['submitted_at']))) ?>. This receipt confirms participation without listing your candidate choices.</p></div><span class="receipt-check">✓ Recorded</span></section>
  <div class="result-positions">
    <?php foreach ($positions as $position): $tally->execute([(int) $position['id']]); $candidates = $tally->fetchAll(); $lead = (int) ($candidates[0]['total_votes'] ?? 0); ?>
      <section class="result-position" aria-labelledby="position-<?= (int) $position['id'] ?>"><div class="result-position-head"><div><span class="eyebrow">POSITION <?= (int) $position['display_order'] ?></span><h2 id="position-<?= (int) $position['id'] ?>"><?= demo_e($position['title']) ?></h2><p><?= demo_e($position['description']) ?></p></div><span><?= $totalBallots ?> ballots</span></div><div class="result-candidates">
        <?php foreach ($candidates as $candidate): $votes = (int) $candidate['total_votes']; $percent = $totalBallots > 0 ? round(100 * $votes / $totalBallots, 1) : 0; ?>
          <div class="result-candidate"><div class="result-candidate-top"><div><strong><?= demo_e($candidate['name']) ?></strong><small><?= demo_e($candidate['slate']) ?> · <?= demo_e($candidate['year_level']) ?></small></div><div class="result-score"><strong><?= $votes ?> votes</strong><span><?= number_format($percent, 1) ?>%</span></div></div><meter class="result-bar" min="0" max="<?= $totalBallots ?>" value="<?= $votes ?>" aria-label="<?= demo_e($candidate['name']) ?>: <?= $votes ?> votes, <?= number_format($percent, 1) ?> percent"><?= number_format($percent, 1) ?>%</meter><?php if ($votes === $lead): ?><span class="leader-label"><?= count(array_filter($candidates, static fn(array $row): bool => (int) $row['total_votes'] === $lead)) > 1 ? 'Tied lead' : 'Leading candidate' ?></span><?php endif; ?></div>
        <?php endforeach; ?>
      </div></section>
    <?php endforeach; ?>
  </div>
  <div class="result-footnote"><strong>About these totals</strong><p>The fictional baseline makes results useful to explore before many people vote here. Each new ballot adds one vote per position. This page shows only aggregate totals; it does not reveal how any account voted. No official SecureVote election data is used.</p></div>
</div>
<?php demo_footer(); ?>
