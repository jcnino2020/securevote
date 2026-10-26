<?php
declare(strict_types=1);
require_once __DIR__.'/community.php';
function sv_next_db(): PDO {
 $pdo=db(); static $ready=false;
 if (!$ready) {
  try { foreach (['election_metadata','election_forum','election_snapshots','election_changes','password_resets','account_security'] as $t) $pdo->query("SELECT 1 FROM $t LIMIT 1"); }
  catch (PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1146) throw $e; foreach (explode(';',file_get_contents(__DIR__.'/../database/migration_next_features.sql')) as $sql) if(trim($sql)) $pdo->exec($sql); }
  $ready=true;
 }
 return $pdo;
}
function sv_security_version(int $uid): string {
 $s=sv_next_db()->prepare('SELECT session_version FROM account_security WHERE user_id=?');$s->execute([$uid]);return (string)($s->fetchColumn()?:'');
}
function sv_rotate_security_version(PDO $pdo,int $uid): string {
 $version=bin2hex(random_bytes(16));
 $s=$pdo->prepare('INSERT INTO account_security(user_id,session_version) VALUES (?,?) ON DUPLICATE KEY UPDATE session_version=VALUES(session_version)');
 $s->execute([$uid,$version]);
 return $version;
}
function sv_public_snapshot(PDO $pdo,int $id): array {
 $s=$pdo->prepare('SELECT title,description,start_datetime,end_datetime,status,results_visibility FROM elections WHERE id=?');$s->execute([$id]);$e=$s->fetch();if(!$e)return [];
 $s=$pdo->prepare("SELECT id,first_name,last_name,party,biography,platform,photo,position_id FROM candidates WHERE election_id=? AND status='active' ORDER BY id");$s->execute([$id]);$e['candidates']=$s->fetchAll();
 $s=$pdo->prepare('SELECT registration_opens,registration_closes,campaign_opens,campaign_closes,results_at FROM election_milestones WHERE election_id=?');$s->execute([$id]);$e['milestones']=$s->fetch()?:[];
 $s=$pdo->prepare('SELECT organization,department,school_year FROM election_metadata WHERE election_id=?');$s->execute([$id]);$e['classification']=$s->fetch()?:[];
 return $e;
}
function sv_record_election_change(int $id): void {
 $pdo=sv_next_db();$pdo->beginTransaction();
 try {
  $lock=$pdo->prepare('SELECT id FROM elections WHERE id=? FOR UPDATE');$lock->execute([$id]);if(!$lock->fetchColumn()){$pdo->rollBack();return;}
  $current=sv_public_snapshot($pdo,$id);$s=$pdo->prepare('SELECT content FROM election_snapshots WHERE election_id=?');$s->execute([$id]);$raw=$s->fetchColumn();$old=$raw?json_decode($raw,true):null;
  if ($old!==null) {
   $labels=['title'=>'Election title','description'=>'Election description','start_datetime'=>'Voting opening time','end_datetime'=>'Voting closing time','status'=>'Election status','results_visibility'=>'Results publication policy','candidates'=>'Candidate roster or profiles','milestones'=>'Registration, campaign, or announcement dates','classification'=>'Organization, department, or school year'];$changed=[];
   foreach($labels as $key=>$label)if(($old[$key]??null)!==($current[$key]??null))$changed[]=$label;
   if($changed){$s=$pdo->prepare('INSERT INTO election_changes (election_id,summary) VALUES (?,?)');$s->execute([$id,implode('; ',$changed).' updated.']);}
  }
  $s=$pdo->prepare('INSERT INTO election_snapshots (election_id,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');$s->execute([$id,json_encode($current,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function sv_seed_change_baselines(): void {
 $pdo=sv_next_db();$ids=$pdo->query('SELECT e.id FROM elections e LEFT JOIN election_snapshots s ON s.election_id=e.id WHERE s.election_id IS NULL')->fetchAll(PDO::FETCH_COLUMN);
 foreach($ids as $id)sv_record_election_change((int)$id);
}
function sv_valid_resource_url(string $url): bool {
 if($url==='')return true;$p=parse_url($url);return filter_var($url,FILTER_VALIDATE_URL)!==false && ($p['scheme']??'')==='https' && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']);
}
/** Only a SHA-256 digest is persisted; the raw token goes solely to the mail transport. */
function sv_issue_password_reset(string $email,?callable $transport=null): void {
 $pdo=sv_next_db();$pdo->exec('DELETE FROM password_resets WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY) LIMIT 100');$s=$pdo->prepare("SELECT id,email FROM users WHERE email=? AND status='active'");$s->execute([$email]);$user=$s->fetch();if(!$user)return;
 $pdo->beginTransaction();
 try{
  $s=$pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$s->execute([$user['id']]);
  $s=$pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 30 MINUTE)');$s->execute([$user['id']]);if((int)$s->fetchColumn()>=3){$pdo->rollBack();return;}
  $token=bin2hex(random_bytes(32));$s=$pdo->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))');$s->execute([$user['id'],hash('sha256',$token)]);$resetId=(int)$pdo->lastInsertId();$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 // Trusted deployment setting; never derive reset origins from a request Host header.
 $origin=rtrim(getenv('SV_PUBLIC_ORIGIN')?:'https://darkblue-hummingbird-464264.hostingersite.com/securevote','/');
 $url=$origin.'/auth/reset-password?token='.$token;
 $message="A password reset was requested for your SecureVote account.

Open this link within 30 minutes:
".$url."

If you did not request this, ignore this email. Your password has not changed.
";
 $send=$transport??static function(string $to,string $body):bool{
  $from=getenv('SV_MAIL_FROM')?:'no-reply@darkblue-hummingbird-464264.hostingersite.com';
  if(!filter_var($from,FILTER_VALIDATE_EMAIL))return false;
  return mail($to,'Reset your SecureVote password',$body,['From'=>'SecureVote <'.$from.'>','Content-Type'=>'text/plain; charset=UTF-8']);
 };
 try{$sent=$send($user['email'],$message);}catch(Throwable $e){$sent=false;}
 if(!$sent){$s=$pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?');$s->execute([$resetId]);error_log('SecureVote password reset delivery failed. Check outbound mail configuration.');}
}
function sv_reset_password(string $token,string $password): bool {
 if(!preg_match('/^[a-f0-9]{64}$/D',$token)||strlen($password)<12||strlen($password)>72)return false;
 $hash=password_hash($password,PASSWORD_DEFAULT);$pdo=sv_next_db();$pdo->beginTransaction();
 try{
  // Same lock order as issuance prevents concurrent issuance/reset deadlocks.
  $s=$pdo->prepare('SELECT user_id FROM password_resets WHERE token_hash=?');$s->execute([hash('sha256',$token)]);$uid=$s->fetchColumn();if(!$uid){$pdo->rollBack();return false;}
  $s=$pdo->prepare("SELECT id FROM users WHERE id=? AND status='active' FOR UPDATE");$s->execute([$uid]);if(!$s->fetchColumn()){$pdo->rollBack();return false;}
  $s=$pdo->prepare('SELECT id FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE');$s->execute([hash('sha256',$token)]);if(!$s->fetchColumn()){$pdo->rollBack();return false;}
  $s=$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');$s->execute([$hash,$uid]);
  $s=$pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL');$s->execute([$uid]);
  sv_rotate_security_version($pdo,(int)$uid);$pdo->commit();
  audit_log('password_reset_completed','Password reset completed; earlier sessions revoked.',(int)$uid);return true;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
