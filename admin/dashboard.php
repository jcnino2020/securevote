<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * admin/dashboard.php
 *
 * The administrator's landing page: what is happening right now, and what
 * needs attention. Deliberately not crowded: five numbers, the elections
 * that are running, the newest audit entries, and any alerts.
 *
 * This page is read-only. Everything that changes data lives on its own
 * page with its own POST handler and CSRF token.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo   = db();

/* ---------------------------------------------------------------------
 | Summary numbers
 * ------------------------------------------------------------------- */
$totals = [
    'elections'   => (int) $pdo->query('SELECT COUNT(*) FROM elections')->fetchColumn(),
    'voters'      => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetchColumn(),
    'active_voters' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'voter' AND status = 'active'")->fetchColumn(),
    'blocked'     => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status <> 'active'")->fetchColumn(),
    'candidates'  => (int) $pdo->query('SELECT COUNT(*) FROM candidates')->fetchColumn(),
    'ballots'     => (int) $pdo->query('SELECT COUNT(*) FROM ballots')->fetchColumn(),
    'failed_logins_24h' => (int) $pdo->query(
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'login_failure' AND created_at > (NOW() - INTERVAL 24 HOUR)"
    )->fetchColumn(),
];

/* ---------------------------------------------------------------------
 | Elections with their turnout, most relevant state first
 * ------------------------------------------------------------------- */
$elections = $pdo->query(
    "SELECT e.*,
            (SELECT COUNT(*) FROM voter_participation vp WHERE vp.election_id = e.id) AS participation_count,
            (SELECT COUNT(*) FROM ballots b WHERE b.election_id = e.id AND b.status = 'submitted') AS voted_count,
            (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id) AS candidate_count
       FROM elections e
      ORDER BY FIELD(e.status, 'open', 'scheduled', 'draft', 'closed', 'archived'), e.start_datetime DESC"
)->fetchAll();


/* ---------------------------------------------------------------------
 | Recent audit activity
 * ------------------------------------------------------------------- */
$recentLogs = $pdo->query(
    'SELECT a.id, a.action, a.description, a.ip_address, a.created_at,
            u.username, u.first_name, u.last_name
       FROM audit_logs a
       LEFT JOIN users u ON u.id = a.user_id
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT 8'
)->fetchAll();

/* ---------------------------------------------------------------------
 | Alerts: things an administrator should look at
 * ------------------------------------------------------------------- */
$alerts = [];

if ($totals['failed_logins_24h'] > 0) {
    $alerts[] = [
        'type'  => $totals['failed_logins_24h'] >= 10 ? 'danger' : 'warning',
        'icon'  => 'ban',
        'text'  => $totals['failed_logins_24h'] . ' failed sign-in attempt'
            . ($totals['failed_logins_24h'] === 1 ? '' : 's') . ' in the last 24 hours.',
        'href'  => 'admin/audit-logs.php?action=login_failure',
        'label' => 'Review them',
    ];
}

if ($totals['blocked'] > 0) {
    $alerts[] = [
        'type'  => 'info',
        'icon'  => 'user',
        'text'  => $totals['blocked'] . ' account' . ($totals['blocked'] === 1 ? ' is' : 's are')
            . ' not active (blocked or inactive).',
        'href'  => 'admin/voters.php?status=blocked',
        'label' => 'Manage accounts',
    ];
}

$drafts = array_filter($elections, static fn(array $e): bool => $e['status'] === 'draft');
if ($drafts !== []) {
    $alerts[] = [
        'type'  => 'warning',
        'icon'  => 'file',
        'text'  => count($drafts) . ' election' . (count($drafts) === 1 ? ' is' : 's are')
            . ' still in draft, so voters cannot see them yet.',
        'href'  => 'admin/elections.php?status=draft',
        'label' => 'Open election setup',
    ];
}

$noCandidates = array_filter(
    $elections,
    static fn(array $e): bool => (int) $e['candidate_count'] === 0
        && in_array(election_effective_status($e), ['open', 'scheduled'], true)
);
foreach ($noCandidates as $election) {
    $alerts[] = [
        'type'  => 'danger',
        'icon'  => 'alert-triangle',
        'text'  => '"' . $election['title'] . '" has no candidates yet but is not in draft.',
        'href'  => 'admin/candidates.php?election_id=' . (int) $election['id'],
        'label' => 'Add candidates',
    ];
}

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Overview of elections, voters and system activity.';
$activeNav    = 'dashboard.php';
$pageActions  = '<a class="btn btn--accent btn--sm" href="' . e(url('admin/elections.php?action=create')) . '">'
    . sv_icon('plus', 16, 'btn__icon') . '<span>New election</span></a>';
$breadcrumb   = [['label' => 'Dashboard', 'href' => 'admin/dashboard.php']];

require __DIR__ . '/../includes/header.php';
?>

<?php
/* ---------------------------------------------------------------------
 | Live figures for the summary tiles
 |
 | Every number below is counted from the database on this request. The
 | election states come from election_effective_status(), so an election
 | stored as "open" whose end time has passed is reported as closed here
 | exactly as it is everywhere else in the system.
 * ------------------------------------------------------------------- */
$statusCounts = ['open' => 0, 'scheduled' => 0, 'draft' => 0, 'closed' => 0, 'archived' => 0];
$primaryOpen  = null;

foreach ($elections as $election) {
    $effective = election_effective_status($election);
    $statusCounts[$effective] = ($statusCounts[$effective] ?? 0) + 1;

    if ($effective === 'open' && $primaryOpen === null) {
        $primaryOpen = $election;
    }
}

// "Votes cast" describes the election that is open right now, because that is
// the turnout that means something today. With nothing open it falls back to
// every election combined.
if ($primaryOpen !== null) {
    $castVotes    = (int) $primaryOpen['voted_count'];
    $castEligible = (int) $primaryOpen['eligible_count'];
    $castCaption  = (string) $primaryOpen['title'];
} else {
    $castVotes    = 0;
    $castEligible = 0;

    foreach ($elections as $election) {
        $castVotes    += (int) $election['voted_count'];
        $castEligible += (int) $election['eligible_count'];
    }

    $castCaption = 'All elections combined';
}

$castTurnout = percent($castVotes, $castEligible);
?>

<section class="stat-grid" aria-label="Summary statistics">
  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('vote', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Elections</p>
      <p class="stat-card__value"><?= e((string) $totals['elections']) ?></p>
      <p class="stat-card__meta">
        <?= e($statusCounts['open'] . ' open, ' . $statusCounts['scheduled'] . ' scheduled, ' . $statusCounts['closed'] . ' closed') ?>
      </p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon"><?= sv_icon('users', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Registered voters</p>
      <p class="stat-card__value"><?= e((string) $totals['voters']) ?></p>
      <p class="stat-card__meta">
        <?= e($totals['active_voters'] . ' active, ' . $totals['blocked'] . ' not active') ?>
      </p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--teal"><?= sv_icon('check-circle', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Votes cast</p>
      <p class="stat-card__value"><?= e((string) $castVotes) ?></p>
      <p class="stat-card__meta">
        <?= e('of ' . $castEligible . ' eligible, ' . $castTurnout . '% turnout') ?>
      </p>
      <div class="progress" role="img" aria-label="<?= e($castTurnout . '% turnout') ?>"
           style="margin-top:6px">
        <span class="progress__bar progress__bar--winner"
              style="width: <?= e((string) min(100, $castTurnout)) ?>%"></span>
      </div>
      <p class="stat-card__meta"><?= e($castCaption) ?></p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--success"><?= sv_icon('user-check', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Candidates</p>
      <p class="stat-card__value"><?= e((string) $totals['candidates']) ?></p>
      <p class="stat-card__meta">Across every election</p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('file', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Ballots submitted</p>
      <p class="stat-card__value"><?= e((string) $totals['ballots']) ?></p>
      <p class="stat-card__meta">Across every election</p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--warning"><?= sv_icon('ban', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Failed sign-ins (24h)</p>
      <p class="stat-card__value"><?= e((string) $totals['failed_logins_24h']) ?></p>
      <p class="stat-card__meta">Recorded in the audit log</p>
    </div>
  </div>
</section>

<?php if ($alerts !== []): ?>
  <section class="card" aria-labelledby="alerts-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="alerts-heading">Needs attention</h2>
        <p class="card__subtitle">A short list, only when something is worth a look.</p>
      </div>
      <span class="badge badge--info"><?= sv_icon('bell', 14) ?><?= e((string) count($alerts)) ?></span>
    </div>
    <div class="card__body stack-sm">
      <?php foreach ($alerts as $alert): ?>
        <div class="alert alert--<?= e($alert['type']) ?>">
          <span class="alert__icon" aria-hidden="true"><?= sv_icon($alert['icon'], 18) ?></span>
          <p class="alert__text"><?= e($alert['text']) ?></p>
          <a class="btn btn--sm btn--secondary" href="<?= e(url($alert['href'])) ?>"><?= e($alert['label']) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<div class="grid-2">
  <section class="card" aria-labelledby="elections-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="elections-heading">Elections</h2>
        <p class="card__subtitle">Turnout updates as ballots are submitted.</p>
      </div>
      <a class="btn btn--sm btn--secondary" href="<?= e(url('admin/elections.php')) ?>">View all</a>
    </div>

    <?php if ($elections === []): ?>
      <div class="empty-state empty-state--inline">
        <span class="empty-state__icon"><?= sv_icon('vote', 24) ?></span>
        <p class="empty-state__title">No elections yet</p>
        <p class="empty-state__text">Create an election, add its positions and candidates, then open it for voting.</p>
        <a class="btn btn--accent" href="<?= e(url('admin/elections.php?action=create')) ?>">
          <?= sv_icon('plus', 16, 'btn__icon') ?><span>Create the first election</span>
        </a>
      </div>
    <?php else: ?>
      <div class="card__body stack">
        <?php foreach (array_slice($elections, 0, 5) as $election): ?>
          <?php
            $effective = election_effective_status($election);
            $eligible  = (int) $election['eligible_count'];
            $voted     = (int) $election['voted_count'];
            $turnout   = percent($voted, $eligible);
          ?>
          <article class="stack-sm">
            <div class="row row--between">
              <div>
                <p class="table__cell-strong">
                  <a href="<?= e(url('admin/elections.php?action=edit&id=' . (int) $election['id'])) ?>">
                    <?= e($election['title']) ?>
                  </a>
                </p>
                <p class="text-sm text-muted">
                  <?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?>
                </p>
              </div>
              <?= election_badge($effective) ?>
            </div>

            <div class="progress" role="img"
                 aria-label="<?= e($voted . ' of ' . $eligible . ' eligible voters have voted (' . $turnout . '%)') ?>">
              <span class="progress__bar" style="width: <?= e((string) min(100, $turnout)) ?>%"></span>
            </div>

            <p class="progress__label">
              <?= e((string) $voted) ?> of <?= e((string) $eligible) ?> voters &middot;
              <?= e((string) $turnout) ?>% turnout &middot;
              <?= e((string) $election['position_count']) ?> positions &middot;
              <?= e((string) $election['candidate_count']) ?> candidates
            </p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card" aria-labelledby="activity-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="activity-heading">Recent activity</h2>
        <p class="card__subtitle">Newest audit entries.</p>
      </div>
      <a class="btn btn--sm btn--secondary" href="<?= e(url('admin/audit-logs.php')) ?>">Full log</a>
    </div>

    <?php if ($recentLogs === []): ?>
      <div class="empty-state empty-state--inline">
        <span class="empty-state__icon"><?= sv_icon('audit', 24) ?></span>
        <p class="empty-state__title">Nothing logged yet</p>
        <p class="empty-state__text">
          Sign-ins, election changes and submitted ballots appear here as they happen.
        </p>
      </div>
    <?php else: ?>
      <div class="card__body">
        <ul class="stack-sm">
          <?php foreach ($recentLogs as $log): ?>
            <li class="review-item">
              <span class="review-item__position"><?= e(str_replace('_', ' ', (string) $log['action'])) ?></span>
              <span class="review-item__choice"><?= e(str_limit($log['description'], 90)) ?></span>
              <span class="review-item__meta">
                <?php if ($log['username'] !== null): ?>
                  <?= e($log['first_name'] . ' ' . $log['last_name']) ?> (<?= e($log['username']) ?>)
                  &middot;
                <?php else: ?>
                  System &middot;
                <?php endif; ?>
                <?= e(relative_time($log['created_at'])) ?>
                <?php if ($log['ip_address'] !== null): ?>
                  &middot; <?= e((string) $log['ip_address']) ?>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
