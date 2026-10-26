<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/community.php';
$pdo = sv_community_db();
$elections = sv_public_elections($pdo);
$electionId = int_input('election_id');
$stmt = $pdo->prepare("SELECT a.*, e.title AS election_title FROM announcements a LEFT JOIN elections e ON e.id = a.election_id
    WHERE a.status = 'published' AND (a.election_id IS NULL OR e.status IN ('scheduled','open','closed','archived'))
    AND (? = 0 OR a.election_id = ? OR a.election_id IS NULL) ORDER BY a.created_at DESC, a.id DESC LIMIT 100");
$stmt->execute([$electionId, $electionId]);
$announcements = $stmt->fetchAll();
$pageTitle = 'Election announcements'; $activeNav = 'announcements.php';
require __DIR__ . '/includes/header.php';
?>
<div class="feature-page stack">
<div class="feature-intro"><h1 class="visually-hidden">Stay in the loop</h1><p>Election dates, campus forums, and updates from your election team.</p></div>
<form method="get" class="feature-toolbar"><label for="election">Filter by election</label><select class="input" name="election_id" id="election"><option value="0">All elections</option><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= $electionId === (int) $election['id'] ? 'selected' : '' ?>><?= e($election['title']) ?></option><?php endforeach; ?></select><button class="btn btn--secondary">Show updates</button></form>
<?php if (!$announcements): ?><div class="feature-empty"><h2>No announcements yet</h2><p>Check back here for updates from your election team.</p></div><?php endif; ?>
<div class="feature-feed"><?php foreach ($announcements as $item): ?><article class="feature-story"><p class="text-muted text-sm"><?= e($item['election_title'] ?? 'All students') ?> · <?= e(fmt_datetime($item['created_at'])) ?></p><h2><?= e($item['title']) ?></h2><div class="feature-prose"><?= nl2br(e($item['body'])) ?></div><?php if ($item['election_id'] && is_voter()): ?><a class="link" href="<?= e(url('voter/elections.php?election_id=' . (int) $item['election_id'])) ?>">View election</a><?php endif; ?></article><?php endforeach; ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
