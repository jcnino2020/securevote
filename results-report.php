<?php
require_once __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/voting.php';require_once __DIR__.'/includes/pdf-report.php';
$user=require_login();$id=int_input('election_id');$pdo=db();$s=$pdo->prepare('SELECT * FROM elections WHERE id=?');$s->execute([$id]);$e=$s->fetch();
if(!$e)sv_error_page(404,'Election not found','Choose an existing election.',[]);
$voted=has_voted($id,(int)$user['id']);
if(!may_view_results($e,$user,$voted))sv_error_page(403,'Results unavailable','The election results publication policy does not allow this report yet.',[]);
// Use a consistent aggregate snapshot without locking voting tables.
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();
try{
 $s=$pdo->prepare('SELECT * FROM elections WHERE id=?');$s->execute([$id]);$e=$s->fetch();
 if(!may_view_results($e,$user,$voted)){ $pdo->rollBack();sv_error_page(403,'Results unavailable','Results access changed. Try again later.',[]);}
 $final=in_array(election_effective_status($e),['closed','archived'],true);$tally=sv_tally_positions($id);
 $s=$pdo->prepare('SELECT COUNT(*) FROM voter_eligibility WHERE election_id=?');$s->execute([$id]);$eligible=(int)$s->fetchColumn();
 $s=$pdo->prepare("SELECT COUNT(*) FROM ballots WHERE election_id=? AND status='submitted'");$s->execute([$id]);$ballots=(int)$s->fetchColumn();$pdo->commit();
}catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();throw $ex;}
$lines=[$e['title'],$final?sv_t('FINAL RECORDED TOTALS'):sv_t('LIVE TOTALS - NOT FINAL'),sprintf(sv_t('Generated: %s (Philippine time)'),fmt_datetime(now())),sprintf(sv_t('Voting: %s'),fmt_range($e['start_datetime'],$e['end_datetime'])),'',sprintf(sv_t('Submitted ballots: %d'),$ballots),sprintf(sv_t('Assigned voters: %d'),$eligible),sprintf(sv_t('Turnout: %s'),($eligible>0?number_format($ballots/$eligible*100,2).'%':sv_t('Unknown (no assigned-voter denominator)'))),'',sv_t('This report contains aggregate totals only. No voter choices are disclosed.'),sv_t('Outcomes follow recorded votes; this report is not an election certification.'),''];
if (str_contains((string) ($e['description'] ?? ''), '[Demo results]')) {
    array_splice($lines, 2, 0, ['DEMO RESULTS: Simulated ballots and tallies for testing; not an official outcome.', '']);
}
if($final){$lines[]=sv_t('OUTCOMES');foreach(sv_final_result_summary($tally) as $outcome){$lines[]=$outcome['position'].' | '.sv_t($outcome['outcome']);$lines[]=sv_t($outcome['detail']);$lines[]='';}}
foreach($tally as $entry){$lines[]=strtoupper($entry['position']['name']);$lines[]=sprintf(sv_t('Seats: %d | Ballots with a choice: %d'),$entry['position']['max_selections'],$entry['ballots_with_choice']);$lines[]=sprintf('%-45s %6s   %s',sv_t('Candidate'),sv_t('Votes'),sv_t('Standing'));$lines[]=str_repeat('-',80);foreach($entry['rows'] as $row){$name=$row['first_name'].' '.$row['last_name'];$label=sv_t(sv_result_badge_label($entry,$row,$final));$lines[]=sprintf('%-45s %6d   %s',mb_strimwidth($name,0,44,'...'),$row['vote_count'],$label);}$lines[]=sprintf(sv_t('Total selections: %d'),$entry['total_selections']);$lines[]='';}
if(!$tally)$lines[]=sv_t('No positions have been configured for this election.');
$pdf=sv_pdf_report($lines);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="securevote-results-'.$id.'.pdf"');header('Cache-Control: private, no-store');header('Content-Length: '.strlen($pdf));echo $pdf;
