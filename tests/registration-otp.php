<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(2);
define('APP_ROOT', dirname(__DIR__));
$_SESSION = [];
function sv_config(string $key, string $default = ''): string { return $default; }
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = class_exists(\Pdo\Sqlite::class) ? \Pdo\Sqlite::connect('sqlite::memory:') : new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($pdo instanceof \Pdo\Sqlite) $pdo->createFunction('NOW', static fn() => date('Y-m-d H:i:s'));
    else $pdo->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, email TEXT UNIQUE, voter_code TEXT UNIQUE, password_hash TEXT, first_name TEXT, last_name TEXT, role TEXT, status TEXT, created_at TEXT)');
    return $pdo;
}
function sv_generate_voter_code(): string { return 'VC-OTP-TEST'; }
function audit_log(string $action, string $detail, ?int $id = null): void {}
require APP_ROOT . '/includes/registration-otp.php';

function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$form = ['first_name' => 'Sample', 'last_name' => 'Voter', 'username' => 'samplevoter', 'email' => 'sample@example.test'];
$sent = '';
$transport = static function (string $email, string $name, string $code) use (&$sent): bool {
    $sent = $code;
    return $email === 'sample@example.test' && $name === 'Sample Voter';
};

check(sv_registration_otp_begin($form, password_hash('Secure12345', PASSWORD_DEFAULT), $transport), 'Code issuance failed');
check((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0, 'Account created before verification');
check(preg_match('/^[0-9]{6}$/D', $sent) === 1, 'Code format invalid');
check(sv_registration_otp_check('not-a-code') === 'invalid', 'Malformed code accepted');
check(sv_registration_otp_check($sent) === 'valid', 'Correct code rejected');

$created = sv_create_registered_voter($form, (string) sv_registration_pending()['password_hash']);
check($created['ok'] === true, 'Verified account not created');
check((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'Expected one account');
check(sv_create_registered_voter($form, 'unused')['ok'] === false, 'Duplicate account accepted');

$_SESSION['pending_registration']['last_sent'] = time() - 61;
$oldCode = $sent;
check(sv_registration_otp_resend($transport), 'Resend failed');
check(sv_registration_otp_check($oldCode) !== 'valid' || $oldCode === $sent, 'Old code remained valid after resend');
$_SESSION['pending_registration']['attempts'] = SV_REGISTRATION_OTP_ATTEMPTS - 1;
$wrongCode = $sent === '999999' ? '000000' : '999999';
check(sv_registration_otp_check($wrongCode) === 'locked', 'Attempt limit not enforced');
check(sv_registration_otp_check($sent) === 'locked', 'Locked code was accepted');
$_SESSION['pending_registration']['expires_at'] = time() - 1;
check(sv_registration_otp_check($sent) === 'expired', 'Expired code accepted');

require APP_ROOT . '/vendor/phpmailer/phpmailer/src/Exception.php';
require APP_ROOT . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require APP_ROOT . '/vendor/phpmailer/phpmailer/src/SMTP.php';
check(class_exists(\PHPMailer\PHPMailer\PHPMailer::class), 'PHPMailer did not load');
echo "Registration OTP lifecycle passed.\n";
