<?php
require_once __DIR__.'/includes/bootstrap.php';
$id=int_input('election_id');$suffix=$id?'?election_id='.$id:'';
$pageTitle='Election guide';$activeNav='guide.php';require __DIR__.'/includes/header.php';
$groups=[
 'Before you vote'=>[['timeline.php','Election timeline','Registration, campaign, and voting dates.'],['forum.php','Candidate forum','Debates, campaign materials, and scheduled events.'],['candidate-qa.php','Candidate Q&A','Read answers reviewed by election administrators.'],['voter/compare.php','Compare candidates','Read candidate platforms side by side.']],
 'Updates'=>[['announcements.php','Announcements','Official election notices.'],['changes.php','Change history','See when published election details changed.'],['voter/reminders.php','Your reminders','Opening and closing reminders for your account.']],
 'Voting help'=>[['practice.php','Practice voting','Try an independent fictional ballot.'],['verify.php','Verify a receipt','Check whether a receipt was recorded.'],['help.php','Help and support','Get help with access, eligibility, or voting.']]
];
?>
<div class="feature-page stack"><div class="feature-intro"><h1 class="visually-hidden"><?= e(sv_t("Everything you need around election day")) ?></h1><p><?= e(sv_t("Prepare, follow updates, or get help. Choose a resource below.")) ?></p></div><?php foreach($groups as $heading=>$links): ?><section class="guide-group"><h2><?= e(sv_t($heading)) ?></h2><ul><?php foreach($links as [$path,$label,$copy]): ?><li><a href="<?= e(url($path.$suffix)) ?>"><strong><?= e(sv_t($label)) ?></strong><span><?= e(sv_t($copy)) ?></span><?= sv_icon('arrow-right',18) ?></a></li><?php endforeach; ?></ul></section><?php endforeach; ?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
