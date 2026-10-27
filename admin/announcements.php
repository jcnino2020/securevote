<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/community.php';
$admin = require_admin(); $pdo = sv_community_db(); $errors = [];
$form = ['id' => 0, 'election_id' => 0, 'title' => '', 'body' => '', 'status' => 'draft'];
$elections = $pdo->query('SELECT id, title, status FROM elections ORDER BY start_datetime DESC')->fetchAll();
if (is_post()) {
    require_csrf();
    $form = ['id' => int_input('id'), 'election_id' => int_input('election_id'), 'title' => input('title', ''), 'body' => input('body', ''), 'status' => input('status', 'draft')];
    if (mb_strlen($form['title']) < 3 || mb_strlen($form['title']) > 150) $errors[] = sv_t('Use a title of 3–150 characters.');
    if (mb_strlen($form['body']) < 10 || mb_strlen($form['body']) > 10000) $errors[] = sv_t('Use an announcement of 10–10,000 characters.');
    if (!in_array($form['status'], ['draft','published','withdrawn'], true)) $errors[] = sv_t('Choose a valid publication status.');
    if ($form['election_id'] && !in_array($form['election_id'], array_map('intval', array_column($elections, 'id')), true)) $errors[] = sv_t('Choose an existing election.');
    if ($form['id']) { $exists = $pdo->prepare('SELECT id FROM announcements WHERE id = ?'); $exists->execute([$form['id']]); if (!$exists->fetchColumn()) $errors[] = sv_t('That announcement no longer exists.'); }
    if (!$errors) {
        if ($form['id']) {
            $stmt = $pdo->prepare('UPDATE announcements SET election_id = ?, title = ?, body = ?, status = ? WHERE id = ?');
            $stmt->execute([$form['election_id'] ?: null, $form['title'], $form['body'], $form['status'], $form['id']]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO announcements (election_id, title, body, status, created_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$form['election_id'] ?: null, $form['title'], $form['body'], $form['status'], $admin['id']]);
            $form['id'] = (int) $pdo->lastInsertId();
        }
        audit_log('announcement_saved', 'Announcement #' . $form['id'] . ' saved as ' . $form['status'] . '.');
        flash_success(sv_t('Announcement saved. Published updates appear on the student announcements page; election drafts stay hidden.'));
        redirect('admin/announcements.php');
    }
} elseif (int_input('id')) {
    $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = ?'); $stmt->execute([int_input('id')]);
    $form = $stmt->fetch() ?: $form;
}
$items = $pdo->query('SELECT a.*, e.title AS election_title FROM announcements a LEFT JOIN elections e ON e.id = a.election_id ORDER BY a.created_at DESC LIMIT 100')->fetchAll();
$pageTitle = sv_t('Manage announcements'); $activeNav = 'announcements.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="feature-page stack"><div class="feature-intro"><h1><?= e(sv_t("Keep students informed")) ?></h1><p><?= e(sv_t("Publish election updates, forum schedules, and reminders. Draft announcements are visible only to administrators.")) ?></p></div>
<?php sv_feature_errors($errors); ?>
<form class="feature-form stack" method="post" action="<?= e(post_url('admin/announcements.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $form['id'] ?>"><h2><?= $form['id'] ? e(sv_t('Edit announcement')) : e(sv_t('New announcement')) ?></h2>
<label for="title"><?= e(sv_t("Title")) ?></label><input id="title" name="title" class="input" required minlength="3" maxlength="150" value="<?= e($form['title']) ?>">
<label for="election"><?= e(sv_t("Audience")) ?></label><select id="election" name="election_id" class="input"><option value="0"><?= e(sv_t("All students")) ?></option><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= (int) $form['election_id'] === (int) $election['id'] ? 'selected' : '' ?>><?= e($election['title'] . ' (' . sv_t($election['status']) . ')') ?></option><?php endforeach; ?></select>
<label for="body"><?= e(sv_t("Announcement")) ?></label><textarea id="body" name="body" class="input" rows="7" required minlength="10" maxlength="10000"><?= e($form['body']) ?></textarea>
<label for="status"><?= e(sv_t("Publication status")) ?></label><select class="input" id="status" name="status"><?php foreach (['draft' => sv_t('Draft'), 'published' => sv_t('Published'), 'withdrawn' => sv_t('Withdrawn')] as $key => $label): ?><option value="<?= $key ?>" <?= $form['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
<div class="row"><button class="btn btn--primary"><?= e(sv_t("Save announcement")) ?></button><?php if ($form['id']): ?><a class="btn btn--secondary" href="<?= e(url('admin/announcements.php')) ?>"><?= e(sv_t("Cancel editing")) ?></a><?php endif; ?><a class="link" href="<?= e(url('announcements.php')) ?>"><?= e(sv_t("View student feed")) ?></a></div></form>
<h2><?= e(sv_t("Recent announcements")) ?></h2><?php if (!$items): ?><p><?= e(sv_t("No announcements yet. Create the first update above.")) ?></p><?php endif; ?>
<div class="feature-feed"><?php foreach ($items as $item): ?><article class="feature-story"><span class="badge badge--neutral"><?= e(sv_t(ucfirst($item['status']))) ?></span><h3><?= e($item['title']) ?></h3><p><?= e($item['election_title'] ?? sv_t('All students')) ?> · <?= e(fmt_datetime($item['created_at'])) ?></p><a class="link" href="<?= e(url('admin/announcements.php?id=' . (int) $item['id'])) ?>"><?= e(sv_t("Edit announcement")) ?></a></article><?php endforeach; ?></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
