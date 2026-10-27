<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$user = demo_require_user();
$pdo = demo_db();
$electionId = (int) ($_GET['election'] ?? $_POST['election'] ?? 0);
$election = demo_election($pdo, $electionId);
if (!$election) { http_response_code(404); exit('Election not found.'); }
if (demo_owned_ballot($pdo, (int) $user['id'], $electionId)) demo_redirect('results.php?election=' . $electionId);
$positions = demo_positions($pdo, $electionId);
$candidatesByPosition = [];
foreach ($positions as $position) $candidatesByPosition[(int) $position['id']] = demo_candidates($pdo, (int) $position['id']);
$errors = [];
$choices = [];
$review = false;
$pending = $_SESSION['pending_demo_ballot'] ?? null;
if (is_array($pending) && ($pending['election_id'] ?? null) === $electionId) $choices = $pending['choices'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    demo_check_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'review') {
        $raw = $_POST['choice'] ?? [];
        if (!is_array($raw)) $raw = [];
        $choices = [];
        foreach ($positions as $position) {
            $positionId = (int) $position['id'];
            $candidateId = filter_var($raw[$positionId] ?? null, FILTER_VALIDATE_INT);
            $validIds = array_map(static fn(array $candidate): int => (int) $candidate['id'], $candidatesByPosition[$positionId]);
            if (!$candidateId || !in_array($candidateId, $validIds, true)) $errors[$positionId] = 'Choose one candidate for this position.';
            else $choices[$positionId] = $candidateId;
        }
        if (!$errors) {
            $_SESSION['pending_demo_ballot'] = ['election_id' => $electionId, 'choices' => $choices];
            $review = true;
        }
    } elseif ($action === 'confirm') {
        if (!is_array($pending) || ($pending['election_id'] ?? null) !== $electionId || !is_array($pending['choices'] ?? null)) {
            $errors[0] = 'Your ballot review expired. Make your selections again.';
        } else {
            $choices = $pending['choices'];
            foreach ($positions as $position) {
                $positionId = (int) $position['id'];
                $validIds = array_map(static fn(array $candidate): int => (int) $candidate['id'], $candidatesByPosition[$positionId]);
                if (!isset($choices[$positionId]) || !in_array((int) $choices[$positionId], $validIds, true)) $errors[0] = 'Your ballot review expired. Make your selections again.';
            }
            if (!$errors) {
                try {
                    $pdo->beginTransaction();
                    $receipt = 'SV-SAMPLE-' . strtoupper(bin2hex(random_bytes(5)));
                    $insert = $pdo->prepare('INSERT INTO demo_ballots (user_id,election_id,receipt,submitted_at) VALUES (?,?,?,?)');
                    $insert->execute([(int) $user['id'], $electionId, $receipt, date('c')]);
                    $ballotId = (int) $pdo->lastInsertId();
                    $insertChoice = $pdo->prepare('INSERT INTO demo_ballot_choices (ballot_id,position_id,candidate_id) VALUES (?,?,?)');
                    foreach ($choices as $positionId => $candidateId) $insertChoice->execute([$ballotId, (int) $positionId, (int) $candidateId]);
                    $pdo->commit();
                    unset($_SESSION['pending_demo_ballot']);
                    demo_redirect('results.php?election=' . $electionId . '&submitted=1');
                } catch (Throwable $error) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if (demo_owned_ballot($pdo, (int) $user['id'], $electionId)) demo_redirect('results.php?election=' . $electionId);
                    error_log('Sample ballot submission failed: ' . $error->getMessage());
                    $errors[0] = 'We could not record your ballot. Please try again.';
                }
            }
        }
    }
}

demo_header('Ballot', 'dashboard');
?>
<div class="election-page"><a class="back-link" href="dashboard.php#elections">← Back to elections</a><div class="election-page-head"><span class="sample-badge">SAMPLE ELECTION</span><h1><?= demo_e($election['title']) ?></h1><p><?= demo_e($election['description']) ?></p><div class="election-meta"><span><?= count($positions) ?> positions</span><span>One candidate per position</span><span>One ballot per account</span></div></div>
<?php if (isset($errors[0])): ?><div class="alert" role="alert"><?= demo_e($errors[0]) ?></div><?php endif; ?>
<?php if ($review): ?>
  <section class="review-panel" aria-labelledby="review-title"><span class="eyebrow">REVIEW YOUR BALLOT</span><h2 id="review-title">Check each choice before submitting.</h2><p>Once submitted, this ballot cannot be changed or submitted again.</p><div class="review-list">
    <?php foreach ($positions as $position): $positionId = (int) $position['id']; foreach ($candidatesByPosition[$positionId] as $candidate) { if ((int) $candidate['id'] === (int) $choices[$positionId]) { $selected = $candidate; break; } } ?>
      <div><span><?= demo_e($position['title']) ?></span><strong><?= demo_e($selected['name']) ?></strong><small><?= demo_e($selected['slate']) ?></small></div>
    <?php endforeach; ?>
  </div><div class="review-actions"><a class="btn btn-outline" href="ballot.php?election=<?= $electionId ?>">Change choices</a><form method="post" action="ballot.php?election=<?= $electionId ?>"><input type="hidden" name="csrf" value="<?= demo_e(demo_csrf()) ?>"><input type="hidden" name="election" value="<?= $electionId ?>"><input type="hidden" name="action" value="confirm"><button class="btn btn-primary" type="submit">Submit ballot →</button></form></div></section>
<?php else: ?>
  <form method="post" action="ballot.php?election=<?= $electionId ?>" class="ballot-form"><input type="hidden" name="csrf" value="<?= demo_e(demo_csrf()) ?>"><input type="hidden" name="election" value="<?= $electionId ?>"><input type="hidden" name="action" value="review">
    <?php foreach ($positions as $position): $positionId = (int) $position['id']; ?>
      <fieldset class="position-card"><legend><span class="eyebrow">POSITION <?= (int) $position['display_order'] ?> OF <?= count($positions) ?></span><strong><?= demo_e($position['title']) ?></strong><small><?= demo_e($position['description']) ?></small></legend><?php if (isset($errors[$positionId])): ?><p class="position-error" role="alert"><?= demo_e($errors[$positionId]) ?></p><?php endif; ?><div class="candidate-grid">
        <?php foreach ($candidatesByPosition[$positionId] as $candidate): ?><label class="candidate-card"><input type="radio" name="choice[<?= $positionId ?>]" value="<?= (int) $candidate['id'] ?>" <?= isset($choices[$positionId]) && (int) $choices[$positionId] === (int) $candidate['id'] ? 'checked' : '' ?>><span class="candidate-content"><span class="candidate-avatar" aria-hidden="true"><?= demo_e(strtoupper(substr($candidate['name'], 0, 1))) ?></span><strong><?= demo_e($candidate['name']) ?></strong><small><?= demo_e($candidate['slate']) ?> · <?= demo_e($candidate['year_level']) ?></small><span class="candidate-statement"><?= demo_e($candidate['statement']) ?></span></span></label><?php endforeach; ?>
      </div></fieldset>
    <?php endforeach; ?>
    <div class="ballot-bottom"><p>Review all positions before recording your sample ballot.</p><button class="btn btn-primary" type="submit">Review my choices →</button></div>
  </form>
<?php endif; ?>
</div>
<?php demo_footer(); ?>
