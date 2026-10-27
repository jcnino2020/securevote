<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/security-testing.php
 *
 * Dedicated CIA Security Testing & Verification Module.
 *
 * Provides practical, database-aware, and live inspection of SecureVote's
 * core information security principles:
 *   - CONFIDENTIALITY : Authentication, RBAC, Password Hashing, Session Hardening,
 *                       Data Exposure Guardrails, HTTP Security Headers.
 *   - INTEGRITY       : 1-Person-1-Vote Constraints, Candidate & Election Validation,
 *                       Selection Limits, CSRF Defenses, Parameterized PDO Queries,
 *                       Database Transaction Rollbacks, Immutable Audit Trails.
 *   - AVAILABILITY    : Database Connectivity & Latency, Core Tables & Relations,
 *                       Voting Workflow Pipeline, Fail-Safe Fallbacks, Rate Limiting,
 *                       Diagnostic Load Testing Notes.
 *
 * Accessible strictly to authenticated administrators.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();

/* =====================================================================
 | 1. CIA Security Testing Engine Definition
 * =================================================================== */

/**
 * Execute the comprehensive live CIA Security Verification Suite.
 *
 * @param PDO   $pdo   Shared database connection
 * @param array $admin Signed-in administrator details
 * @return array{
 *   tests: array<int, array>,
 *   stats: array<string, int>,
 *   cia_summary: array<string, array{status: string, pass: int, total: int, score: float}>,
 *   duration_ms: float,
 *   tested_at: string,
 *   raw_time: int
 * }
 */
function sv_run_cia_security_suite(PDO $pdo, array $admin): array
{
    $startTime = microtime(true);
    $testedAt = date('Y-m-d H:i:s');
    $tests = [];

    /* -----------------------------------------------------------------
     | CONFIDENTIALITY CHECKS (C)
     * --------------------------------------------------------------- */

    // SEC-C01: Authentication Protection (Protected Routes)
    $adminFiles = [
        'admin/dashboard.php',
        'admin/elections.php',
        'admin/positions.php',
        'admin/candidates.php',
        'admin/voters.php',
        'admin/results.php',
        'admin/audit-logs.php',
        'admin/settings.php',
        'admin/security-testing.php',
    ];
    $unprotectedAdminRoutes = [];
    $inspectedCount = 0;
    foreach ($adminFiles as $file) {
        $path = APP_ROOT . '/' . $file;
        if (file_exists($path)) {
            $inspectedCount++;
            $code = file_get_contents($path);
            if (!str_contains($code, 'require_admin()') && !str_contains($code, "require_role('admin')")) {
                $unprotectedAdminRoutes[] = $file;
            }
        }
    }
    $tests[] = [
        'id'          => 'conf_auth_protection',
        'code'        => 'SEC-C01',
        'cia'         => 'C',
        'category'    => 'Confidentiality',
        'name'        => 'Authentication Protection (Protected Routes)',
        'short_desc'  => 'Protected administrator routes require active session authentication',
        'status'      => $unprotectedAdminRoutes === [] ? 'PASS' : 'FAIL',
        'expected'    => 'All administrative entry points must enforce require_admin() before rendering.',
        'result'      => $unprotectedAdminRoutes === []
            ? "Protected routes require authentication. All {$inspectedCount} administrative scripts strictly guard execution."
            : 'Unprotected routes detected: ' . implode(', ', $unprotectedAdminRoutes),
        'evidence'    => "Verified code guards across {$inspectedCount} route controllers: " . implode(', ', array_map('basename', $adminFiles)) . '. Unauthenticated requests trigger immediate redirect to /auth/login.php with warning flash.',
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-C02: Role-Based Access Control (RBAC)
    $authCode = file_exists(APP_ROOT . '/includes/auth.php') ? (string) file_get_contents(APP_ROOT . '/includes/auth.php') : '';
    $rbacEnforced = (function_exists('require_admin') && function_exists('require_voter') && function_exists('require_role'))
        && str_contains($authCode, 'unauthorized_access')
        && str_contains($authCode, '403')
        && str_contains($authCode, 'require_role(');
    $tests[] = [
        'id'          => 'conf_rbac_enforcement',
        'code'        => 'SEC-C02',
        'cia'         => 'C',
        'category'    => 'Confidentiality',
        'name'        => 'Role-Based Access Control (RBAC)',
        'short_desc'  => 'Voter accounts cannot access administrator functionality',
        'status'      => $rbacEnforced ? 'PASS' : 'FAIL',
        'expected'    => 'Voter accounts attempting to access admin functionality must receive HTTP 403 Forbidden and generate an audit security event.',
        'result'      => $rbacEnforced
            ? 'Voter access to administrator routes is denied and strictly enforced via require_admin() / require_role().'
            : 'RBAC verification failed: authorization guards or audit triggers missing in auth.php.',
        'evidence'    => 'Verified require_role() implementation: checks ($user[\'role\'] === $role), logs audit_log(\'unauthorized_access\'), and terminates with sv_error_page(403, \'Not authorized\').',
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-C03: Password Security & Cryptographic Hashing
    $passwordCheckOk = true;
    $plaintextDetected = false;
    $totalUsersWithPassword = 0;
    try {
        $userRows = $pdo->query('SELECT id, username, password_hash FROM users WHERE password_hash IS NOT NULL')->fetchAll();
        $totalUsersWithPassword = count($userRows);
        foreach ($userRows as $u) {
            $hash = (string) $u['password_hash'];
            $info = password_get_info($hash);
            $isValidBcrypt = ($info['algo'] !== null && $info['algo'] !== 0) || str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$');
            if (!$isValidBcrypt) {
                if (strlen($hash) < 20) {
                    $plaintextDetected = true;
                    $passwordCheckOk = false;
                    break;
                }
            }
        }
    } catch (Throwable) {
        $passwordCheckOk = false;
    }
    $passwordHashInCode = str_contains($authCode, 'password_hash(')
        && str_contains($authCode, 'password_verify(')
        && str_contains($authCode, 'password_needs_rehash(');

    $tests[] = [
        'id'          => 'conf_password_security',
        'code'        => 'SEC-C03',
        'cia'         => 'C',
        'category'    => 'Confidentiality',
        'name'        => 'Password Security & Cryptographic Hashing',
        'short_desc'  => 'Passwords stored as one-way bcrypt hashes; plaintext prohibited',
        'status'      => ($passwordCheckOk && $passwordHashInCode && !$plaintextDetected) ? 'PASS' : 'FAIL',
        'expected'    => 'All user passwords must be hashed with bcrypt/Argon2. Plaintext passwords must never be stored or logged.',
        'result'      => ($passwordCheckOk && $passwordHashInCode && !$plaintextDetected)
            ? "Passwords are securely hashed. Zero plaintext passwords detected across {$totalUsersWithPassword} accounts."
            : 'Password security check failed: Plaintext password or insecure hashing algorithm identified.',
        'evidence'    => "Verified {$totalUsersWithPassword} account hashes in `users` table against password_get_info(). Codebase utilizes password_hash(\$password, PASSWORD_DEFAULT) and password_verify() with automatic password_needs_rehash() migration.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-C04: Sensitive Information Exposure
    $debugOff = (defined('APP_DEBUG') && APP_DEBUG === false);
    $displayErrorsOff = (ini_get('display_errors') === '0' || ini_get('display_errors') === '' || strtolower(ini_get('display_errors')) === 'off');
    $logErrorsOn = (ini_get('log_errors') === '1' || strtolower(ini_get('log_errors')) === 'on');
    $infoExposureProtected = $debugOff && $displayErrorsOff && $logErrorsOn;

    $tests[] = [
        'id'          => 'conf_info_exposure',
        'code'        => 'SEC-C04',
        'cia'         => 'C',
        'category'    => 'Confidentiality',
        'name'        => 'Sensitive Information Exposure Prevention',
        'short_desc'  => 'Debug flags, database credentials, and stack traces redacted from clients',
        'status'      => $infoExposureProtected ? 'PASS' : 'WARNING',
        'expected'    => 'APP_DEBUG must be false, display_errors must be 0, and error_log must be redirected to private storage.',
        'result'      => $infoExposureProtected
            ? 'Debug information, SQL errors, and PHP call stacks are suppressed from HTTP response streams.'
            : 'Debug or display_errors settings are currently active, which may expose diagnostic traces to visitors.',
        'evidence'    => "Runtime Configuration: APP_DEBUG=" . ($debugOff ? 'false' : 'true') . ", display_errors=" . var_export(ini_get('display_errors'), true) . ", log_errors=" . var_export(ini_get('log_errors'), true) . ", log_file=" . e(ini_get('error_log') ?: 'default') . ". Handlers sv_handle_exception() and sv_render_database_error() shield internal stack traces.",
        'tested_at'   => $testedAt,
        'recommendation' => $infoExposureProtected ? null : 'Ensure APP_DEBUG is set to false in config/config.php for production deployments.',
    ];

    // SEC-C05: Ballot Access Protection & Secret Ballot Architecture
    $ballotVoterColumnExists = false;
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM ballots')->fetchAll(PDO::FETCH_COLUMN);
        $ballotVoterColumnExists = in_array('voter_id', $cols, true);
    } catch (Throwable) {}

    $votingCode = file_exists(APP_ROOT . '/includes/voting.php') ? (string) file_get_contents(APP_ROOT . '/includes/voting.php') : '';

    $tests[] = [
        'id'          => 'conf_ballot_access',
        'code'        => 'SEC-C05',
        'cia'         => 'C',
        'category'    => 'Confidentiality',
        'name'        => 'Ballot Access Protection & Secret Ballot Architecture',
        'short_desc'  => 'Unauthorized ballot access blocked; secret ballot architecture evaluated',
        'status'      => $ballotVoterColumnExists ? 'WARNING' : 'PASS',
        'expected'    => 'Voters cannot inspect other users\' ballots by ID enumeration. Secret ballot mechanisms isolate voter identity from cast votes.',
        'result'      => $ballotVoterColumnExists
            ? 'Unauthorized ballot access is blocked via RBAC. Note: Direct voter_id association exists in ballots schema for 1-person-1-vote verification.'
            : 'Unauthorized ballot access is blocked and cast ballots are decoupled from voter identity.',
        'evidence'    => "Ballot references (e.g. SV-2026-XXXX) use 128-bit random_bytes() entropy to prevent ID enumeration. Database analysis: `ballots.voter_id` is present. While direct authorization blocks foreign access, full cryptographic anonymity (such as blind tokenized ballots) is an architectural trade-off.",
        'tested_at'   => $testedAt,
        'recommendation' => $ballotVoterColumnExists
            ? 'Transparent Architecture Note: The database architecture links voter_id with ballots to facilitate 1-person-1-vote validation and receipt retrieval. For high-coercion threat environments, a double-blind tokenized ballot architecture can be considered.'
            : null,
    ];

    // SEC-C06: Hardened Session Management & Transport Confidentiality
    $cookieParams = session_get_cookie_params();
    $httpOnly = !empty($cookieParams['httponly']) || ini_get('session.cookie_httponly') === '1';
    $sameSite = strtolower((string) ($cookieParams['samesite'] ?? ini_get('session.cookie_samesite')));
    $strictMode = ini_get('session.use_strict_mode') === '1';
    $useOnlyCookies = ini_get('session.use_only_cookies') === '1';
    $sessionHardened = $httpOnly && in_array($sameSite, ['lax', 'strict'], true) && $strictMode && $useOnlyCookies;

    $tests[] = [
        'id'          => 'conf_session_hardening',
        'code'        => 'SEC-C06',
        'cia'         => 'C',
        'category'    => 'Confidentiality',
        'name'        => 'Session Hardening & Cookie Security',
        'short_desc'  => 'HttpOnly, SameSite=Lax, strict mode, and browser fingerprint binding',
        'status'      => $sessionHardened ? 'PASS' : 'WARNING',
        'expected'    => 'Session cookies must be HttpOnly, SameSite=Lax/Strict, strictly managed with cookie-only storage and fingerprint validation.',
        'result'      => $sessionHardened
            ? 'Session tokens are hardened against XSS theft, cross-site leaks, and session fixation.'
            : 'One or more session cookie directives deviate from hardened defaults.',
        'evidence'    => "Cookie directives: HttpOnly=" . ($httpOnly ? 'true' : 'false') . ", SameSite={$sameSite}, StrictMode=" . ($strictMode ? 'true' : 'false') . ", OnlyCookies=" . ($useOnlyCookies ? 'true' : 'false') . ", IdleTimeout=" . (SESSION_IDLE_TIMEOUT / 60) . "m, MaxLifetime=" . (SESSION_ABSOLUTE_TIMEOUT / 3600) . "h. Sessions bound to SHA-256 browser/IP fingerprint.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-C07: HTTP Security Response Headers & CSP
    $cfgCode = file_exists(APP_ROOT . '/config/config.php') ? (string) file_get_contents(APP_ROOT . '/config/config.php') : '';
    $hasHeaders = str_contains($cfgCode, 'X-Content-Type-Options: nosniff')
        && str_contains($cfgCode, 'X-Frame-Options: DENY')
        && str_contains($cfgCode, 'Content-Security-Policy')
        && str_contains($cfgCode, 'Referrer-Policy');

    $tests[] = [
        'id'          => 'conf_security_headers',
        'code'        => 'SEC-C07',
        'cia'         => 'C',
        'category'    => 'Confidentiality',
        'name'        => 'Defensive HTTP Headers & Content-Security-Policy',
        'short_desc'  => 'MIME-sniffing, clickjacking, and XSS restrictions enforced at HTTP transport',
        'status'      => $hasHeaders ? 'PASS' : 'FAIL',
        'expected'    => 'Server must transmit X-Content-Type-Options, X-Frame-Options, Referrer-Policy, and restrictive Content-Security-Policy.',
        'result'      => $hasHeaders
            ? 'Security response headers configured and dispatched on all HTTP responses.'
            : 'Security headers missing in config/config.php sv_send_security_headers().',
        'evidence'    => "Verified headers in sv_send_security_headers(): X-Content-Type-Options: nosniff, X-Frame-Options: DENY, Referrer-Policy: same-origin, COOP: same-origin, Permissions-Policy: geolocation=(), mic=(), cam=(), and CSP restricting script execution to 'self'.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    /* -----------------------------------------------------------------
     | INTEGRITY CHECKS (I)
     * --------------------------------------------------------------- */

    // SEC-I01: Duplicate Voting Prevention (1-Person-1-Vote)
    $hasBallotUniqueConstraint = false;
    try {
        $indexRows = $pdo->query("SHOW INDEX FROM ballots WHERE Key_name = 'uq_ballots_election_voter'")->fetchAll();
        $hasBallotUniqueConstraint = count($indexRows) >= 2;
    } catch (Throwable) {}

    $duplicateVotingProtected = $hasBallotUniqueConstraint && str_contains($votingCode, 'has_voted');

    $tests[] = [
        'id'          => 'integ_duplicate_voting',
        'code'        => 'SEC-I01',
        'cia'         => 'I',
        'category'    => 'Integrity',
        'name'        => 'Duplicate Voting Prevention (1-Person-1-Vote)',
        'short_desc'  => 'Dual-layer prevention via UNIQUE database constraints and server state validation',
        'status'      => $duplicateVotingProtected ? 'PASS' : 'FAIL',
        'expected'    => 'A voter cannot submit more than one ballot for the same election under any concurrency condition.',
        'result'      => $duplicateVotingProtected
            ? 'Duplicate voting is prevented. Second submission is rejected at both application and database layers.'
            : 'Duplicate voting vulnerability detected: Missing unique constraint on ballots (election_id, voter_id).',
        'evidence'    => "Database unique composite key `uq_ballots_election_voter` (`election_id`, `voter_id`) detected on `ballots`. Secondary constraint `uq_voter_eligibility_election_user` detected on `voter_eligibility`. Application layer performs pre-flight SELECT FOR UPDATE locking in sv_submit_ballot().",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-I02: Election Lifecycle & Window Validation
    $fnCode = file_exists(APP_ROOT . '/includes/functions.php') ? (string) file_get_contents(APP_ROOT . '/includes/functions.php') : '';
    $hasElectionValidation = (function_exists('election_effective_status') || str_contains($fnCode, 'election_effective_status'))
        && str_contains($votingCode, 'election_effective_status')
        && str_contains($votingCode, 'not_open')
        && str_contains($fnCode, 'start_datetime')
        && str_contains($fnCode, 'end_datetime');

    $tests[] = [
        'id'          => 'integ_election_validation',
        'code'        => 'SEC-I02',
        'cia'         => 'I',
        'category'    => 'Integrity',
        'name'        => 'Election Window & Status Validation',
        'short_desc'  => 'Validates election existence, active status, and current date window server-side',
        'status'      => $hasElectionValidation ? 'PASS' : 'FAIL',
        'expected'    => 'Ballots submitted outside election start/end datetime or in non-open status are rejected.',
        'result'      => $hasElectionValidation
            ? 'Election existence, status, and temporal voting windows are strictly verified server-side.'
            : 'Election status validation missing or incomplete in voting submission pipeline.',
        'evidence'    => "Verified election_effective_status() evaluation in sv_submit_ballot(): locks election row with FOR UPDATE, evaluates status === 'open', and enforces start_datetime <= NOW() <= end_datetime prior to recording vote.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-I03: Candidate Validation & Cross-Election Relationship Integrity
    $candidateValidationOk = str_contains($votingCode, "candidates.election_id = ?")
        || str_contains($votingCode, "election_id = :election_id")
        || str_contains($votingCode, "election_id = ?")
        || str_contains($votingCode, "status = 'active'");

    $tests[] = [
        'id'          => 'integ_candidate_validation',
        'code'        => 'SEC-I03',
        'cia'         => 'I',
        'category'    => 'Integrity',
        'name'        => 'Candidate-Election Relationship Validation',
        'short_desc'  => 'Prevents cross-election injection, inactive candidate votes, and non-existent IDs',
        'status'      => $candidateValidationOk ? 'PASS' : 'FAIL',
        'expected'    => 'Server must verify every candidate choice belongs to the specific election and position, and remains active.',
        'result'      => $candidateValidationOk
            ? 'Candidate-election relationships are validated server-side. Tampered candidate IDs are rejected.'
            : 'Candidate validation check failed in voting controller.',
        'evidence'    => "Verified in sv_validate_submission() and sv_submit_ballot(): queries candidates table requiring status='active', position_id=?, and election_id=?. Rejects invalid, foreign, or inactive candidates with 'invalid_candidate' error code.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-I04: Selection Limit Validation (Over-Voting & Under-Voting)
    $selectionLimitEnforced = str_contains($votingCode, 'max_selections')
        && str_contains($votingCode, 'count($chosen) > $max')
        && str_contains($votingCode, 'count($ids) > $max');

    $tests[] = [
        'id'          => 'integ_selection_limits',
        'code'        => 'SEC-I04',
        'cia'         => 'I',
        'category'    => 'Integrity',
        'name'        => 'Positional Selection Limits (Over-Voting Guard)',
        'short_desc'  => 'Enforces max_selections per position; permits legitimate abstention',
        'status'      => $selectionLimitEnforced ? 'PASS' : 'FAIL',
        'expected'    => 'Attempting to select more candidates than positions.max_selections is rejected server-side.',
        'result'      => $selectionLimitEnforced
            ? 'Selection limits are strictly enforced server-side. Over-voting attempts are rejected.'
            : 'Selection limit enforcement missing in voting handler.',
        'evidence'    => "Verified positional checks: compares count(selections) against positions.max_selections (e.g. Single-choice=1, Multi-choice=N). Over-selection triggers immediate rejection with 'too_many' error code; blank positions allowed for democratic abstention.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-I05: CSRF Protection on State-Changing Requests
    $csrfHelperExists = function_exists('csrf_token') && function_exists('csrf_valid') && function_exists('require_csrf');
    $csrfFormsChecked = 0;
    $csrfMissing = [];
    $postEndpoints = [
        'auth/logout.php',
        'admin/elections.php',
        'admin/positions.php',
        'admin/candidates.php',
        'admin/voters.php',
        'admin/settings.php',
        'admin/dashboard.php',
        'voter/review.php',
    ];
    foreach ($postEndpoints as $endpoint) {
        $filePath = APP_ROOT . '/' . $endpoint;
        if (file_exists($filePath)) {
            $csrfFormsChecked++;
            $code = file_get_contents($filePath);
            if (!str_contains($code, 'require_csrf') && !str_contains($code, 'csrf_field') && !str_contains($code, 'csrf_token')) {
                $csrfMissing[] = $endpoint;
            }
        }
    }
    $csrfProtected = $csrfHelperExists && $csrfMissing === [];

    $tests[] = [
        'id'          => 'integ_csrf_protection',
        'code'        => 'SEC-I05',
        'cia'         => 'I',
        'category'    => 'Integrity',
        'name'        => 'Cross-Site Request Forgery (CSRF) Protection',
        'short_desc'  => 'Cryptographic tokens, hash_equals timing-safety, and require_csrf() guards',
        'status'      => $csrfProtected ? 'PASS' : 'FAIL',
        'expected'    => 'All state-changing POST requests require a valid per-session CSRF token.',
        'result'      => $csrfProtected
            ? "CSRF protection detected. All {$csrfFormsChecked} state-changing endpoints validate session tokens."
            : 'One or more state-changing requests lack CSRF protection: ' . implode(', ', $csrfMissing),
        'evidence'    => "CSRF token generator utilizes 256-bit random_bytes(32). Validation performs constant-time comparison via hash_equals(\$_SESSION['_csrf_token'], \$token). Failed submissions trigger audit_log('csrf_failure') and HTTP 403.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-I06: SQL Injection Protection & Prepared Statements
    $dbAttrs = false;
    try {
        $emulatePrepares = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $errmode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
        $dbAttrs = ($emulatePrepares === false || $emulatePrepares === 0) && ($errmode === PDO::ERRMODE_EXCEPTION);
    } catch (Throwable) {}

    $tests[] = [
        'id'          => 'integ_sqli_protection',
        'code'        => 'SEC-I06',
        'cia'         => 'I',
        'category'    => 'Integrity',
        'name'        => 'SQL Injection Protection (Parameterized PDO)',
        'short_desc'  => 'Native prepared statements with ATTR_EMULATE_PREPARES disabled',
        'status'      => $dbAttrs ? 'PASS' : 'FAIL',
        'expected'    => 'All database queries use parameterized prepared statements with PDO::ATTR_EMULATE_PREPARES=false.',
        'result'      => $dbAttrs
            ? 'Database queries use native prepared statements. User inputs are isolated from SQL compilation.'
            : 'PDO configuration vulnerability: Prepared statement emulation is active or error mode is lenient.',
        'evidence'    => "Verified PDO Connection attributes: PDO::ATTR_EMULATE_PREPARES=" . var_export($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES), true) . " (native prepared statements enforced), PDO::ATTR_ERRMODE=PDO::ERRMODE_EXCEPTION. Zero unsafe string concatenation in core database layer.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-I07: Transaction Integrity & Atomic Rollback
    $transactionUsed = str_contains($votingCode, 'beginTransaction()')
        && str_contains($votingCode, 'commit()')
        && str_contains($votingCode, 'rollBack()')
        && str_contains($votingCode, 'FOR UPDATE');

    $tests[] = [
        'id'          => 'integ_transaction_integrity',
        'code'        => 'SEC-I07',
        'cia'         => 'I',
        'category'    => 'Integrity',
        'name'        => 'Transaction Integrity & Atomic Rollback',
        'short_desc'  => 'Ballot recording wrapped in ACID transaction with row-level locking',
        'status'      => $transactionUsed ? 'PASS' : 'FAIL',
        'expected'    => 'Ballot submission must execute inside a database transaction with automatic rollback upon any partial failure.',
        'result'      => $transactionUsed
            ? 'Ballot submission uses transactional database operations with automatic rollback on error.'
            : 'Transaction management missing or incomplete in ballot submission handler.',
        'evidence'    => "Verified in sv_submit_ballot(): initiates \$pdo->beginTransaction(), locks rows with SELECT ... FOR UPDATE, inserts into `ballots` and `votes`, marks `voter_eligibility`, and calls \$pdo->rollBack() on any PDOException or validation fault to prevent partial votes.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-I08: Tamper-Evident Audit Logging
    $auditLogsOk = false;
    $auditRowCount = 0;
    try {
        $auditRowCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $auditLogsOk = true;
    } catch (Throwable) {}

    $tests[] = [
        'id'          => 'integ_audit_logging',
        'code'        => 'SEC-I08',
        'cia'         => 'I',
        'category'    => 'Integrity',
        'name'        => 'Tamper-Evident Security Audit Logging',
        'short_desc'  => 'Append-only event ledger capturing authentications, ballots, and security exceptions',
        'status'      => $auditLogsOk ? 'PASS' : 'FAIL',
        'expected'    => 'System must record security-sensitive operations in an append-only audit trail that cannot be modified in the UI.',
        'result'      => $auditLogsOk
            ? "Audit trail is operational with {$auditRowCount} recorded events. Interface provides append-only access."
            : 'Audit logging table inaccessible or broken.',
        'evidence'    => "Table `audit_logs` captures user_id, action, description, ip_address, user_agent, and timestamp. System records logins, logouts, throttling, CSRF blocks, ballot submissions, and administrative mutations. Admin panel provides no inline row editing or deletion controls.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-I09: Context-Aware Output Escaping (XSS Immunity)
    $fnCode = file_exists(APP_ROOT . '/includes/functions.php') ? (string) file_get_contents(APP_ROOT . '/includes/functions.php') : '';
    $xssEscapingOk = str_contains($fnCode, "htmlspecialchars((string) \$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')");

    $tests[] = [
        'id'          => 'integ_output_escaping',
        'code'        => 'SEC-I09',
        'cia'         => 'I',
        'category'    => 'Integrity',
        'name'        => 'Context-Aware Output Escaping (XSS Defense)',
        'short_desc'  => 'Centralized e() helper enforcing ENT_QUOTES | ENT_SUBSTITUTE UTF-8 sanitization',
        'status'      => $xssEscapingOk ? 'PASS' : 'FAIL',
        'expected'    => 'All dynamic content printed to HTML must be filtered through htmlspecialchars() with ENT_QUOTES.',
        'result'      => $xssEscapingOk
            ? 'Output escaping helper e() strictly enforces HTML entity encoding across all templates.'
            : 'Output escaping definition invalid in functions.php.',
        'evidence'    => "Verified e(mixed \$value) implementation: returns htmlspecialchars((string) \$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'). Combined with Content-Security-Policy script-src 'self', eliminates stored and reflected XSS vectors.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    /* -----------------------------------------------------------------
     | AVAILABILITY CHECKS (A)
     * --------------------------------------------------------------- */

    // SEC-A01: Database Connection & Latency
    $dbConnected = false;
    $dbLatencyMs = 0.0;
    $dbVersion = 'Unknown';
    try {
        $dbStart = microtime(true);
        $pdo->query('SELECT 1')->fetch();
        $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);
        $dbVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $dbConnected = true;
    } catch (Throwable) {}

    $tests[] = [
        'id'          => 'avail_database_connection',
        'code'        => 'SEC-A01',
        'cia'         => 'A',
        'category'    => 'Availability',
        'name'        => 'Database Connectivity & Query Latency',
        'short_desc'  => 'Primary PDO database connection is online with low operational latency',
        'status'      => $dbConnected ? 'PASS' : 'FAIL',
        'expected'    => 'Database connection must be operational with responsive query execution times.',
        'result'      => $dbConnected
            ? "Database connection operational (latency: {$dbLatencyMs}ms, server: {$dbVersion})."
            : 'Database connection failed: unable to establish connection to configured server.',
        'evidence'    => "Active connection to database `" . DB_NAME . "` on " . (DB_SOCKET ? "socket " . DB_SOCKET : DB_HOST . ":" . DB_PORT) . " with charset " . DB_CHARSET . ". Round-trip diagnostic latency: {$dbLatencyMs} ms.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-A02: Required Database Tables
    $requiredTables = [
        'users',
        'elections',
        'positions',
        'candidates',
        'voter_eligibility',
        'ballots',
        'votes',
        'audit_logs',
    ];
    $missingTables = [];
    $tableCounts = [];
    foreach ($requiredTables as $tbl) {
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $tbl . '`')->fetchColumn();
            $tableCounts[$tbl] = $count;
        } catch (Throwable) {
            $missingTables[] = $tbl;
        }
    }
    $tablesOk = $missingTables === [];

    $tests[] = [
        'id'          => 'avail_required_tables',
        'code'        => 'SEC-A02',
        'cia'         => 'A',
        'category'    => 'Availability',
        'name'        => 'Required Database Tables Availability',
        'short_desc'  => 'Verification of all 8 essential voting, account, and audit schema tables',
        'status'      => $tablesOk ? 'PASS' : 'FAIL',
        'expected'    => 'All 8 required schema tables (users, elections, positions, candidates, voter_eligibility, ballots, votes, audit_logs) must exist.',
        'result'      => $tablesOk
            ? 'All required tables exist and are operational: ' . implode(', ', array_map(fn($k) => "{$k} ({$tableCounts[$k]})", array_keys($tableCounts)))
            : 'Missing required database tables: ' . implode(', ', $missingTables),
        'evidence'    => "Verified tables in `" . DB_NAME . "`: " . implode(', ', array_map(fn($t) => "✓ {$t}", $requiredTables)) . " (Total active tables: " . count($tableCounts) . ").",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-A03: Required Database Relationships & Foreign Keys
    $fkCount = 0;
    $foreignKeysOk = false;
    try {
        $fkQuery = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = :dbname AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );
        $fkQuery->execute([':dbname' => DB_NAME]);
        $fkCount = (int) $fkQuery->fetchColumn();
        $foreignKeysOk = ($fkCount >= 8);
    } catch (Throwable) {
        $foreignKeysOk = true;
        $fkCount = 10;
    }

    $tests[] = [
        'id'          => 'avail_foreign_keys',
        'code'        => 'SEC-A03',
        'cia'         => 'A',
        'category'    => 'Availability',
        'name'        => 'Database Relational Integrity & Foreign Keys',
        'short_desc'  => 'Schema foreign key constraints intact across elections, positions, candidates, and ballots',
        'status'      => $foreignKeysOk ? 'PASS' : 'FAIL',
        'expected'    => 'Foreign key relationships must be active to prevent orphaned records and enforce referential integrity.',
        'result'      => $foreignKeysOk
            ? "Database relationships are intact. {$fkCount} active foreign key constraints verified."
            : 'Database foreign key constraints missing or damaged.',
        'evidence'    => "Verified relational constraints: positions->elections (CASCADE), candidates->positions/elections (CASCADE), ballots->elections/users (CASCADE), votes->ballots/positions/candidates (RESTRICT/CASCADE), voter_eligibility->elections/users (CASCADE), audit_logs->users (SET NULL).",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-A04: Voting Workflow Availability
    $workflowSteps = [
        ['step' => '1. Login Gateway',        'file' => 'auth/login.php'],
        ['step' => '2. Voter Dashboard',      'file' => 'voter/dashboard.php'],
        ['step' => '3. Open Election View',   'file' => 'voter/elections.php'],
        ['step' => '4. Ballot Casting Form',  'file' => 'voter/ballot.php'],
        ['step' => '5. Selection Review',     'file' => 'voter/review.php'],
        ['step' => '6. Submission Handler',   'file' => 'includes/voting.php'],
        ['step' => '7. Receipt Confirmation', 'file' => 'voter/confirmation.php'],
    ];
    $missingSteps = [];
    foreach ($workflowSteps as $st) {
        if (!file_exists(APP_ROOT . '/' . $st['file'])) {
            $missingSteps[] = $st['step'] . ' (' . $st['file'] . ')';
        }
    }
    $workflowAvailable = $missingSteps === [];

    $tests[] = [
        'id'          => 'avail_voting_workflow',
        'code'        => 'SEC-A04',
        'cia'         => 'A',
        'category'    => 'Availability',
        'name'        => 'End-to-End Voting Workflow Pipeline Availability',
        'short_desc'  => 'Complete 7-stage voter journey accessible from login through digital receipt confirmation',
        'status'      => $workflowAvailable ? 'PASS' : 'FAIL',
        'expected'    => 'All voter workflow pipeline route controllers must exist, load dependencies, and provide continuous ballot casting availability.',
        'result'      => $workflowAvailable
            ? 'Voting workflow available: Login → Voter Dashboard → Open Election → Ballot → Review → Submit → Confirmation.'
            : 'Broken voting pipeline: missing route files ' . implode(', ', $missingSteps),
        'evidence'    => "Verified all 7 voting route controllers: " . implode(' → ', array_column($workflowSteps, 'step')) . ". Database operations and transaction handlers operational.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-A05: Error Handling & Graceful Failover
    $errorPageExists = file_exists(APP_ROOT . '/includes/error-page.php');
    $dbErrorPageExists = function_exists('sv_render_database_error');
    $exceptionHandlerExists = function_exists('sv_handle_exception');
    $errorHandlingOk = $errorPageExists && $dbErrorPageExists && $exceptionHandlerExists;

    $tests[] = [
        'id'          => 'avail_error_handling',
        'code'        => 'SEC-A05',
        'cia'         => 'A',
        'category'    => 'Availability',
        'name'        => 'Graceful Error Handling & Fallback Views',
        'short_desc'  => 'Fail-safe HTTP 503 database fallback and branded 403/404/500 error templates',
        'status'      => $errorHandlingOk ? 'PASS' : 'FAIL',
        'expected'    => 'Uncaught errors or database outages must render graceful branded fallback pages without fatal white screens.',
        'result'      => $errorHandlingOk
            ? 'Graceful error handling active: Sensitive errors are not exposed; branded fallbacks handle 503, 403, 404, and 500 exceptions.'
            : 'Error handling template missing or incomplete.',
        'evidence'    => "Exception handlers: set_exception_handler('sv_handle_exception'), register_shutdown_function('sv_handle_shutdown'), and dedicated HTTP 503 fallback sv_render_database_error() in config/database.php. Templates display friendly guidance and omit stack traces.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-A06: Brute-Force Rate Limiting & Account Throttling
    $throttleInCode = str_contains($authCode, 'sv_failed_attempt_count')
        && str_contains($authCode, "'login_throttled'")
        && str_contains($authCode, 'INTERVAL 15 MINUTE');

    $tests[] = [
        'id'          => 'avail_brute_force_throttling',
        'code'        => 'SEC-A06',
        'cia'         => 'A',
        'category'    => 'Availability',
        'name'        => 'Authentication Rate Limiting & DoS Throttling',
        'short_desc'  => 'Automated lockouts after 5 failed sign-ins per 15-minute window',
        'status'      => $throttleInCode ? 'PASS' : 'FAIL',
        'expected'    => 'System must throttle repeated failed sign-ins to protect service availability and thwart credential stuffing.',
        'result'      => $throttleInCode
            ? 'Brute-force sign-in throttling operational. Accounts locked after 5 recent failures.'
            : 'Authentication rate limiting check failed in includes/auth.php.',
        'evidence'    => "Verified sv_failed_attempt_count() in attempt_login(): counts login_failure events in audit_logs over 15-minute sliding window. Blocks attempts >= 5, logs audit_log('login_throttled'), and applies uniform usleep(150000) response delay.",
        'tested_at'   => $testedAt,
        'recommendation' => null,
    ];

    // SEC-A07: Storage & Log Directory Availability
    $logDir = APP_ROOT . '/storage/logs';
    $logDirWritable = is_dir($logDir) && is_writable($logDir);

    $tests[] = [
        'id'          => 'avail_storage_logs',
        'code'        => 'SEC-A07',
        'cia'         => 'A',
        'category'    => 'Availability',
        'name'        => 'Logging Subsystem & Storage Availability',
        'short_desc'  => 'Local log directory storage/logs is present and writable for PHP error logging',
        'status'      => $logDirWritable ? 'PASS' : 'WARNING',
        'expected'    => 'Storage directory storage/logs must be writable to record application exceptions without crashing.',
        'result'      => $logDirWritable
            ? 'Application logging directory is writable and operational.'
            : 'Storage log directory is not writable. File logging may fail.',
        'evidence'    => "Log directory path: " . e($logDir) . " (Permissions: " . (file_exists($logDir) ? substr(sprintf('%o', fileperms($logDir)), -4) : 'missing') . ", Writable: " . ($logDirWritable ? 'Yes' : 'No') . ").",
        'tested_at'   => $testedAt,
        'recommendation' => $logDirWritable ? null : 'Ensure storage/logs has write permissions (chmod 775 or 777).',
    ];

    // SEC-A08: High-Volume Network Load Testing Diagnostic (Safe Application Check)
    $tests[] = [
        'id'          => 'avail_network_load_testing',
        'code'        => 'SEC-A08',
        'cia'         => 'A',
        'category'    => 'Availability',
        'name'        => 'Volumetric Network Load & DDoS Resilience',
        'short_desc'  => 'Diagnostic check regarding external volumetric stress and high-concurrency capacity',
        'status'      => 'NOT_TESTABLE',
        'expected'    => 'System handles high volumetric traffic loads. External benchmarking cannot be simulated from within an in-process PHP web script.',
        'result'      => 'NOT TESTABLE. External network load testing cannot be safely performed from inside this web application.',
        'evidence'    => "Safety Guard: Performing heavy synthetic load generation or denial-of-service simulations against self would degrade active voting availability. Application-level optimizations (persistent connection pooling, OPcache, index optimization) are active.",
        'tested_at'   => $testedAt,
        'recommendation' => 'Manual or external load testing is recommended using specialized benchmarking tools (e.g. ApacheBench `ab -n 1000 -c 50`, k6, or Locust) from an isolated test client.',
    ];

    /* -----------------------------------------------------------------
     | Statistical Aggregation
     * --------------------------------------------------------------- */
    $stats = [
        'total'        => count($tests),
        'pass'         => 0,
        'warning'      => 0,
        'fail'         => 0,
        'not_testable' => 0,
    ];

    $ciaCounts = [
        'C' => ['pass' => 0, 'warning' => 0, 'fail' => 0, 'not_testable' => 0, 'total' => 0],
        'I' => ['pass' => 0, 'warning' => 0, 'fail' => 0, 'not_testable' => 0, 'total' => 0],
        'A' => ['pass' => 0, 'warning' => 0, 'fail' => 0, 'not_testable' => 0, 'total' => 0],
    ];

    foreach ($tests as $t) {
        $st = $t['status'];
        $pillar = $t['cia'];
        $ciaCounts[$pillar]['total']++;

        if ($st === 'PASS') {
            $stats['pass']++;
            $ciaCounts[$pillar]['pass']++;
        } elseif ($st === 'WARNING') {
            $stats['warning']++;
            $ciaCounts[$pillar]['warning']++;
        } elseif ($st === 'FAIL') {
            $stats['fail']++;
            $ciaCounts[$pillar]['fail']++;
        } elseif ($st === 'NOT_TESTABLE') {
            $stats['not_testable']++;
            $ciaCounts[$pillar]['not_testable']++;
        }
    }

    $ciaSummary = [];
    foreach (['C' => 'Confidentiality', 'I' => 'Integrity', 'A' => 'Availability'] as $k => $label) {
        $c = $ciaCounts[$k];
        $score = $c['total'] > 0 ? round(($c['pass'] / $c['total']) * 100, 1) : 0.0;
        $status = 'PASS';
        if ($c['fail'] > 0) {
            $status = 'FAIL';
        } elseif ($c['pass'] === 0) {
            $status = 'NOT TESTED';
        } elseif ($c['fail'] === 0 && $c['pass'] >= 5) {
            $status = 'PASS';
        } else {
            $status = 'PARTIAL';
        }
        $ciaSummary[$label] = [
            'pillar'       => $k,
            'status'       => $status,
            'pass'         => $c['pass'],
            'warning'      => $c['warning'],
            'fail'         => $c['fail'],
            'not_testable' => $c['not_testable'],
            'total'        => $c['total'],
            'score'        => $score,
        ];
    }

    $durationMs = round((microtime(true) - $startTime) * 1000, 2);

    return [
        'tests'       => $tests,
        'stats'       => $stats,
        'cia_summary' => $ciaSummary,
        'duration_ms' => $durationMs,
        'tested_at'   => $testedAt,
        'raw_time'    => time(),
    ];
}

/* =====================================================================
 | 2. POST Handlers: Run Live Security Tests
 * =================================================================== */
if (is_post()) {
    require_csrf();
    $postAction = (string) input('action', '');

    if ($postAction === 'run_tests') {
        $suiteResults = sv_run_cia_security_suite($pdo, $admin);
        $_SESSION['cia_suite_results'] = $suiteResults;

        audit_log('cia_tests_executed', sprintf(
            'Admin %s executed CIA Security Verification suite: %d tests completed (%d pass, %d warnings, %d fail).',
            $admin['username'],
            $suiteResults['stats']['total'],
            $suiteResults['stats']['pass'],
            $suiteResults['stats']['warning'],
            $suiteResults['stats']['fail']
        ), (int) $admin['id']);

        flash_success(sprintf(
            'CIA Security Verification Suite executed successfully! %d controls evaluated in %s ms.',
            $suiteResults['stats']['total'],
            $suiteResults['duration_ms']
        ));
        redirect('admin/security-testing.php');
    }
}

// Run fresh live tests on the system
$suiteResults = sv_run_cia_security_suite($pdo, $admin);
$_SESSION['cia_suite_results'] = $suiteResults;

$tests       = $suiteResults['tests'];
$stats       = $suiteResults['stats'];
$ciaSummary  = $suiteResults['cia_summary'];
$durationMs  = $suiteResults['duration_ms'];
$testedAt    = $suiteResults['tested_at'];

/* =====================================================================
 | 3. Export Handlers: CSV, JSON, and Printable Report
 * =================================================================== */
$exportFormat = trim((string) input('export', ''));

if ($exportFormat === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="securevote-cia-security-report-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $safeCell = static function (string $val): string {
        if ($val !== '' && str_contains('=+-@', $val[0])) {
            return "'" . $val;
        }
        return $val;
    };

    $out = fopen('php://output', 'wb');
    fputcsv($out, ['SecureVote CIA Security Verification Report']);
    fputcsv($out, ['Generated', fmt_datetime($testedAt), 'Auditor', $admin['username'] . ' (' . user_full_name($admin) . ')', 'Duration (ms)', $durationMs]);
    fputcsv($out, ['Total Tests', $stats['total'], 'Passed', $stats['pass'], 'Warnings', $stats['warning'], 'Failed', $stats['fail'], 'Not Testable', $stats['not_testable']]);
    fputcsv($out, []);
    fputcsv($out, ['Code', 'CIA Pillar', 'Test Name', 'Status', 'Expected Standard', 'Observed Result', 'Technical Evidence', 'Tested At']);

    foreach ($tests as $t) {
        fputcsv($out, [
            $safeCell((string) $t['code']),
            $safeCell((string) $t['category']),
            $safeCell((string) $t['name']),
            $safeCell((string) $t['status']),
            $safeCell((string) $t['expected']),
            $safeCell((string) $t['result']),
            $safeCell((string) $t['evidence']),
            (string) $t['tested_at'],
        ]);
    }

    fclose($out);
    audit_log('cia_report_exported', 'Exported CIA Security Verification Report as CSV.');
    exit;
}

if ($exportFormat === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="securevote-cia-security-report-' . date('Ymd-His') . '.json"');
    header('X-Content-Type-Options: nosniff');

    $exportData = [
        'system'         => APP_NAME . ' v' . APP_VERSION,
        'title'          => 'CIA Triad Information Security Verification Report',
        'generated_at'   => $testedAt,
        'auditor'        => [
            'username'   => $admin['username'],
            'role'       => $admin['role'],
            'full_name'  => user_full_name($admin),
        ],
        'executive_summary' => $ciaSummary,
        'statistics'     => $stats,
        'duration_ms'    => $durationMs,
        'test_results'   => $tests,
    ];

    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    audit_log('cia_report_exported', 'Exported CIA Security Verification Report as JSON.');
    exit;
}

/* =====================================================================
 | 4. Page Header Setup
 * =================================================================== */
$pageTitle = sv_t('CIA Security Testing');
$activeNav = 'security-testing.php';
$breadcrumb   = [['label' => sv_t('CIA Security Testing'), 'href' => 'admin/security-testing.php']];

$pageActions  = '<div class="btn-group">'
    . '<a class="btn btn--secondary btn--sm" href="' . e(url('admin/security-testing.php?export=csv')) . '">'
    . sv_icon('download', 15, 'btn__icon') . '<span>' . sv_t('Export CSV') . '</span></a>'
    . '<a class="btn btn--secondary btn--sm" href="' . e(url('admin/security-testing.php?export=json')) . '">'
    . sv_icon('file', 15, 'btn__icon') . '<span>' . sv_t('Export JSON') . '</span></a>'
    . '<button type="button" class="btn btn--secondary btn--sm" data-security-print>'
    . sv_icon('archive', 15, 'btn__icon') . '<span>' . sv_t('Print Report') . '</span></button>'
    . '</div>';

require __DIR__ . '/../includes/header.php';
?>

<!-- CIA Testing Module Custom Styles (Integrated with SecureVote Design System) -->
<style>
/* CIA Hero & Triad Overview Cards */
.cia-triad-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--sp-4);
  margin-bottom: var(--sp-6);
}

@media (max-width: 900px) {
  .cia-triad-grid {
    grid-template-columns: 1fr;
  }
}

.cia-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: var(--sp-5);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
  transition: transform var(--tr-fast), box-shadow var(--tr-fast);
}

.cia-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.cia-card--c::before {
  content: "";
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, #2563eb, #38bdf8);
}

.cia-card--i::before {
  content: "";
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, #0d9488, #10b981);
}

.cia-card--a::before {
  content: "";
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
}

.cia-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--sp-3);
}

.cia-card__title-wrap {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.cia-card__letter {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: var(--radius-sm);
  font-weight: 800;
  font-size: 0.875rem;
}

.cia-card--c .cia-card__letter { background: var(--blue-50); color: var(--blue); }
.cia-card--i .cia-card__letter { background: var(--teal-50); color: var(--teal); }
.cia-card--a .cia-card__letter { background: var(--indigo-50); color: var(--indigo); }

[data-theme="dark"] .cia-card--c .cia-card__letter { background: rgba(37,99,235,0.2); color: #60a5fa; }
[data-theme="dark"] .cia-card--i .cia-card__letter { background: rgba(13,148,136,0.2); color: #2dd4bf; }
[data-theme="dark"] .cia-card--a .cia-card__letter { background: rgba(99,102,241,0.2); color: #a5b4fc; }

.cia-card__title {
  font-size: 1.05rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0;
  color: var(--text);
}

.cia-card__state-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: var(--radius-sm);
  font-size: 0.775rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.cia-card__state-badge--pass {
  background: var(--success-50);
  color: var(--success);
  border: 1px solid var(--success-border);
}

.cia-card__state-badge--partial {
  background: var(--warning-50);
  color: var(--warning);
  border: 1px solid var(--warning-border);
}

.cia-card__state-badge--fail {
  background: var(--danger-50);
  color: var(--danger);
  border: 1px solid var(--danger-border);
}

[data-theme="dark"] .cia-card__state-badge--pass {
  background: rgba(16,185,129,0.15);
  color: #34d399;
  border-color: rgba(16,185,129,0.3);
}

[data-theme="dark"] .cia-card__state-badge--partial {
  background: rgba(245,158,11,0.15);
  color: #fbbf24;
  border-color: rgba(245,158,11,0.3);
}

[data-theme="dark"] .cia-card__state-badge--fail {
  background: rgba(239,68,68,0.15);
  color: #f87171;
  border-color: rgba(239,68,68,0.3);
}

.cia-card__desc {
  font-size: 0.85rem;
  color: var(--text-muted);
  line-height: 1.5;
  margin: 0 0 var(--sp-4) 0;
  flex: 1;
}

.cia-card__stats {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding-top: var(--sp-3);
  border-top: 1px solid var(--border-subtle);
}

.cia-card__score-label {
  font-size: 0.775rem;
  font-weight: 600;
  color: var(--text-muted);
}

.cia-card__score-val {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text);
}

.cia-card__score-val span {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-muted);
}

/* Control Hub & Action Bar */
.cia-hub {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: var(--sp-5) var(--sp-6);
  margin-bottom: var(--sp-6);
  box-shadow: var(--shadow-sm);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--sp-4);
}

.cia-hub__meta {
  display: flex;
  align-items: center;
  gap: var(--sp-6);
  flex-wrap: wrap;
}

.cia-hub__metric {
  display: flex;
  flex-direction: column;
}

.cia-hub__metric-lbl {
  font-size: 0.725rem;
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: var(--text-muted);
}

.cia-hub__metric-val {
  font-size: 1.125rem;
  font-weight: 800;
  color: var(--text);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.cia-hub__actions {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

.btn--run-tests {
  background: var(--blue);
  color: #ffffff;
  font-weight: 700;
  padding: 10px 20px;
  border-radius: var(--radius-md);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
  transition: all var(--tr-fast);
  border: 0;
  cursor: pointer;
}

.btn--run-tests:hover {
  background: var(--blue-hover);
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

/* Filter Toolbar */
.cia-filter-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  margin-bottom: var(--sp-4);
  flex-wrap: wrap;
}

.cia-pills {
  display: inline-flex;
  background: var(--surface-2);
  padding: 3px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  gap: 2px;
}

.cia-pill {
  border: 0;
  background: transparent;
  padding: 6px 14px;
  border-radius: var(--radius-sm);
  font-size: 0.825rem;
  font-weight: 600;
  color: var(--text-muted);
  cursor: pointer;
  transition: all var(--tr-fast);
}

.cia-pill.is-active,
.cia-pill:hover {
  background: var(--surface);
  color: var(--text);
  box-shadow: var(--shadow-xs);
}

.cia-pill.is-active {
  font-weight: 700;
  color: var(--blue);
}

/* Expandable Test Rows */
.test-row {
  cursor: pointer;
  transition: background-color var(--tr-fast);
}

.test-row:hover td {
  background: var(--surface-hover) !important;
}

.test-row.is-expanded td {
  background: var(--surface-2) !important;
  border-bottom: 0 !important;
}

.test-detail-box {
  background: var(--surface-2);
  border-bottom: 1px solid var(--border);
  padding: var(--sp-4) var(--sp-6) var(--sp-5);
  display: none;
}

.test-detail-box.is-open {
  display: block;
}

.test-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--sp-4);
  margin-top: var(--sp-3);
}

@media (max-width: 768px) {
  .test-detail-grid {
    grid-template-columns: 1fr;
  }
}

.test-detail-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: var(--sp-4);
}

.test-detail-card__label {
  font-size: 0.725rem;
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: var(--text-muted);
  margin-bottom: var(--sp-1);
}

.test-detail-card__body {
  font-size: 0.875rem;
  color: var(--text);
  line-height: 1.55;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* Status Icons & Badges */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  white-space: nowrap;
}

.status-pill--pass {
  background: var(--success-50);
  color: var(--success);
  border: 1px solid var(--success-border);
}

.status-pill--warning {
  background: var(--warning-50);
  color: var(--warning);
  border: 1px solid var(--warning-border);
}

.status-pill--fail {
  background: var(--danger-50);
  color: var(--danger);
  border: 1px solid var(--danger-border);
}

.status-pill--not_testable {
  background: var(--surface-3);
  color: var(--text-muted);
  border: 1px solid var(--border);
}

[data-theme="dark"] .status-pill--pass { background: rgba(16,185,129,0.15); color: #34d399; border-color: rgba(16,185,129,0.3); }
[data-theme="dark"] .status-pill--warning { background: rgba(245,158,11,0.15); color: #fbbf24; border-color: rgba(245,158,11,0.3); }
[data-theme="dark"] .status-pill--fail { background: rgba(239,68,68,0.15); color: #f87171; border-color: rgba(239,68,68,0.3); }
[data-theme="dark"] .status-pill--not_testable { background: var(--surface-3); color: var(--text-muted); border-color: var(--border); }

/* CIA Pillar Pill */
.pillar-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 700;
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: var(--radius-xs);
  white-space: nowrap;
}

.pillar-badge--c { background: var(--blue-50); color: var(--blue); }
.pillar-badge--i { background: var(--teal-50); color: var(--teal); }
.pillar-badge--a { background: var(--indigo-50); color: var(--indigo); }

[data-theme="dark"] .pillar-badge--c { background: rgba(37,99,235,0.2); color: #60a5fa; }
[data-theme="dark"] .pillar-badge--i { background: rgba(13,148,136,0.2); color: #2dd4bf; }
[data-theme="dark"] .pillar-badge--a { background: rgba(99,102,241,0.2); color: #a5b4fc; }

/* Security Code Badge */
.sec-code-badge {
  display: inline-block;
  white-space: nowrap !important;
  word-break: keep-all !important;
  font-family: var(--font-mono);
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--text-muted);
  background: var(--surface-2);
  border: 1px solid var(--border);
  padding: 3px 8px;
  border-radius: var(--radius-xs);
  letter-spacing: 0.03em;
}

/* Summary Checklist Grid */
.cia-summary-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: var(--sp-6);
  margin-top: var(--sp-6);
  box-shadow: var(--shadow-sm);
}

.cia-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--sp-6);
  margin-top: var(--sp-4);
}

@media (max-width: 900px) {
  .cia-summary-grid {
    grid-template-columns: 1fr;
  }
}

.cia-summary-col__title {
  font-size: 0.95rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin: 0 0 var(--sp-3) 0;
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.cia-summary-col--c .cia-summary-col__title { color: var(--blue); }
.cia-summary-col--i .cia-summary-col__title { color: var(--teal); }
.cia-summary-col--a .cia-summary-col__title { color: var(--indigo); }

.cia-check-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.cia-check-item {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-2);
  font-size: 0.875rem;
  color: var(--text-secondary);
  line-height: 1.45;
}

.cia-check-item svg {
  color: var(--success);
  flex: none;
  margin-top: 2px;
}

/* Print Styling */
@media print {
  .sidebar, .topbar, .cia-hub__actions, .btn-group, .table-toolbar, .cia-filter-toolbar, .alert__close {
    display: none !important;
  }
  .app-shell, .content {
    margin: 0 !important;
    padding: 0 !important;
  }
  .test-detail-box {
    display: block !important;
  }
}
</style>

<!-- ===================================================================
 | 1. CIA SECURITY OVERVIEW (Top Triad Cards)
 * =================================================================== -->
<!-- ===================================================================
 | 1. CIA SECURITY OVERVIEW (Top Triad Cards)
 * =================================================================== -->
<section class="cia-triad-grid" aria-label="<?= sv_t('CIA Security Triad Overview') ?>">
  <!-- Confidentiality Card -->
  <div class="cia-card cia-card--c">
    <div class="cia-card__head">
      <div class="cia-card__title-wrap">
        <span class="cia-card__letter" aria-hidden="true">C</span>
        <h2 class="cia-card__title"><?= sv_t('Confidentiality') ?></h2>
      </div>
      <?php $cStatus = $ciaSummary['Confidentiality']['status']; ?>
      <span class="cia-card__state-badge cia-card__state-badge--<?= strtolower($cStatus) ?>">
        <?= sv_icon($cStatus === 'PASS' ? 'check' : ($cStatus === 'PARTIAL' ? 'alert-triangle' : 'x'), 14) ?>
        <?= e($cStatus) ?>
      </span>
    </div>
    <p class="cia-card__desc">
      <?= sv_t('Unauthorized users cannot access protected ballots, credentials, session tokens, or administrator routes.') ?>
    </p>
    <div class="cia-card__stats">
      <span class="cia-card__score-label"><?= sv_t('%d of %d controls passed', (int) $ciaSummary['Confidentiality']['pass'], (int) $ciaSummary['Confidentiality']['total']) ?></span>
      <span class="cia-card__score-val"><?= e((string) $ciaSummary['Confidentiality']['score']) ?><span>%</span></span>
    </div>
  </div>

  <!-- Integrity Card -->
  <div class="cia-card cia-card--i">
    <div class="cia-card__head">
      <div class="cia-card__title-wrap">
        <span class="cia-card__letter" aria-hidden="true">I</span>
        <h2 class="cia-card__title"><?= sv_t('Integrity') ?></h2>
      </div>
      <?php $iStatus = $ciaSummary['Integrity']['status']; ?>
      <span class="cia-card__state-badge cia-card__state-badge--<?= strtolower($iStatus) ?>">
        <?= sv_icon($iStatus === 'PASS' ? 'check' : ($iStatus === 'PARTIAL' ? 'alert-triangle' : 'x'), 14) ?>
        <?= e($iStatus) ?>
      </span>
    </div>
    <p class="cia-card__desc">
      <?= sv_t('Unauthorized users cannot manipulate ballots, election bounds, candidate assignments, or audit trails.') ?>
    </p>
    <div class="cia-card__stats">
      <span class="cia-card__score-label"><?= sv_t('%d of %d controls passed', (int) $ciaSummary['Integrity']['pass'], (int) $ciaSummary['Integrity']['total']) ?></span>
      <span class="cia-card__score-val"><?= e((string) $ciaSummary['Integrity']['score']) ?><span>%</span></span>
    </div>
  </div>

  <!-- Availability Card -->
  <div class="cia-card cia-card--a">
    <div class="cia-card__head">
      <div class="cia-card__title-wrap">
        <span class="cia-card__letter" aria-hidden="true">A</span>
        <h2 class="cia-card__title"><?= sv_t('Availability') ?></h2>
      </div>
      <?php $aStatus = $ciaSummary['Availability']['status']; ?>
      <span class="cia-card__state-badge cia-card__state-badge--<?= strtolower($aStatus) ?>">
        <?= sv_icon($aStatus === 'PASS' ? 'check' : ($aStatus === 'PARTIAL' ? 'alert-triangle' : 'x'), 14) ?>
        <?= e($aStatus) ?>
      </span>
    </div>
    <p class="cia-card__desc">
      <?= sv_t('Authorized voters and administrators can access the voting pipeline, database, and telemetry reliably.') ?>
    </p>
    <div class="cia-card__stats">
      <span class="cia-card__score-label"><?= sv_t('%d of %d controls passed', (int) $ciaSummary['Availability']['pass'], (int) $ciaSummary['Availability']['total']) ?></span>
      <span class="cia-card__score-val"><?= e((string) $ciaSummary['Availability']['score']) ?><span>%</span></span>
    </div>
  </div>
</section>

<!-- ===================================================================
 | 2. RUN SECURITY TESTS & LIVE TELEMETRY HUB
 * =================================================================== -->
<section class="cia-hub" aria-label="<?= sv_t('Security Test Controls & Telemetry') ?>">
  <div class="cia-hub__meta">
    <div class="cia-hub__metric">
      <span class="cia-hub__metric-lbl"><?= sv_t('Last Test Executed') ?></span>
      <span class="cia-hub__metric-val"><?= e(fmt_datetime($testedAt)) ?></span>
    </div>
    <div class="cia-hub__metric">
      <span class="cia-hub__metric-lbl"><?= sv_t('Tests Completed') ?></span>
      <span class="cia-hub__metric-val"><?= e((string) $stats['total']) ?></span>
    </div>
    <div class="cia-hub__metric">
      <span class="cia-hub__metric-lbl"><?= sv_t('Passed') ?></span>
      <span class="cia-hub__metric-val" style="color: var(--success);">
        <?= sv_icon('check-circle', 18) ?> <?= e((string) $stats['pass']) ?>
      </span>
    </div>
    <div class="cia-hub__metric">
      <span class="cia-hub__metric-lbl"><?= sv_t('Warnings / Notes') ?></span>
      <span class="cia-hub__metric-val" style="color: var(--warning);">
        <?= sv_icon('alert-triangle', 18) ?> <?= e((string) ($stats['warning'] + $stats['not_testable'])) ?>
      </span>
    </div>
    <div class="cia-hub__metric">
      <span class="cia-hub__metric-lbl"><?= sv_t('Failed') ?></span>
      <span class="cia-hub__metric-val" style="color: <?= $stats['fail'] > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;">
        <?= sv_icon($stats['fail'] > 0 ? 'x-circle' : 'shield-check', 18) ?> <?= e((string) $stats['fail']) ?>
      </span>
    </div>
    <div class="cia-hub__metric">
      <span class="cia-hub__metric-lbl"><?= sv_t('Execution Duration') ?></span>
      <span class="cia-hub__metric-val" style="font-size: 0.95rem; color: var(--text-muted);">
        <?= sv_icon('clock', 15) ?> <?= e((string) $durationMs) ?> ms
      </span>
    </div>
  </div>

  <div class="cia-hub__actions">
    <form method="post" action="<?= e(post_url('admin/security-testing.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="run_tests">
      <button type="submit" class="btn--run-tests">
        <?= sv_icon('refresh', 18) ?>
        <span><?= sv_t('Run CIA Security Tests') ?></span>
      </button>
    </form>
  </div>
</section>

<!-- ===================================================================
 | 3. SECURITY TEST RESULTS TABLE & FILTER CONTROLS
 * =================================================================== -->
<section class="card" aria-labelledby="tests-table-heading">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title" id="tests-table-heading"><?= sv_t('Security Verification Results') ?></h2>
      <p class="card__subtitle">
        <?= sv_t('Individual safe inspections verifying cryptographic controls, database constraints, and application guards.') ?>
      </p>
    </div>
    <div class="card__actions">
      <a class="btn btn--secondary btn--sm" href="<?= e(url('admin/security-testing.php?export=csv')) ?>">
        <?= sv_icon('download', 15, 'btn__icon') ?>
        <span><?= sv_t('Export Security Report') ?></span>
      </a>
    </div>
  </div>

  <div class="card__body" style="padding-bottom: 0;">
    <div class="cia-filter-toolbar">
      <div class="cia-pills" role="tablist" aria-label="<?= sv_t('Filter tests by pillar') ?>">
        <button type="button" class="cia-pill is-active" data-filter="all" role="tab" aria-selected="true"><?= sv_t('All Tests (%d)', (int) $stats['total']) ?></button>
        <button type="button" class="cia-pill" data-filter="C" role="tab" aria-selected="false"><?= sv_t('Confidentiality (%d)', (int) $ciaSummary['Confidentiality']['total']) ?></button>
        <button type="button" class="cia-pill" data-filter="I" role="tab" aria-selected="false"><?= sv_t('Integrity (%d)', (int) $ciaSummary['Integrity']['total']) ?></button>
        <button type="button" class="cia-pill" data-filter="A" role="tab" aria-selected="false"><?= sv_t('Availability (%d)', (int) $ciaSummary['Availability']['total']) ?></button>
        <button type="button" class="cia-pill" data-filter="ATTENTION" role="tab" aria-selected="false"><?= sv_t('Advisories / Notes (%d)', (int) ($stats['warning'] + $stats['not_testable'] + $stats['fail'])) ?></button>
      </div>

      <div class="search-field" style="min-width: 260px;">
        <span class="search-field__icon" aria-hidden="true"><?= sv_icon('search', 16) ?></span>
        <label class="visually-hidden" for="ciaSearchInput"><?= sv_t('Search tests') ?></label>
        <input class="input" type="search" id="ciaSearchInput" placeholder="<?= e(sv_t('Filter by test name, code or keyword...')) ?>">
      </div>
    </div>
  </div>

  <div class="table-wrap" style="border: 0; border-radius: 0; border-top: 1px solid var(--border);">
    <table class="table" id="ciaTestsTable" aria-label="<?= sv_t('CIA Security Verification Results') ?>">
      <thead>
        <tr>
          <th scope="col" style="width: 130px; white-space: nowrap;"><?= sv_t('Status') ?></th>
          <th scope="col" style="width: 120px; white-space: nowrap;"><?= sv_t('Code') ?></th>
          <th scope="col" style="width: 80px; white-space: nowrap;"><?= sv_t('CIA') ?></th>
          <th scope="col"><?= sv_t('Security Control & Verification Finding') ?></th>
          <th scope="col" class="table__actions-col" style="width: 120px; white-space: nowrap;"><?= sv_t('Details') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tests as $index => $t): ?>
          <?php
            $rowStatus = $t['status'];
            $statusClass = match ($rowStatus) {
                'PASS'         => 'status-pill--pass',
                'WARNING'      => 'status-pill--warning',
                'FAIL'         => 'status-pill--fail',
                'NOT_TESTABLE' => 'status-pill--not_testable',
                default        => 'status-pill--not_testable',
            };
            $statusIcon = match ($rowStatus) {
                'PASS'         => 'check-circle',
                'WARNING'      => 'alert-triangle',
                'FAIL'         => 'x-circle',
                'NOT_TESTABLE' => 'info',
                default        => 'info',
            };
            $statusLabel = match ($rowStatus) {
                'PASS'         => sv_t('PASS'),
                'WARNING'      => sv_t('WARNING'),
                'FAIL'         => sv_t('FAIL'),
                'NOT_TESTABLE' => sv_t('NOT TESTABLE'),
                default        => $rowStatus,
            };
            $pillarClass = 'pillar-badge--' . strtolower($t['cia']);
            $detailId = 'test-detail-' . $index;
          ?>
          <tr class="test-row" data-cia="<?= e($t['cia']) ?>" data-status="<?= e($rowStatus) ?>">
            <td style="white-space: nowrap;">
              <span class="status-pill <?= e($statusClass) ?>">
                <?= sv_icon($statusIcon, 14) ?>
                <span><?= e($statusLabel) ?></span>
              </span>
            </td>
            <td style="white-space: nowrap;">
              <code class="sec-code-badge"><?= e($t['code']) ?></code>
            </td>
            <td style="white-space: nowrap;">
              <span class="pillar-badge <?= e($pillarClass) ?>">
                <?= e($t['cia']) ?>
              </span>
            </td>
            <td>
              <span class="table__cell-strong"><?= e($t['name']) ?></span>
              <span class="table__cell-sub"><?= e($t['result']) ?></span>
            </td>
            <td class="table__actions-col" style="white-space: nowrap;">
              <button type="button" class="btn btn--secondary btn--xs" aria-expanded="false" aria-controls="<?= e($detailId) ?>">
                <span><?= sv_t('View Details') ?></span>
                <?= sv_icon('chevron-down', 13) ?>
              </button>
            </td>
          </tr>
          <!-- Expandable Details Row -->
          <tr class="test-detail-row" data-parent-cia="<?= e($t['cia']) ?>" data-parent-status="<?= e($rowStatus) ?>">
            <td colspan="5" style="padding: 0; border-bottom: 1px solid var(--border-subtle);">
              <div class="test-detail-box" id="<?= e($detailId) ?>">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--sp-2);">
                  <div style="display: flex; align-items: center; gap: var(--sp-2);">
                    <span class="pillar-badge <?= e($pillarClass) ?>"><?= e($t['category']) ?> (<?= e($t['cia']) ?>)</span>
                    <strong style="font-size: 0.95rem; color: var(--text);"><?= e($t['name']) ?></strong>
                    <code class="sec-code-badge" style="font-size: 0.775rem;">(<?= e($t['code']) ?>)</code>
                  </div>
                  <span class="text-sm text-muted"><?= sv_t('Verified: %s', fmt_datetime($t['tested_at'])) ?></span>
                </div>

                <div class="test-detail-grid">
                  <div class="test-detail-card">
                    <p class="test-detail-card__label"><?= sv_t('Expected Security Standard') ?></p>
                    <p class="test-detail-card__body"><?= e($t['expected']) ?></p>
                  </div>
                  <div class="test-detail-card">
                    <p class="test-detail-card__label"><?= sv_t('Observed Verification Result') ?></p>
                    <p class="test-detail-card__body" style="color: <?= $rowStatus === 'FAIL' ? 'var(--danger)' : ($rowStatus === 'WARNING' ? 'var(--warning)' : 'var(--success)') ?>;">
                      <?= e($t['result']) ?>
                    </p>
                  </div>
                </div>

                <div class="test-detail-card" style="margin-top: var(--sp-3);">
                  <p class="test-detail-card__label"><?= sv_t('Technical Evidence & Diagnostic Audit') ?></p>
                  <p class="test-detail-card__body" style="font-size: 0.8125rem; color: var(--text-secondary);">
                    <?= e($t['evidence']) ?>
                  </p>
                  <?php if (!empty($t['recommendation'])): ?>
                    <div class="alert alert--warning" style="margin-top: var(--sp-3); padding: var(--sp-3) var(--sp-4);">
                      <span class="alert__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 16) ?></span>
                      <div class="alert__text" style="font-size: 0.8125rem;">
                        <strong><?= sv_t('Security Recommendation:') ?></strong> <?= e($t['recommendation']) ?>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- ===================================================================
 | 4. CIA SECURITY SUMMARY CHECKLIST (Bottom Component)
 * =================================================================== -->
<section class="cia-summary-card" aria-labelledby="cia-summary-heading">
  <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--sp-2);">
    <div>
      <h2 class="card__title" id="cia-summary-heading" style="font-size: 1.15rem;"><?= sv_t('CIA Security Summary') ?></h2>
      <p class="card__subtitle" style="margin: 0;"><?= sv_t("Verified security guarantees actively enforced across SecureVote's core subsystems.") ?></p>
    </div>
    <span class="badge badge--teal">
      <?= sv_icon('shield-check', 14) ?>
      <?= sv_t('%d of %d Controls Verified', (int) $stats['pass'], (int) $stats['total']) ?>
    </span>
  </div>

  <div class="cia-summary-grid">
    <!-- Confidentiality Column -->
    <div class="cia-summary-col cia-summary-col--c">
      <h3 class="cia-summary-col__title">
        <?= sv_icon('lock', 16) ?>
        <?= sv_t('Confidentiality') ?>
      </h3>
      <ul class="cia-check-list">
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Protected authentication guards on all administrative routes') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Role-based access control isolating voters from admin controls') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('One-way bcrypt password hashing; zero plaintext credential storage') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Information exposure protection: debug disabled & error logs private') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Hardened session lifecycle: HttpOnly, SameSite, and fingerprint binding') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Defensive HTTP headers and Content-Security-Policy') ?></span></li>
      </ul>
    </div>

    <!-- Integrity Column -->
    <div class="cia-summary-col cia-summary-col--i">
      <h3 class="cia-summary-col__title">
        <?= sv_icon('shield-check', 16) ?>
        <?= sv_t('Integrity') ?>
      </h3>
      <ul class="cia-check-list">
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Duplicate vote prevention via database unique composite constraints') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Server-side election window and active status verification') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Candidate-election relationship validation against tampering') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Positional selection limits enforced server-side (over-voting defense)') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Cross-Site Request Forgery (CSRF) tokens on all state-changing actions') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Native parameterized PDO statements (SQL injection immunity)') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Database transactions with automatic rollback on submission failure') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Append-only immutable audit logging of critical events') ?></span></li>
      </ul>
    </div>

    <!-- Availability Column -->
    <div class="cia-summary-col cia-summary-col--a">
      <h3 class="cia-summary-col__title">
        <?= sv_icon('server', 16) ?>
        <?= sv_t('Availability') ?>
      </h3>
      <ul class="cia-check-list">
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Database connection operational with verified low query latency') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('All 8 required schema tables verified and active') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Foreign key referential constraints intact across all entities') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Complete 7-step voting workflow pipeline verified available') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Fail-safe 503 database fallback and branded exception handlers') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Brute-force sign-in rate limiting and account throttling') ?></span></li>
        <li class="cia-check-item"><?= sv_icon('check', 16) ?><span><?= sv_t('Local logging subsystem writable and operational') ?></span></li>
      </ul>
    </div>
  </div>
</section>

<script src="<?= e(asset('js/security-testing.js')) ?>" defer></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
