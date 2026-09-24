<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * config/database.php
 *
 * Single PDO connection factory. Every query in the project goes through
 * the prepared statements created from this connection, which is what
 * makes SQL injection ineffective:
 *
 *   - PDO::ATTR_EMULATE_PREPARES = false  -> the placeholder is sent to
 *     MySQL separately from the SQL text, so user input can never be
 *     parsed as SQL.
 *   - PDO::ATTR_ERRMODE = EXCEPTION       -> a broken query throws
 *     instead of silently returning false.
 *   - The database user should only be granted SELECT / INSERT /
 *     UPDATE / DELETE on this schema (see README section 5).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Return the shared PDO connection, opening it on first use.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // unix_socket wins when configured, otherwise fall back to TCP.
    if (DB_SOCKET !== '') {
        $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', DB_SOCKET, DB_NAME, DB_CHARSET);
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00', NAMES utf8mb4",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        // If TCP/host connection failed on shared hosting (e.g. Hostinger), try alternate host
        $fallbackHost = (DB_HOST === 'localhost') ? '127.0.0.1' : 'localhost';
        try {
            $fallbackDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $fallbackHost, DB_PORT, DB_NAME, DB_CHARSET);
            $pdo = new PDO($fallbackDsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e2) {
            error_log('DB connection failed: ' . $e->getMessage() . ' | Fallback error: ' . $e2->getMessage());
            sv_render_database_error();
            exit;
        }
    }

    return $pdo;
}

/**
 * Render the friendly "database unavailable" page and stop.
 * Kept here (not in functions.php) so it is available even when the
 * database, and therefore the session user lookup, is broken.
 */
function sv_render_database_error(): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database unavailable. Check config/config.php and that MySQL/MariaDB is running.\n");
        return;
    }

    http_response_code(503);
    $detail = APP_DEBUG ? 'Check config/config.php and confirm MySQL/MariaDB is running.' : '';
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Service unavailable &middot; SecureVote</title>
<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES) ?>/assets/css/style.css">
</head>
<body class="app-auth">
  <main class="auth-card" role="alert">
    <div class="brand brand--lg">
      <span class="brand__mark" aria-hidden="true"></span>
      <span class="brand__word">Secure<span>Vote</span></span>
    </div>
    <h1 class="auth-card__title">Service temporarily unavailable</h1>
    <p class="text-muted">SecureVote cannot reach its database right now, so no ballots can be accepted.</p>
    <?php if ($detail !== ''): ?>
      <p class="text-muted text-sm"><?= htmlspecialchars($detail, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <p class="text-muted text-sm">Please try again in a few minutes.</p>
  </main>
</body>
</html>
    <?php
}
