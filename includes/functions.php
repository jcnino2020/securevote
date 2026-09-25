<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/functions.php
 *
 * Small, dependency-free helpers shared by every page. Grouped by topic:
 *   1. Output escaping and URLs
 *   2. Request input
 *   3. Dates and formatting
 *   4. Status vocabulary (the single source of truth for every badge)
 *   5. Elections (state machine helpers)
 *   6. Audit logging
 *   7. Pagination
 *   8. Error pages
 *
 * Nothing in this file may assume that a user is logged in.
 */

/* =====================================================================
 | 1. Output escaping and URLs
 * =================================================================== */

/**
 * Escape a value for HTML output.
 *
 * Every piece of dynamic text in this project goes through e() before it
 * is printed. That is the whole defence against stored and reflected
 * cross-site scripting: the database keeps the original text, and the
 * browser only ever receives inert entities.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a URL that works both at a vhost root and inside a sub-folder. */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');

    return BASE_URL . ($path === '' ? '/' : '/' . $path);
}

/** Build a URL inside assets/ with automatic cache-busting. */
function asset(string $path): string
{
    $cleanPath = ltrim($path, '/');
    $filePath  = __DIR__ . '/../assets/' . $cleanPath;
    $version   = file_exists($filePath) ? (string) filemtime($filePath) : (defined('APP_VERSION') ? (string) APP_VERSION : '2026');

    return url('assets/' . $cleanPath . '?v=' . $version);
}

/** Send a Location header and stop. Only ever used with a hard-coded path. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** The current script's file name, e.g. "elections.php". */
function current_script(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
}

/** Rebuild the current query string with some values replaced. */
function query_with(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }

    return $params === [] ? '' : '?' . http_build_query($params);
}

/** Shorten a long string for table cells. */
function str_limit(?string $text, int $limit = 80): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
}

/** Initials for the avatar circles, e.g. "JB". */
function initials(?string $first, ?string $last = ''): string
{
    $a = mb_substr(trim((string) $first), 0, 1);
    $b = mb_substr(trim((string) $last), 0, 1);

    return mb_strtoupper($a . $b) ?: '?';
}

/** Mask a Student ID or Voter Identifier for privacy in logs e.g. "2024-***23". */
function sv_mask_identifier(string $id): string
{
    $id = trim($id);
    $len = mb_strlen($id);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    $start = mb_substr($id, 0, min(4, (int) ($len / 2)));
    $end   = mb_substr($id, -2);

    return $start . str_repeat('*', max(2, $len - mb_strlen($start) - 2)) . $end;
}


/* =====================================================================
 | 2. Request input
 * =================================================================== */

function is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

/** Read a trimmed scalar from POST first, then GET. */
function input(string $key, ?string $default = null): ?string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    if (is_array($value)) {
        return $default;
    }
    if ($value === null) {
        return $default;
    }

    return trim((string) $value);
}

/** Read an integer from POST/GET, clamped to a floor of 0 by default. */
function int_input(string $key, int $default = 0): int
{
    $value = input($key, null);
    if ($value === null || $value === '' || !is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}

/** Read an array from POST (e.g. checkbox groups), always returning an array. */
function array_input(string $key): array
{
    $value = $_POST[$key] ?? [];

    return is_array($value) ? array_values($value) : [];
}

/** Read a checkbox: present as "1" means true. */
function checkbox_input(string $key): bool
{
    return ($_POST[$key] ?? null) === '1';
}

/** Which page number are we on? Never below 1. */
function current_page_number(): int
{
    return max(1, int_input('page', 1));
}

/* =====================================================================
 | 3. Dates and formatting
 * =================================================================== */

/** Current timestamp in the application timezone. */
function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Turn a MySQL DATETIME into a DateTimeImmutable in the app timezone. */
function to_datetime(?string $value): ?DateTimeImmutable
{
    if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
        return null;
    }

    try {
        return new DateTimeImmutable($value, new DateTimeZone(APP_TIMEZONE));
    } catch (Throwable) {
        return null;
    }
}

/** "Mar 4, 2026 9:30 AM" */
function fmt_datetime(?string $value, string $format = 'M j, Y g:i A'): string
{
    $dt = to_datetime($value);

    return $dt === null ? '—' : $dt->format($format);
}

/** "Mar 4, 2026" */
function fmt_date(?string $value): string
{
    return fmt_datetime($value, 'M j, Y');
}

/** "9:30 AM" */
function fmt_time(?string $value): string
{
    return fmt_datetime($value, 'g:i A');
}

/** "Mar 4, 2026 8:00 AM – Mar 11, 2026 5:00 PM" */
function fmt_range(?string $start, ?string $end): string
{
    if ($start === null && $end === null) {
        return '—';
    }

    return fmt_datetime($start) . ' – ' . fmt_datetime($end);
}

/** "3 minutes ago", "in 2 days". Falls back to an absolute date after a week. */
function relative_time(?string $value): string
{
    $dt = to_datetime($value);
    if ($dt === null) {
        return '—';
    }

    $diff = $dt->getTimestamp() - time();
    $past = $diff < 0;
    $diff = abs($diff);

    if ($diff < 60) {
        return $past ? 'just now' : 'in a moment';
    }

    $units = [
        31536000 => 'year',
        2592000  => 'month',
        604800   => 'week',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
    ];

    foreach ($units as $seconds => $name) {
        if ($diff >= $seconds) {
            $count = (int) floor($diff / $seconds);
            $label = $count . ' ' . $name . ($count > 1 ? 's' : '');
            break;
        }
    }

    if ($diff >= 604800) {
        return $past ? 'on ' . $dt->format('M j, Y') : 'on ' . $dt->format('M j, Y');
    }

    return $past ? $label . ' ago' : 'in ' . $label;
}

/** Countdown text used on election cards, e.g. "Closes in 6 days". */
function countdown_text(array $election): string
{
    $status = election_effective_status($election);

    if ($status === 'open') {
        return 'Closes in ' . relative_time($election['end_datetime']);
    }
    if ($status === 'scheduled') {
        return 'Opens in ' . relative_time($election['start_datetime']);
    }
    if ($status === 'closed' || $status === 'archived') {
        return 'Closed ' . relative_time($election['end_datetime']);
    }

    return 'Not yet scheduled';
}

/** Percentage rounded to one decimal place, safe for a zero denominator. */
function percent(int|float $part, int|float $total): float
{
    if ($total <= 0) {
        return 0.0;
    }

    return round(($part / $total) * 100, 1);
}

/* =====================================================================
 | 4. Status vocabulary
 |
 | Every status shown anywhere in the interface comes from these three
 | maps, so a badge can never mean two different things on two pages.
 * =================================================================== */

/**
 * Render a status badge. Colour is never the only signal: the label is
 * always present, which is what keeps the interface accessible.
 */
function badge(string $label, string $variant = 'neutral', bool $dot = true): string
{
    $safeVariant = in_array($variant, ['neutral', 'info', 'success', 'warning', 'danger', 'dark', 'teal'], true)
        ? $variant
        : 'neutral';

    $dotHtml = $dot ? '<span class="badge__dot" aria-hidden="true"></span>' : '';

    return '<span class="badge badge--' . $safeVariant . '">' . $dotHtml . e($label) . '</span>';
}

/** Election status -> [label, badge variant]. */
function election_status_meta(string $status): array
{
    return match ($status) {
        'draft'     => ['Draft', 'neutral'],
        'scheduled' => ['Scheduled', 'info'],
        'open'      => ['Open', 'success'],
        'closed'    => ['Closed', 'neutral'],
        'archived'  => ['Archived', 'dark'],
        default     => [ucfirst($status), 'neutral'],
    };
}

/** Account status -> [label, badge variant]. */
function account_status_meta(string $status): array
{
    return match ($status) {
        'active'   => ['Active', 'success'],
        'inactive' => ['Inactive', 'neutral'],
        'blocked'  => ['Blocked', 'danger'],
        default    => [ucfirst($status), 'neutral'],
    };
}

/** Candidate status -> [label, badge variant]. */
function candidate_status_meta(string $status): array
{
    return match ($status) {
        'active'       => ['Active', 'success'],
        'inactive'     => ['Inactive', 'neutral'],
        'disqualified' => ['Disqualified', 'danger'],
        default        => [ucfirst($status), 'neutral'],
    };
}

/** Ballot status -> [label, badge variant]. */
function ballot_status_meta(string $status): array
{
    return match ($status) {
        'submitted'   => ['Submitted', 'teal'],
        'invalidated' => ['Invalidated', 'danger'],
        default       => [ucfirst($status), 'neutral'],
    };
}

/** Shorthand: badge from an election status string. */
function election_badge(string $status): string
{
    [$label, $variant] = election_status_meta($status);

    return badge($label, $variant);
}

function account_badge(string $status): string
{
    [$label, $variant] = account_status_meta($status);

    return badge($label, $variant);
}

function candidate_badge(string $status): string
{
    [$label, $variant] = candidate_status_meta($status);

    return badge($label, $variant);
}

/** Security event severity -> [label, badge variant] */
function security_severity_meta(string $severity): array
{
    return match (strtolower(trim($severity))) {
        'critical'      => ['Critical', 'danger'],
        'high'          => ['High', 'danger'],
        'medium'        => ['Medium', 'warning'],
        'low'           => ['Low', 'info'],
        'informational' => ['Informational', 'neutral'],
        default         => [ucfirst($severity), 'neutral'],
    };
}

function security_severity_badge(string $severity): string
{
    [$label, $variant] = security_severity_meta($severity);

    return badge($label, $variant);
}

/** Classify audit log action into consistent security severity level */
function audit_action_severity(string $action, string $description = ''): string
{
    return match ($action) {
        'session_hijack_attempt', 'csrf_failure'      => 'High',
        'unauthorized_access'                         => 'High',
        'login_throttled', 'login_blocked'            => 'Medium',
        'ballot_rejected', 'ballot_validation_failed' => 'Medium',
        'voter_status_changed', 'voter_deleted'       => 'Medium',
        'candidate_deleted', 'position_deleted'       => 'Medium',
        'login_failure', 'password_change_failed'     => 'Low',
        'password_rehash', 'session_idle_timeout'     => 'Low',
        'audit_log_exported', 'results_exported'      => 'Low',
        default                                       => 'Informational',
    };
}

/** Options for a <select> of election statuses. */
function election_status_options(): array
{
    return [
        'draft'     => 'Draft',
        'scheduled' => 'Scheduled',
        'open'      => 'Open',
        'closed'    => 'Closed',
        'archived'  => 'Archived',
    ];
}

function results_visibility_options(): array
{
    return [
        'hidden'      => 'Hidden — nobody can view results',
        'after_vote'  => 'After voting — a voter sees results once they vote',
        'after_close' => 'After close — results appear when the election closes',
    ];
}

/* =====================================================================
 | 5. Elections
 * =================================================================== */

/**
 * The state an election is *actually* in right now.
 *
 * The stored status is the administrator's intent, the datetime window is
 * the truth. An election marked "open" whose end time has passed is
 * treated as closed, so a forgotten click can never accept a late vote.
 */
function election_effective_status(array $election): string
{
    $stored = (string) ($election['status'] ?? 'draft');

    if (in_array($stored, ['draft', 'archived', 'closed'], true)) {
        return $stored;
    }

    $start = to_datetime($election['start_datetime'] ?? null);
    $end   = to_datetime($election['end_datetime'] ?? null);
    $now   = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));

    if ($start !== null && $now < $start) {
        return 'scheduled';
    }
    if ($end !== null && $now > $end) {
        return 'closed';
    }

    return 'open';
}

/** True only when a vote may be cast right now. */
function election_is_votable(array $election): bool
{
    return election_effective_status($election) === 'open';
}

/**
 * Why a given election is not currently votable. Used to explain the state
 * to the voter instead of silently hiding the button.
 */
function election_block_reason(array $election, bool $isEligible, bool $hasVoted): ?string
{
    $status = election_effective_status($election);

    if ($status === 'draft') {
        return 'This election is still being prepared and is not open to voters.';
    }
    if ($status === 'archived') {
        return 'This election has been archived and is no longer votable.';
    }
    if ($status === 'scheduled') {
        return 'This election has not started yet. Voting opens on '
            . fmt_datetime($election['start_datetime']) . '.';
    }
    if ($status === 'closed') {
        return 'This election is closed. Voting ended on '
            . fmt_datetime($election['end_datetime']) . '.';
    }
    if (!$isEligible) {
        return 'Your account is not on the voter list for this election.';
    }
    if ($hasVoted) {
        return 'You have already submitted your ballot for this election.';
    }

    return null;
}

/**
 * Heading and explanation for the 403 shown when a voter reaches a ballot they
 * may not use. One message per state, because "not available" is not an
 * explanation, and each one states what the server did.
 *
 * @return array{0: string, 1: string} [title, message]
 */
function election_state_denial(string $status, array $election): array
{
    return match ($status) {
        'scheduled' => [
            'This election has not started yet',
            'This election has not started yet. Voting opens on '
                . fmt_datetime($election['start_datetime'])
                . '. The server refused this request, so no ballot was opened.',
        ],
        'closed' => [
            'This election is closed',
            'This election is closed. Voting is no longer available: it ended on '
                . fmt_datetime($election['end_datetime'])
                . '. The server refused this request, so no ballot was opened.',
        ],
        'archived' => [
            'This election has been archived',
            'This election has been archived and can no longer be voted in.',
        ],
        'draft' => [
            'This election is not open yet',
            'This election is still being prepared and has not been published to voters.',
        ],
        default => [
            'Voting is not open',
            'This election is not accepting ballots right now.',
        ],
    };
}

/**
 * May this user see the results of this election?
 * Implements the election's configured results policy.
 */
function may_view_results(array $election, ?array $user = null, bool $hasVoted = false): bool
{
    if ($user !== null && ($user['role'] ?? '') === 'admin') {
        return true;
    }

    $status = election_effective_status($election);
    $closed = in_array($status, ['closed', 'archived'], true);

    return match ((string) ($election['results_visibility'] ?? 'after_close')) {
        'hidden'      => false,
        'after_vote'  => $hasVoted || $closed,
        'after_close' => $closed,
        default       => $closed,
    };
}


/** Human explanation of why results are or are not visible. */
function results_policy_notice(array $election, bool $hasVoted = false): ?string
{
    $policy = (string) ($election['results_visibility'] ?? 'after_close');
    $closed = in_array(election_effective_status($election), ['closed', 'archived'], true);

    if ($policy === 'hidden') {
        return 'Results for this election are configured as hidden.';
    }
    if ($policy === 'after_vote' && !$hasVoted && !$closed) {
        return 'Results become available after you submit your ballot.';
    }
    if ($policy === 'after_close' && !$closed) {
        return 'Results become available once the election closes.';
    }

    return null;
}

/** Total number of voters registered for an election. */
function election_eligible_count(int $electionId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM voter_eligibility WHERE election_id = ?');
    $stmt->execute([$electionId]);

    return (int) $stmt->fetchColumn();
}

/** How many of those voters have already submitted a ballot. */
function election_turnout(int $electionId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM voter_eligibility WHERE election_id = ? AND has_voted = 1');
    $stmt->execute([$electionId]);

    return (int) $stmt->fetchColumn();
}

/* =====================================================================
 | 6. Audit logging
 * =================================================================== */

/** The best available client IP, IPv4 or IPv6, capped to the column width. */
function client_ip(): ?string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $ip === '' ? null : mb_substr($ip, 0, 45);
}

function client_user_agent(): ?string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    return $ua === '' ? null : mb_substr($ua, 0, 255);
}

/**
 * Append an event to the audit trail.
 *
 * Logging must never break a request, so any failure here is swallowed
 * after being written to the PHP error log.
 */
function audit_log(string $action, string $description, ?int $userId = null): void
{
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent, created_at)
             VALUES (:user_id, :action, :description, :ip, :ua, :created_at)'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => mb_substr($action, 0, 50),
            ':description' => mb_substr($description, 0, 255),
            ':ip'          => client_ip(),
            ':ua'          => client_user_agent(),
            ':created_at'  => now(),
        ]);
    } catch (Throwable $e) {
        error_log('audit_log() failed for action ' . $action . ': ' . $e->getMessage());
    }
}

/* =====================================================================
 | 7. Pagination
 * =================================================================== */

/**
 * Work out the window of rows to fetch.
 *
 * @return array{page:int, per_page:int, offset:int, pages:int, total:int}
 */
function paginate(int $total, int $perPage = 10, ?int $page = null): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($page ?? current_page_number(), $pages));

    return [
        'page'     => $page,
        'per_page' => $perPage,
        'offset'   => ($page - 1) * $perPage,
        'pages'    => $pages,
        'total'    => $total,
    ];
}

/** Render pagination controls. Returns '' when everything fits on one page. */
function pagination_links(array $page, string $label = 'records'): string
{
    if ($page['pages'] <= 1) {
        return '';
    }

    $html   = '<nav class="pagination" aria-label="Pagination">';
    $html  .= '<span class="pagination__summary">'
        . e('Showing page ' . $page['page'] . ' of ' . $page['pages'] . ' · ' . $page['total'] . ' ' . $label)
        . '</span>';
    $html  .= '<span class="pagination__controls">';

    $prevDisabled = $page['page'] <= 1;
    $nextDisabled = $page['page'] >= $page['pages'];

    $html .= '<a class="btn btn--sm btn--secondary' . ($prevDisabled ? ' is-disabled' : '') . '"'
        . ($prevDisabled ? ' aria-disabled="true" tabindex="-1"' : '')
        . ' href="' . e(query_with(['page' => $page['page'] - 1])) . '">Previous</a>';

    $start = max(1, $page['page'] - 2);
    $end   = min($page['pages'], $start + 4);
    $start = max(1, $end - 4);

    for ($i = $start; $i <= $end; $i++) {
        $current = $i === (int) $page['page'];
        $html .= '<a class="pagination__page' . ($current ? ' is-current' : '') . '"'
            . ($current ? ' aria-current="page"' : '')
            . ' href="' . e(query_with(['page' => $i])) . '">' . $i . '</a>';
    }

    $html .= '<a class="btn btn--sm btn--secondary' . ($nextDisabled ? ' is-disabled' : '') . '"'
        . ($nextDisabled ? ' aria-disabled="true" tabindex="-1"' : '')
        . ' href="' . e(query_with(['page' => $page['page'] + 1])) . '">Next</a>';

    $html .= '</span></nav>';

    return $html;
}

/* =====================================================================
 | 8. Error pages
 * =================================================================== */

/**
 * Render a friendly, branded error page and stop.
 *
 * Used for 403 (unauthorized), 404 (not found) and 500 (unexpected).
 * No stack trace, no SQL, no file path: only what the visitor needs.
 */
function sv_error_page(int $code, string $title, string $message, array $links = []): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, sprintf("[%d] %s - %s\n", $code, $title, $message));
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code($code);
    }

    require __DIR__ . '/error-page.php';
    exit;
}

/** Handle an unexpected exception: log the detail, show a calm page. */
function sv_handle_exception(Throwable $e): void
{
    error_log(sprintf(
        'Unhandled %s in %s:%d - %s',
        $e::class,
        $e->getFile(),
        $e->getLine(),
        $e->getMessage()
    ));

    sv_error_page(
        500,
        'Something went wrong',
        'The request could not be completed. The error has been recorded in the application log.'
    );
}

/** Turn fatal errors (out of memory, parse errors) into the same page. */
function sv_handle_shutdown(): void
{
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatal, true)) {
        return;
    }

    error_log(sprintf('Fatal error: %s in %s:%d', $error['message'], $error['file'], $error['line']));
    sv_error_page(500, 'Something went wrong', 'The request could not be completed.');
}
