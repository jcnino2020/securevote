<?php
require_once __DIR__.'/includes/bootstrap.php'; require_once __DIR__.'/includes/engagement.php';
$pdo=sv_engagement_db(); $elections=sv_public_elections($pdo); $id=int_input('election_id');
if ($id) $elections=array_values(array_filter($elections,fn($e)=>(int)$e['id']===$id));
if ($id && !$elections) sv_error_page(404,'Election unavailable','Choose a published election.',[]);
$pageTitle=sv_t('Election timeline'); $activeNav='timeline.php'; require __DIR__.'/includes/header.php';
$stmt=$pdo->prepare('SELECT * FROM election_milestones WHERE election_id=?');
?>
<div class="feature-page stack"><h1 class="visually-hidden"><?= e(sv_t('Election timeline')) ?></h1><p><?= e(sv_t('Plan your vote. All times are Philippine time (Asia/Manila).')) ?></p>
<?php if (!$elections): ?><p><?= e(sv_t('No published elections yet.')) ?></p><?php endif; ?>
<?php foreach ($elections as $election): $stmt->execute([$election['id']]); $milestones=$stmt->fetch() ?: []; ?><section class="feature-entry"><h2><?= e($election['title']) ?></h2><?= election_badge(election_effective_status($election)) ?><ol class="election-timeline">
<?php foreach (sv_timeline($election,$milestones) as [$label,$at]): ?><li><strong><?= e(sv_t($label)) ?></strong><span><?php if ($at): ?><time datetime="<?= e(to_datetime($at)->format(DATE_ATOM)) ?>"><?= e(fmt_datetime($at)) ?></time><?php else: ?><?= e(sv_t('Not announced')) ?><?php endif; ?></span></li><?php endforeach; ?></ol><p><?= e(sv_t('Results access follows the election publication policy; the announcement date does not unlock results.')) ?></p><a class="link" href="<?= e(url('candidate-qa.php?election_id='.(int)$election['id'])) ?>"><?= e(sv_t('Candidate Q&A')) ?></a></section><?php endforeach; ?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
