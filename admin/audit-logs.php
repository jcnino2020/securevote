<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/audit-logs.php
 *
 * The audit trail: who did what, from where, and when.
 *
 * This page is read-only by design. Nothing in the interface can edit or
 * delete a single audit entry, because a log an administrator can quietly
 * rewrite is not evidence. The only bulk action is an explicit, itself
 * logged, retention purge on the Settings page.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();

/* =====================================================================
 | Filters
 * =================================================================== */

$search    = trim((string) input('q', ''));
$action    = trim((string) input('log_action', ''));
$severity  = trim((string) input('severity', ''));
$userId    = int_input('user_id', 0);
$dateFrom  = trim((string) input('date_from', ''));
$dateTo    = trim((string) input('date_to', ''));

$where  = [];
$params = [];

if ($search !== '') {
    // Distinct placeholders: named parameters must not be reused in one
    // statement when prepared statements are not emulated.
    $where[]              = '(a.description LIKE :q_desc OR a.ip_address LIKE :q_ip OR u.username LIKE :q_user)';
    $params[':q_desc']    = '%' . $search . '%';
    $params[':q_ip']      = '%' . $search . '%';
    $params[':q_user']    = '%' . $search . '%';
}
if ($action !== '') {
    $where[]             = 'a.action = :action';
    $params[':action']   = $action;
}
if ($severity !== '') {
    $severityActions = match (strtolower($severity)) {
        'high', 'critical' => ['session_hijack_attempt', 'csrf_failure', 'unauthorized_access'],
        'medium'           => ['login_throttled', 'login_blocked', 'ballot_rejected', 'ballot_validation_failed', 'voter_status_changed', 'voter_deleted', 'candidate_deleted', 'position_deleted'],
        'low'              => ['login_failure', 'password_change_failed', 'results_exported', 'audit_log_exported', 'password_rehash', 'session_idle_timeout'],
        'informational'    => ['login_success', 'google_login_success', 'logout', 'ballot_submitted', 'direct_ballot_submitted', 'election_created', 'election_updated', 'election_opened', 'candidate_created', 'candidate_updated', 'position_created', 'position_updated', 'voter_created', 'system_initialized', 'eligibility_added', 'eligibility_bulk_added', 'voter_updated'],
        default            => [],
    };
    if ($severityActions !== []) {
        $inPlaceholders = [];
        foreach ($severityActions as $i => $sevAct) {
            $k = ':sev_' . $i;
            $inPlaceholders[] = $k;
            $params[$k] = $sevAct;
        }
        $where[] = 'a.action IN (' . implode(',', $inPlaceholders) . ')';
    }
}
if ($userId > 0) {
    $where[]             = 'a.user_id = :user_id';
    $params[':user_id']  = $userId;
}

/** Validate a Y-m-d string without letting PHP guess. */
$validDate = static function (string $value): ?string {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone(APP_TIMEZONE));

    return ($dt !== false && $dt->format('Y-m-d') === $value) ? $value : null;
};

$from = $validDate($dateFrom);
$to   = $validDate($dateTo);

if ($from !== null) {
    $where[]           = 'a.created_at >= :from';
    $params[':from']   = $from . ' 00:00:00';
}
if ($to !== null) {
    $where[]         = 'a.created_at <= :to';
    $params[':to']   = $to . ' 23:59:59';
}

$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id' . $whereSql
);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pageInfo = paginate($total, 25);

$listStmt = $pdo->prepare(
    'SELECT a.id, a.user_id, a.action, a.description, a.ip_address, a.user_agent, a.created_at,
            u.username, u.first_name, u.last_name, u.role
       FROM audit_logs a
       LEFT JOIN users u ON u.id = a.user_id'
    . $whereSql
    . ' ORDER BY a.created_at DESC, a.id DESC'
    . sprintf(' LIMIT %d OFFSET %d', $pageInfo['per_page'], $pageInfo['offset'])
);
$listStmt->execute($params);
$logs = $listStmt->fetchAll();

/* =====================================================================
 | Filter option lists
 * =================================================================== */

$actions = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

$users = $pdo->query(
    'SELECT id, username, first_name, last_name, role
       FROM users
      ORDER BY role ASC, last_name ASC, first_name ASC'
)->fetchAll();

/* =====================================================================
 | CSV export
 * =================================================================== */

if ((string) input('export', '') === 'csv') {
    // Same CSV-injection guard as the results export.
    $safeCell = static function (string $value): string {
        if ($value !== '' && str_contains('=+-@', $value[0])) {
            return "'" . $value;
        }

        return $value;
    };

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="securevote-audit-log.csv"');
    header('X-Content-Type-Options: nosniff');

    // Export everything that matches the current filters, not just the page
    // that happens to be on screen.
    $exportStmt = $pdo->prepare(
        'SELECT a.id, a.user_id, a.action, a.description, a.ip_address, a.user_agent, a.created_at,
                u.username, u.first_name, u.last_name, u.role
           FROM audit_logs a
           LEFT JOIN users u ON u.id = a.user_id'
        . $whereSql
        . ' ORDER BY a.created_at DESC, a.id DESC'
    );
    $exportStmt->execute($params);
    $exportRows = $exportStmt->fetchAll();

    $exported = count($exportRows);

    $out = fopen('php://output', 'wb');
    fputcsv($out, ['SecureVote audit log export']);
    fputcsv($out, ['Generated', now(), 'Entries exported', $exported]);
    fputcsv($out, []);
    fputcsv($out, ['Timestamp', 'Actor', 'Role', 'Action', 'Description', 'IP address', 'User agent']);

    foreach ($exportRows as $log) {
        fputcsv($out, [
            (string) $log['created_at'],
            $log['username'] !== null ? (string) $log['username'] : 'anonymous',
            (string) ($log['role'] ?? ''),
            $safeCell((string) $log['action']),
            $safeCell((string) $log['description']),
            (string) ($log['ip_address'] ?? ''),
            $safeCell((string) ($log['user_agent'] ?? '')),
        ]);
    }

    fclose($out);

    audit_log('audit_log_exported', 'Exported ' . $exported . ' audit entr'
        . ($exported === 1 ? 'y' : 'ies') . ' as CSV.');

    exit;
}

/* =====================================================================
 | Header
 * =================================================================== */

$hasFilters = $search !== '' || $action !== '' || $severity !== '' || $userId > 0 || $from !== null || $to !== null;

$pageTitle    = 'Audit logs';
$pageSubtitle = 'Every security and administrative event, newest first. Entries cannot be edited.';
$activeNav    = 'audit-logs.php';
$pageActions  = '<a class="btn btn--secondary btn--sm" href="'
    . e(url('admin/audit-logs.php' . query_with(['export' => 'csv', 'page' => null]))) . '">'
    . sv_icon('download', 16, 'btn__icon') . '<span>Export CSV</span></a>';
$breadcrumb   = [['label' => 'Audit logs', 'href' => 'admin/audit-logs.php']];

require __DIR__ . '/../includes/header.php';
?>

<section class="card">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title">Event log</h2>
      <p class="card__subtitle">
        <?= e((string) $total) ?> entr<?= $total === 1 ? 'y' : 'ies' ?><?= $hasFilters ? ' matching the filters' : '' ?>
      </p>
    </div>
    <span class="badge badge--teal"><?= sv_icon('shield-check', 14) ?>Append-only in the interface</span>
  </div>

  <form class="table-toolbar" method="get" action="<?= e(url('admin/audit-logs.php')) ?>" role="search">
    <div class="search-field">
      <span class="search-field__icon" aria-hidden="true"><?= sv_icon('search', 16) ?></span>
      <label class="visually-hidden" for="q">Search the audit log</label>
      <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>"
             placeholder="Search description, IP or username">
    </div>

    <div class="row">
      <label class="visually-hidden" for="severity">Filter by severity</label>
      <select class="select" id="severity" name="severity" data-auto-submit>
        <option value="">All severities</option>
        <option value="high" <?= in_array($severity, ['high', 'critical'], true) ? 'selected' : '' ?>>Critical & High</option>
        <option value="medium" <?= $severity === 'medium' ? 'selected' : '' ?>>Medium</option>
        <option value="low" <?= $severity === 'low' ? 'selected' : '' ?>>Low</option>
        <option value="informational" <?= $severity === 'informational' ? 'selected' : '' ?>>Informational</option>
      </select>

      <label class="visually-hidden" for="log_action">Filter by action</label>
      <select class="select" id="log_action" name="log_action" data-auto-submit>
        <option value="">All actions</option>
        <?php foreach ($actions as $option): ?>
          <option value="<?= e((string) $option) ?>" <?= $action === (string) $option ? 'selected' : '' ?>>
            <?= e(str_replace('_', ' ', (string) $option)) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="visually-hidden" for="user_id">Filter by account</label>
      <select class="select" id="user_id" name="user_id" data-auto-submit>
        <option value="">Any account</option>
        <?php foreach ($users as $option): ?>
          <option value="<?= e((string) $option['id']) ?>" <?= $userId === (int) $option['id'] ? 'selected' : '' ?>>
            <?= e($option['username']) ?> (<?= e((string) $option['role']) ?>)
          </option>
        <?php endforeach; ?>
      </select>

      <label class="visually-hidden" for="date_from">From date</label>
      <input class="input" type="date" id="date_from" name="date_from" value="<?= e((string) $from) ?>">

      <label class="visually-hidden" for="date_to">To date</label>
      <input class="input" type="date" id="date_to" name="date_to" value="<?= e((string) $to) ?>">

      <button class="btn btn--secondary btn--sm" type="submit">
        <?= sv_icon('filter', 16, 'btn__icon') ?><span>Apply</span>
      </button>
      <?php if ($hasFilters): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/audit-logs.php')) ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($logs === []): ?>
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('audit', 26) ?></span>
      <p class="empty-state__title">
        <?= e($hasFilters ? 'No audit entries match those filters' : 'Nothing logged yet') ?>
      </p>
      <p class="empty-state__text">
        <?= e($hasFilters
            ? 'Try a wider date range, or clear the filters.'
            : 'Sign-ins, failed attempts, election changes and submitted ballots are written here as they happen.') ?>
      </p>
      <?php if ($hasFilters): ?>
        <a class="btn btn--secondary" href="<?= e(url('admin/audit-logs.php')) ?>">Clear filters</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden">Audit entries with time, severity, actor, action, description and source address</caption>
        <thead>
          <tr>
            <th scope="col">When</th>
            <th scope="col">Severity</th>
            <th scope="col">Actor</th>
            <th scope="col">Action</th>
            <th scope="col">Description</th>
            <th scope="col">Source</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td>
                <span class="nowrap"><?= e(fmt_datetime($log['created_at'])) ?></span>
                <span class="table__cell-sub"><?= e(relative_time($log['created_at'])) ?></span>
              </td>
              <td>
                <?= security_severity_badge(audit_action_severity((string) $log['action'], (string) $log['description'])) ?>
              </td>
              <td>
                <?php if ($log['username'] !== null): ?>
                  <span class="table__cell-strong"><?= e($log['username']) ?></span>
                  <span class="table__cell-sub"><?= e(ucfirst((string) $log['role'])) ?></span>
                <?php else: ?>
                  <span class="table__cell-strong">Anonymous</span>
                  <span class="table__cell-sub">No account matched</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge--neutral"><?= e(str_replace('_', ' ', (string) $log['action'])) ?></span>
              </td>
              <td><?= e((string) $log['description']) ?></td>
              <td>
                <span class="mono"><?= e((string) ($log['ip_address'] ?? '—')) ?></span>
                <?php if (($log['user_agent'] ?? null) !== null): ?>
                  <span class="table__cell-sub"><?= e(str_limit((string) $log['user_agent'], 46)) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?= pagination_links($pageInfo, 'audit entries') ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
