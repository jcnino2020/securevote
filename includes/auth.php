<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/auth.php
 *
 * Authentication (who you are) and authorization (what you may do).
 *
 * Supported Sign-in Methods:
 *   1. Google OAuth 2.0 (Verified email & Google profile)
 *   2. Database Email or Username + Password
 *   3. Voter Code / Student Passcode (e.g. VC-2026-XXXX)
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
        'SELECT id, username, email, voter_code, google_id, avatar, first_name, last_name, role, status, created_at, last_login
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

    if (function_exists('sv_security_version') && !hash_equals(sv_security_version((int)$row['id']), (string)($_SESSION['security_version'] ?? ''))) {
        sv_end_user_session('Your password changed. Sign in again.', 'session_password_reset');
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

/** Generate a unique personal Voter Code e.g. VC-2026-8F3K2Q */
function sv_generate_voter_code(): string
{
    return 'VC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/* =====================================================================
 | Session lifecycle
 * =================================================================== */

/**
 * A combined browser/IP fingerprint.
 *
 * Kept coarse on purpose: we want to stop a stolen session cookie being
 * used from a completely different machine, but we do not want a roving
 * laptop to drop its session every time Wi-Fi switches.
 */
function sv_fingerprint(): string
{
    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    $ip    = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

    // For IPv4 we use the /24 subnet; for IPv6 the /48. That tolerates
    // carrier-grade NAT churn without making the check useless.
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $subnet = implode('.', array_slice($parts, 0, 3)) . '.0';
    } else {
        $parts = explode(':', $ip);
        $subnet = implode(':', array_slice($parts, 0, 3)) . '::';
    }

    return hash('sha256', $agent . '|' . $subnet);
}

/** Check that the current session is still fresh and bound to this browser. */
function sv_enforce_session_integrity(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $now = time();

    // 1. Idle timeout (30 minutes of no requests).
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && ($now - $lastActivity) > SESSION_IDLE_TIMEOUT) {
        sv_end_user_session('You were signed out after 30 minutes of inactivity.', 'session_idle_timeout');
        return;
    }

    // 2. Absolute timeout (2 hours total session lifetime).
    $createdAt = (int) ($_SESSION['created_at'] ?? 0);
    if ($createdAt > 0 && ($now - $createdAt) > SESSION_ABSOLUTE_TIMEOUT) {
        sv_end_user_session('Your session reached its maximum 2-hour duration. Please sign in again.', 'session_absolute_timeout');
        return;
    }

    // 3. Fingerprint binding.
    $expected = (string) ($_SESSION['fingerprint'] ?? '');
    if ($expected !== '' && !hash_equals($expected, sv_fingerprint())) {
        audit_log('session_hijack_attempt', 'Session rejected: browser fingerprint mismatch.');
        sv_end_user_session('Your session could not be verified. Please sign in again.', 'session_fingerprint_mismatch');
        return;
    }

    // Still valid: bump the idle timer.
    $_SESSION['last_activity'] = $now;
}

/** Alias for bootstrap session guard. */
function sv_enforce_session_security(): void
{
    sv_enforce_session_integrity();
}

/** Tear down the session in a single place. */
function sv_end_user_session(string $flashMessage = '', string $auditReason = ''): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId > 0 && $auditReason !== '') {
        audit_log($auditReason, 'Session terminated (' . $auditReason . ').', $userId);
    }

    unset(
        $_SESSION['user_id'],
        $_SESSION['user_role'],
        $_SESSION['security_version'],
        $_SESSION['fingerprint'],
        $_SESSION['created_at'],
        $_SESSION['last_activity']
    );

    session_regenerate_id(true);

    unset($_SESSION['_traffic_visitor_key']);

    if ($flashMessage !== '') {
        flash_warning($flashMessage);
    }
}

/* =====================================================================
 | Sign-in and sign-out
 * =================================================================== */

/** How many failed sign-in attempts happened recently for this identifier? */
function sv_failed_attempt_count(string $identifier): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM audit_logs
          WHERE action = 'login_failure'
            AND description LIKE :desc
            AND created_at > (NOW() - INTERVAL 15 MINUTE)"
    );
    $stmt->execute([':desc' => '%"' . $identifier . '"%']);

    return (int) $stmt->fetchColumn();
}

/**
 * Verify credentials (supports Username, Email, and Voter Code).
 *
 * @return array{ok:bool, user?:array, error?:string}
 */
function attempt_login(string $identifier, string $password): array
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return ['ok' => false, 'error' => 'Enter your username, email, or voter code.'];
    }

    if (sv_failed_attempt_count($identifier) >= 5) {
        audit_log('login_throttled', 'Blocked sign-in for "' . $identifier . '": too many recent failures.');

        return [
            'ok'    => false,
            'error' => 'Too many failed attempts for this account. Please wait about 15 minutes and try again.',
        ];
    }

    // Match by username, email, OR voter_code
    $stmt = db()->prepare(
        'SELECT id, username, email, voter_code, google_id, avatar, password_hash, first_name, last_name, role, status
         FROM users
         WHERE username = :id1 OR email = :id2 OR voter_code = :id3 LIMIT 1'
    );
    $stmt->execute([':id1' => $identifier, ':id2' => $identifier, ':id3' => $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        audit_log('login_failure', 'Failed sign-in for "' . $identifier . '": no such account.');
        usleep(150000);

        return ['ok' => false, 'error' => 'Incorrect username/email/voter code or password.'];
    }

    if ($user['status'] !== 'active') {
        audit_log('login_blocked', 'Blocked sign-in for "' . $identifier . '": account status is ' . $user['status'] . '.', (int) $user['id']);

        return [
            'ok'    => false,
            'error' => $user['status'] === 'blocked'
                ? 'This account has been blocked. Please contact the election administrator.'
                : 'This account is inactive. Please contact the election administrator.',
        ];
    }

    // If account has a password, verify password
    if (!empty($user['password_hash'])) {
        if ($password === '' || !password_verify($password, (string) $user['password_hash'])) {
            audit_log('login_failure', 'Failed sign-in for "' . $identifier . '": wrong password.', (int) $user['id']);
            usleep(150000);

            return ['ok' => false, 'error' => 'Incorrect password entered.'];
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update  = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([$newHash, (int) $user['id']]);
            audit_log('password_rehash', 'Password hash upgraded to current algorithm cost.', (int) $user['id']);
        }
    } else {
        // OAuth-only account or passcode-only account
        if (strtoupper($identifier) !== strtoupper((string) $user['voter_code'])) {
            return ['ok' => false, 'error' => 'This account uses Google Sign-In or a password. Please sign in with Google.'];
        }
    }

    return ['ok' => true, 'user' => $user];
}

/**
 * Handle Google OAuth Sign-in or Auto-Registration.
 *
 * @param array{google_id:string, email:string, first_name?:string, last_name?:string, avatar?:string} $profile
 * @return array{ok:bool, user:array, is_new?:bool, error?:string}
 */
function sv_login_or_register_google_user(array $profile): array
{
    $pdo = db();
    $googleId  = trim((string) ($profile['google_id'] ?? ''));
    $email     = strtolower(trim((string) ($profile['email'] ?? '')));
    $firstName = trim((string) ($profile['first_name'] ?? 'Google'));
    $lastName  = trim((string) ($profile['last_name'] ?? 'User'));
    $avatar    = trim((string) ($profile['avatar'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email address received from Google OAuth.', 'user' => []];
    }

    // 1. Check if user already exists by google_id or email
    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = :gid OR email = :email LIMIT 1');
    $stmt->execute([':gid' => $googleId, ':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['status'] !== 'active') {
            return [
                'ok'    => false,
                'error' => 'Your voter account is ' . $user['status'] . '. Please contact the election administrator.',
                'user'  => $user,
            ];
        }

        // Update google_id and avatar if missing
        $updateStmt = $pdo->prepare(
            'UPDATE users SET google_id = :gid, avatar = COALESCE(:avatar, avatar), last_login = NOW() WHERE id = :id'
        );
        $updateStmt->execute([
            ':gid'    => $googleId,
            ':avatar' => $avatar !== '' ? $avatar : null,
            ':id'     => (int) $user['id'],
        ]);

        $user['google_id'] = $googleId;
        if ($avatar !== '') {
            $user['avatar'] = $avatar;
        }

        login_user($user);
        audit_log('google_login_success', 'Signed in via Google OAuth as "' . $user['email'] . '".', (int) $user['id']);

        return ['ok' => true, 'user' => $user, 'is_new' => false];
    }

    // 2. Auto-Register New Voter Account via Google
    $baseUsername = strtolower(explode('@', $email)[0]);
    $baseUsername = preg_replace('/[^a-z0-9._-]/', '', $baseUsername);
    if (strlen($baseUsername) < 3) {
        $baseUsername = 'voter' . rand(100, 999);
    }

    $username = $baseUsername;
    $voterCode = sv_generate_voter_code();

    // Ensure username uniqueness
    for ($i = 1; $i <= 50; $i++) {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $check->execute([$username]);
        if (!$check->fetch()) {
            break;
        }
        $username = $baseUsername . $i;
    }

    // Ensure voter_code uniqueness
    for ($i = 1; $i <= 20; $i++) {
        $check = $pdo->prepare('SELECT id FROM users WHERE voter_code = ? LIMIT 1');
        $check->execute([$voterCode]);
        if (!$check->fetch()) {
            break;
        }
        $voterCode = sv_generate_voter_code();
    }

    try {
        $insert = $pdo->prepare(
            "INSERT INTO users (username, email, voter_code, google_id, avatar, password_hash, first_name, last_name, role, status, created_at, last_login)
             VALUES (:username, :email, :voter_code, :google_id, :avatar, NULL, :first_name, :last_name, 'voter', 'active', NOW(), NOW())"
        );
        $insert->execute([
            ':username'   => $username,
            ':email'      => $email,
            ':voter_code' => $voterCode,
            ':google_id'  => $googleId,
            ':avatar'     => $avatar !== '' ? $avatar : null,
            ':first_name' => $firstName !== '' ? $firstName : 'Voter',
            ':last_name'  => $lastName !== '' ? $lastName : 'Student',
        ]);

        $newUserId = (int) $pdo->lastInsertId();

        $newUserStmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $newUserStmt->execute([$newUserId]);
        $newUser = $newUserStmt->fetch();

        login_user($newUser);
        audit_log('google_register_success', 'Created voter account via Google OAuth for "' . $email . '" (Voter Code: ' . $voterCode . ').', $newUserId);

        return ['ok' => true, 'user' => $newUser, 'is_new' => true];
    } catch (PDOException $e) {
        error_log('Google registration failed: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Could not create your account with Google. Please try again or contact the election administrator.', 'user' => []];
    }
}

/** Establish the authenticated session for a verified user. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['_traffic_visitor_key']);

    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['user_role']     = (string) $user['role'];
    $_SESSION['security_version'] = sv_security_version((int)$user['id']);
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
        $_SESSION['security_version'],
        $_SESSION['fingerprint'],
        $_SESSION['created_at'],
        $_SESSION['last_activity']
    );

    session_regenerate_id(true);
    unset($_SESSION['_traffic_visitor_key']);
    csrf_rotate();

    flash_success('You have been signed out. Your ballot, if submitted, is safely recorded.');
}

/* =====================================================================
 | Authorization guards
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

/** Require one specific role. */
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
            . $user['role'] . ', so the page was not opened.',
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
    $stmt = db()->prepare('SELECT 1 FROM ballots WHERE election_id = ? AND voter_id = ? AND status = \'submitted\' LIMIT 1');
    $stmt->execute([$electionId, $userId]);

    return (bool) $stmt->fetchColumn();
}

/** The voter's submitted ballot details, if any. */
function voter_ballot(int $electionId, int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT id, election_id, voter_id, ballot_reference, submitted_at, status
           FROM ballots
          WHERE election_id = ? AND voter_id = ? LIMIT 1'
    );
    $stmt->execute([$electionId, $userId]);

    return $stmt->fetch() ?: null;
}
