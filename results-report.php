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
$pdf = sv_pdf_results_report([
    'title' => (string) $e['title'],
    'final' => $final,
    'demo' => str_contains((string) ($e['description'] ?? ''), '[Demo results]'),
    'generated' => fmt_datetime(now()),
    'window' => fmt_range($e['start_datetime'], $e['end_datetime']),
    'ballots' => $ballots,
    'eligible' => $eligible,
    'tally' => $tally,
    'outcomes' => $final ? sv_final_result_summary($tally) : [],
]);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="securevote-results-' . $id . '.pdf"');
header('Cache-Control: private, no-store');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
