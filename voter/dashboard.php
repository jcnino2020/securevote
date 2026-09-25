<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/dashboard.php
 *
 * The voter's home screen (2026 Edition):
 *   - Personal Voter Identity & Voter Code Card
 *   - Active Elections open for voting
 *   - Cast Ballots History with Verification Tokens
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter  = require_voter();
$pdo    = db();
$userId = (int) $voter['id'];

// Load elections and this voter's ballot submissions
$stmt = $pdo->prepare(
    "SELECT e.*,
            b.id AS ballot_id,
            b.ballot_reference,
            b.submitted_at AS ballot_submitted_at,
            (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.status = 'active') AS candidate_count
       FROM elections e
       LEFT JOIN ballots b ON b.election_id = e.id AND b.voter_id = :user_id AND b.status = 'submitted'
      WHERE e.status IN ('open', 'scheduled', 'closed', 'archived')
      ORDER BY FIELD(e.status, 'open', 'scheduled', 'closed', 'archived'), e.start_datetime DESC"
);
$stmt->execute([':user_id' => $userId]);
$elections = $stmt->fetchAll();

$awaiting = [];
$voted    = [];

foreach ($elections as $election) {
    $status = election_effective_status($election);
    $hasVoted = !empty($election['ballot_reference']);

    if ($hasVoted) {
        $voted[] = $election;
    } elseif ($status === 'open') {
        $awaiting[] = $election;
    }
}

$pageTitle    = 'Welcome, ' . (string) $voter['first_name'];
$pageSubtitle = 'Your verified voter portal, active elections, and official ballot receipts.';
$activeNav    = 'dashboard.php';
$breadcrumb   = [['label' => 'Dashboard', 'href' => 'voter/dashboard.php']];

require __DIR__ . '/../includes/header.php';
?>

<!-- Voter Identity Card (2026 Refined Surface) -->
<section class="card" style="margin-bottom: var(--sp-6);">
  <div class="card__body" style="padding: var(--sp-5);">
    <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:var(--sp-4);">
      <div style="display:flex; align-items:center; gap:var(--sp-4);">
        <span class="avatar avatar--lg" aria-hidden="true">
          <?= initials($voter['first_name'], $voter['last_name']) ?>
        </span>
        <div>
          <div style="display:flex; align-items:center; gap:8px;">
            <h2 style="font-size:1.25rem; font-weight:700; margin:0; letter-spacing:-0.015em;"><?= e(user_full_name($voter)) ?></h2>
            <span class="badge badge--success">Verified Voter</span>
            <?php if (!empty($voter['google_id'])): ?>
              <span class="badge badge--primary" style="display:inline-flex; align-items:center; gap:4px;">
                <?= sv_icon('google', 12) ?> Google Linked
              </span>
            <?php endif; ?>
          </div>
          <p class="text-sm text-muted" style="margin:3px 0 0 0;">
            <span class="mono">@<?= e($voter['username']) ?></span> &middot; <?= e($voter['email']) ?>
          </p>
        </div>
      </div>

      <!-- Voter Code Display -->
      <div style="background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:var(--sp-2) var(--sp-4); text-align:right;">
        <div style="font-size:0.725rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">
          Your Personal Voter Code
        </div>
        <div style="display:flex; align-items:center; gap:8px; margin-top:2px;">
          <span class="mono" id="my-voter-code" style="font-size:1.05rem; font-weight:700; color:var(--blue); letter-spacing:0.04em;">
            <?= e((string) ($voter['voter_code'] ?? 'VC-2026-0000')) ?>
          </span>
          <button class="btn btn--ghost btn--sm" type="button" data-copy="#my-voter-code" style="padding:2px 6px;">
            <?= sv_icon('copy', 14) ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Stats Grid -->
<section class="stat-grid" aria-label="Your voting summary" style="margin-bottom: var(--sp-6);">
  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('vote', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Available Elections</p>
      <p class="stat-card__value"><?= e((string) count($elections)) ?></p>
      <p class="stat-card__meta">Active on university registry</p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--warning"><?= sv_icon('clock', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Waiting For Your Ballot</p>
      <p class="stat-card__value"><?= e((string) count($awaiting)) ?></p>
      <p class="stat-card__meta">Open right now</p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--teal"><?= sv_icon('check-circle', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Ballots Submitted</p>
      <p class="stat-card__value"><?= e((string) count($voted)) ?></p>
      <p class="stat-card__meta">Verified with digital receipts</p>
    </div>
  </div>
</section>

<?php if ($awaiting !== []): ?>
  <section class="card" aria-labelledby="awaiting-heading" style="margin-bottom: var(--sp-6);">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="awaiting-heading">Open for your vote</h2>
        <p class="card__subtitle">These elections are accepting ballots right now.</p>
      </div>
      <span class="badge badge--success"><?= sv_icon('vote', 14) ?> Open</span>
    </div>
    <div class="card__body stack">
      <?php foreach ($awaiting as $election): ?>
        <article class="review-item" style="padding:var(--sp-4); border-radius:10px; background:var(--bg-card); border:1px solid var(--border-color);">
          <div class="row row--between" style="align-items:center;">
            <div>
              <span class="review-item__choice" style="font-size:1.1rem; font-weight:700;"><?= e($election['title']) ?></span>
              <span class="review-item__meta" style="display:block; margin-top:4px;">
                <?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?>
                &middot; <strong style="color:#d97706;"><?= e(countdown_text($election)) ?></strong>
                &middot; <?= e((string) $election['position_count']) ?> positions
              </span>
            </div>
            <div class="btn-group">
              <a class="btn btn--secondary btn--sm"
                 href="<?= e(url('voter/elections.php?election_id=' . (int) $election['id'])) ?>">
                <?= sv_icon('info', 14) ?> <span>Details</span>
              </a>
              <a class="btn btn--primary"
                 href="<?= e(url('voter/ballot.php?election_id=' . (int) $election['id'])) ?>">
                <?= sv_icon('vote', 16, 'btn__icon') ?><span>Vote Now</span>
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($voted !== []): ?>
  <section class="card" aria-labelledby="voted-heading" style="margin-bottom: var(--sp-6);">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="voted-heading">Your cast ballots & receipts</h2>
        <p class="card__subtitle">Your participation is confirmed with official verification tokens.</p>
      </div>
      <span class="badge badge--teal"><?= sv_icon('check-circle', 14) ?> Recorded</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th scope="col">Election</th>
            <th scope="col">Receipt Token</th>
            <th scope="col">Submitted At</th>
            <th scope="col" class="table__actions-col">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($voted as $election): ?>
            <tr>
              <td class="table__cell-strong"><?= e($election['title']) ?></td>
              <td>
                <span class="reference-code reference-code--sm">
                  <?= e((string) $election['ballot_reference']) ?>
                </span>
              </td>
              <td><?= e(fmt_datetime((string) $election['ballot_submitted_at'])) ?></td>
              <td class="table__actions-col">
                <a class="btn btn--secondary btn--sm" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>">
                  <?= sv_icon('results', 14, 'btn__icon') ?><span>View Results</span>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
