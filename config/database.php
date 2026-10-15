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
 * Return the shared PDO connection, opening it on first use. Optional callers
 * such as traffic logging can request null instead of interrupting the page.
 */
function db(bool $required = true): ?PDO
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
            if ($required) {
                sv_render_database_error();
                exit;
            }
            return null;
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
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Service unavailable &middot; SecureVote</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/assets/css/style.css', ENT_QUOTES) ?>">
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body.app-auth, body.app-shell--auth {
    margin: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 32px 16px;
    font-family: Figtree, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background-color: #f8fafc;
    color: #0f172a;
    -webkit-font-smoothing: antialiased;
  }
  @media (prefers-color-scheme: dark) {
    body.app-auth, body.app-shell--auth {
      background-color: #0b1222;
      color: #f8fafc;
    }
  }
  .auth-main {
    width: 100%;
    max-width: 490px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    margin: 0 auto;
  }
  .error-card {
    width: 100%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
    padding: 40px 32px 32px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    overflow: hidden;
  }
  @media (prefers-color-scheme: dark) {
    .error-card {
      background: #152037;
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4);
    }
  }
  .error-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #ef4444, #f59e0b, #3b82f6);
  }
  .brand {
    margin-bottom: 24px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
  }
  .brand__word { font-size: 1.35rem; font-weight: 800; color: inherit; letter-spacing: -0.02em; }
  .brand__word span { color: #2563eb; }
  .error-card__badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-radius: 9999px;
    font-size: 0.8125rem;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    margin-bottom: 14px;
    background: rgba(220, 38, 38, 0.08);
    color: #dc2626;
    border: 1px solid #fecaca;
  }
  .error-card__title {
    font-size: 1.55rem;
    font-weight: 800;
    margin: 0 0 10px 0;
    letter-spacing: -0.025em;
    color: inherit;
    line-height: 1.25;
  }
  .error-card__message {
    font-size: 0.95rem;
    color: #64748b;
    margin: 0 0 20px 0;
    line-height: 1.6;
    max-width: 400px;
  }
  @media (prefers-color-scheme: dark) {
    .error-card__message { color: #94a3b8; }
  }
  .text-muted { color: #64748b; }
  .text-sm { font-size: 0.8125rem; }
</style>
</head>
<body class="app-shell app-shell--auth app-auth">
  <div class="auth-main">
    <main class="auth-card error-card" role="alert">
      <div class="brand brand--lg">
        <svg class="brand__mark" width="36" height="36" viewBox="0 0 32 32" role="img" aria-label="SecureVote shield logo">
          <path d="M16 2.75 5.5 6.5v9.1c0 6.6 4.4 11.9 10.5 13.65C22.1 27.5 26.5 22.2 26.5 15.6V6.5Z" fill="#2563eb"/>
          <path d="m11.4 16.2 3.2 3.2 6-6.6" fill="none" stroke="#ffffff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="brand__word">Secure<span>Vote</span></span>
      </div>

      <div class="error-card__badge" aria-hidden="true">
        <span>HTTP 503 &middot; Service Unavailable</span>
      </div>

      <h1 class="error-card__title">Service temporarily unavailable</h1>
      <p class="error-card__message">SecureVote cannot reach its database right now, so no ballots can be accepted.</p>
      <?php if ($detail !== ''): ?>
        <p class="text-muted text-sm" style="margin-bottom: 12px;"><?= htmlspecialchars($detail, ENT_QUOTES) ?></p>
      <?php endif; ?>
      <p class="text-muted text-sm">Please try again in a few minutes.</p>
    </main>
  </div>
</body>
</html>
    <?php
}
