<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/csrf.php
 *
 * Cross-Site Request Forgery protection.
 *
 * Every state-changing request (create, update, delete, submit a ballot)
 * is a POST that must carry a token which only this server could have
 * put in the page. An attacker's page on another origin cannot read our
 * token, so it cannot forge a request that our server will accept.
 *
 * The token is compared with hash_equals(), which takes the same amount
 * of time for a wrong token as for a right one (no timing leak).
 */

/** The session's CSRF token, generated on first use. */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

/** Hidden input for a form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Is this token the one we issued for this session? */
function csrf_valid(?string $token): bool
{
    if (!is_string($token) || $token === '' || empty($_SESSION['_csrf_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['_csrf_token'], $token);
}

/**
 * Throw away the current token and issue a new one.
 * Called after login and logout so a token captured before a session
 * change is worthless afterwards.
 */
function csrf_rotate(): string
{
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

    return $_SESSION['_csrf_token'];
}

/**
 * Guard for every POST handler.
 *
 * A missing or stale token is treated as a security event: it is logged
 * to the audit trail and answered with 403, never with a silent
 * "something went wrong".
 */
function require_csrf(): void
{
    if (csrf_valid($_POST['csrf_token'] ?? null)) {
        return;
    }

    $action = current_script();
    audit_log('csrf_failure', 'Rejected POST to ' . $action . ': missing or invalid CSRF token.');

    flash_error('Your session token was missing or had expired. Please try again.');
    sv_error_page(
        403,
        'Request blocked',
        'SecureVote rejected this request because its security token was missing or no longer valid. '
        . 'This usually means the page was open for too long, or the form was submitted from another site.',
        [['label' => 'Back to dashboard', 'href' => url(dashboard_path_for_current_user())]]
    );
}

/** Where should a signed-in user be sent? */
function dashboard_path_for_current_user(): string
{
    return (($_SESSION['user_role'] ?? '') === 'admin') ? 'admin/dashboard.php' : 'voter/dashboard.php';
}
