<?php
/** Admin-only site-wide traffic and visitor journeys. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$pdo = db();

$rangeOptions = ['24h' => [sv_t('Last 24 hours'), 'INTERVAL 1 DAY'], '7d' => [sv_t('Last 7 days'), 'INTERVAL 7 DAY'], '30d' => [sv_t('Last 30 days'), 'INTERVAL 30 DAY'], 'all' => [sv_t('All time'), null]];
$range = (string) ($_GET['range'] ?? '7d');
if (!isset($rangeOptions[$range])) $range = '7d';
$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$visitor = (string) ($_GET['visitor'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $visitor)) $visitor = '';
$type = (string) ($_GET['type'] ?? 'all');
if (!in_array($type, ['all', 'page_view', 'link_click'], true)) $type = 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 40;

$ready = true;
try {
    $pdo->query('SELECT id FROM site_traffic_events LIMIT 0');
} catch (Throwable $exception) {
    try {
        sv_traffic_create_table($pdo);
    } catch (Throwable $setupException) {
        $ready = false;
    }
}

$where = [];
$params = [];
if ($rangeOptions[$range][1] !== null) $where[] = 't.created_at >= NOW() - ' . $rangeOptions[$range][1];
if ($type !== 'all') { $where[] = 't.event_type = :type'; $params[':type'] = $type; }
if ($visitor !== '') { $where[] = 't.visitor_key = :visitor'; $params[':visitor'] = $visitor; }
if ($search !== '') {
    $where[] = '(t.page_path LIKE :q_page OR t.target_path LIKE :q_target OR t.ip_address LIKE :q_ip OR u.username LIKE :q_username OR u.first_name LIKE :q_first OR u.last_name LIKE :q_last)';
    foreach (['q_page','q_target','q_ip','q_username','q_first','q_last'] as $key) $params[':' . $key] = '%' . $search . '%';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$fromSql = ' FROM site_traffic_events t LEFT JOIN users u ON u.id = t.user_id ' . $whereSql;
$fetch = static function (string $select, array $bindings = []) use ($pdo, $fromSql, $params): array {
    $stmt = $pdo->prepare($select);
    $stmt->execute($bindings ?: $params);
    return $stmt->fetchAll();
};
$summary = ['views' => 0, 'clicks' => 0, 'visitors' => 0, 'accounts' => 0];
$topPages = $topTargets = $devices = $browsers = $systems = $referrers = $events = $journeys = [];
$totalEvents = 0;
if ($ready) {
    $row = $fetch('SELECT COUNT(*) AS total, SUM(t.event_type = \'page_view\') AS views, SUM(t.event_type = \'link_click\') AS clicks, COUNT(DISTINCT t.visitor_key) AS visitors, COUNT(DISTINCT t.user_id) AS accounts' . $fromSql)[0] ?? [];
    $totalEvents = (int) ($row['total'] ?? 0);
    $summary = ['views' => (int) ($row['views'] ?? 0), 'clicks' => (int) ($row['clicks'] ?? 0), 'visitors' => (int) ($row['visitors'] ?? 0), 'accounts' => (int) ($row['accounts'] ?? 0)];
    $extraCondition = $where ? ' AND ' : ' WHERE ';
    $topPages = $fetch('SELECT t.page_path AS path, COUNT(*) AS hits' . $fromSql . $extraCondition . 't.event_type = \'page_view\' GROUP BY t.page_path ORDER BY hits DESC LIMIT 8', $params);
    $topTargets = $fetch('SELECT t.target_path AS path, COUNT(*) AS hits' . $fromSql . $extraCondition . 't.event_type = \'link_click\' AND t.target_path IS NOT NULL GROUP BY t.target_path ORDER BY hits DESC LIMIT 8', $params);
    $devices = $fetch('SELECT t.device_type AS label, COUNT(*) AS hits' . $fromSql . ' GROUP BY t.device_type ORDER BY hits DESC', $params);
    $browsers = $fetch('SELECT t.browser AS label, COUNT(*) AS hits' . $fromSql . ' GROUP BY t.browser ORDER BY hits DESC LIMIT 5', $params);
    $systems = $fetch('SELECT t.operating_system AS label, COUNT(*) AS hits' . $fromSql . ' GROUP BY t.operating_system ORDER BY hits DESC LIMIT 5', $params);
    $referrers = $fetch('SELECT t.referrer_path AS label, COUNT(*) AS hits' . $fromSql . $extraCondition . 't.referrer_path IS NOT NULL GROUP BY t.referrer_path ORDER BY hits DESC LIMIT 5', $params);
    $offset = ($page - 1) * $perPage;
    $events = $fetch('SELECT t.*, u.username, u.first_name, u.last_name, u.role' . $fromSql . ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    $journeyRows = $fetch('SELECT t.*, u.username, u.first_name, u.last_name' . $fromSql . ' ORDER BY t.created_at DESC, t.id DESC LIMIT 400', $params);
    foreach ($journeyRows as $event) {
        $key = $event['visitor_key'];
        if (!isset($journeys[$key])) {
            if (count($journeys) >= 8) continue;
            $journeys[$key] = ['actor' => $event, 'events' => [], 'last' => $event['created_at']];
        }
        if (count($journeys[$key]['events']) < 12) $journeys[$key]['events'][] = $event;
    }
}
$buildUrl = static function (array $overrides = []) use ($range, $search, $visitor, $type): string {
    $query = array_filter(array_merge(['range' => $range, 'q' => $search, 'visitor' => $visitor, 'type' => $type], $overrides), static fn($value) => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/traffic.php') . ($query ? '?' . http_build_query($query) : '');
};
$actorLabel = static function (array $event): string {
    if (!empty($event['user_id'])) return trim(($event['first_name'] ?? '') . ' ' . ($event['last_name'] ?? '')) ?: (string) ($event['username'] ?? 'Account #' . $event['user_id']);
    return sv_t('Guest') . ' · ' . strtoupper(substr((string) $event['visitor_key'], 0, 6));
};
$pageTitle = sv_t('Site traffic');
$showPageTitle = false;
$activeNav = 'traffic.php';
$bodyClass = 'page-admin-traffic';
$pageActions = '<a class="btn btn--secondary btn--sm" href="' . e($buildUrl()) . '">' . sv_icon('refresh', 16) . '<span>' . e(sv_t('Refresh')) . '</span></a>';
require __DIR__ . '/../includes/header.php';
?>
<section class="traffic-intro">
  <div><h1><?= e(sv_t("Site traffic")) ?></h1><p><?= e(sv_t("Follow page visits and link clicks across SecureVote. Events appear as they arrive; refresh to update.")) ?></p></div>
  <div class="traffic-live"><span aria-hidden="true"></span> <?= e(sv_t("First-party tracking")) ?></div>
</section>
<?php if (!$ready): ?>
  <div class="alert alert--warning" role="alert"><?= e(sv_t("Traffic storage is unavailable. Import database/migration_site_traffic.sql with your database administrator, then refresh this page.")) ?></div>
<?php else: ?>
<form class="traffic-filters" method="get" action="<?= e(url('admin/traffic.php')) ?>" aria-label="<?= e(sv_t('Traffic filters')) ?>">
  <div class="traffic-range" role="group" aria-label="<?= e(sv_t('Time range')) ?>">
    <?php foreach ($rangeOptions as $key => [$label]): ?><a href="<?= e($buildUrl(['range' => $key, 'page' => null])) ?>" class="<?= $range === $key ? 'is-active' : '' ?>" <?= $range === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  </div>
  <label class="traffic-search"><span class="sr-only"><?= e(sv_t("Search traffic")) ?></span><input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(sv_t('Search path, IP, or account')) ?>"></label>
  <select name="type" aria-label="<?= e(sv_t('Event type')) ?>"><option value="all"><?= e(sv_t("All events")) ?></option><option value="page_view" <?= $type === 'page_view' ? 'selected' : '' ?>><?= e(sv_t("Page views")) ?></option><option value="link_click" <?= $type === 'link_click' ? 'selected' : '' ?>><?= e(sv_t("Link clicks")) ?></option></select>
  <input type="hidden" name="range" value="<?= e($range) ?>">
  <?php if ($visitor !== ''): ?><input type="hidden" name="visitor" value="<?= e($visitor) ?>"><?php endif; ?>
  <button class="btn btn--primary btn--sm" type="submit"><?= e(sv_t("Apply filters")) ?></button>
  <?php if ($search !== '' || $visitor !== '' || $type !== 'all'): ?><a class="traffic-clear" href="<?= e(url('admin/traffic.php?range=' . $range)) ?>"><?= e(sv_t("Clear")) ?></a><?php endif; ?>
</form>
<section class="traffic-metrics" aria-label="<?= e(sv_t('Traffic summary')) ?>">
  <?php foreach ([[sv_t('Page views'), $summary['views'], sv_t('Pages opened')], [sv_t('Link clicks'), $summary['clicks'], sv_t('Every on-page link')], [sv_t('Visitor sessions'), $summary['visitors'], sv_t('Anonymous or signed in')], [sv_t('Known accounts'), $summary['accounts'], sv_t('Signed-in identities')]] as [$label, $value, $hint]): ?>
  <div><span><?= e($label) ?></span><strong><?= number_format($value) ?></strong><small><?= e($hint) ?></small></div>
  <?php endforeach; ?>
</section>
<div class="traffic-breakdown">
  <section class="traffic-panel"><div class="traffic-panel__head"><h2><?= e(sv_t("Most visited pages")) ?></h2><span><?= e(sv_t("Page views")) ?></span></div>
    <?php if (!$topPages): ?><p class="traffic-empty"><?= e(sv_t("No page views in this range.")) ?></p><?php endif; ?>
    <?php foreach ($topPages as $item): ?><div class="traffic-rank"><code title="<?= e($item['path']) ?>"><?= e($item['path']) ?></code><strong><?= number_format((int) $item['hits']) ?></strong></div><?php endforeach; ?>
  </section>
  <section class="traffic-panel"><div class="traffic-panel__head"><h2><?= e(sv_t("Most clicked destinations")) ?></h2><span><?= e(sv_t("Link clicks")) ?></span></div>
    <?php if (!$topTargets): ?><p class="traffic-empty"><?= e(sv_t("No link clicks in this range.")) ?></p><?php endif; ?>
    <?php foreach ($topTargets as $item): ?><div class="traffic-rank"><code title="<?= e($item['path']) ?>"><?= e($item['path']) ?></code><strong><?= number_format((int) $item['hits']) ?></strong></div><?php endforeach; ?>
  </section>
</div>
<div class="traffic-meta-grid">
<?php foreach ([[sv_t('Devices'), $devices], [sv_t('Browsers'), $browsers], [sv_t('Operating systems'), $systems], [sv_t('Internal referrers'), $referrers]] as [$heading, $items]): ?>
  <section class="traffic-panel"><div class="traffic-panel__head"><h2><?= e($heading) ?></h2><span><?= e(sv_t("Recorded events")) ?></span></div>
    <?php if (!$items): ?><p class="traffic-empty"><?= e(sv_t("No data in this range.")) ?></p><?php endif; ?>
    <?php foreach ($items as $item): ?><div class="traffic-rank"><code title="<?= e($item['label']) ?>"><?= e($item['label']) ?></code><strong><?= number_format((int) $item['hits']) ?></strong></div><?php endforeach; ?>
  </section>
<?php endforeach; ?>
</div>
<section class="traffic-journeys"><div class="traffic-section-head"><div><h2><?= e(sv_t("Visitor journeys")) ?></h2><p><?= e(sv_t("Recent steps grouped by browser session. Select a session to see its events.")) ?></p></div><span><?= e(sprintf(sv_t('%d shown'), count($journeys))) ?></span></div>
  <?php if (!$journeys): ?><p class="traffic-empty"><?= e(sv_t("No journeys match these filters yet.")) ?></p><?php endif; ?>
  <div class="traffic-journey-grid"><?php foreach ($journeys as $key => $journey): $steps = array_reverse($journey['events']); ?>
    <article class="traffic-journey"><div class="traffic-journey__head"><div><strong><?= e($actorLabel($journey['actor'])) ?></strong><small><?= e(date('M j, g:i A', strtotime($journey['last']))) ?></small></div><a href="<?= e($buildUrl(['visitor' => $key, 'page' => null])) ?>"><?= e(sv_t("View session")) ?> <?= sv_icon('arrow-right', 14) ?></a></div>
      <ol><?php foreach ($steps as $step): ?><li><span class="traffic-event-mark <?= $step['event_type'] === 'link_click' ? 'traffic-event-mark--click' : '' ?>"></span><span><small><?= e($step['event_type'] === 'link_click' ? sv_t('Clicked') : sv_t('Opened')) ?></small><code><?= e($step['event_type'] === 'link_click' ? ($step['target_path'] ?? '') : $step['page_path']) ?></code></span></li><?php endforeach; ?></ol>
    </article>
  <?php endforeach; ?></div>
</section>
<section class="traffic-panel traffic-log"><div class="traffic-section-head"><div><h2><?= e(sv_t("Event stream")) ?></h2><p><?= e(sprintf(sv_t('%s matching events · newest first'), number_format($totalEvents))) ?></p></div><span><?= e(sv_t("Admin access only")) ?></span></div>
  <div class="traffic-table-wrap"><table><thead><tr><th><?= e(sv_t("Time")) ?></th><th><?= e(sv_t("Visitor")) ?></th><th><?= e(sv_t("Event")) ?></th><th><?= e(sv_t("Page → destination")) ?></th><th><?= e(sv_t("Referrer")) ?></th><th><?= e(sv_t("Client")) ?></th><th><?= e(sv_t("IP")) ?></th></tr></thead><tbody>
  <?php foreach ($events as $event): ?><tr>
    <td class="traffic-nowrap"><?= e(date('M j, g:i A', strtotime($event['created_at']))) ?></td>
    <td><a href="<?= e($buildUrl(['visitor' => $event['visitor_key'], 'page' => null])) ?>"><?= e($actorLabel($event)) ?></a><small><?= e(substr($event['visitor_key'], 0, 10)) ?></small></td>
    <td><span class="traffic-tag <?= $event['event_type'] === 'link_click' ? 'traffic-tag--click' : '' ?>"><?= e($event['event_type'] === 'link_click' ? sv_t('Click') : sv_t('View')) ?></span></td>
    <td><code><?= e($event['page_path']) ?></code><?php if ($event['target_path']): ?><span class="traffic-arrow">→</span><code><?= e($event['target_path']) ?></code><?php endif; ?></td>
    <td><code><?= e($event['referrer_path'] ?: '—') ?></code></td>
    <td><?= e($event['device_type']) ?><small><?= e($event['browser'] . ' · ' . $event['operating_system']) ?></small></td>
    <td><code><?= e($event['ip_address'] ?: '—') ?></code></td>
  </tr><?php endforeach; ?>
  <?php if (!$events): ?><tr><td colspan="7" class="traffic-empty"><?= e(sv_t("No events match these filters.")) ?></td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if ($totalEvents > $perPage): ?><nav class="traffic-pagination" aria-label="<?= e(sv_t('Event pages')) ?>"><span><?= e(sprintf(sv_t('Page %d of %d'), $page, (int) ceil($totalEvents / $perPage))) ?></span><?php if ($page > 1): ?><a href="<?= e($buildUrl(['page' => $page - 1])) ?>"><?= e(sv_t("Previous")) ?></a><?php endif; ?><?php if ($page * $perPage < $totalEvents): ?><a href="<?= e($buildUrl(['page' => $page + 1])) ?>"><?= e(sv_t("Next")) ?></a><?php endif; ?></nav><?php endif; ?>
</section>
<p class="traffic-note"><?= e(sv_t("Routes and external hostnames only. Query strings, form entries, ballot selections, and receipt codes are never captured. A guest session is a browser session, not a verified person.")) ?></p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
