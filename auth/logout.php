<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * auth/logout.php
 *
 * Signing out is a state change, so it is a POST protected by a CSRF
 * token rather than a plain link. A link would let any other site log the
 * visitor out, and would also show up in browser history and referrer
 * headers.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    header('Allow: POST');
    sv_error_page(
        405,
        'Method not allowed',
        'Signing out must be confirmed from inside the application.',
        [['label' => 'Return to my dashboard', 'href' => url(dashboard_path_for_current_user())]]
    );
}

require_csrf();
require_login();

logout_user();

redirect('auth/login.php');
