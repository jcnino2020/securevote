<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/auth.php
 *
 * Authentication (who you are) and authorization (what you may do).
 *
 * Rules enforced here:
 *   - passwords are only ever stored and compared as bcrypt hashes
 *   - the session id is regenerated on privilege change (login/logout)
 *   - a session dies after 30 minutes idle or 2 hours total
 *   - the session is bound to a browser fingerprint
 *   - a disabled or blocked account loses its session immediately
 *   - a page that requires a role cannot be reached by the other role
 */

/* =====================================================================
 | Current identity
 * =================================================================== */

/**
 * The signed-in user, loaded fresh from the database.
 *
 * Reading the row on every request (instead of trusting session values)
 * means blocking an account takes effect at once, and role changes
 * cannot be faked by editing a cookie.
 */
function current_user(): ?array
{
    static $cached = null;
    static $loaded = false;

    if ($loaded) {
        return $cached;
    }
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return $cached = null;
    }

    $stmt = db()->prepare(
        'SELECT id, username, email, first_name, last_name, role, status, created_at, last_login
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        // Account deleted while the session was open.
        sv_end_user_session('Your account is no longer available.', 'session_invalid');

        return $cached = null;
    }

    if ($row['status'] !== 'active') {
        // Blocked or deactivated mid-session: end it immediately.
        sv_end_user_session(
            'Your account is no longer active. Please contact the election administrator.',
            'session_deactivated'
        );

        return $cached = null;
    }

    return $cached = $row;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function is_voter(): bool
{
    return (current_user()['role'] ?? '') === 'voter';
}

/** Convenience for views: "Juan Dela Cruz". */
function user_full_name(?array $user = null): string
{
    $user = $user ?? current_user();
    if ($user === null) {
        return 'Guest';
    }

    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

/* =====================================================================
 | Session lifecycle
 * =================================================================== */

/**
 * A cheap fingerprint of the browser. If it changes mid-session, the
 * cookie was probably copied to another machine.
 *
 * Deliberately NOT built from the IP address: students on the campus
 * network change IPs legitimately, and locking them out would be worse
 * than the attack it prevents.
 */
function sv_fingerprint(): string
{
    return hash('sha256', mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));
}

/**
 * Expire the current session safely and send the visitor to the login
 * page. The session itself is kept (with a new id) so the flash message
 * survives the redirect.
 */
function sv_end_user_session(string $message, string $auditAction = 'logout'): void
{
    if (isset($_SESSION['user_id'])) {
        audit_log($auditAction, 'Session ended for user #' . (int) $_SESSION['user_id'] . '.');
    }

    unset(
        $_SESSION['user_id'],
        $_SESSION['user_role'],
        $_SESSION['fingerprint'],
        $_SESSION['created_at'],
        $_SESSION['last_activity']
    );

    session_regenerate_id(true);
    csrf_rotate();

    flash_warning($message);
    redirect('auth/login.php');
}

/**
 * Per-request session checks. Called from bootstrap.php before any page
 * code runs, so no page can accidentally skip them.
 */
function sv_enforce_session_security(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $now = time();

    // 1. Absolute lifetime: even an active session must be re-established.
    if (!empty($_SESSION['created_at']) && ($now - (int) $_SESSION['created_at']) > SESSION_ABSOLUTE_TIMEOUT) {
        sv_end_user_session('Your session reached its maximum lifetime. Please sign in again.', 'session_expired');
    }

    // 2. Idle timeout.
    if (!empty($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
        sv_end_user_session('You were signed out after 30 minutes of inactivity.', 'session_timeout');
    }
    $_SESSION['last_activity'] = $now;

    // 3. Browser binding.
    $fingerprint = sv_fingerprint();
    if (!empty($_SESSION['fingerprint']) && !hash_equals((string) $_SESSION['fingerprint'], $fingerprint)) {
        audit_log('session_fingerprint_mismatch', 'Session fingerprint changed; session terminated as a precaution.');
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_role'],
            $_SESSION['fingerprint'],
            $_SESSION['created_at'],
            $_SESSION['last_activity']
        );
        session_regenerate_id(true);
        csrf_rotate();
        flash_error('For security, the session was closed because the browser signature changed.');
        redirect('auth/login.php');
    }
}

/* =====================================================================
 | Sign in / sign out
 * =================================================================== */

/** How many failed attempts for this identifier in the last 15 minutes. */
function sv_failed_attempt_count(string $identifier): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM audit_logs
          WHERE action = 'login_failure'
            AND description LIKE :pattern
            AND created_at > (NOW() - INTERVAL 15 MINUTE)"
    );
    // Escape LIKE wildcards so a crafted username cannot widen the match.
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $identifier);
    $stmt->execute([':pattern' => 'Failed sign-in for "' . $escaped . '"%']);

    return (int) $stmt->fetchColumn();
}

/**
 * Verify credentials.
 *
 * @return array{ok:bool, user?:array, error?:string}
 */
function attempt_login(string $identifier, string $password): array
{
    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return ['ok' => false, 'error' => 'Enter your username or email address and your password.'];
    }

    // Simple brute-force brake: five failures inside 15 minutes locks the
    // identifier for the rest of the window. Public IPs are shared on
    // campus, so the brake is per account, not per address.
    if (sv_failed_attempt_count($identifier) >= 5) {
        audit_log('login_throttled', 'Blocked sign-in for "' . $identifier . '": too many recent failures.');

        return [
            'ok'    => false,
            'error' => 'Too many failed attempts for this account. Please wait about 15 minutes and try again.',
        ];
    }

    // Two distinct placeholders on purpose: a named parameter must not be
    // reused inside one statement when prepared statements are not emulated.
    $stmt = db()->prepare(
        'SELECT id, username, email, password_hash, first_name, last_name, role, status
         FROM users WHERE username = :username OR email = :email LIMIT 1'
    );
    $stmt->execute([':username' => $identifier, ':email' => $identifier]);
    $user = $stmt->fetch();

    // Unlike a wrong password, an unknown account is logged without a
    // user id because there is nobody to attribute it to.
    if (!$user) {
        audit_log('login_failure', 'Failed sign-in for "' . $identifier . '": no such account.');
        usleep(200000); // slow down scripted guessing a little

        return ['ok' => false, 'error' => 'Incorrect username/email or password.'];
    }

    if ($user['status'] !== 'active') {
        audit_log(
            'login_blocked',
            'Blocked sign-in for "' . $identifier . '": account status is ' . $user['status'] . '.',
            (int) $user['id']
        );

        return [
            'ok'    => false,
            'error' => $user['status'] === 'blocked'
                ? 'This account has been blocked. Please contact the election administrator.'
                : 'This account is inactive. Please contact the election administrator.',
        ];
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        audit_log('login_failure', 'Failed sign-in for "' . $identifier . '": wrong password.', (int) $user['id']);
        usleep(200000);

        return ['ok' => false, 'error' => 'Incorrect username/email or password.'];
    }

    // Transparent upgrade when the hashing cost has been raised.
    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update  = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([$newHash, (int) $user['id']]);
        audit_log('password_rehash', 'Password hash upgraded to the current algorithm cost.', (int) $user['id']);
    }

    return ['ok' => true, 'user' => $user];
}

/** Establish the authenticated session for a verified user. */
function login_user(array $user): void
{
    // New session id: an id captured before login cannot be reused.
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['user_role']     = (string) $user['role'];
    $_SESSION['fingerprint']   = sv_fingerprint();
    $_SESSION['created_at']    = time();
    $_SESSION['last_activity'] = time();

    csrf_rotate();

    $stmt = db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $stmt->execute([(int) $user['id']]);

    audit_log('login_success', 'Signed in as "' . $user['username'] . '".', (int) $user['id']);
}

/** Sign out, keeping the flash message alive. */
function logout_user(): void
{
    $user = current_user();

    if ($user !== null) {
        audit_log('logout', 'Signed out "' . $user['username'] . '".', (int) $user['id']);
    }

    unset(
        $_SESSION['user_id'],
        $_SESSION['user_role'],
        $_SESSION['fingerprint'],
        $_SESSION['created_at'],
        $_SESSION['last_activity']
    );

    session_regenerate_id(true);
    csrf_rotate();

    flash_success('You have been signed out. Your ballot, if submitted, is already recorded.');
}

/* =====================================================================
 | Authorization guards
 |
 | Each guard either lets the request continue or stops it. Failing
 | closed is the only safe default: if the check cannot be completed,
 | access is denied.
 * =================================================================== */

/** Require *some* signed-in user. */
function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        $_SESSION['intended_path'] = (string) ($_SERVER['REQUEST_URI'] ?? '');
        flash_warning('Please sign in to continue.');
        redirect('auth/login.php');
    }

    return $user;
}

/** Require one specific role. Used by require_admin() / require_voter(). */
function require_role(string $role): array
{
    $user = require_login();

    if (($user['role'] ?? '') !== $role) {
        audit_log(
            'unauthorized_access',
            'User "' . $user['username'] . '" (' . $user['role'] . ') was denied ' . current_script() . '.',
            (int) $user['id']
        );

        sv_error_page(
            403,
            'Not authorized',
            'This area is reserved for ' . $role . ' accounts. You are signed in as a '
            . $user['role'] . ', so the page was not opened. The attempt has been recorded in the audit log.',
            [['label' => 'Return to my dashboard', 'href' => url(dashboard_path_for_current_user())]]
        );
    }

    return $user;
}

function require_admin(): array
{
    return require_role('admin');
}

function require_voter(): array
{
    return require_role('voter');
}

/** Login and registration are for visitors only. */
function require_guest(): void
{
    if (is_logged_in()) {
        redirect(dashboard_path_for_current_user());
    }
}

/* =====================================================================
 | Per-voter helpers used by the voting flow
 * =================================================================== */

/** The eligibility row for a voter in an election, or null. */
function voter_eligibility(int $electionId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM voter_eligibility WHERE election_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$electionId, $userId]);

    return $stmt->fetch() ?: null;
}

/** Has this voter already submitted a ballot for this election? */
function has_voted(int $electionId, int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM ballots WHERE election_id = ? AND voter_id = ? LIMIT 1');
    $stmt->execute([$electionId, $userId]);

    return (bool) $stmt->fetchColumn();
}

/** The voter's submitted ballot, if any. */
function voter_ballot(int $electionId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM ballots WHERE election_id = ? AND voter_id = ? LIMIT 1');
    $stmt->execute([$electionId, $userId]);

    return $stmt->fetch() ?: null;
}
