<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/dashboard.php
 *
 * Security Operations Center (SOC) Security Monitoring Dashboard.
 *
 * Summarizes audit events, authentication activity, ballot submissions,
 * configured controls, election status, and administrator alerts.
 *
 * Read-only monitoring center adhering to SecureVote brand guidelines.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();

/* =====================================================================
 | POST Handlers: Security Alert Dismissal & Resolution
 * =================================================================== */
if (is_post()) {
    require_csrf();
    $postAction = (string) input('action', '');
    $currentRange = trim((string) input('range', '24h'));
    $rangeQuery = in_array($currentRange, ['24h', '7d', '30d', 'all'], true) && $currentRange !== '24h'
        ? '?range=' . urlencode($currentRange)
        : '';

    if ($postAction === 'resolve_alert') {
        $alertKey   = trim((string) input('alert_key', ''));
        $alertTitle = trim((string) input('alert_title', 'Security Alert'));
        $severity   = trim((string) input('severity', 'Medium'));
        $reason     = trim((string) input('reason', ''));

        if ($alertKey !== '') {
            sv_dismiss_alert($pdo, $alertKey, $alertTitle, $severity, $reason, (int) $admin['id']);
            flash_success(sv_t('Security alert "%s" marked as resolved.', $alertTitle));
        }
        redirect('admin/dashboard.php' . $rangeQuery);
    }

    if ($postAction === 'resolve_all_alerts') {
        $alertsToResolve = json_decode((string) input('alerts_json', '[]'), true);
        $resolvedCount = 0;
        if (is_array($alertsToResolve)) {
            foreach ($alertsToResolve as $a) {
                if (!empty($a['key'])) {
                    sv_dismiss_alert(
                        $pdo,
                        (string) $a['key'],
                        (string) ($a['title'] ?? 'Security Alert'),
                        (string) ($a['severity'] ?? 'Medium'),
                        'Bulk resolved by administrator',
                        (int) $admin['id']
                    );
                    $resolvedCount++;
                }
            }
        }
        flash_success(sv_t('%s security alert(s) marked as resolved.', $resolvedCount > 0 ? (string)$resolvedCount : sv_t('All')));
        redirect('admin/dashboard.php' . $rangeQuery);
    }

    if ($postAction === 'restore_alert') {
        $dismissedId = (int) input('dismissed_id', 0);
        if ($dismissedId > 0) {
            sv_restore_alert($pdo, $dismissedId, (int) $admin['id']);
            flash_success(sv_t('Security alert restored to active monitoring.'));
        }
        $glue = $rangeQuery !== '' ? '&' : '?';
        redirect('admin/dashboard.php' . $rangeQuery . $glue . 'tab=resolved');
    }

    if ($postAction === 'restore_all_alerts') {
        sv_restore_all_alerts($pdo, (int) $admin['id']);
        flash_success(sv_t('All resolved alerts restored to active monitoring.'));
        redirect('admin/dashboard.php' . $rangeQuery);
    }
}

/* =====================================================================
 | 1. Time Window Filter & Intervals
 * =================================================================== */
$range = trim((string) input('range', '24h'));
if (!in_array($range, ['24h', '7d', '30d', 'all'], true)) {
    $range = '24h';
}

$activeTab = trim((string) input('tab', 'active'));
if (!in_array($activeTab, ['active', 'resolved'], true)) {
    $activeTab = 'active';
}

$rangeIntervals = [
    '24h' => ['sql' => 'NOW() - INTERVAL 24 HOUR', 'label' => sv_t('Last 24 Hours'), 'short' => '24h'],
    '7d'  => ['sql' => 'NOW() - INTERVAL 7 DAY',   'label' => sv_t('Last 7 Days'),    'short' => '7d'],
    '30d' => ['sql' => 'NOW() - INTERVAL 30 DAY',  'label' => sv_t('Last 30 Days'),   'short' => '30d'],
    'all' => ['sql' => "'1970-01-01 00:00:00'",    'label' => sv_t('All Time'),       'short' => sv_t('All')],
];

$sinceSql   = $rangeIntervals[$range]['sql'];
$rangeLabel = $rangeIntervals[$range]['label'];

/* =====================================================================
 | 2. System Health Telemetry (Real Verification)
 * =================================================================== */
$healthChecks = [];

// Database Connection & Latency Check
$dbStart = microtime(true);
$dbOk    = false;
try {
    $pdo->query('SELECT 1');
    $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);
    $dbOk      = true;
    $healthChecks['database'] = [
        'label'  => sv_t('Database (MySQL)'),
        'status' => sv_t('Operational'),
        'meta'   => sv_t('%s ms latency', (string) $dbLatency),
        'ok'     => true,
    ];
} catch (Throwable $e) {
    $healthChecks['database'] = [
        'label'  => sv_t('Database (MySQL)'),
        'status' => sv_t('Degraded'),
        'meta'   => sv_t('Connection latency high'),
        'ok'     => false,
    ];
}

// Authentication & Role-Based Access Control
$healthChecks['auth'] = [
    'label'  => sv_t('Authentication & RBAC'),
    'status' => sv_t('Configured'),
    'meta'   => sv_t('Role checks in application routes'),
    'ok'     => true,
];

// Voting Integrity Engine
$healthChecks['voting'] = [
    'label'  => sv_t('Voting System Engine'),
    'status' => sv_t('Configured'),
    'meta'   => sv_t('Voting workflow and unique ballot constraint'),
    'ok'     => true,
];

// Audit Logging Subsystem
$totalLogsCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$healthChecks['audit'] = [
    'label'  => sv_t('Audit Logging Subsystem'),
    'status' => sv_t('Observed'),
    'meta'   => sv_t('%d recorded entries', $totalLogsCount),
    'ok'     => true,
];

// Session Management & CSRF Defense
$healthChecks['session'] = [
    'label'  => sv_t('Session Management & CSRF'),
    'status' => sv_t('Configured'),
    'meta'   => sv_t('Session and CSRF controls in application'),
    'ok'     => true,
];

/* =====================================================================
 | 3. Top Security Statistics (Dynamically retrieved from DB)
 * =================================================================== */
$failedLoginsCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action = 'login_failure' AND created_at >= {$sinceSql}"
)->fetchColumn();

$successfulLoginsCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action IN ('login_success', 'google_login_success') AND created_at >= {$sinceSql}"
)->fetchColumn();

$unauthorizedAccessCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action IN ('unauthorized_access', 'csrf_failure', 'session_hijack_attempt') AND created_at >= {$sinceSql}"
)->fetchColumn();

$blockedRequestsCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action IN ('login_throttled', 'login_blocked', 'unauthorized_access', 'csrf_failure', 'ballot_rejected', 'session_hijack_attempt') AND created_at >= {$sinceSql}"
)->fetchColumn();

$totalSecurityEventsCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE created_at >= {$sinceSql}"
)->fetchColumn();

// Active authenticated sessions within the past 30 minutes (minimum 1 for current admin)
$activeSessionsRaw = (int) $pdo->query(
    "SELECT COUNT(DISTINCT COALESCE(user_id, ip_address)) FROM audit_logs WHERE created_at >= (NOW() - INTERVAL 30 MINUTE)"
)->fetchColumn();
$activeSessions = $activeSessionsRaw;

$totalVotesSubmitted = (int) $pdo->query(
    "SELECT COUNT(*) FROM ballots WHERE status = 'submitted'"
)->fetchColumn();

$totalElections = (int) $pdo->query('SELECT COUNT(*) FROM elections')->fetchColumn();
$totalVoters    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetchColumn();
$activeVoters   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'voter' AND status = 'active'")->fetchColumn();
$blockedUsers   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status <> 'active'")->fetchColumn();

// Check for duplicate vote attempts blocked in the audit trail
$duplicateVoteBlocks = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action = 'ballot_rejected' AND description LIKE '%already%' AND created_at >= {$sinceSql}"
)->fetchColumn();

$invalidBallotBlocks = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action IN ('ballot_validation_failed', 'csrf_failure') AND created_at >= {$sinceSql}"
)->fetchColumn();

/* =====================================================================
 | 4. Elections & Open Election Counts
 * =================================================================== */
$elections = $pdo->query(
    "SELECT e.*,
            (SELECT COUNT(*) FROM voter_eligibility ve WHERE ve.election_id = e.id) AS eligible_count,
            (SELECT COUNT(*) FROM ballots b WHERE b.election_id = e.id AND b.status = 'submitted') AS voted_count,
            (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id) AS candidate_count
       FROM elections e
      ORDER BY FIELD(e.status, 'open', 'scheduled', 'draft', 'closed', 'archived'), e.start_datetime DESC"
)->fetchAll();

$openElectionsCount = 0;
$statusCounts = ['open' => 0, 'scheduled' => 0, 'draft' => 0, 'closed' => 0, 'archived' => 0];

foreach ($elections as $election) {
    $effective = election_effective_status($election);
    $statusCounts[$effective] = ($statusCounts[$effective] ?? 0) + 1;
    if ($effective === 'open') {
        $openElectionsCount++;
    }
}

/* =====================================================================
 | 5. Security Alerts (Rule-Based Evaluation + DB-Persisted Dismissal)
 * =================================================================== */
$criticalHijacks = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action = 'session_hijack_attempt' AND created_at >= (NOW() - INTERVAL 24 HOUR)"
)->fetchColumn();

$criticalFailed24h = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action = 'login_failure' AND created_at >= (NOW() - INTERVAL 24 HOUR)"
)->fetchColumn();

$criticalUnauth24h = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action = 'unauthorized_access' AND created_at >= (NOW() - INTERVAL 24 HOUR)"
)->fetchColumn();

$rawAlerts = [];

if ($criticalFailed24h >= 5) {
    $rawAlerts[] = [
        'key'      => 'repeated_failed_auth',
        'type'     => 'danger',
        'severity' => 'High',
        'icon'     => 'ban',
        'title'    => sv_t('Repeated Failed Authentication'),
        'text'     => sv_t('%d failed sign-in attempts detected in the last 24 hours.', $criticalFailed24h),
        'href'     => 'admin/audit-logs.php?log_action=login_failure',
        'label'    => sv_t('Review in Audit Log'),
    ];
} elseif ($failedLoginsCount > 0) {
    $rawAlerts[] = [
        'key'      => 'auth_warning',
        'type'     => 'warning',
        'severity' => 'Low',
        'icon'     => 'ban',
        'title'    => sv_t('Authentication Warning'),
        'text'     => sv_t(
            $failedLoginsCount === 1 ? '%d failed sign-in attempt recorded in %s.' : '%d failed sign-in attempts recorded in %s.',
            $failedLoginsCount,
            strtolower($rangeLabel)
        ),
        'href'     => 'admin/audit-logs.php?log_action=login_failure',
        'label'    => sv_t('Review attempts'),
    ];
}

if ($unauthorizedAccessCount > 0) {
    $rawAlerts[] = [
        'key'      => 'unauthorized_access_blocked',
        'type'     => 'danger',
        'severity' => 'High',
        'icon'     => 'shield',
        'title'    => sv_t('Unauthorized Access Attempt Blocked'),
        'text'     => sv_t(
            $unauthorizedAccessCount === 1 ? '%d unauthorized request was blocked by role authorization guards.' : '%d unauthorized requests were blocked by role authorization guards.',
            $unauthorizedAccessCount
        ),
        'href'     => 'admin/audit-logs.php?log_action=unauthorized_access',
        'label'    => sv_t('Inspect violations'),
    ];
}

if ($duplicateVoteBlocks > 0) {
    $rawAlerts[] = [
        'key'      => 'duplicate_vote_prevented',
        'type'     => 'warning',
        'severity' => 'Medium',
        'icon'     => 'alert-triangle',
        'title'    => sv_t('Duplicate Voting Attempt Prevented'),
        'text'     => sv_t(
            $duplicateVoteBlocks === 1 ? '%d duplicate ballot attempt was rejected by 1-Person-1-Vote constraint.' : '%d duplicate ballot attempts were rejected by 1-Person-1-Vote constraint.',
            $duplicateVoteBlocks
        ),
        'href'     => 'admin/audit-logs.php?log_action=ballot_rejected',
        'label'    => sv_t('View in Audit Log'),
    ];
}

if ($blockedUsers > 0) {
    $rawAlerts[] = [
        'key'      => 'restricted_account_status',
        'type'     => 'info',
        'severity' => 'Informational',
        'icon'     => 'user',
        'title'    => sv_t('Restricted Account Status'),
        'text'     => sv_t(
            $blockedUsers === 1 ? '%d voter account is currently deactivated or blocked.' : '%d voter accounts are currently deactivated or blocked.',
            $blockedUsers
        ),
        'href'     => 'admin/voters.php?status=blocked',
        'label'    => sv_t('Manage accounts'),
    ];
}

$drafts = array_filter($elections, static fn(array $e): bool => $e['status'] === 'draft');
if ($drafts !== []) {
    $rawAlerts[] = [
        'key'      => 'draft_elections_pending',
        'type'     => 'warning',
        'severity' => 'Low',
        'icon'     => 'file',
        'title'    => sv_t('Draft Elections Pending'),
        'text'     => sv_t(
            count($drafts) === 1 ? '%d election remains in draft mode.' : '%d elections remain in draft mode.',
            count($drafts)
        ),
        'href'     => 'admin/elections.php?status=draft',
        'label'    => sv_t('Open setup'),
    ];
}

$noCandidates = array_filter(
    $elections,
    static fn(array $e): bool => (int) $e['candidate_count'] === 0
        && in_array(election_effective_status($e), ['open', 'scheduled'], true)
);
foreach ($noCandidates as $ncElection) {
    $rawAlerts[] = [
        'key'      => 'candidate_missing_' . (int) $ncElection['id'],
        'type'     => 'danger',
        'severity' => 'High',
        'icon'     => 'alert-triangle',
        'title'    => sv_t('Candidate Population Missing'),
        'text'     => sv_t('"%s" is published without active candidates.', $ncElection['title']),
        'href'     => 'admin/candidates.php?election_id=' . (int) $ncElection['id'],
        'label'    => sv_t('Add candidates'),
    ];
}

// Map against database-persisted dismissals
$dismissedMap    = sv_get_dismissed_alerts($pdo);
$resolvedHistory = sv_get_resolved_alerts_list($pdo);

$alerts         = [];
$resolvedAlerts = [];

foreach ($rawAlerts as $a) {
    if (isset($dismissedMap[$a['key']])) {
        $meta = $dismissedMap[$a['key']];
        $resolvedAlerts[] = array_merge($a, [
            'dismissed_id' => (int) $meta['id'],
            'dismissed_at' => $meta['dismissed_at'],
            'dismissed_by' => $meta['username'] ?? 'admin',
            'reason'       => $meta['reason'] ?? '',
        ]);
    } else {
        $alerts[] = $a;
    }
}

/* =====================================================================
 | 6. Overall Security Status Indicator Logic
 * =================================================================== */
$hasActiveCriticalAlert = false;
$hasActiveWarningAlert  = false;
foreach ($alerts as $al) {
    if ($al['type'] === 'danger') {
        $hasActiveCriticalAlert = true;
    } elseif ($al['type'] === 'warning') {
        $hasActiveWarningAlert = true;
    }
}

if ($hasActiveCriticalAlert) {
    $systemSecurityStatus = 'CRITICAL';
    $systemStatusClass    = 'critical';
    $systemStatusIcon     = 'ban';
    $systemStatusTitle    = sv_t('CRITICAL SECURITY ALERT');
    $systemStatusDesc     = sv_t('High-severity security events or repeated unauthorized access attempts require immediate review.');
} elseif ($hasActiveWarningAlert) {
    $systemSecurityStatus = 'WARNING';
    $systemStatusClass    = 'warning';
    $systemStatusIcon     = 'alert-triangle';
    $systemStatusTitle    = sv_t('SECURITY WARNING');
    $systemStatusDesc     = sv_t('Active authentication warnings or security incidents detected within the monitoring period.');
} else {
    $systemSecurityStatus = 'CLEAR';
    $systemStatusClass    = 'secure';
    $systemStatusIcon     = 'shield-check';
    $systemStatusTitle    = sv_t('NO OPEN RULE ALERTS');
    $systemStatusDesc     = sv_t('No rule-based alerts are currently open. Review the event log for the full activity record.');
}

/* =====================================================================
 | 7. Authentication Activity & Failed Logins Grouping
 * =================================================================== */
$authLogouts = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action IN ('logout', 'session_idle_timeout', 'session_deactivated') AND created_at >= {$sinceSql}"
)->fetchColumn();

$authThrottled = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action IN ('login_throttled', 'login_blocked') AND created_at >= {$sinceSql}"
)->fetchColumn();

$totalAuthAttempts = $successfulLoginsCount + $failedLoginsCount;
$authSuccessRate   = $totalAuthAttempts > 0 ? percent($successfulLoginsCount, $totalAuthAttempts) : null;

// Recent successful authentications
$recentLogins = $pdo->query(
    "SELECT a.id, a.user_id, a.ip_address, a.created_at, u.username, u.first_name, u.last_name, u.role
       FROM audit_logs a
       JOIN users u ON u.id = a.user_id
      WHERE a.action IN ('login_success', 'google_login_success')
      ORDER BY a.created_at DESC
      LIMIT 4"
)->fetchAll();

// Failed login monitoring breakdown grouped by username & IP
$failedLoginAttempts = $pdo->query(
    "SELECT
       COALESCE(u.username, SUBSTRING_INDEX(SUBSTRING_INDEX(a.description, '\"', 2), '\"', -1)) AS attempt_user,
       a.ip_address,
       a.user_agent,
       COUNT(*) AS attempt_count,
       MAX(a.created_at) AS latest_attempt,
       COALESCE(u.status, 'unregistered') AS account_status
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.user_id
    WHERE a.action = 'login_failure'
      AND a.created_at >= {$sinceSql}
    GROUP BY attempt_user, a.ip_address
    ORDER BY attempt_count DESC, latest_attempt DESC
    LIMIT 5"
)->fetchAll();

/* =====================================================================
 | 8. Unauthorized Access Violations (Blocked by RBAC / CSRF)
 * =================================================================== */
$unauthorizedEvents = $pdo->query(
    "SELECT a.id, a.user_id, a.action, a.description, a.ip_address, a.user_agent, a.created_at,
            u.username, u.role
       FROM audit_logs a
       LEFT JOIN users u ON u.id = a.user_id
      WHERE a.action IN ('unauthorized_access', 'csrf_failure', 'session_hijack_attempt')
        AND a.created_at >= {$sinceSql}
      ORDER BY a.created_at DESC
      LIMIT 5"
)->fetchAll();

/* =====================================================================
 | 9. Recent Security Audit Events
 * =================================================================== */
$recentSecurityLogs = $pdo->query(
    'SELECT a.id, a.action, a.description, a.ip_address, a.user_agent, a.created_at,
            u.username, u.first_name, u.last_name, u.role
       FROM audit_logs a
       LEFT JOIN users u ON u.id = a.user_id
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT 10'
)->fetchAll();

/* =====================================================================
 | 10. Security Event Chart Data (Aggregated from DB)
 * =================================================================== */
$chartBuckets = [];
if ($range === '24h') {
    // 12 2-hour buckets over past 24 hours
    for ($i = 11; $i >= 0; $i--) {
        $hour = (int) (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('G');
        $bucketStart = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))
            ->setTime((int) (floor($hour / 2) * 2), 0)
            ->modify('-' . ($i * 2) . ' hours');
        $label = $bucketStart->format('H:00');
        $chartBuckets[] = [
            'label'     => $label,
            'from'      => $bucketStart->format('Y-m-d H:i:s'),
            'to'        => $bucketStart->modify('+2 hours')->modify('-1 second')->format('Y-m-d H:i:s'),
            'success'   => 0,
            'failed'    => 0,
            'blocked'   => 0,
            'ballots'   => 0,
        ];
    }
} elseif ($range === '7d') {
    // 7 daily buckets
    for ($i = 6; $i >= 0; $i--) {
        $dt = new DateTimeImmutable("-{$i} days", new DateTimeZone(APP_TIMEZONE));
        $chartBuckets[] = [
            'label'     => $dt->format('M j'),
            'from'      => $dt->format('Y-m-d 00:00:00'),
            'to'        => $dt->format('Y-m-d 23:59:59'),
            'success'   => 0,
            'failed'    => 0,
            'blocked'   => 0,
            'ballots'   => 0,
        ];
    }
} elseif ($range === '30d') {
    // 10 3-day buckets
    for ($i = 9; $i >= 0; $i--) {
        $daysBack = $i * 3;
        $dt = new DateTimeImmutable("-{$daysBack} days", new DateTimeZone(APP_TIMEZONE));
        $chartBuckets[] = [
            'label'     => $dt->format('M j'),
            'from'      => $dt->modify('-2 days')->format('Y-m-d 00:00:00'),
            'to'        => $dt->format('Y-m-d 23:59:59'),
            'success'   => 0,
            'failed'    => 0,
            'blocked'   => 0,
            'ballots'   => 0,
        ];
    }
} else {
    // 6 monthly buckets for All Time
    for ($i = 5; $i >= 0; $i--) {
        $dt = new DateTimeImmutable("-{$i} months", new DateTimeZone(APP_TIMEZONE));
        $chartBuckets[] = [
            'label'     => $dt->format('M Y'),
            'from'      => $dt->format('Y-m-01 00:00:00'),
            'to'        => $dt->format('Y-m-t 23:59:59'),
            'success'   => 0,
            'failed'    => 0,
            'blocked'   => 0,
            'ballots'   => 0,
        ];
    }
}

// Populate buckets from audit_logs
$bucketLogsStmt = $pdo->prepare(
    "SELECT action, created_at FROM audit_logs WHERE created_at >= :startTime"
);
$bucketLogsStmt->execute([':startTime' => $chartBuckets[0]['from']]);
$rawChartLogs = $bucketLogsStmt->fetchAll();

foreach ($rawChartLogs as $rLog) {
    $logTime = $rLog['created_at'];
    $action  = (string) $rLog['action'];
    foreach ($chartBuckets as &$bucket) {
        if ($logTime >= $bucket['from'] && $logTime <= $bucket['to']) {
            if (in_array($action, ['login_success', 'google_login_success'], true)) {
                $bucket['success']++;
            } elseif (in_array($action, ['login_failure', 'password_change_failed'], true)) {
                $bucket['failed']++;
            } elseif (in_array($action, ['unauthorized_access', 'csrf_failure', 'session_hijack_attempt', 'login_throttled', 'login_blocked', 'ballot_rejected'], true)) {
                $bucket['blocked']++;
            } elseif (in_array($action, ['ballot_submitted', 'direct_ballot_submitted'], true)) {
                $bucket['ballots']++;
            }
            break;
        }
    }
    unset($bucket);
}

// Calculate max bucket value for SVG scaling
$maxChartVal = 1;
foreach ($chartBuckets as $b) {
    $sum = $b['success'] + $b['failed'] + $b['blocked'] + $b['ballots'];
    if ($sum > $maxChartVal) {
        $maxChartVal = $sum;
    }
}

/* =====================================================================
 | 11. Header & Breadcrumbs
 * =================================================================== */
$pageTitle   = sv_t('Operations center');
$activeNav   = 'dashboard.php';
$bodyClass   = 'page-admin-dashboard';
$breadcrumb   = [];
$pageActions  = '<a class="btn btn--secondary btn--sm" href="' . e(url('admin/dashboard.php' . query_with())) . '">'
    . sv_icon('refresh', 16, 'btn__icon') . '<span>' . sv_t('Refresh telemetry') . '</span></a>';

require __DIR__ . '/../includes/header.php';
?>

<!-- Command view: alert state, scope, and immediate investigation paths. -->
<section class="soc-command soc-command--<?= e($systemStatusClass) ?>" aria-labelledby="soc-command-heading">
  <div class="soc-command__main">
    <div class="soc-command__eyebrow"><span class="soc-command__signal" aria-hidden="true"></span> SECUREVOTE / <?= sv_t('OPERATIONS INTELLIGENCE') ?></div>
    <h2 id="soc-command-heading"><?= sv_t('Election operations') ?> <span><?= sv_t('at a glance.') ?></span></h2>
    <p><?= sv_t('Investigate authentication, ballot integrity, and election activity from the audit trail and current election records.') ?></p>
    <div class="soc-command__state" role="status">
      <span class="soc-command__state-icon"><?= sv_icon($systemStatusIcon, 24) ?></span>
      <div><strong><?= e($systemStatusTitle) ?></strong><span><?= e($systemStatusDesc) ?></span></div>
    </div>
    <div class="soc-command__actions">
      <a class="btn btn--primary" href="#alerts-heading"><?= sv_t('Review %d open alert(s)', count($alerts)) ?> <?= sv_icon('arrow-right', 16) ?></a>
      <a class="btn btn--secondary" href="<?= e(url('admin/audit-logs.php')) ?>"><?= sv_t('Open audit log') ?></a>
      <a class="btn btn--secondary" href="<?= e(url('admin/traffic.php')) ?>"><?= sv_t('Review site traffic') ?></a>
    </div>
  </div>
  <div class="soc-command__aside" aria-label="<?= sv_t('Monitoring context') ?>">
    <div class="soc-command__aside-head"><span><?= sv_t('MONITORING CONTEXT') ?></span><?= sv_icon('audit', 18) ?></div>
    <dl>
      <div><dt><?= sv_t('Open alerts') ?></dt><dd><?= e((string) count($alerts)) ?></dd></div>
      <div><dt><?= sv_t('Audit events in view') ?></dt><dd><?= e((string) $totalSecurityEventsCount) ?></dd></div>
      <div><dt><?= sv_t('Open elections') ?></dt><dd><?= e((string) $openElectionsCount) ?></dd></div>
      <div><dt><?= sv_t('Database response') ?></dt><dd><?= $dbOk ? e((string) $dbLatency) . ' ms' : sv_t('Unavailable') ?></dd></div>
      <div><dt><?= sv_t('Snapshot time') ?></dt><dd><?= e((new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('M j, g:i A')) ?></dd></div>
    </dl>
    <p><?= sv_t('Snapshot of the database at page load. Refresh to update.') ?></p>
  </div>
</section>

<div class="soc-controlbar" aria-label="<?= sv_t('Monitoring controls') ?>">
  <div class="soc-controlbar__label"><span class="soc-controlbar__dot" aria-hidden="true"></span> <?= sv_t('Event window') ?> <strong><?= e($rangeLabel) ?></strong></div>
  <nav class="soc-range-pills" aria-label="<?= sv_t('Monitoring time range') ?>">
    <?php foreach ($rangeIntervals as $key => $opt): ?>
      <a class="soc-range-pill <?= $range === $key ? 'is-active' : '' ?>"
         href="<?= e(url('admin/dashboard.php?range=' . $key)) ?>"
         <?= $range === $key ? 'aria-current="page"' : '' ?>><?= e($opt['short']) ?></a>
    <?php endforeach; ?>
  </nav>
</div>

<nav class="soc-jumpbar" aria-label="<?= sv_t('Operations sections') ?>">
  <a href="#signals-heading"><?= sv_t('Signals') ?></a>
  <a href="#health-heading"><?= sv_t('Controls') ?></a>
  <a href="#alerts-heading"><?= sv_t('Alerts') ?> <span><?= e((string) count($alerts)) ?></span></a>
  <a href="#auth-heading"><?= sv_t('Identity') ?></a>
  <a href="#integrity-heading"><?= sv_t('Ballots') ?></a>
  <a href="#live-feed-heading"><?= sv_t('Event stream') ?></a>
  <a href="#elections-heading"><?= sv_t('Elections') ?></a>
</nav>

<h2 class="soc-section-label" id="signals-heading"><span>01 / <?= sv_t('SIGNALS') ?></span> <?= sv_t('Snapshot metrics') ?> <small><?= sv_t('Audit-based counts for %s unless noted.', strtolower($rangeLabel)) ?></small></h2>

<!-- Top Security Summary Cards (8 KPI Grid) -->
<section class="soc-stat-grid" aria-label="<?= sv_t('Security statistics summary') ?>">
  <!-- 1. Security Status -->
  <div class="soc-stat-card">
    <div class="soc-stat-card__top">
      <span class="soc-stat-card__label"><?= sv_t('Security Status') ?></span>
      <span class="soc-stat-card__icon soc-stat-card__icon--<?= e($systemStatusClass === 'secure' ? 'teal' : ($systemStatusClass === 'warning' ? 'warning' : 'danger')) ?>">
        <?= sv_icon($systemStatusIcon, 18) ?>
      </span>
    </div>
    <p class="soc-stat-card__value"><?= e($systemSecurityStatus) ?></p>
    <p class="soc-stat-card__meta"><?= e($systemStatusClass === 'secure' ? sv_t('No open rule alerts') : sv_t('Requires admin attention')) ?></p>
  </div>

  <!-- 2. Recently Observed Actors -->
  <div class="soc-stat-card">
    <div class="soc-stat-card__top">
      <span class="soc-stat-card__label"><?= sv_t('Recent Actors') ?></span>
      <span class="soc-stat-card__icon soc-stat-card__icon--blue"><?= sv_icon('fingerprint', 18) ?></span>
    </div>
    <p class="soc-stat-card__value"><?= e((string) $activeSessions) ?></p>
    <p class="soc-stat-card__meta"><?= sv_t('Unique account or IP in audit events, 30 min') ?></p>
  </div>

  <!-- 3. Failed Login Attempts -->
  <div class="soc-stat-card">
    <div class="soc-stat-card__top">
      <span class="soc-stat-card__label"><?= sv_t('Failed Logins') ?></span>
      <span class="soc-stat-card__icon soc-stat-card__icon--warning"><?= sv_icon('ban', 18) ?></span>
    </div>
    <p class="soc-stat-card__value"><?= e((string) $failedLoginsCount) ?></p>
    <p class="soc-stat-card__meta"><?= e($rangeLabel) ?></p>
  </div>

  <!-- 4. Unauthorized Access Attempts -->
  <div class="soc-stat-card">
    <div class="soc-stat-card__top">
      <span class="soc-stat-card__label"><?= sv_t('Unauthorized Access') ?></span>
      <span class="soc-stat-card__icon soc-stat-card__icon--danger"><?= sv_icon('shield', 18) ?></span>
    </div>
    <p class="soc-stat-card__value"><?= e((string) $unauthorizedAccessCount) ?></p>
    <p class="soc-stat-card__meta"><?= sv_t('Blocked by RBAC / CSRF') ?></p>
  </div>

  <!-- 5. Votes Submitted -->
  <div class="soc-stat-card">
    <div class="soc-stat-card__top">
      <span class="soc-stat-card__label"><?= sv_t('Votes Submitted') ?></span>
      <span class="soc-stat-card__icon soc-stat-card__icon--teal"><?= sv_icon('vote', 18) ?></span>
    </div>
    <p class="soc-stat-card__value"><?= e((string) $totalVotesSubmitted) ?></p>
    <p class="soc-stat-card__meta"><?= sv_t('Submitted ballots in database, all time') ?></p>
  </div>

  <!-- 6. Open Elections -->
  <div class="soc-stat-card">
    <div class="soc-stat-card__top">
      <span class="soc-stat-card__label"><?= sv_t('Open Elections') ?></span>
      <span class="soc-stat-card__icon soc-stat-card__icon--success"><?= sv_icon('check-circle', 18) ?></span>
    </div>
    <p class="soc-stat-card__value"><?= e((string) $openElectionsCount) ?></p>
    <p class="soc-stat-card__meta"><?= sv_t('%d total election(s)', $totalElections) ?></p>
  </div>

  <!-- 7. Security Events -->
  <div class="soc-stat-card">
    <div class="soc-stat-card__top">
      <span class="soc-stat-card__label"><?= sv_t('Audit Events') ?></span>
      <span class="soc-stat-card__icon soc-stat-card__icon--navy"><?= sv_icon('audit', 18) ?></span>
    </div>
    <p class="soc-stat-card__value"><?= e((string) $totalSecurityEventsCount) ?></p>
    <p class="soc-stat-card__meta"><?= sv_t('Recorded in %s', strtolower($rangeLabel)) ?></p>
  </div>

  <!-- 8. Blocked Requests -->
  <div class="soc-stat-card">
    <div class="soc-stat-card__top">
      <span class="soc-stat-card__label"><?= sv_t('Blocked Requests') ?></span>
      <span class="soc-stat-card__icon soc-stat-card__icon--danger"><?= sv_icon('ban', 18) ?></span>
    </div>
    <p class="soc-stat-card__value"><?= e((string) $blockedRequestsCount) ?></p>
    <p class="soc-stat-card__meta"><?= sv_t('Throttled / Access Refused') ?></p>
  </div>
</section>

<p class="soc-group-label">02 / <?= sv_t('CONTROL INVENTORY') ?></p>
<!-- System Health Matrix -->
<section class="card mb-6" aria-labelledby="health-heading">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title" id="health-heading"><?= sv_t('Control & Service Inventory') ?></h2>
      <p class="card__subtitle"><?= sv_t('Database response is measured now. Other rows describe configured application controls or observed records.') ?></p>
    </div>
    <span class="badge badge--neutral"><?= sv_icon('clock', 14) ?><?= sv_t('Snapshot at page load') ?></span>
  </div>
  <div class="card__body">
    <div class="soc-health-grid">
      <?php foreach ($healthChecks as $hc): ?>
        <div class="soc-health-item">
          <div class="soc-health-item__top">
            <div class="soc-health-item__header">
              <span class="soc-health-dot <?= $hc['ok'] ? '' : 'soc-health-dot--warning' ?>" aria-hidden="true"></span>
              <span class="soc-health-item__title"><?= e($hc['label']) ?></span>
            </div>
            <span class="badge badge--<?= $hc['ok'] ? 'teal' : 'warning' ?>">
              <?= e($hc['status']) ?>
            </span>
          </div>
          <div class="soc-health-item__meta">
            <?= e($hc['meta']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<p class="soc-group-label">03 / <?= sv_t('DETECTION & RESPONSE') ?></p>
<!-- Security Activity Chart & Active Alerts Row -->
<div class="grid-2 mb-6">
  <!-- Security Event Chart -->
  <section class="card" aria-labelledby="chart-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="chart-heading"><?= sv_t('Event Activity') ?></h2>
        <p class="card__subtitle"><?= sv_t('Stacked audit events by time bucket') ?><?= $range === 'all' ? ' (' . sv_t('last six months') . ')' : ' (' . e($rangeLabel) . ')' ?>.</p>
      </div>
      <span class="badge badge--neutral"><?= sv_icon('clock', 14) ?><?= e($rangeLabel) ?></span>
    </div>
    <div class="card__body">
      <div class="soc-chart-wrap">
        <?php
          $svgWidth  = 600;
          $svgHeight = 180;
          $padLeft   = 35;
          $padRight  = 15;
          $padTop    = 20;
          $padBottom = 30;
          $chartW    = $svgWidth - $padLeft - $padRight;
          $chartH    = $svgHeight - $padTop - $padBottom;
          $bCount    = count($chartBuckets);
          $barGroupW = $bCount > 0 ? $chartW / $bCount : $chartW;
          $barWidth  = max(4, min(18, $barGroupW * 0.65));
        ?>
        <svg class="soc-chart-svg" viewBox="0 0 <?= $svgWidth ?> <?= $svgHeight ?>" role="img" aria-label="<?= sv_t('Security events distribution chart') ?>">
          <!-- Horizontal Grid Lines -->
          <?php for ($g = 0; $g <= 3; $g++): ?>
            <?php
              $gy = $padTop + ($chartH * ($g / 3));
              $gval = round($maxChartVal * (1 - ($g / 3)));
            ?>
            <line x1="<?= $padLeft ?>" y1="<?= $gy ?>" x2="<?= $svgWidth - $padRight ?>" y2="<?= $gy ?>" stroke="var(--border)" stroke-width="1" stroke-dasharray="3 3"/>
            <text x="<?= $padLeft - 6 ?>" y="<?= $gy + 4 ?>" font-size="10" fill="var(--text-muted)" text-anchor="end" font-family="var(--font-sans)"><?= $gval ?></text>
          <?php endfor; ?>

          <!-- Bars -->
          <?php foreach ($chartBuckets as $idx => $b): ?>
            <?php
              $bx = $padLeft + ($idx * $barGroupW) + (($barGroupW - $barWidth) / 2);
              $totalB = $b['success'] + $b['failed'] + $b['blocked'] + $b['ballots'];
              $stackY = $padTop + $chartH;
              $segments = [
                  ['count' => $b['success'], 'color' => 'var(--blue)'],
                  ['count' => $b['ballots'], 'color' => 'var(--teal)'],
                  ['count' => $b['failed'], 'color' => 'var(--warning)'],
                  ['count' => $b['blocked'], 'color' => 'var(--danger)'],
              ];
            ?>
            <g class="soc-chart-bar" tabindex="0" role="graphics-symbol" aria-label="<?= e($b['label']) ?>: <?= $totalB ?> events">
              <title><?= e($b['label']) ?>: <?= $b['success'] ?> logins, <?= $b['failed'] ?> failed, <?= $b['blocked'] ?> blocked, <?= $b['ballots'] ?> ballots</title>
              <?php foreach ($segments as $segment): ?>
                <?php if ($segment['count'] > 0): ?>
                  <?php $segmentH = ($segment['count'] / $maxChartVal) * $chartH; $stackY -= $segmentH; ?>
                  <rect x="<?= $bx ?>" y="<?= $stackY ?>" width="<?= $barWidth ?>" height="<?= $segmentH ?>" fill="<?= $segment['color'] ?>"/>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if ($idx % max(1, (int) ceil($bCount / 8)) === 0 || $idx === $bCount - 1): ?>
                <text x="<?= $bx + ($barWidth / 2) ?>" y="<?= $svgHeight - 10 ?>" font-size="10" fill="var(--text-muted)" text-anchor="middle" font-family="var(--font-sans)"><?= e($b['label']) ?></text>
              <?php endif; ?>
            </g>
          <?php endforeach; ?>
        </svg>
      </div>

      <div class="soc-legend">
        <span class="soc-legend-item"><span class="soc-legend-swatch" style="background:var(--blue)"></span> <?= sv_t('Successful Sign-ins') ?></span>
        <span class="soc-legend-item"><span class="soc-legend-swatch" style="background:var(--warning)"></span> <?= sv_t('Failed Sign-ins') ?></span>
        <span class="soc-legend-item"><span class="soc-legend-swatch" style="background:var(--danger)"></span> <?= sv_t('Blocked / Security Warnings') ?></span>
        <span class="soc-legend-item"><span class="soc-legend-swatch" style="background:var(--teal)"></span> <?= sv_t('Ballots Submitted') ?></span>
      </div>
    </div>
  </section>

  <!-- Security Alerts & Incidents Area with DB Persistence -->
  <section class="card" aria-labelledby="alerts-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="alerts-heading"><?= sv_t('Security Alerts & Incidents') ?></h2>
        <p class="card__subtitle"><?= sv_t('Rule evaluations from audit and election records. Resolutions are recorded in the database.') ?></p>
      </div>
      <div class="row row--compact" style="gap:var(--sp-2);">
        <span class="badge badge--<?= count($alerts) > 0 ? ($alerts[0]['type'] === 'danger' ? 'danger' : 'warning') : 'success' ?>">
          <?= sv_icon(count($alerts) > 0 ? 'bell' : 'check-circle', 14) ?>
          <?= e((string) count($alerts)) ?> <?= sv_t('active') ?>
        </span>
        <?php if ($resolvedHistory !== []): ?>
          <span class="badge badge--neutral">
            <?= sv_icon('check', 12) ?>
            <?= e((string) count($resolvedHistory)) ?> <?= sv_t('resolved') ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card__body stack-sm">
      <!-- Tabs for Active vs Resolved -->
      <div class="tabs mb-4" role="tablist" aria-label="<?= sv_t('Security alerts views') ?>">
        <button type="button" class="tab <?= $activeTab === 'active' ? 'is-active' : '' ?>" id="alert-tab-active-btn" role="tab" aria-selected="<?= $activeTab === 'active' ? 'true' : 'false' ?>" onclick="switchAlertTab('active')">
          <?= sv_icon('bell', 14) ?> <?= sv_t('Active Alerts (%d)', count($alerts)) ?>
        </button>
        <button type="button" class="tab <?= $activeTab === 'resolved' ? 'is-active' : '' ?>" id="alert-tab-resolved-btn" role="tab" aria-selected="<?= $activeTab === 'resolved' ? 'true' : 'false' ?>" onclick="switchAlertTab('resolved')">
          <?= sv_icon('check-circle', 14) ?> <?= sv_t('Resolved History (%d)', count($resolvedHistory)) ?>
        </button>
      </div>

      <!-- Tab 1: Active Alerts Panel -->
      <div id="alert-panel-active" style="display: <?= $activeTab === 'active' ? 'block' : 'none' ?>;">
        <?php if (count($alerts) > 1): ?>
          <div class="row mb-3" style="justify-content: flex-end;">
            <form method="post" action="<?= e(post_url('admin/dashboard.php' . ($range !== '24h' ? '?range=' . urlencode($range) : ''))) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="resolve_all_alerts">
              <input type="hidden" name="alerts_json" value="<?= e(json_encode($alerts)) ?>">
              <button type="submit" class="btn btn--sm btn--outline" onclick="return confirm('<?= sv_t('Mark all active security alerts as resolved?') ?>');">
                <?= sv_icon('check', 14) ?> <?= sv_t('Resolve All Active Alerts') ?>
              </button>
            </form>
          </div>
        <?php endif; ?>

        <?php if ($alerts === []): ?>
          <div class="empty-state empty-state--inline">
            <span class="empty-state__icon text-success"><?= sv_icon('shield-check', 26) ?></span>
            <p class="empty-state__title"><?= sv_t('No Active Security Alerts') ?></p>
            <p class="empty-state__text"><?= sv_t('No rule-based alerts are currently open. Continue reviewing the event stream for context.') ?></p>
            <?php if ($resolvedHistory !== []): ?>
              <p class="text-xs text-muted mt-2">
                <?= sv_t('%d alert(s) have been resolved by administrators.', count($resolvedHistory)) ?>
                <a href="javascript:void(0)" onclick="switchAlertTab('resolved')" class="font-semibold text-main"><?= sv_t('View Resolved History') ?> &rarr;</a>
              </p>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="stack-sm">
            <?php foreach ($alerts as $alert): ?>
              <div class="alert alert--<?= e($alert['type']) ?>">
                <span class="alert__icon" aria-hidden="true"><?= sv_icon($alert['icon'], 18) ?></span>
                <div class="alert__text">
                  <div class="row row--compact" style="gap:var(--sp-2); margin-bottom: 2px;">
                    <strong><?= e($alert['title']) ?></strong>
                    <span class="badge badge--<?= $alert['type'] === 'danger' ? 'danger' : ($alert['type'] === 'warning' ? 'warning' : 'info') ?> text-xs">
                      <?= e($alert['severity']) ?>
                    </span>
                  </div>
                  <div><?= e($alert['text']) ?></div>
                </div>
                <div class="alert__actions">
                  <a class="btn btn--sm btn--secondary" href="<?= e(url($alert['href'])) ?>"><?= e($alert['label']) ?></a>
                  <button type="button" class="btn btn--sm btn--success" onclick="openResolveModal('<?= e(addslashes($alert['key'])) ?>', '<?= e(addslashes($alert['title'])) ?>', '<?= e(addslashes($alert['severity'])) ?>', '<?= e(addslashes($alert['text'])) ?>')" title="<?= sv_t('Mark this alert as resolved') ?>">
                    <?= sv_icon('check', 14) ?> <?= sv_t('Mark Resolved') ?>
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Tab 2: Resolved Alerts History Panel -->
      <div id="alert-panel-resolved" style="display: <?= $activeTab === 'resolved' ? 'block' : 'none' ?>;">
        <?php if ($resolvedHistory === []): ?>
          <div class="empty-state empty-state--inline">
            <span class="empty-state__icon text-muted"><?= sv_icon('archive', 26) ?></span>
            <p class="empty-state__title"><?= sv_t('No Resolved Alerts') ?></p>
            <p class="empty-state__text"><?= sv_t('Acknowledged security alerts and resolution records will be saved here persistently.') ?></p>
          </div>
        <?php else: ?>
          <div class="stack-sm">
            <div class="row mb-2" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--sp-2);">
              <span class="text-xs text-muted"><?= sv_t('Showing %d alert(s) resolved in database.', count($resolvedHistory)) ?></span>
              <form method="post" action="<?= e(post_url('admin/dashboard.php' . ($range !== '24h' ? '?range=' . urlencode($range) . '&tab=resolved' : '?tab=resolved'))) ?>" onsubmit="return confirm('<?= sv_t('Restore all resolved alerts back to active status?') ?>');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore_all_alerts">
                <button type="submit" class="btn btn--sm btn--ghost text-xs">
                  <?= sv_icon('refresh', 13) ?> <?= sv_t('Restore All to Active') ?>
                </button>
              </form>
            </div>
            <div class="table-wrap">
              <table class="table table--compact">
                <thead>
                  <tr>
                    <th scope="col"><?= sv_t('Incident') ?></th>
                    <th scope="col"><?= sv_t('Severity') ?></th>
                    <th scope="col"><?= sv_t('Resolved By') ?></th>
                    <th scope="col"><?= sv_t('Date Resolved') ?></th>
                    <th scope="col"><?= sv_t('Note') ?></th>
                    <th scope="col" style="text-align: right;"><?= sv_t('Action') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($resolvedHistory as $res): ?>
                    <tr>
                      <td>
                        <span class="table__cell-strong"><?= e($res['alert_title']) ?></span>
                        <span class="table__cell-sub mono text-xs"><?= e($res['alert_key']) ?></span>
                      </td>
                      <td><?= security_severity_badge($res['severity'] ?? 'Medium') ?></td>
                      <td>
                        <span class="font-medium"><?= e($res['username'] ?? 'admin') ?></span>
                      </td>
                      <td>
                        <span><?= e(relative_time($res['dismissed_at'])) ?></span>
                        <span class="table__cell-sub text-xs"><?= e(date('M j, g:i A', strtotime($res['dismissed_at']))) ?></span>
                      </td>
                      <td>
                        <span class="text-xs text-muted"><?= e($res['reason'] ?: sv_t('Acknowledged by admin')) ?></span>
                      </td>
                      <td style="text-align: right;">
                        <form method="post" action="<?= e(post_url('admin/dashboard.php' . ($range !== '24h' ? '?range=' . urlencode($range) . '&tab=resolved' : '?tab=resolved'))) ?>" class="d-inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="restore_alert">
                          <input type="hidden" name="dismissed_id" value="<?= (int) $res['id'] ?>">
                          <button type="submit" class="btn btn--sm btn--outline" title="<?= sv_t('Re-open') ?>">
                            <?= sv_icon('refresh', 13) ?> <?= sv_t('Re-open') ?>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<p class="soc-group-label">04 / <?= sv_t('IDENTITY ACTIVITY') ?></p>
<!-- Authentication Monitoring & Failed Login Threat Patterns Row -->
<div class="grid-2 mb-6">
  <!-- Authentication Activity Panel -->
  <section class="card" aria-labelledby="auth-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="auth-heading"><?= sv_t('Authentication Activity') ?></h2>
        <p class="card__subtitle"><?= sv_t('Identity validation telemetry & session lifecycle (%s).', e($rangeLabel)) ?></p>
      </div>
      <span class="badge badge--teal"><?= sv_icon('key', 14) ?><?= $authSuccessRate === null ? sv_t('No attempts') : sv_t('%s%% success', e((string) $authSuccessRate)) ?></span>
    </div>
    <div class="card__body">
      <div class="soc-metric-list mb-4">
        <div class="soc-metric-row">
          <span class="soc-metric-label"><?= sv_icon('check', 16, 'text-success') ?> <?= sv_t('Successful Sign-ins') ?></span>
          <span class="soc-metric-value text-success"><?= e((string) $successfulLoginsCount) ?></span>
        </div>
        <div class="soc-metric-row">
          <span class="soc-metric-label"><?= sv_icon('x', 16, 'text-warning') ?> <?= sv_t('Failed Sign-in Attempts') ?></span>
          <span class="soc-metric-value text-warning"><?= e((string) $failedLoginsCount) ?></span>
        </div>
        <div class="soc-metric-row">
          <span class="soc-metric-label"><?= sv_icon('logout', 16, 'text-muted') ?> <?= sv_t('Recorded Sign-outs / Idle Expirations') ?></span>
          <span class="soc-metric-value"><?= e((string) $authLogouts) ?></span>
        </div>
        <div class="soc-metric-row">
          <span class="soc-metric-label"><?= sv_icon('ban', 16, 'text-danger') ?> <?= sv_t('Throttled / Blocked Authentication Requests') ?></span>
          <span class="soc-metric-value text-danger"><?= e((string) $authThrottled) ?></span>
        </div>
      </div>

      <!-- Authentication Ratio Progress Bar -->
      <div class="progress mb-4" role="img" aria-label="<?= sv_t('Authentication success ratio: %s', $authSuccessRate === null ? sv_t('No attempts recorded') : e((string) $authSuccessRate) . '%') ?>">
        <span class="progress__bar progress__bar--winner" style="width: <?= e((string) min(100, $authSuccessRate ?? 0)) ?>%"></span>
      </div>

      <h3 class="card__title text-sm mb-3"><?= sv_t('Recent Authenticated Logins') ?></h3>
      <?php if ($recentLogins === []): ?>
        <p class="text-sm text-muted"><?= sv_t('No sign-in events logged recently.') ?></p>
      <?php else: ?>
        <ul class="soc-feed-list">
          <?php foreach ($recentLogins as $login): ?>
            <li class="soc-feed-item">
              <span class="soc-feed-time"><?= e(relative_time($login['created_at'])) ?></span>
              <span class="soc-feed-actor">
                <span class="soc-feed-user"><?= e($login['username']) ?></span>
                <span class="soc-feed-role">(<?= e((string) $login['role']) ?>)</span>
              </span>
              <span class="soc-feed-desc"><?= sv_t('Signed in successfully via bcrypt/OAuth') ?></span>
              <span class="soc-feed-ip"><?= e((string) ($login['ip_address'] ?? '127.0.0.1')) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <!-- Failed Login Monitoring & Threat Patterns -->
  <section class="card" aria-labelledby="failed-auth-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="failed-auth-heading"><?= sv_t('Failed Login Monitoring') ?></h2>
        <p class="card__subtitle"><?= sv_t('Repeated authentication failures and origin IPs flagged for review.') ?></p>
      </div>
      <a class="btn btn--sm btn--secondary" href="<?= e(url('admin/audit-logs.php?log_action=login_failure')) ?>"><?= sv_t('View all') ?></a>
    </div>
    <div class="card__body">
      <?php if ($failedLoginAttempts === []): ?>
        <div class="empty-state empty-state--inline">
          <span class="empty-state__icon text-success"><?= sv_icon('check-circle', 26) ?></span>
          <p class="empty-state__title"><?= sv_t('Zero Failed Sign-ins') ?></p>
          <p class="empty-state__text"><?= sv_t('No failed login attempts recorded in %s.', e(strtolower($rangeLabel))) ?></p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th scope="col"><?= sv_t('Target User') ?></th>
                <th scope="col"><?= sv_t('Attempts') ?></th>
                <th scope="col"><?= sv_t('Classification') ?></th>
                <th scope="col"><?= sv_t('Origin IP') ?></th>
                <th scope="col"><?= sv_t('Latest') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($failedLoginAttempts as $fla): ?>
                <?php
                  $attempts = (int) $fla['attempt_count'];
                  if ($attempts >= 5) {
                      $classification = sv_t('Suspicious Login Pattern');
                      $sev = 'High';
                  } elseif ($attempts >= 2) {
                      $classification = sv_t('Repeated Failed Authentication');
                      $sev = 'Medium';
                  } else {
                      $classification = sv_t('Single Authentication Failure');
                      $sev = 'Low';
                  }
                ?>
                <tr>
                  <td>
                    <span class="table__cell-strong"><?= e((string) $fla['attempt_user']) ?></span>
                    <span class="table__cell-sub"><?= sv_t('Account: %s', e((string) $fla['account_status'])) ?></span>
                  </td>
                  <td>
                    <span class="table__cell-strong text-danger"><?= e((string) $attempts) ?></span>
                  </td>
                  <td>
                    <?= security_severity_badge($sev) ?>
                    <span class="table__cell-sub"><?= e($classification) ?></span>
                  </td>
                  <td>
                    <span class="mono"><?= e((string) ($fla['ip_address'] ?? '—')) ?></span>
                  </td>
                  <td>
                    <span class="nowrap text-xs text-muted"><?= e(relative_time($fla['latest_attempt'])) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<p class="soc-group-label">05 / <?= sv_t('BALLOT & ACCESS INTEGRITY') ?></p>
<!-- Unauthorized Access & Voting Integrity Row -->
<div class="grid-2 mb-6">
  <!-- Unauthorized Access Attempts Panel -->
  <section class="card" aria-labelledby="unauth-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="unauth-heading"><?= sv_t('Unauthorized Access Attempts') ?></h2>
        <p class="card__subtitle"><?= sv_t('Role-based authorization (RBAC) and CSRF security boundary enforcement.') ?></p>
      </div>
      <a class="btn btn--sm btn--secondary" href="<?= e(url('admin/audit-logs.php?severity=high')) ?>"><?= sv_t('Security logs') ?></a>
    </div>
    <div class="card__body">
      <?php if ($unauthorizedEvents === []): ?>
        <div class="empty-state empty-state--inline">
          <span class="empty-state__icon text-success"><?= sv_icon('shield-check', 26) ?></span>
          <p class="empty-state__title"><?= sv_t('No blocked boundary events') ?></p>
          <p class="empty-state__text"><?= sv_t('No unauthorized page requests or CSRF token mismatches were recorded in %s.', e(strtolower($rangeLabel))) ?></p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th scope="col"><?= sv_t('Actor') ?></th>
                <th scope="col"><?= sv_t('Action / Target') ?></th>
                <th scope="col"><?= sv_t('Result') ?></th>
                <th scope="col"><?= sv_t('Severity') ?></th>
                <th scope="col"><?= sv_t('When') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($unauthorizedEvents as $ue): ?>
                <tr>
                  <td>
                    <span class="table__cell-strong"><?= e($ue['username'] ?? sv_t('Anonymous')) ?></span>
                    <span class="table__cell-sub"><?= e($ue['role'] ?? sv_t('Unauthenticated')) ?></span>
                  </td>
                  <td>
                    <span><?= e(str_limit($ue['description'], 60)) ?></span>
                    <span class="table__cell-sub mono"><?= e((string) ($ue['ip_address'] ?? '—')) ?></span>
                  </td>
                  <td>
                    <span class="badge badge--danger"><?= sv_icon('ban', 12) ?><?= sv_t('BLOCKED') ?></span>
                  </td>
                  <td>
                    <?= security_severity_badge('High') ?>
                  </td>
                  <td>
                    <span class="nowrap text-xs text-muted"><?= e(relative_time($ue['created_at'])) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Ballot submission and rejection records -->
  <section class="card" aria-labelledby="integrity-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="integrity-heading"><?= sv_t('Ballot Submissions & Rejections') ?></h2>
        <p class="card__subtitle"><?= sv_t('Submission counts and rejection events recorded by the voting workflow.') ?></p>
      </div>
      <a class="btn btn--sm btn--secondary" href="<?= e(url('admin/security-testing.php')) ?>"><?= sv_t('Open security checks') ?></a>
    </div>
    <div class="card__body">
      <div class="soc-metric-list mb-4">
        <div class="soc-metric-row">
          <span class="soc-metric-label"><?= sv_icon('check-circle', 16, 'text-success') ?> <?= sv_t('Submitted Ballots Recorded') ?></span>
          <span class="soc-metric-value text-success"><?= e((string) $totalVotesSubmitted) ?></span>
        </div>
        <div class="soc-metric-row">
          <span class="soc-metric-label"><?= sv_icon('ban', 16, 'text-warning') ?> <?= sv_t('Duplicate Vote Attempts Blocked') ?></span>
          <span class="soc-metric-value text-warning"><?= e((string) $duplicateVoteBlocks) ?></span>
        </div>
        <div class="soc-metric-row">
          <span class="soc-metric-label"><?= sv_icon('ban', 16, 'text-danger') ?> <?= sv_t('Invalid Ballot Requests Refused') ?></span>
          <span class="soc-metric-value text-danger"><?= e((string) $invalidBallotBlocks) ?></span>
        </div>
      </div>

      <!-- The dashboard reports observed events; detailed checks live in the verification suite. -->
      <div class="alert alert--info" style="background:var(--surface-2);border-color:var(--border);">
        <span class="alert__icon" aria-hidden="true"><?= sv_icon('shield', 18) ?></span>
        <div class="alert__text text-xs">
          <strong><?= sv_t('What this panel measures:') ?></strong> <?= sv_t('submitted ballot rows and recorded rejection events. It does not independently certify anonymity or detect every database integrity fault.') ?>
        </div>
      </div>
    </div>
  </section>
</div>

<p class="soc-group-label">06 / <?= sv_t('EVIDENCE STREAM') ?></p>
<!-- Recent Security Events Feed / Audit Timeline -->
<section class="card mb-6" aria-labelledby="live-feed-heading">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title" id="live-feed-heading"><?= sv_t('Recent Security Events Timeline') ?></h2>
      <p class="card__subtitle"><?= sv_t('Chronological audit stream of authentications, administrative actions, and security events.') ?></p>
    </div>
    <a class="btn btn--sm btn--secondary" href="<?= e(url('admin/audit-logs.php')) ?>"><?= sv_t('View full audit log') ?></a>
  </div>

  <?php if ($recentSecurityLogs === []): ?>
    <div class="card__body empty-state empty-state--inline">
      <span class="empty-state__icon"><?= sv_icon('audit', 24) ?></span>
      <p class="empty-state__title"><?= sv_t('No Audit Events Logged') ?></p>
      <p class="empty-state__text"><?= sv_t('System events appear here automatically as they occur.') ?></p>
    </div>
  <?php else: ?>
    <div class="card__body">
      <div class="table-wrap">
        <table class="table">
          <caption class="visually-hidden"><?= sv_t('Chronological security events with timestamp, severity, actor, action, and description') ?></caption>
          <thead>
            <tr>
              <th scope="col"><?= sv_t('When') ?></th>
              <th scope="col"><?= sv_t('Severity') ?></th>
              <th scope="col"><?= sv_t('Actor') ?></th>
              <th scope="col"><?= sv_t('Event Type') ?></th>
              <th scope="col"><?= sv_t('Description') ?></th>
              <th scope="col"><?= sv_t('Source IP') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentSecurityLogs as $log): ?>
              <?php
                $sev = audit_action_severity((string) $log['action'], (string) $log['description']);
              ?>
              <tr>
                <td>
                  <span class="nowrap text-xs text-muted"><?= e(fmt_datetime($log['created_at'])) ?></span>
                  <span class="table__cell-sub"><?= e(relative_time($log['created_at'])) ?></span>
                </td>
                <td>
                  <?= security_severity_badge($sev) ?>
                </td>
                <td>
                  <?php if ($log['username'] !== null): ?>
                    <span class="table__cell-strong"><?= e($log['username']) ?></span>
                    <span class="table__cell-sub"><?= e(ucfirst((string) ($log['role'] ?? 'user'))) ?></span>
                  <?php else: ?>
                    <span class="table__cell-strong"><?= sv_t('System / Anonymous') ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge badge--neutral"><?= e(str_replace('_', ' ', (string) $log['action'])) ?></span>
                </td>
                <td>
                  <span><?= e(str_limit($log['description'], 85)) ?></span>
                </td>
                <td>
                  <span class="mono"><?= e((string) ($log['ip_address'] ?? '—')) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</section>

<p class="soc-group-label">07 / <?= sv_t('ELECTION OPERATIONS') ?></p>
<!-- Election Security Overview Table -->
<section class="card" aria-labelledby="elections-heading">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title" id="elections-heading"><?= sv_t('Election Security & Turnout Overview') ?></h2>
      <p class="card__subtitle"><?= sv_t('Election status, eligible voters, submitted ballots, and turnout from current records.') ?></p>
    </div>
    <a class="btn btn--sm btn--secondary" href="<?= e(url('admin/elections.php')) ?>"><?= sv_t('Manage elections') ?></a>
  </div>

  <?php if ($elections === []): ?>
    <div class="empty-state empty-state--inline">
      <span class="empty-state__icon"><?= sv_icon('vote', 24) ?></span>
      <p class="empty-state__title"><?= sv_t('No elections found') ?></p>
      <p class="empty-state__text"><?= sv_t('Create an election to begin monitoring voting integrity.') ?></p>
      <a class="btn btn--accent" href="<?= e(url('admin/elections.php?action=create')) ?>">
        <?= sv_icon('plus', 16, 'btn__icon') ?><span><?= sv_t('Create election') ?></span>
      </a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden"><?= sv_t('Elections overview table with status, eligible voters, votes cast, turnout, and security state') ?></caption>
        <thead>
          <tr>
            <th scope="col"><?= sv_t('Election') ?></th>
            <th scope="col"><?= sv_t('Status') ?></th>
            <th scope="col"><?= sv_t('Voters') ?></th>
            <th scope="col"><?= sv_t('Votes Cast') ?></th>
            <th scope="col"><?= sv_t('Turnout') ?></th>
            <th scope="col"><?= sv_t('Operations State') ?></th>
            <th scope="col"><?= sv_t('Actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($elections as $el): ?>
            <?php
              $effective = election_effective_status($el);
              $eligible  = (int) ($el['eligible_count'] ?? 0);
              $voted   = (int) ($el['voted_count'] ?? 0);
              $turnout = $eligible > 0 ? percent($voted, $eligible) : null;

              $secState = match ($effective) {
                  'open'      => [sv_t('Voting open'), 'teal'],
                  'scheduled' => [sv_t('Scheduled'), 'info'],
                  'closed'    => [sv_t('Voting closed'), 'neutral'],
                  'draft'     => [sv_t('Draft setup'), 'warning'],
                  default     => [sv_t('Archived'), 'neutral'],
              };
            ?>
            <tr>
              <td>
                <span class="table__cell-strong">
                  <a href="<?= e(url('admin/elections.php?action=edit&id=' . (int) $el['id'])) ?>">
                    <?= e($el['title']) ?>
                  </a>
                </span>
                <span class="table__cell-sub"><?= e(fmt_range($el['start_datetime'], $el['end_datetime'])) ?></span>
              </td>
              <td><?= election_badge($effective) ?></td>
              <td><span class="mono"><?= e((string) $eligible) ?></span></td>
              <td><span class="mono table__cell-strong"><?= e((string) $voted) ?></span></td>
              <td>
                <?php if ($turnout === null): ?>
                  <span class="text-muted"><?= sv_t('No eligible voters') ?></span>
                <?php else: ?>
                  <span class="mono font-bold"><?= e((string) $turnout) ?>%</span>
                  <div class="progress" role="img" aria-label="<?= sv_t('%s%% turnout', (string) $turnout) ?>" style="margin-top:4px;min-width:70px;">
                    <span class="progress__bar <?= $turnout >= 50 ? 'progress__bar--winner' : '' ?>" style="width: <?= e((string) min(100, $turnout)) ?>%"></span>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge--<?= e($secState[1]) ?>">
                  <?= sv_icon('shield-check', 12) ?><?= e($secState[0]) ?>
                </span>
              </td>
              <td>
                <div class="row row--compact">
                  <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/candidates.php?election_id=' . (int) $el['id'])) ?>" title="<?= sv_t('View Candidates') ?>">
                    <?= sv_icon('users', 14) ?>
                  </a>
                  <a class="btn btn--sm btn--ghost" href="<?= e(url('admin/audit-logs.php?q=' . urlencode($el['title']))) ?>" title="<?= sv_t('Filter Audit Log') ?>">
                    <?= sv_icon('audit', 14) ?>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- Modal: Mark Security Alert as Resolved -->
<dialog class="modal" id="resolveAlertModal">
  <form method="post" action="<?= e(post_url('admin/dashboard.php' . ($range !== '24h' ? '?range=' . urlencode($range) : ''))) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="resolve_alert">
    <input type="hidden" name="alert_key" id="resolveModalKey" value="">
    <input type="hidden" name="alert_title" id="resolveModalTitle" value="">
    <input type="hidden" name="severity" id="resolveModalSeverity" value="Medium">

    <div class="modal__header">
      <div class="row row--compact" style="gap:var(--sp-2);">
        <span class="text-success"><?= sv_icon('check-circle', 20) ?></span>
        <h3 class="modal__title"><?= sv_t('Resolve Security Alert') ?></h3>
      </div>
      <button type="button" class="btn btn--sm btn--ghost" onclick="document.getElementById('resolveAlertModal').close();" aria-label="<?= sv_t('Close dialog') ?>">
        <?= sv_icon('x', 18) ?>
      </button>
    </div>

    <div class="modal__body stack-md">
      <p class="text-sm"><?= sv_t('You are recording this security alert / incident as acknowledged and resolved in the database:') ?></p>
      
      <div class="notice" style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:var(--sp-3) var(--sp-4);">
        <strong id="resolveModalDisplayTitle" class="font-semibold text-main"><?= sv_t('Alert Title') ?></strong>
        <p id="resolveModalDisplayText" class="text-xs text-muted" style="margin-top:4px;"><?= sv_t('Alert Description') ?></p>
      </div>

      <div class="field">
        <label for="resolveModalReason" class="field__label"><?= sv_t('Resolution Note / Action Taken (Optional)') ?></label>
        <textarea id="resolveModalReason" name="reason" class="input" rows="3" placeholder="<?= e(sv_t('e.g. Reviewed IP activity and confirmed legitimate administration access.')) ?>"></textarea>
        <p class="field__help"><?= sv_t('This explanation and your admin username will be saved in the persistent security alert registry.') ?></p>
      </div>
    </div>

    <div class="modal__footer">
      <button type="button" class="btn btn--secondary" onclick="document.getElementById('resolveAlertModal').close();"><?= sv_t('Cancel') ?></button>
      <button type="submit" class="btn btn--success">
        <?= sv_icon('check', 16) ?> <?= sv_t('Confirm & Mark Resolved') ?>
      </button>
    </div>
  </form>
</dialog>

<script>
function openResolveModal(key, title, severity, text) {
  var modal = document.getElementById('resolveAlertModal');
  if (!modal) return;
  document.getElementById('resolveModalKey').value = key;
  document.getElementById('resolveModalTitle').value = title;
  document.getElementById('resolveModalSeverity').value = severity;
  document.getElementById('resolveModalDisplayTitle').textContent = title;
  document.getElementById('resolveModalDisplayText').textContent = text || '';
  document.getElementById('resolveModalReason').value = '';
  modal.showModal();
}

function switchAlertTab(tabName) {
  var activeTabBtn = document.getElementById('alert-tab-active-btn');
  var resolvedTabBtn = document.getElementById('alert-tab-resolved-btn');
  var activePanel = document.getElementById('alert-panel-active');
  var resolvedPanel = document.getElementById('alert-panel-resolved');

  if (tabName === 'resolved') {
    if (activeTabBtn) {
      activeTabBtn.classList.remove('is-active');
      activeTabBtn.setAttribute('aria-selected', 'false');
    }
    if (resolvedTabBtn) {
      resolvedTabBtn.classList.add('is-active');
      resolvedTabBtn.setAttribute('aria-selected', 'true');
    }
    if (activePanel) activePanel.style.display = 'none';
    if (resolvedPanel) resolvedPanel.style.display = 'block';
  } else {
    if (resolvedTabBtn) {
      resolvedTabBtn.classList.remove('is-active');
      resolvedTabBtn.setAttribute('aria-selected', 'false');
    }
    if (activeTabBtn) {
      activeTabBtn.classList.add('is-active');
      activeTabBtn.setAttribute('aria-selected', 'true');
    }
    if (resolvedPanel) resolvedPanel.style.display = 'none';
    if (activePanel) activePanel.style.display = 'block';
  }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
