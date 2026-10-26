<?php
require_once __DIR__.'/includes/bootstrap.php'; require_once __DIR__.'/includes/engagement.php';
$pdo=sv_engagement_db(); $elections=sv_public_elections($pdo); $id=int_input('election_id',(int)($elections[0]['id']??0));
$selected=null; foreach ($elections as $e) if ((int)$e['id']===$id) $selected=$e;
if ($id && !$selected) sv_error_page(404,'Election unavailable','Choose a published election.',[]);
$stmt=$pdo->prepare("SELECT a.question,a.answer,c.first_name,c.last_name,p.name AS position_name FROM candidate_answers a JOIN candidates c ON c.id=a.candidate_id JOIN positions p ON p.id=c.position_id WHERE c.election_id=? AND c.status='active' AND a.status='published' ORDER BY p.display_order,c.last_name,a.id"); $stmt->execute([$id]); $answers=$stmt->fetchAll();
$pageTitle=sv_t('Candidate Q&A'); $activeNav='candidate-qa.php'; require __DIR__.'/includes/header.php';
?>
<div class="feature-page stack"><h1 class="visually-hidden"><?= e(sv_t('Candidate Q&A')) ?></h1><p><?= e(sv_t('Read candidate answers reviewed and published by election administrators.')) ?></p><form method="get" class="feature-toolbar"><label for="qa-election"><?= e(sv_t('Election')) ?></label><select class="input" id="qa-election" name="election_id"><?php foreach ($elections as $e): ?><option value="<?= (int)$e['id'] ?>" <?= (int)$e['id']===$id?'selected':'' ?>><?= e($e['title']) ?></option><?php endforeach; ?></select><button class="btn btn--secondary"><?= e(sv_t('Choose election')) ?></button></form>
<?php if (!$answers): ?><div class="feature-empty"><h2><?= e(sv_t('No answers published yet')) ?></h2><p><?= e(sv_t('Check again after administrators publish candidate answers.')) ?></p></div><?php endif; ?>
<?php foreach ($answers as $a): ?><article class="feature-entry"><p><?= e($a['first_name'].' '.$a['last_name'].' · '.$a['position_name']) ?></p><h2><?= e($a['question']) ?></h2><div class="feature-prose"><?= nl2br(e($a['answer'])) ?></div></article><?php endforeach; ?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
