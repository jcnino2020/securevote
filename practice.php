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
<div class="feature-page feature-page--narrow stack"><div class="feature-intro"><span class="badge badge--warning"><?= e(sv_t("Practice only")) ?></span><h1 class="visually-hidden"><?= e(sv_t("Try voting before election day")) ?></h1><p><?= e(sv_t("These candidates are fictional. Practice choices are never saved to the database and do not affect your eligibility or real votes.")) ?></p></div>
<ol class="practice-steps" aria-label="<?= e(sv_t("Practice progress")) ?>"><?php foreach (['choose' => 'Choose', 'review' => 'Review', 'done' => 'Finish'] as $key => $label): ?><li <?= $stage === $key ? 'aria-current="step"' : '' ?>><?= e(sv_t($label)) ?></li><?php endforeach; ?></ol>
<?php sv_feature_errors($errors); ?>
<?php if ($stage === 'done'): ?><section class="feature-empty" role="status"><h2><?= e(sv_t("You have completed the practice ballot")) ?></h2><p><?= e(sv_t("In a real election, this step records your ballot and gives you a receipt. This demo has recorded no vote and issued no receipt.")) ?></p><div class="row"><a class="btn btn--primary" href="<?= e(url('practice.php')) ?>"><?= e(sv_t("Practice again")) ?></a><a class="btn btn--secondary" href="<?= e(url(is_voter() ? 'voter/elections.php' : 'auth/login.php')) ?>"><?= e(sv_t("Continue to real elections")) ?></a></div></section>
<?php else: ?><form method="post" class="stack" action="<?= e(post_url('practice.php')) ?>"><?= csrf_field() ?>
<?php foreach ($ballot as $entry): $position = $entry['position']; $pid = $position['id']; ?>
<fieldset class="practice-contest"><legend><?= e($position['name']) ?></legend>
<?php if ($stage === 'choose'): ?><p><?= sprintf(sv_t('Choose up to %d. Leave blank to abstain.'), (int) $position['max_selections']) ?></p><?php foreach ($entry['candidates'] as $candidate): ?><label class="practice-option"><input type="checkbox" name="votes[<?= $pid ?>][]" value="<?= $candidate['id'] ?>" <?= in_array($candidate['id'], $plan[$pid] ?? [], true) ? 'checked' : '' ?>><span><?= e($candidate['first_name'] . ' ' . $candidate['last_name']) ?><small><?= e(sv_t("Fictional practice candidate")) ?></small></span></label><?php endforeach; ?>
<?php else: ?><ul><?php foreach ($entry['candidates'] as $candidate): if (!in_array($candidate['id'], $plan[$pid] ?? [], true)) continue; ?><li><?= e($candidate['first_name'] . ' ' . $candidate['last_name']) ?><input type="hidden" name="votes[<?= $pid ?>][]" value="<?= $candidate['id'] ?>"></li><?php endforeach; ?><?php if (empty($plan[$pid])): ?><li><?= e(sv_t("Abstain — no candidate selected")) ?></li><?php endif; ?></ul><?php endif; ?></fieldset>
<?php endforeach; ?>
<div class="row"><?php if ($stage === 'review'): ?><button class="btn btn--secondary" name="action" value="edit"><?= e(sv_t("Edit practice choices")) ?></button><?php endif; ?><button class="btn btn--primary" name="action" value="<?= $stage === 'review' ? 'finish' : 'review' ?>"><?= $stage === 'review' ? e(sv_t('Finish practice ballot')) : e(sv_t('Review practice choices')) ?></button></div></form><?php endif; ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
