<?php
if(getenv('SV_TEST_ALLOW_WRITES')!=='1'||!str_starts_with((string)getenv('SV_DB_SOCKET'),'/tmp/securevote-feature'))exit(2);
require __DIR__.'/../includes/bootstrap.php';$pdo=sv_next_db();
$pdo->exec("DELETE FROM users WHERE username='resetbrowser'");
if(($argv[1]??'')==='cleanup'){echo "Recovery fixture removed.\n";exit;}
$s=$pdo->prepare("INSERT INTO users(username,email,voter_code,password_hash,first_name,last_name,role,status) VALUES ('resetbrowser','resetbrowser@example.test','VC-RESET-BROWSER',?,'Reset','Student','voter','active')");$s->execute([password_hash('FeatureTest123',PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();
$s=$pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))');$s->execute([$uid,hash('sha256',str_repeat('a',64))]);echo "Local recovery fixture ready.\n";
