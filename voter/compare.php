<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';
require_once __DIR__ . '/../includes/community.php';
require_voter();
$pdo = db(); $elections = sv_public_elections($pdo);
$id = int_input('election_id', (int) ($elections[0]['id'] ?? 0));
$selected = null;
foreach ($elections as $election) if ((int) $election['id'] === $id) $selected = $election;
if ($id && !$selected) sv_error_page(404, sv_t('Election unavailable'), sv_t('Choose a published election to compare candidates.'), []);
$ballot = $selected ? sv_build_ballot($id) : [];
$positionId = int_input('position_id', (int) ($ballot[0]['position']['id'] ?? 0));
$entry = null;
foreach ($ballot as $row) if ((int) $row['position']['id'] === $positionId) $entry = $row;
$pageTitle = sv_t('Compare candidates'); $activeNav = 'compare.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="feature-page stack"><div class="feature-intro"><h1 class="visually-hidden"><?= e(sv_t("Know your candidates")) ?></h1><p><?= e(sv_t("Read platforms side by side before making your choice. Comparing candidates does not select anyone on your ballot.")) ?></p></div>
<form method="get" class="feature-toolbar"><label for="election"><?= e(sv_t("Election")) ?></label><select class="input" id="election" name="election_id"><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= $id === (int) $election['id'] ? 'selected' : '' ?>><?= e($election['title']) ?></option><?php endforeach; ?></select><button class="btn btn--secondary"><?= e(sv_t("Choose election")) ?></button></form>
<?php if ($selected): ?><nav class="feature-tabs" aria-label="<?= e(sv_t('Compare by position')) ?>"><?php foreach ($ballot as $row): ?><a class="btn btn--<?= $positionId === (int) $row['position']['id'] ? 'primary' : 'secondary' ?>" href="<?= e(url('voter/compare.php?election_id=' . $id . '&position_id=' . (int) $row['position']['id'])) ?>" <?= $positionId === (int) $row['position']['id'] ? 'aria-current="page"' : '' ?>><?= e($row['position']['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
<?php if (!$entry || !$entry['candidates']): ?><div class="feature-empty"><h2><?= e(sv_t("No candidates to compare")) ?></h2><p><?= e(sv_t("Choose another election or position, or check again when candidates are published.")) ?></p></div><?php else: ?>
<a class="link" href="<?= e(url('candidate-qa.php?election_id='.$id)) ?>"><?= e(sv_t('Candidate Q&A')) ?></a>
<h2><?= e($entry['position']['name']) ?></h2><p class="text-muted"><?= e(sprintf(sv_t('Choose up to %d when voting. Scroll horizontally to see every candidate.'), (int) $entry['position']['max_selections'])) ?></p>
<div class="comparison-scroll" role="region" tabindex="0" aria-label="<?= e(sv_t('Candidate comparison')) ?>"><table class="comparison-table"><caption class="visually-hidden"><?= e(sprintf(sv_t('Candidate biographies and platforms for %s'), $entry['position']['name'])) ?></caption><thead><tr><th scope="col"><?= e(sv_t("Candidate details")) ?></th><?php foreach ($entry['candidates'] as $candidate): ?><th scope="col"><?= e($candidate['first_name'] . ' ' . $candidate['last_name']) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach (['party' => sv_t('Party / affiliation'), 'biography' => sv_t('Experience and biography'), 'platform' => sv_t('Platform and priorities')] as $key => $label): ?><tr><th scope="row"><?= e($label) ?></th><?php foreach ($entry['candidates'] as $candidate): ?><td><div class="feature-prose"><?= nl2br(e(trim((string) $candidate[$key]) ?: sv_t('Not provided.'))) ?></div></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
<a class="btn btn--primary" href="<?= e(url('voter/elections.php?election_id=' . $id)) ?>"><?= e(sv_t("Back to election and voting")) ?></a>
<?php endif; ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
