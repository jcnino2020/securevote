<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/community.php';
require_admin(); $pdo = sv_community_db(); $errors = [];
$statuses = ['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'];
if (is_post()) {
    require_csrf(); $id = int_input('id'); $status = input('status', ''); $response = input('response', '');
    if (!isset($statuses[$status])) $errors[] = 'Choose a valid ticket status.';
    if (mb_strlen($response) > 5000) $errors[] = 'Keep the reply under 5,000 characters.';
    $stmt = $pdo->prepare('SELECT id FROM support_tickets WHERE id = ?'); $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) $errors[] = 'That report no longer exists.';
    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE support_tickets SET status = ?, response = ? WHERE id = ?'); $stmt->execute([$status, $response, $id]);
        audit_log('support_updated', 'Support ticket #' . $id . ' set to ' . $status . '.');
        flash_success('Report updated. Signed-in reporters can read the reply in Help. Guest reporters must follow up with their administrator; no email was sent.'); redirect('admin/support.php');
    }
}
$filter = input('filter', 'open'); if (!isset($statuses[$filter]) && $filter !== 'all') $filter = 'open';
$stmt = $pdo->prepare("SELECT t.*, u.username FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id WHERE (? = 'all' OR t.status = ?) ORDER BY t.created_at DESC LIMIT 100"); $stmt->execute([$filter, $filter]); $tickets = $stmt->fetchAll();
$pageTitle = 'Student issue reports'; $activeNav = 'support.php'; require __DIR__ . '/../includes/header.php';
?>
<div class="feature-page stack"><div class="feature-intro"><h1>Help students get unstuck</h1><p>Review reports, reply to signed-in students, and track resolution. Guest reports include contact details for manual follow-up.</p></div><?php sv_feature_errors($errors); ?>
<nav class="feature-tabs" aria-label="Report status"><?php foreach (['all' => 'All'] + $statuses as $key => $label): ?><a class="btn btn--<?= $filter === $key ? 'primary' : 'secondary' ?>" href="<?= e(url('admin/support.php?filter=' . $key)) ?>" <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
<?php if (!$tickets): ?><div class="feature-empty"><h2>No reports in this view</h2><p>New reports will appear here when students request help.</p></div><?php endif; ?>
<?php foreach ($tickets as $ticket): ?><article class="feature-story"><span class="badge badge--neutral"><?= e($statuses[$ticket['status']]) ?></span><h2><?= e($ticket['subject']) ?></h2><p class="text-muted text-sm"><?= e($ticket['reference']) ?> · <?= e($ticket['email']) ?> · <?= e($ticket['username'] ?? 'Guest') ?> · <?= e($ticket['category']) ?> · <?= e(fmt_datetime($ticket['created_at'])) ?></p><p class="feature-prose"><?= nl2br(e($ticket['message'])) ?></p>
<form class="stack" method="post" action="<?= e(post_url('admin/support.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>"><label for="status-<?= (int) $ticket['id'] ?>">Status</label><select class="input" name="status" id="status-<?= (int) $ticket['id'] ?>"><?php foreach ($statuses as $key => $label): ?><option value="<?= $key ?>" <?= (is_post() && int_input('id') === (int) $ticket['id'] ? input('status') : $ticket['status']) === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><label for="response-<?= (int) $ticket['id'] ?>">Reply<?= $ticket['user_id'] ? ' (visible to this student)' : ' / follow-up notes (guest cannot view here)' ?></label><textarea class="input" name="response" id="response-<?= (int) $ticket['id'] ?>" rows="4" maxlength="5000"><?= e(is_post() && int_input('id') === (int) $ticket['id'] ? input('response', '') : ($ticket['response'] ?? '')) ?></textarea><button class="btn btn--primary">Save report update</button></form></article><?php endforeach; ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
