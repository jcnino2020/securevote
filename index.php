<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * index.php
 *
 * The single entry point. It does not render anything itself: it sends
 * the visitor to the right place.
 *
 *   signed in  -> the dashboard for their role
 *   visitor    -> the sign-in page
 *
 * Keeping this page trivial means there is only one place a visitor can
 * arrive, and the login page stays the front door of the system (as the
 * brand kit requires).
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_path_for_current_user());
}

redirect('auth/login.php');
