<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * auth/authenticate.php
 *
 * Handles the sign-in POST. Kept separate from login.php so the form page
 * stays a pure GET view, which makes the flow easy to explain and easy to
 * test: POST here, redirect there.
 *
 * Order of checks (this order matters):
 *   1. the request must be a POST
 *   2. the CSRF token must match the session
 *   3. credentials are verified with password_verify()
 *   4. the account status must be "active"
 *   5. only then is a new session id issued
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// 1. Only POST is accepted. A link or a typed URL gets an honest 405.
if (!is_post()) {
    header('Allow: POST');
    sv_error_page(
        405,
        'Method not allowed',
        'Sign-in details must be submitted from the sign-in form.',
        [['label' => 'Go to the sign-in form', 'href' => url('auth/login.php')]]
    );
}

// 2. Cross-site request forgery check. require_csrf() logs the event and
//    answers 403 rather than guessing what the visitor meant.
require_csrf();

// 3. Raw POST values: the password is never trimmed, because spaces can be
//    part of a password.
$identifier = (string) input('identifier', '');
$password   = (string) ($_POST['password'] ?? '');

$result = attempt_login($identifier, $password);

if (($result['ok'] ?? false) === true) {
    $user = $result['user'];
    login_user($user);

    $roleName = sv_language() === 'fil' ? ($user['role'] === 'admin' ? 'tagapangasiwa (admin)' : 'botante') : $user['role'];
    flash_success(sprintf(sv_t('Welcome back, %s. You are signed in as %s.'), $user['first_name'], $roleName));

    // Return the visitor to the page they were trying to open, but only if it
    // is a path inside this application. Anything that could be read as an
    // absolute or protocol-relative URL is ignored, so this cannot be turned
    // into an open redirect.
    $intended = $_SESSION['intended_path'] ?? '';
    unset($_SESSION['intended_path']);

    if (
        is_string($intended)
        && str_starts_with($intended, '/')
        && !str_starts_with($intended, '//')
        && !str_contains($intended, '\\')
    ) {
        header('Location: ' . $intended);
        exit;
    }

    redirect(dashboard_path_for_current_user());
}

// 4. Failure: record the reason in the session (never in the URL) and send
//    the visitor back to the form.
$fields = [];
if ($identifier === '') {
    $fields['identifier'] = sv_t('Enter your email, username, or voter code.');
}
if ($identifier !== '' && $password === '' && ($result['error'] ?? '') === 'Incorrect password entered.') {
    $fields['password'] = sv_t('Enter your password.');
}

$_SESSION['_login_error'] = [
    'message' => sv_t((string) ($result['error'] ?? 'Sign-in failed. Please try again.')),
    'fields'  => $fields,
];
// Keep the typed identifier so the visitor does not have to retype it. The
// password is deliberately never kept.
$_SESSION['_login_identifier'] = mb_substr($identifier, 0, 150);

redirect('auth/login.php');
