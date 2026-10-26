<?php
/** Run only on a disposable schema: SV_TEST_ALLOW_WRITES=1 php tests/community-features.php */
if (getenv('SV_TEST_ALLOW_WRITES') !== '1' || !str_starts_with((string) getenv('SV_DB_SOCKET'), '/tmp/securevote-feature')) {
    fwrite(STDERR, "Use the isolated feature-test socket and SV_TEST_ALLOW_WRITES=1.\n"); exit(2);
}
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/community.php';
require __DIR__ . '/../includes/voter-import.php';
require __DIR__ . '/../includes/voting.php';
set_exception_handler(function (Throwable $e): void { fwrite(STDERR, 'FAIL ' . $e->getMessage() . "\n"); exit(1); });
$checks = 0;
function check(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; echo "PASS $label\n"; }
function csv_parse(string $csv): array { $f = fopen('php://temp', 'r+'); fwrite($f, $csv); rewind($f); $r = sv_parse_voter_csv($f); fclose($f); return $r; }
$parsed = csv_parse("\xEF\xBB\xBFemail,username,last_name,first_name\nnew@example.test,newstudent,Student,New\n");
check(!$parsed['errors'] && $parsed['rows'][0]['first_name'] === 'New', 'CSV accepts UTF-8 BOM and reordered headers');
check(count(csv_parse("username,email,first_name,last_name\naaa,a@example.test,A,B\naaa,b@example.test,C,D\n")['errors']) > 0, 'Duplicate usernames rejected');
check(count(csv_parse("username,email,first_name,last_name\naaa,a@example.test,A,B\nbbb,a@example.test,C,D\n")['errors']) > 0, 'Duplicate emails rejected');
check(count(csv_parse("username,email,first_name,last_name,password\naaa,a@example.test,A,B,unsafe\n")['errors']) > 0, 'Password columns rejected');
check(count(csv_parse("username,email,first_name,last_name\nbad user,no,A,B\n")['errors']) === 2, 'Invalid username and email rejected');
check(count(csv_parse("username,email,first_name,last_name\n")['errors']) > 0, 'Header-only CSV rejected');
$long = "username,email,first_name,last_name\n";
for ($i = 0; $i < 501; $i++) $long .= "usr$i,u$i@example.test,A,B\n";
check(count(csv_parse($long)['errors']) > 0, '501-row CSV rejected');
$pdo = sv_community_db();
$pdo->exec('DROP TABLE feature_rate_limits'); // exercise lazy migration with partial setup
// Fresh PHP function cache is already ready, explicitly exercise migration SQL as deployment would.
$sql = file_get_contents(__DIR__ . '/../database/migration_community_features.sql');
foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $pdo->exec($statement);
foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $pdo->exec($statement);
check((int) $pdo->query('SELECT COUNT(*) FROM feature_rate_limits')->fetchColumn() === 0, 'Migration applies safely twice');
$pdo->exec("DELETE FROM users WHERE username IN ('newstudent','blockedtest','rollbackone')");
$pdo->exec("INSERT INTO users (username,email,voter_code,first_name,last_name,role,status) VALUES ('blockedtest','blocked@example.test','VC-BLOCKED','Block','Test','voter','blocked')");
$blocked = csv_parse("username,email,first_name,last_name\nblockedtest,blocked@example.test,Block,Test\n");
check(count(sv_check_import_rows($pdo, $blocked['rows'])['errors']) === 1, 'Blocked voters cannot be imported');
$admin = csv_parse("username,email,first_name,last_name\nadmin,admin@securevote.local,A,B\n");
check(count(sv_check_import_rows($pdo, $admin['rows'])['errors']) === 1, 'Administrator accounts cannot be imported');
$counts = sv_commit_voter_import($pdo, $parsed['rows'], 1);
check($counts === ['created' => 1, 'existing' => 0], 'Import creates one account and enrols it');
$row = $pdo->query("SELECT * FROM users WHERE username = 'newstudent'")->fetch();
check($row['password_hash'] === null && str_starts_with($row['voter_code'], 'VC-'), 'Imported account has a generated code for signing in');
check(attempt_login($row['voter_code'], '')['ok'], 'Imported voter can sign in with generated code');
check(!attempt_login($row['username'], 'arbitrary-password')['ok'], 'Code-only voter cannot sign in with an arbitrary password');
$pdo->prepare('UPDATE voter_eligibility SET has_voted = 1 WHERE election_id = 1 AND user_id = ?')->execute([$row['id']]);
$counts = sv_commit_voter_import($pdo, $parsed['rows'], 1);
check($counts === ['created' => 0, 'existing' => 1], 'Reimport retains matching account');
check((int) $pdo->query('SELECT has_voted FROM voter_eligibility WHERE election_id = 1 AND user_id = ' . (int) $row['id'])->fetchColumn() === 1, 'Reimport preserves participation');
$conflict = $parsed['rows']; $conflict[0]['email'] = 'conflict@example.test';
check(count(sv_check_import_rows($pdo, $conflict)['errors']) === 1, 'Partial identity matches rejected');
$rollback = csv_parse("username,email,first_name,last_name\nrollbackone,rollback@example.test,A,B\nblockedtest,blocked@example.test,A,B\n");
try { sv_commit_voter_import($pdo, $rollback['rows'], 1); check(false, 'Conflict must throw'); } catch (RuntimeException $e) {}
check((int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'rollbackone'")->fetchColumn() === 0 && !$pdo->inTransaction(), 'Conflicting import leaves no partial accounts');
$duplicate = csv_parse("username,email,first_name,last_name\nrollbackone,rollback@example.test,A,B\n")['rows'];
try { sv_commit_voter_import($pdo, [$duplicate[0], $duplicate[0]], 1); check(false, 'Database duplicate must throw'); } catch (PDOException $e) {}
check((int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'rollbackone'")->fetchColumn() === 0, 'Late database failure rolls back earlier inserts');
$pdo->exec("INSERT INTO elections (title,start_datetime,end_datetime,status) VALUES ('Closed test',NOW()-INTERVAL 2 DAY,NOW()-INTERVAL 1 DAY,'closed')");
$closed = (int) $pdo->lastInsertId();
try { sv_commit_voter_import($pdo, $parsed['rows'], $closed); check(false, 'Closed election must throw'); } catch (RuntimeException $e) {}
check(!$pdo->inTransaction(), 'Closed election import rejected');
$practice = sv_practice_ballot();
check(sv_validate_submission($practice, [])['ok'], 'Practice supports abstention');
check(!sv_validate_submission($practice, [1 => [101,102]])['ok'], 'Practice enforces single-selection limit');
check(!sv_validate_submission($practice, [2 => [201,202,203]])['ok'], 'Practice enforces multi-selection limit');
check(!sv_validate_submission($practice, [1 => [201]])['ok'], 'Practice rejects candidates from another position');
check(sv_validate_submission($practice, [1 => [101], 2 => [201,202]])['ok'], 'Valid practice ballot accepted');
$election = $pdo->query('SELECT * FROM elections WHERE id = 1')->fetch();
check(count(sv_election_readiness($pdo, $election)) === 5, 'Readiness queries all five preparation checks');
$pdo->exec("DELETE FROM feature_rate_limits");
check(sv_feature_allow('test-receipt', 2, 900), 'First receipt attempt allowed');
check(sv_feature_allow('test-receipt', 2, 900), 'Second receipt attempt allowed');
check(!sv_feature_allow('test-receipt', 2, 900), 'Database rate limit blocks third attempt');
echo "Completed $checks checks.\n";
