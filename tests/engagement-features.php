<?php
if (getenv('SV_TEST_ALLOW_WRITES') !== '1' || !str_starts_with((string)getenv('SV_DB_SOCKET'), '/tmp/securevote-feature')) exit(2);
require __DIR__.'/../includes/bootstrap.php';
require __DIR__.'/../includes/engagement.php';
set_exception_handler(function(Throwable $e) { fwrite(STDERR,'FAIL '.$e->getMessage()."\n"); exit(1); });
$n=0;
function verify(bool $ok,string $label):void { global $n; if (!$ok) throw new RuntimeException($label); $n++; echo "PASS $label\n"; }
$pdo=sv_engagement_db();
$sql=file_get_contents(__DIR__.'/../database/migration_engagement.sql');
foreach (explode(';',$sql) as $s) if(trim($s)) $pdo->exec($s);
verify(true,'Additive migration is repeatable');
$pdo->beginTransaction();
try {
$pdo->exec("INSERT INTO users (username,email,voter_code,first_name,last_name,role,status) VALUES ('engagementtest','engagement@example.test','VC-ENGAGEMENT','Feature','Tester','voter','active')"); $uid=(int)$pdo->lastInsertId();
$now=time(); $stamp=fn($offset)=>date('Y-m-d H:i:s',$now+$offset);
$insert=$pdo->prepare('INSERT INTO elections (title,start_datetime,end_datetime,status) VALUES (?,?,?,?)');
$insert->execute(['Reminder election',$stamp(-3600),$stamp(90000),'open']); $eid=(int)$pdo->lastInsertId();
$assign=$pdo->prepare('INSERT INTO voter_eligibility (election_id,user_id) VALUES (?,?)'); $assign->execute([$eid,$uid]);
verify(count(sv_reminders($pdo,$uid,$now))===1,'Eligible voter sees open reminder');
$items=sv_reminders($pdo,$uid,$now);$pdo->prepare('INSERT INTO reminder_dismissals (user_id,reminder_key) VALUES (?,?)')->execute([$uid,$items[0]['key']]);
verify(!sv_reminders($pdo,$uid,$now),'Dismissal hides current reminder');
verify(count(sv_reminders($pdo,$uid,$now+7200))===1,'Closing reminder appears separately');
$pdo->prepare('INSERT INTO ballots (election_id,voter_id,ballot_reference) VALUES (?,?,?)')->execute([$eid,$uid,'SV-2026-ENG123']);
verify(!sv_reminders($pdo,$uid,$now+7200),'Submitted ballot suppresses reminders');
$insert->execute(['Upcoming election',$stamp(3600),$stamp(100000),'scheduled']); $soon=(int)$pdo->lastInsertId(); $assign->execute([$soon,$uid]);
verify(count(sv_reminders($pdo,$uid,$now))===1,'Upcoming opening within 24 hours appears');
verify(!sv_reminders($pdo,$uid,$now-90000),'Distant future produces no opening reminder');
$pdo->prepare("UPDATE elections SET status='draft' WHERE id=?")->execute([$soon]); verify(!sv_reminders($pdo,$uid,$now),'Draft elections hidden');
$pdo->prepare("UPDATE elections SET status='closed' WHERE id=?")->execute([$soon]); verify(!sv_reminders($pdo,$uid,$now),'Closed elections hidden');
$pdo->prepare("UPDATE elections SET status='open',start_datetime=?,end_datetime=? WHERE id=?")->execute([$stamp(-7200),$stamp(-1),$soon]); verify(!sv_reminders($pdo,$uid,$now),'Expired voting window hidden');
$pdo->prepare('DELETE FROM voter_eligibility WHERE election_id=? AND user_id=?')->execute([$soon,$uid]); verify(!sv_reminders($pdo,$uid,$now),'Unassigned voter sees no reminder');
$e=$pdo->query('SELECT * FROM elections WHERE id='.$eid)->fetch();$rows=sv_timeline($e,[]);
verify(count($rows)===7 && $rows[4][1]===$e['start_datetime'] && $rows[0][1]===null,'Timeline uses authoritative voting dates and leaves unknown phases blank');
$_SESSION['language']='fil'; verify(sv_t('Election timeline')==='Iskedyul ng eleksiyon','Filipino translations work'); verify(sv_t('<script>user text</script>')==='<script>user text</script>','Untranslated content preserved');
$_SESSION['language']='en'; verify(sv_t('Election timeline')==='Election timeline','English restored');
} finally { $pdo->rollBack(); }
echo "$n checks passed. Test data rolled back.\n";
