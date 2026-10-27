<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/election-data.php';
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
session_name('SECUREVOTE_DEMO_SESSION');
$cookiePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/demo/index.php'))), '/') . '/';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
session_set_cookie_params(['lifetime' => 0, 'path' => $cookiePath, 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com; style-src 'self' https://fonts.googleapis.com; script-src 'self'");
header('Cache-Control: no-store');

function demo_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function demo_csrf(): string { return $_SESSION['demo_csrf'] ??= bin2hex(random_bytes(32)); }
function demo_check_csrf(): void {
    if (!hash_equals(demo_csrf(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form session. Reload the page and try again.');
    }
}
function demo_redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }

function demo_db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    // Keep the demo account store outside public_html. Local tests may override the path.
    $path = getenv('SV_DEMO_DB_FILE') ?: dirname(__DIR__, 3) . '/securevote-demo.sqlite';
    try {
        $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        chmod($path, 0600);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('CREATE TABLE IF NOT EXISTS demo_users (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT NOT NULL, last_name TEXT NOT NULL, username TEXT NOT NULL UNIQUE COLLATE NOCASE, email TEXT NOT NULL UNIQUE COLLATE NOCASE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS demo_login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, identifier_hash TEXT NOT NULL, attempted_at INTEGER NOT NULL)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS demo_login_attempts_lookup ON demo_login_attempts (identifier_hash, attempted_at)');
        demo_seed_elections($pdo);
        return $pdo;
    } catch (Throwable $error) {
        error_log('SecureVote demo database unavailable: ' . $error->getMessage());
        http_response_code(503);
        exit('The account service is temporarily unavailable.');
    }
}

function demo_user(): ?array {
    $id = (int) ($_SESSION['demo_user_id'] ?? 0);
    if ($id < 1) return null;
    $stmt = demo_db()->prepare('SELECT id, first_name, last_name, username, email, created_at FROM demo_users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) unset($_SESSION['demo_user_id']);
    return $user ?: null;
}

function demo_require_user(): array {
    $user = demo_user();
    if (!$user) demo_redirect('login.php');
    if (time() - (int) ($_SESSION['demo_login_at'] ?? 0) > 7200) {
        unset($_SESSION['demo_user_id'], $_SESSION['demo_login_at']);
        demo_redirect('login.php');
    }
    return $user;
}

function demo_logo(): string {
    return '<svg class="brand-mark" viewBox="0 0 32 32" role="img" aria-label="SecureVote shield logo"><path d="M16 2.75 5.5 6.5v9.1c0 6.6 4.4 11.9 10.5 13.65C22.1 27.5 26.5 22.2 26.5 15.6V6.5Z" fill="currentColor"/><path d="m11.4 16.2 3.2 3.2 6-6.6" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function demo_header(string $title, string $active = ''): void {
    $user = demo_user();
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#1a2750"><title><?= demo_e($title) ?> · SecureVote</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="site.css"><link rel="stylesheet" href="elections.css"></head><body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header"><a class="brand" href="./"><?= demo_logo() ?><span>Secure<span class="brand-accent">Vote</span></span></a><nav aria-label="Main navigation"><a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="./">Home</a><?php if ($user): ?><a class="<?= $active === 'dashboard' ? 'is-active' : '' ?>" href="dashboard.php">Dashboard</a><a class="<?= $active === 'results' ? 'is-active' : '' ?>" href="results.php">Results</a><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= demo_e(demo_csrf()) ?>"><button type="submit">Sign out</button></form><?php else: ?><a class="<?= $active === 'login' ? 'is-active' : '' ?>" href="login.php">Sign in</a><a class="header-cta <?= $active === 'register' ? 'is-active' : '' ?>" href="register.php">Register</a><?php endif; ?></nav></header>
<main id="main">
<?php
}

function demo_footer(): void {
    ?></main><footer class="site-footer"><span>SecureVote · Academic civic voting platform</span><span>Sample elections and ballots are separate from official voting</span></footer></body></html><?php
}
