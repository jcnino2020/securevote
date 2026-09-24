<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * config/config.php
 *
 * Central configuration. Every entry point includes this file first
 * (normally through includes/bootstrap.php) so that error handling,
 * timezone, session rules and security headers are identical on every
 * page. Nothing here may depend on a logged-in user.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
 | 1. Debug switch
 |
 | Keep this FALSE during the endterm defence. TRUE prints stack traces
 | and database error text into the browser, which is exactly the
 | "information disclosure" problem the project is meant to prevent.
 * ------------------------------------------------------------------- */
define('APP_DEBUG', false);

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

define('APP_ROOT', dirname(__DIR__));
define('LOG_DIR', APP_ROOT . '/storage/logs');
if (is_dir(LOG_DIR) && is_writable(LOG_DIR)) {
    ini_set('error_log', LOG_DIR . '/php-error.log');
}

/* ---------------------------------------------------------------------
 | 2. Application identity
 * ------------------------------------------------------------------- */
define('APP_NAME', 'SecureVote');
define('APP_TAGLINE', 'Secure online voting for authorized participants.');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Manila');

date_default_timezone_set(APP_TIMEZONE);

/* ---------------------------------------------------------------------
 | 3. Database credentials
 |
 | Values may be overridden with environment variables so the same code
 | runs on XAMPP, on the lab machine and on a shared host without editing
 | this file. Defaults match a stock XAMPP / MariaDB install.
 |
 |   SV_DB_HOST  SV_DB_PORT  SV_DB_NAME  SV_DB_USER  SV_DB_PASS
 |   SV_DB_SOCKET  (set to a socket path to ignore host/port, e.g.
 |                  /Applications/XAMPP/xamppfiles/var/mysql/mysql.sock)
 * ------------------------------------------------------------------- */
define('DB_HOST',   getenv('SV_DB_HOST') ?: 'localhost');
define('DB_PORT',   getenv('SV_DB_PORT') ?: '3306');
define('DB_NAME',   getenv('SV_DB_NAME') ?: 'u184230832_securevote');
define('DB_USER',   getenv('SV_DB_USER') ?: 'u184230832_ibmn');
define('DB_PASS',   (string) (getenv('SV_DB_PASS') ?: 'Bsit4b2026'));
define('DB_SOCKET', (string) (getenv('SV_DB_SOCKET') ?: ''));
define('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------
 | 4. Session hardening
 |
 | These are the values the project defends during the presentation:
 |   - the cookie is HttpOnly (JavaScript cannot read it)
 |   - the cookie is SameSite=Lax (blocks cross-site form posting)
 |   - strict mode rejects a session ID the server never issued
 |   - cookies only (an ID in the URL could be leaked through a link)
 * ------------------------------------------------------------------- */
define('SESSION_NAME', 'SECUREVOTE_SESSION');
define('SESSION_IDLE_TIMEOUT', 1800);   // 30 minutes of inactivity
define('SESSION_ABSOLUTE_TIMEOUT', 7200); // hard 2 hour cap, then re-login

/* ---------------------------------------------------------------------
 | 5. Base URL detection
 |
 | The app must work both at a vhost root (http://localhost:8000/) and in
 | a sub-folder (http://localhost/secure-voting-system/). We derive the
 | sub-folder by comparing the application folder with the document root.
 * ------------------------------------------------------------------- */
$svDocRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? str_replace('\\', '/', rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/'))
    : '';
$svAppRoot = str_replace('\\', '/', (string) APP_ROOT);
$svBase    = '';

if ($svDocRoot !== '' && str_starts_with($svAppRoot, $svDocRoot)) {
    $svBase = substr($svAppRoot, strlen($svDocRoot));
}
define('BASE_URL', rtrim($svBase, '/'));
unset($svDocRoot, $svAppRoot, $svBase);


/* ---------------------------------------------------------------------
 | 6. Google OAuth 2.0 Credentials
 |
 | Provide GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to enable Google
 | sign-in. If left empty in development, SecureVote provides an instant
 | verified OAuth sign-in flow for testing.
 * ------------------------------------------------------------------- */
define('GOOGLE_CLIENT_ID',     (string) (getenv('GOOGLE_CLIENT_ID') ?: ''));
define('GOOGLE_CLIENT_SECRET', (string) (getenv('GOOGLE_CLIENT_SECRET') ?: ''));
define('GOOGLE_OAUTH_ENABLED', true);


/* ---------------------------------------------------------------------
 | 6. Helpers that must exist before anything else runs
 * ------------------------------------------------------------------- */

/** True when the request reached us over HTTPS. */
function sv_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    // Behind a reverse proxy / load balancer.
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }

    return false;
}

/**
 * Send the security response headers once per request.
 *
 * Content-Security-Policy stays restrictive: no inline <script>, no
 * remote scripts. Fonts are allowed from Google Fonts because the brand
 * kit uses Figtree, and a local fallback stack is declared in CSS.
 */
function sv_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('X-XSS-Protection: 0'); // modern browsers: rely on CSP, not the legacy filter
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin');

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "img-src 'self' data:",
        "font-src 'self' https://fonts.gstatic.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "script-src 'self'",
        "connect-src 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));

    if (sv_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Start the hardened session. Safe to call from every page: it does
 * nothing if a session already exists.
 */
function sv_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_ABSOLUTE_TIMEOUT);

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,                       // session cookie, dies with the browser
        'path'     => (BASE_URL === '' ? '/' : BASE_URL . '/'),
        'domain'   => '',
        'secure'   => sv_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/* ---------------------------------------------------------------------
 | 7. Run the request-level setup (skipped for CLI linting / cron)
 * ------------------------------------------------------------------- */
if (PHP_SAPI !== 'cli') {
    sv_send_security_headers();
    sv_start_session();
}
