<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/community.php';
$admin = require_admin(); $pdo = sv_community_db(); $errors = [];
$form = ['id' => 0, 'election_id' => 0, 'title' => '', 'body' => '', 'status' => 'draft'];
$elections = $pdo->query('SELECT id, title, status FROM elections ORDER BY start_datetime DESC')->fetchAll();
if (is_post()) {
    require_csrf();
    $form = ['id' => int_input('id'), 'election_id' => int_input('election_id'), 'title' => input('title', ''), 'body' => input('body', ''), 'status' => input('status', 'draft')];
    if (mb_strlen($form['title']) < 3 || mb_strlen($form['title']) > 150) $errors[] = 'Use a title of 3–150 characters.';
    if (mb_strlen($form['body']) < 10 || mb_strlen($form['body']) > 10000) $errors[] = 'Use an announcement of 10–10,000 characters.';
    if (!in_array($form['status'], ['draft','published','withdrawn'], true)) $errors[] = 'Choose a valid publication status.';
    if ($form['election_id'] && !in_array($form['election_id'], array_map('intval', array_column($elections, 'id')), true)) $errors[] = 'Choose an existing election.';
    if ($form['id']) { $exists = $pdo->prepare('SELECT id FROM announcements WHERE id = ?'); $exists->execute([$form['id']]); if (!$exists->fetchColumn()) $errors[] = 'That announcement no longer exists.'; }
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
        flash_success('Announcement saved. Published updates appear on the student announcements page; election drafts stay hidden.');
        redirect('admin/announcements.php');
    }
} elseif (int_input('id')) {
    $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = ?'); $stmt->execute([int_input('id')]);
    $form = $stmt->fetch() ?: $form;
}
$items = $pdo->query('SELECT a.*, e.title AS election_title FROM announcements a LEFT JOIN elections e ON e.id = a.election_id ORDER BY a.created_at DESC LIMIT 100')->fetchAll();
$pageTitle = 'Manage announcements'; $activeNav = 'announcements.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="feature-page stack"><div class="feature-intro"><h1>Keep students informed</h1><p>Publish election updates, forum schedules, and reminders. Draft announcements are visible only to administrators.</p></div>
<?php sv_feature_errors($errors); ?>
<form class="feature-form stack" method="post" action="<?= e(post_url('admin/announcements.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $form['id'] ?>"><h2><?= $form['id'] ? 'Edit announcement' : 'New announcement' ?></h2>
<label for="title">Title</label><input id="title" name="title" class="input" required minlength="3" maxlength="150" value="<?= e($form['title']) ?>">
<label for="election">Audience</label><select id="election" name="election_id" class="input"><option value="0">All students</option><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= (int) $form['election_id'] === (int) $election['id'] ? 'selected' : '' ?>><?= e($election['title'] . ' (' . $election['status'] . ')') ?></option><?php endforeach; ?></select>
<label for="body">Announcement</label><textarea id="body" name="body" class="input" rows="7" required minlength="10" maxlength="10000"><?= e($form['body']) ?></textarea>
<label for="status">Publication status</label><select class="input" id="status" name="status"><?php foreach (['draft' => 'Draft', 'published' => 'Published', 'withdrawn' => 'Withdrawn'] as $key => $label): ?><option value="<?= $key ?>" <?= $form['status'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
<div class="row"><button class="btn btn--primary">Save announcement</button><?php if ($form['id']): ?><a class="btn btn--secondary" href="<?= e(url('admin/announcements.php')) ?>">Cancel editing</a><?php endif; ?><a class="link" href="<?= e(url('announcements.php')) ?>">View student feed</a></div></form>
<h2>Recent announcements</h2><?php if (!$items): ?><p>No announcements yet. Create the first update above.</p><?php endif; ?>
<div class="feature-feed"><?php foreach ($items as $item): ?><article class="feature-story"><span class="badge badge--neutral"><?= e(ucfirst($item['status'])) ?></span><h3><?= e($item['title']) ?></h3><p><?= e($item['election_title'] ?? 'All students') ?> · <?= e(fmt_datetime($item['created_at'])) ?></p><a class="link" href="<?= e(url('admin/announcements.php?id=' . (int) $item['id'])) ?>">Edit announcement</a></article><?php endforeach; ?></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
