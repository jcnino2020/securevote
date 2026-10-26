<?php
/** Aggregate election charts. Caller supplies $chartElectionId, $chartEligible,
 * $chartBallots and $chartPositions. No voter or ballot identity is queried. */
$chartTurnout = $chartEligible > 0 ? min(100, percent($chartBallots, $chartEligible)) : 0;
$chartRemaining = max(0, $chartEligible - $chartBallots);
$chartDailyStmt = db()->prepare(
    "SELECT DATE(submitted_at) AS vote_day, COUNT(*) AS ballot_total
       FROM ballots
      WHERE election_id = ? AND status = 'submitted'
      GROUP BY DATE(submitted_at)
      ORDER BY vote_day DESC
      LIMIT 10"
);
$chartDailyStmt->execute([$chartElectionId]);
$chartDaily = array_reverse($chartDailyStmt->fetchAll());
$chartPeak = 0;
foreach ($chartDaily as $chartDay) {
    $chartPeak = max($chartPeak, (int) $chartDay['ballot_total']);
}
?>
<section class="result-visuals" aria-label="Election results charts">
  <div class="result-visuals__panel">
    <div class="result-visuals__heading">
      <h2>Participation</h2>
      <p>Submitted ballots compared with the eligible voter list.</p>
    </div>
    <?php if ($chartEligible > 0): ?>
      <div class="result-visuals__turnout">
        <div class="result-donut" role="img" aria-label="<?= e((string) $chartTurnout) ?> percent turnout, <?= e((string) $chartBallots) ?> of <?= e((string) $chartEligible) ?> eligible voters"
             style="--result-share: <?= e((string) $chartTurnout) ?>%">
          <span><strong><?= e((string) $chartTurnout) ?>%</strong><small>turnout</small></span>
        </div>
        <dl class="result-visuals__legend">
          <div><dt><i class="result-dot result-dot--cast"></i> Voted</dt><dd><?= e((string) $chartBallots) ?></dd></div>
          <div><dt><i class="result-dot result-dot--remaining"></i> Not yet voted</dt><dd><?= e((string) $chartRemaining) ?></dd></div>
        </dl>
      </div>
    <?php else: ?>
      <p class="result-visuals__empty">No eligible voter count is available for this election.</p>
    <?php endif; ?>
  </div>
  <div class="result-visuals__panel">
    <div class="result-visuals__heading">
      <h2>Ballot activity</h2>
      <p><?= $chartDaily === [] ? 'Daily totals will appear after the first ballot.' : 'Submitted ballots on the most recent ' . count($chartDaily) . ' voting day' . (count($chartDaily) === 1 ? '' : 's') . '.' ?></p>
    </div>
    <?php if ($chartDaily !== []): ?>
      <div class="result-days" role="img" aria-label="<?= e(implode(', ', array_map(static fn (array $day): string => date('M j', strtotime((string) $day['vote_day'])) . ': ' . (int) $day['ballot_total'] . ' ballots', $chartDaily))) ?>">
        <?php foreach ($chartDaily as $chartDay): ?>
          <?php $chartCount = (int) $chartDay['ballot_total']; ?>
          <div class="result-days__day" title="<?= e(date('M j, Y', strtotime((string) $chartDay['vote_day']))) ?>: <?= e((string) $chartCount) ?> ballots">
            <strong><?= e((string) $chartCount) ?></strong>
            <span class="result-days__track"><span style="height: <?= e((string) max(6, (int) round(100 * $chartCount / max(1, $chartPeak)))) ?>%"></span></span>
            <small><?= e(date('M j', strtotime((string) $chartDay['vote_day']))) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="result-visuals__empty">No ballots have been submitted yet.</p>
    <?php endif; ?>
  </div>
</section>
<?php if ($chartPositions !== []): ?>
<section class="result-visuals__panel result-visuals__panel--wide" aria-labelledby="position-participation-heading">
  <div class="result-visuals__heading">
    <h2 id="position-participation-heading">Choices by position</h2>
    <p>How many submitted ballots included a choice for each position.</p>
  </div>
  <div class="result-position-chart">
    <?php foreach ($chartPositions as $chartPosition): ?>
      <?php $chartChoices = min($chartBallots, (int) $chartPosition['ballots_with_choice']); ?>
      <div class="result-position-chart__row">
        <span><?= e((string) $chartPosition['position']['name']) ?></span>
        <div class="result-position-chart__track" role="img" aria-label="<?= e((string) $chartPosition['position']['name']) ?>: <?= e((string) $chartChoices) ?> of <?= e((string) $chartBallots) ?> ballots included a choice">
          <span style="width: <?= e((string) ($chartBallots > 0 ? min(100, percent($chartChoices, $chartBallots)) : 0)) ?>%"></span>
        </div>
        <strong><?= e((string) $chartChoices) ?> / <?= e((string) $chartBallots) ?></strong>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
