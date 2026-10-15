<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/bootstrap.php
 *
 * The single entry point for shared code. Every page starts with:
 *
 *   require_once __DIR__ . '/../includes/bootstrap.php';
 *
 * Load order matters:
 *   config  -> constants, security headers, session
 *   database-> db()
 *   functions, flash, csrf, auth, icons
 *   then the global error handlers and the session guards.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/traffic.php';

// Any uncaught exception or fatal error becomes a calm, branded page and
// a line in storage/logs/php-error.log, never a stack trace on screen.
set_exception_handler('sv_handle_exception');
register_shutdown_function('sv_handle_shutdown');

// Session lifetime, idle timeout and browser binding are checked before
// the page body runs, so no page can forget them.
if (PHP_SAPI !== 'cli') {
    sv_enforce_session_security();
    sv_track_page_request();
}
