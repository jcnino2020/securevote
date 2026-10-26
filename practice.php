<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/voting.php';
require_once __DIR__ . '/includes/community.php';
$ballot = sv_practice_ballot(); $errors = []; $plan = []; $stage = 'choose';
if (is_post()) {
    require_csrf();
    $action = input('action', 'review');
    $result = sv_validate_submission($ballot, $_POST['votes'] ?? []);
    $errors = $result['errors']; $plan = $result['plan'];
    if ($result['ok'] && $action === 'review') $stage = 'review';
    if ($result['ok'] && $action === 'finish') $stage = 'done';
}
$pageTitle = 'Practice ballot'; $activeNav = 'practice.php';
require __DIR__ . '/includes/header.php';
?>
<div class="feature-page feature-page--narrow stack"><div class="feature-intro"><span class="badge badge--warning">Practice only</span><h1 class="visually-hidden">Try voting before election day</h1><p>These candidates are fictional. Practice choices are never saved to the database and do not affect your eligibility or real votes.</p></div>
<ol class="practice-steps" aria-label="Practice progress"><?php foreach (['choose' => 'Choose', 'review' => 'Review', 'done' => 'Finish'] as $key => $label): ?><li <?= $stage === $key ? 'aria-current="step"' : '' ?>><?= e($label) ?></li><?php endforeach; ?></ol>
<?php sv_feature_errors($errors); ?>
<?php if ($stage === 'done'): ?><section class="feature-empty" role="status"><h2>You have completed the practice ballot</h2><p>In a real election, this step records your ballot and gives you a receipt. This demo has recorded no vote and issued no receipt.</p><div class="row"><a class="btn btn--primary" href="<?= e(url('practice.php')) ?>">Practice again</a><a class="btn btn--secondary" href="<?= e(url(is_voter() ? 'voter/elections.php' : 'auth/login.php')) ?>">Continue to real elections</a></div></section>
<?php else: ?><form method="post" class="stack" action="<?= e(post_url('practice.php')) ?>"><?= csrf_field() ?>
<?php foreach ($ballot as $entry): $position = $entry['position']; $pid = $position['id']; ?>
<fieldset class="practice-contest"><legend><?= e($position['name']) ?></legend>
<?php if ($stage === 'choose'): ?><p>Choose up to <?= (int) $position['max_selections'] ?>. Leave blank to abstain.</p><?php foreach ($entry['candidates'] as $candidate): ?><label class="practice-option"><input type="checkbox" name="votes[<?= $pid ?>][]" value="<?= $candidate['id'] ?>" <?= in_array($candidate['id'], $plan[$pid] ?? [], true) ? 'checked' : '' ?>><span><?= e($candidate['first_name'] . ' ' . $candidate['last_name']) ?><small>Fictional practice candidate</small></span></label><?php endforeach; ?>
<?php else: ?><ul><?php foreach ($entry['candidates'] as $candidate): if (!in_array($candidate['id'], $plan[$pid] ?? [], true)) continue; ?><li><?= e($candidate['first_name'] . ' ' . $candidate['last_name']) ?><input type="hidden" name="votes[<?= $pid ?>][]" value="<?= $candidate['id'] ?>"></li><?php endforeach; ?><?php if (empty($plan[$pid])): ?><li>Abstain — no candidate selected</li><?php endif; ?></ul><?php endif; ?></fieldset>
<?php endforeach; ?>
<div class="row"><?php if ($stage === 'review'): ?><button class="btn btn--secondary" name="action" value="edit">Edit practice choices</button><?php endif; ?><button class="btn btn--primary" name="action" value="<?= $stage === 'review' ? 'finish' : 'review' ?>"><?= $stage === 'review' ? 'Finish practice ballot' : 'Review practice choices' ?></button></div></form><?php endif; ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
