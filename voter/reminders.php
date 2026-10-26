<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/engagement.php';
$voter=require_voter(); $pdo=sv_engagement_db(); $items=sv_reminders($pdo,(int)$voter['id']);
if (is_post()) {
    require_csrf(); $key=input('key','');
    foreach ($items as $item) if (hash_equals($item['key'],$key)) {
        $stmt=$pdo->prepare('INSERT IGNORE INTO reminder_dismissals (user_id,reminder_key) VALUES (?,?)');
        $stmt->execute([$voter['id'],$key]); break;
    }
    redirect('voter/reminders.php');
}
$pageTitle=sv_t('Election reminders'); $activeNav='reminders.php'; require __DIR__.'/../includes/header.php';
?>
<div class="feature-page stack"><h2><?= e(sv_t('Do not miss your vote')) ?></h2><p><?= e(sv_t('Reminders update when you visit. Opening and closing alerts appear within 24 hours.')) ?></p>
<?php if (!$items): ?><div class="feature-empty"><h3><?= e(sv_t('You are all caught up')) ?></h3><p><?= e(sv_t('No reminders right now. Check the election timeline for upcoming dates.')) ?></p></div><?php endif; ?>
<?php foreach ($items as $item): ?><article class="feature-entry"><h3><?= e($item['election']['title']) ?></h3><p><?= e($item['message']) ?></p><p><?= e(fmt_range($item['election']['start_datetime'],$item['election']['end_datetime'])) ?></p><div class="row"><a class="btn btn--primary" href="<?= e(url('voter/elections.php?election_id='.(int)$item['election']['id'])) ?>"><?= e(sv_t('Election details')) ?></a><form method="post"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($item['key']) ?>"><button class="btn btn--ghost"><?= e(sv_t('Dismiss reminder')) ?></button></form></div></article><?php endforeach; ?>
<a class="link" href="<?= e(url('timeline.php')) ?>"><?= e(sv_t('Election timeline')) ?></a></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
