<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/community.php';
require_admin(); $pdo = db();
$elections = $pdo->query('SELECT * FROM elections ORDER BY start_datetime DESC')->fetchAll();
$id = int_input('election_id', (int) ($elections[0]['id'] ?? 0)); $selected = null;
foreach ($elections as $election) if ((int) $election['id'] === $id) $selected = $election;
$checks = $selected ? sv_election_readiness($pdo, $selected) : [];
$pageTitle = sv_t('Election readiness'); $activeNav = 'readiness.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="feature-page stack"><div class="feature-intro"><h1><?= e(sv_t("Prepare for election day")) ?></h1><p><?= e(sv_t("Review the setup before opening voting. These checks are advisory; the server still validates every real ballot.")) ?></p></div>
<form method="get" class="feature-toolbar"><label for="election"><?= e(sv_t("Election")) ?></label><select id="election" name="election_id" class="input"><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= $id === (int) $election['id'] ? 'selected' : '' ?>><?= e($election['title']) ?></option><?php endforeach; ?></select><button class="btn btn--secondary"><?= e(sv_t("Check readiness")) ?></button></form>
<?php if (!$selected): ?><div class="feature-empty"><h2><?= e(sv_t("No elections yet")) ?></h2><a class="link" href="<?= e(url('admin/elections.php')) ?>"><?= e(sv_t("Create an election")) ?></a></div><?php else: ?><h2><?= e($selected['title']) ?></h2><p><?= election_badge(election_effective_status($selected)) ?> · <?= e(sprintf(sv_t('%d of %d checks ready'), count(array_filter($checks, fn($c) => $c['ok'])), count($checks))) ?></p><ul class="readiness-list"><?php foreach ($checks as $check): ?><li><span class="badge badge--<?= $check['ok'] ? 'success' : 'warning' ?>"><?= $check['ok'] ? e(sv_t('Ready')) : e(sv_t('Review')) ?></span><div><h3><?= e($check['label']) ?></h3><p><?= e($check['detail']) ?></p></div><a class="btn btn--secondary btn--sm" href="<?= e(url($check['href'])) ?>"><?= e(sv_t("Review setup")) ?></a></li><?php endforeach; ?></ul><a class="btn btn--primary" href="<?= e(url('admin/elections.php?action=edit&id=' . $id)) ?>"><?= e(sv_t("Manage this election")) ?></a><?php endif; ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
