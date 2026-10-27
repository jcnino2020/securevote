<?php
require_once __DIR__.'/../includes/bootstrap.php'; require_once __DIR__.'/../includes/engagement.php';
require_admin(); $pdo=sv_engagement_db(); sv_seed_change_baselines(); $errors=[];
$elections=$pdo->query('SELECT id,title,start_datetime,end_datetime FROM elections ORDER BY start_datetime DESC')->fetchAll();
$id=int_input('election_id',(int)($elections[0]['id']??0)); $selected=null;
foreach ($elections as $e) if ((int)$e['id']===$id) $selected=$e;
$fields=['registration_opens'=>sv_t('Registration opens'),'registration_closes'=>sv_t('Registration closes'),'campaign_opens'=>sv_t('Campaign starts'),'campaign_closes'=>sv_t('Campaign ends'),'results_at'=>sv_t('Results announcement')];
$stmt=$pdo->prepare('SELECT * FROM election_milestones WHERE election_id=?'); $stmt->execute([$id]); $schedule=$stmt->fetch() ?: [];
$stmt=$pdo->prepare("SELECT id,first_name,last_name FROM candidates WHERE election_id=? ORDER BY last_name"); $stmt->execute([$id]); $candidates=$stmt->fetchAll();
$qa=['id'=>0,'candidate_id'=>0,'question'=>'','answer'=>'','status'=>'draft'];
if (is_post()) {
 require_csrf(); if (!$selected) $errors[]=sv_t('Choose an existing election.');
 if (input('action')==='schedule') {
  foreach ($fields as $key=>$label) {
   $raw=input($key,''); $dt=$raw===''?null:DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,new DateTimeZone(APP_TIMEZONE));
   if ($raw!=='' && (!$dt || $dt->format('Y-m-d\TH:i')!==$raw)) $errors[]=sprintf(sv_t('Enter a valid date for %s.'), $label);
   $schedule[$key]=$dt?$dt->format('Y-m-d H:i:s'):null;
  }
  foreach ([['registration_opens','registration_closes'],['campaign_opens','campaign_closes']] as [$start,$end]) if ($schedule[$start] && $schedule[$end] && $schedule[$start]>=$schedule[$end]) $errors[]=sv_t('Each phase must end after it starts.');
  if ($selected && $schedule['campaign_closes'] && $schedule['campaign_closes']>$selected['start_datetime']) $errors[]=sv_t('Campaigning must end by voting opening.');
  if ($selected && $schedule['results_at'] && $schedule['results_at']<$selected['end_datetime']) $errors[]=sv_t('The results announcement must follow voting closing.');
  if (!$errors) {
   $stmt=$pdo->prepare('INSERT INTO election_milestones (election_id,registration_opens,registration_closes,campaign_opens,campaign_closes,results_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE registration_opens=VALUES(registration_opens),registration_closes=VALUES(registration_closes),campaign_opens=VALUES(campaign_opens),campaign_closes=VALUES(campaign_closes),results_at=VALUES(results_at)');
   $stmt->execute([$id,...array_map(fn($k)=>$schedule[$k],array_keys($fields))]); sv_record_election_change($id); audit_log('timeline_saved','Election #'.$id.' timeline updated.'); flash_success(sv_t('Timeline saved. Published elections show these dates.')); redirect('admin/engagement.php?election_id='.$id);
  }
 } elseif (input('action')==='answer') {
  $qa=['id'=>int_input('id'),'candidate_id'=>int_input('candidate_id'),'question'=>input('question',''),'answer'=>input('answer',''),'status'=>input('status','draft')];
  if (!in_array($qa['candidate_id'],array_map('intval',array_column($candidates,'id')),true)) $errors[]=sv_t('Choose a candidate in this election.');
  if (mb_strlen($qa['question'])<5 || mb_strlen($qa['question'])>300) $errors[]=sv_t('Use a question of 5–300 characters.');
  if (mb_strlen($qa['answer'])<10 || mb_strlen($qa['answer'])>10000) $errors[]=sv_t('Use an answer of 10–10,000 characters.');
  if (!in_array($qa['status'],['draft','published','withdrawn'],true)) $errors[]=sv_t('Choose a valid publication status.');
  if ($qa['id']) { $stmt=$pdo->prepare('SELECT a.id FROM candidate_answers a JOIN candidates c ON c.id=a.candidate_id WHERE a.id=? AND c.election_id=?'); $stmt->execute([$qa['id'],$id]); if (!$stmt->fetchColumn()) $errors[]=sv_t('Answer not found in this election.'); }
  if (!$errors) {
   if ($qa['id']) { $stmt=$pdo->prepare('UPDATE candidate_answers SET candidate_id=?,question=?,answer=?,status=? WHERE id=?'); $stmt->execute([$qa['candidate_id'],$qa['question'],$qa['answer'],$qa['status'],$qa['id']]); }
   else { $stmt=$pdo->prepare('INSERT INTO candidate_answers (candidate_id,question,answer,status) VALUES (?,?,?,?)'); $stmt->execute([$qa['candidate_id'],$qa['question'],$qa['answer'],$qa['status']]); $qa['id']=(int)$pdo->lastInsertId(); }
   audit_log('candidate_answer_saved','Answer #'.$qa['id'].' saved as '.$qa['status'].'.'); flash_success(sv_t('Candidate answer saved. Only published answers for active candidates in published elections are visible.')); redirect('admin/engagement.php?election_id='.$id);
  }
 } else $errors[]=sv_t('Choose a valid action.');
} elseif (int_input('answer_id')) {
 $stmt=$pdo->prepare('SELECT a.* FROM candidate_answers a JOIN candidates c ON c.id=a.candidate_id WHERE a.id=? AND c.election_id=?'); $stmt->execute([int_input('answer_id'),$id]); $qa=$stmt->fetch() ?: $qa;
}
$stmt=$pdo->prepare('SELECT a.*,c.first_name,c.last_name FROM candidate_answers a JOIN candidates c ON c.id=a.candidate_id WHERE c.election_id=? ORDER BY a.updated_at DESC'); $stmt->execute([$id]); $answers=$stmt->fetchAll();
$pageTitle=sv_t('Timeline & candidate Q&A'); $activeNav='engagement.php'; require __DIR__.'/../includes/header.php';
?>
<div class="feature-page stack"><h2><?= e(sv_t("Keep students informed")) ?></h2><form method="get" class="feature-toolbar"><label for="election"><?= e(sv_t("Election")) ?></label><select id="election" name="election_id" class="input"><?php foreach ($elections as $e): ?><option value="<?= (int)$e['id'] ?>" <?= (int)$e['id']===$id?'selected':'' ?>><?= e($e['title']) ?></option><?php endforeach; ?></select><button class="btn btn--secondary"><?= e(sv_t("Choose election")) ?></button></form>
<?php sv_feature_errors($errors); if ($selected): ?>
<section class="feature-entry"><h2><?= e(sv_t("Election timeline")) ?></h2><p><?= e(sv_t("All dates use Philippine time. Blank dates appear as “Not announced.” Voting dates remain managed in Elections. These dates provide information; they do not change registration access or results permissions.")) ?></p><p><?= e(sv_t("Voting:")) ?> <?= e(fmt_range($selected['start_datetime'],$selected['end_datetime'])) ?></p><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="schedule"><input type="hidden" name="election_id" value="<?= $id ?>"><?php foreach ($fields as $key=>$label): ?><label><?= e($label) ?><input class="input" type="datetime-local" name="<?= e($key) ?>" value="<?= e(empty($schedule[$key])?'':to_datetime($schedule[$key])->format('Y-m-d\TH:i')) ?>"></label><?php endforeach; ?><button class="btn btn--primary"><?= e(sv_t("Save timeline")) ?></button></form></section>
<section class="feature-entry"><h2><?= $qa['id']?e(sv_t('Edit candidate answer')):e(sv_t('Add candidate answer')) ?></h2><p><?= e(sv_t("Review the candidate’s supplied answer before choosing Published. Draft and withdrawn answers stay hidden.")) ?></p><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="answer"><input type="hidden" name="election_id" value="<?= $id ?>"><input type="hidden" name="id" value="<?= (int)$qa['id'] ?>"><label for="qa-candidate"><?= e(sv_t("Candidate")) ?></label><select id="qa-candidate" name="candidate_id" class="input" required><option value=""><?= e(sv_t("Choose a candidate")) ?></option><?php foreach ($candidates as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$qa['candidate_id']===(int)$c['id']?'selected':'' ?>><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?></select><label><?= e(sv_t("Question")) ?><input class="input" name="question" minlength="5" maxlength="300" required value="<?= e($qa['question']) ?>"></label><label><?= e(sv_t("Candidate answer")) ?><textarea class="input" name="answer" minlength="10" maxlength="10000" rows="6" required><?= e($qa['answer']) ?></textarea></label><label for="qa-status"><?= e(sv_t("Publication status")) ?></label><select id="qa-status" class="input" name="status"><?php foreach (['draft'=>sv_t('Draft'),'published'=>sv_t('Published'),'withdrawn'=>sv_t('Withdrawn')] as $statusKey=>$statusLabel): ?><option value="<?= e($statusKey) ?>" <?= $qa['status']===$statusKey?'selected':'' ?>><?= e($statusLabel) ?></option><?php endforeach; ?></select><button class="btn btn--primary"><?= e(sv_t("Save answer")) ?></button></form></section>
<section><h2><?= e(sv_t("Saved answers")) ?></h2><?php if (!$answers): ?><p><?= e(sv_t("No candidate answers yet.")) ?></p><?php endif; ?><?php foreach ($answers as $a): ?><article class="feature-entry"><h3><?= e($a['question']) ?></h3><p><?= e($a['first_name'].' '.$a['last_name'].' · '.sv_t($a['status'])) ?></p><a class="link" href="<?= e(url('admin/engagement.php?election_id='.$id.'&answer_id='.(int)$a['id'])) ?>"><?= e(sv_t("Review or edit")) ?></a></article><?php endforeach; ?></section>
<?php endif; ?></div><?php require __DIR__.'/../includes/footer.php'; ?>
