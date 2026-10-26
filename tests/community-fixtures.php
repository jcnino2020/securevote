<?php
if (getenv('SV_TEST_ALLOW_WRITES') !== '1' || !str_starts_with((string) getenv('SV_DB_SOCKET'), '/tmp/securevote-feature')) { fwrite(STDERR, "Use the disposable test database only.\n"); exit(2); }
require __DIR__ . '/../includes/bootstrap.php';
$pdo = db();
$pdo->prepare('UPDATE users SET password_hash = ? WHERE username = ?')->execute([password_hash('FeatureTest123', PASSWORD_DEFAULT), 'admin']);
$pdo->prepare("INSERT INTO users (username,email,voter_code,password_hash,first_name,last_name,role,status) VALUES ('browserstudent','browser@example.test','VC-TEST-BROWSER',?,'Browser','Student','voter','active') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)")->execute([password_hash('FeatureTest123', PASSWORD_DEFAULT)]);
$uid = $pdo->query("SELECT id FROM users WHERE username='browserstudent'")->fetchColumn();
$pdo->prepare('INSERT IGNORE INTO voter_eligibility (election_id,user_id) VALUES (1,?)')->execute([$uid]);
$pdo->prepare("INSERT INTO ballots (election_id,voter_id,ballot_reference) VALUES (2,?,'SV-2026-ABC123') ON DUPLICATE KEY UPDATE ballot_reference=VALUES(ballot_reference)")->execute([$uid]);
$pdo->exec("INSERT INTO elections (title,start_datetime,end_datetime,status) VALUES ('Private draft election',NOW(),NOW()+INTERVAL 1 DAY,'draft')");
$draft = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO announcements (election_id,title,body,status,created_by) VALUES (?,'Secret draft update','This must not be publicly visible.','published',1)")->execute([$draft]);
$pdo->exec("INSERT INTO announcements (title,body,status,created_by) VALUES ('Unpublished test update','This announcement must stay private.','draft',1)");
echo "Browser fixtures ready.\n";
