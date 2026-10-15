<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/elections.php
 *
 * Voter Election Directory & Ballot Access (2026 Edition):
 *   - Displays all elections this voter is eligible for
 *   - Clear visual status of whether ballot has been cast
 *   - Access to ballot submission or digital receipt & results
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter  = require_voter();
$pdo    = db();
$userId = (int) $voter['id'];

$focusId = int_input('election_id', 0);

/* =====================================================================
 | Load Elections with Voter Participation Status
 * =================================================================== */

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
      ORDER BY FIELD(e.status, 'open', 'scheduled', 'closed', 'archived'),
               e.start_datetime DESC"
);
$stmt->execute([':user_id' => $userId]);
$elections = $stmt->fetchAll();

$focus = null;
if ($focusId > 0) {
    $focusStmt = $pdo->prepare(
        'SELECT e.*, b.id AS ballot_id, b.ballot_reference, b.submitted_at AS ballot_submitted_at
           FROM elections e
           LEFT JOIN ballots b ON b.election_id = e.id AND b.voter_id = :user_id AND b.status = \'submitted\'
          WHERE e.id = :id LIMIT 1'
    );
    $focusStmt->execute([':user_id' => $userId, ':id' => $focusId]);
    $focus = $focusStmt->fetch();

    if (!$focus) {
        sv_error_page(
            404,
            'Election not found',
            'That election does not exist, or it has been removed.',
            [['label' => 'Back to elections', 'href' => url('voter/elections.php')]]
        );
    }
}

$focusBallot = null;
if ($focus !== null) {
    $focusBallot = sv_build_ballot((int) $focus['id']);
}

/* =====================================================================
 | Header
 * =================================================================== */

$pageTitle  = $focus !== null ? (string) $focus['title'] : 'Elections';
$activeNav  = 'elections.php';
$breadcrumb = $focus !== null
    ? [['label' => 'Dashboard', 'href' => 'voter/dashboard.php'], ['label' => 'Elections', 'href' => 'voter/elections.php'], ['label' => 'Details']]
    : [['label' => 'Dashboard', 'href' => 'voter/dashboard.php'], ['label' => 'Elections']];

require __DIR__ . '/../includes/header.php';
?>

<?php if ($focus !== null): ?>
  <?php
    $focusStatus = election_effective_status($focus);
    $focusVoted  = !empty($focus['ballot_reference']);
    $policyNote  = results_policy_notice($focus, $focusVoted);
  ?>
  <section class="card" style="margin-bottom: var(--sp-6);">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title"><?= e($focus['title']) ?></h2>
        <p class="card__subtitle"><?= e(fmt_range($focus['start_datetime'], $focus['end_datetime'])) ?></p>
      </div>
      <div style="display:flex; gap:8px; align-items:center;">
        <?= election_badge($focusStatus) ?>
        <?php if ($focusVoted): ?>
          <span class="badge badge--success"><?= sv_icon('check-circle', 12) ?> Ballot Cast</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="card__body stack">
      <?php if (($focus['description'] ?? '') !== ''): ?>
        <p><?= e((string) $focus['description']) ?></p>
      <?php endif; ?>

      <dl class="kv">
        <div class="kv__row">
          <dt class="kv__key">Voting opens</dt>
          <dd class="kv__value"><?= e(fmt_datetime($focus['start_datetime'])) ?></dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Voting closes</dt>
          <dd class="kv__value">
            <?= e(fmt_datetime($focus['end_datetime'])) ?>
            <span class="text-muted text-sm">(<?= e(countdown_text($focus)) ?>)</span>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key">Your Voting Status</dt>
          <dd class="kv__value">
            <?php if ($focusVoted): ?>
              <span class="badge badge--success" style="font-size:0.85rem;">
                <?= sv_icon('check-circle', 14) ?> Submitted on <?= e(fmt_datetime((string) $focus['ballot_submitted_at'])) ?>
              </span>
              <span class="text-sm text-muted" style="margin-left:8px;">Receipt: <code class="mono"><?= e((string) $focus['ballot_reference']) ?></code></span>
            <?php elseif ($focusStatus === 'open'): ?>
              <span class="badge badge--primary" style="font-size:0.85rem;">
                <?= sv_icon('shield-check', 14) ?> Eligible & Ready to Vote
              </span>
            <?php else: ?>
              <span class="badge badge--neutral"><?= ucfirst($focusStatus) ?></span>
            <?php endif; ?>
          </dd>
        </div>
      </dl>

      <?php if ($policyNote !== null): ?>
        <div class="notice">
          <span class="notice__icon" aria-hidden="true"><?= sv_icon('eye', 18) ?></span>
          <span><?= e($policyNote) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <div class="card__footer">
      <div class="row">
        <?php if ($focusStatus === 'open' && !$focusVoted): ?>
          <a class="btn btn--primary"
             href="<?= e(url('voter/ballot.php?election_id=' . (int) $focus['id'])) ?>">
            <?= sv_icon('vote', 16, 'btn__icon') ?><span>Cast Your Ballot Now</span>
          </a>
        <?php elseif ($focusVoted): ?>
          <a class="btn btn--secondary"
             href="<?= e(url('voter/confirmation.php')) ?>">
            <?= sv_icon('file', 16, 'btn__icon') ?><span>View My Ballot Receipt</span>
          </a>
        <?php endif; ?>

        <a class="btn btn--secondary"
           href="<?= e(url('voter/results.php?election_id=' . (int) $focus['id'])) ?>">
          <?= sv_icon('results', 16, 'btn__icon') ?><span>View Results Tally</span>
        </a>

        <a class="btn btn--ghost" href="<?= e(url('voter/elections.php')) ?>">Back to all elections</a>
      </div>
    </div>
  </section>

  <!-- Contested Positions & Candidates -->
  <section class="card" aria-labelledby="positions-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="positions-heading">Contested Positions and Candidates</h2>
        <p class="card__subtitle">Candidate roster for this election.</p>
      </div>
    </div>
    <div class="card__body">
      <?php if ($focusBallot === []): ?>
        <div class="empty-state empty-state--inline">
          <span class="empty-state__icon"><?= sv_icon('positions', 24) ?></span>
          <p class="empty-state__title">No positions published yet</p>
          <p class="empty-state__text">
            An administrator is currently configuring candidates for this election.
          </p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th scope="col">#</th>
                <th scope="col">Position</th>
                <th scope="col">Description</th>
                <th scope="col">Max Selections</th>
                <th scope="col">Active Candidates</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($focusBallot as $index => $entry): ?>
                <tr>
                  <td class="mono"><?= e((string) ($index + 1)) ?></td>
                  <td class="table__cell-strong"><?= e($entry['position']['name']) ?></td>
                  <td><?= e($entry['position']['description'] ?: '—') ?></td>
                  <td>
                    <?= e((string) $entry['position']['max_selections']) ?>
                    <span class="table__cell-sub">
                      <?= (int) $entry['position']['max_selections'] === 1
                          ? 'Single choice'
                          : 'Choose up to ' . (int) $entry['position']['max_selections'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge badge--neutral"><?= count($entry['candidates']) ?> candidates</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php else: ?>

  <?php if ($elections === []): ?>
    <section class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('vote', 26) ?></span>
        <p class="empty-state__title">No active elections found</p>
        <p class="empty-state__text">
          There are currently no active or scheduled elections on the university registry.
        </p>
      </div>
    </section>
  <?php else: ?>
    <section class="student-section" aria-labelledby="all-elections-heading">
      <div class="student-section__heading">
        <div>
          <h2 id="all-elections-heading">All elections</h2>
          <p><?= e((string) count($elections)) ?> election<?= count($elections) === 1 ? '' : 's' ?> available for your account.</p>
        </div>
      </div>
      <div class="election-explorer" data-election-explorer>
        <div class="election-explorer__top">
          <label class="election-explorer__search">
            <?= sv_icon('search', 18) ?>
            <span class="visually-hidden">Search elections</span>
            <input type="search" data-election-search placeholder="Search elections" autocomplete="off">
          </label>
          <p class="election-explorer__count" data-election-count role="status" aria-live="polite"></p>
        </div>
        <div class="election-explorer__filters" role="group" aria-label="Filter elections by status">
          <button type="button" data-election-filter="all" aria-pressed="true">All</button>
          <button type="button" data-election-filter="open" aria-pressed="false">Open now</button>
          <button type="button" data-election-filter="scheduled" aria-pressed="false">Upcoming</button>
          <button type="button" data-election-filter="past" aria-pressed="false">Past</button>
        </div>
      </div>
      <div class="voter-election-list" data-election-list>
        <?php foreach ($elections as $election): ?>
          <?php
            $status   = election_effective_status($election);
            $hasVoted = !empty($election['ballot_reference']);
            $canVote  = ($status === 'open' && !$hasVoted);
          ?>
          <article class="voter-election" data-election-card data-status="<?= e($status) ?>" data-title="<?= e($election['title']) ?>">
            <div class="voter-election__body">
              <?= election_badge($status) ?>
              <h3><?= e($election['title']) ?></h3>
              <p><?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?></p>
              <span class="voter-election__meta">
                <?= e((string) $election['position_count']) ?> positions &middot;
                <?= e((string) $election['candidate_count']) ?> candidates
                <?php if ($hasVoted): ?> &middot; Ballot submitted<?php elseif ($status === 'open'): ?> &middot; <?= e(countdown_text($election)) ?><?php endif; ?>
              </span>
            </div>
            <div class="voter-election__actions">
              <?php if ($canVote): ?>
                <a class="btn btn--primary" href="<?= e(url('voter/ballot.php?election_id=' . (int) $election['id'])) ?>">Open ballot <?= sv_icon('arrow-right', 16) ?></a>
              <?php elseif ($hasVoted): ?>
                <a class="btn btn--secondary" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>">View results <?= sv_icon('arrow-right', 16) ?></a>
              <?php elseif (in_array($status, ['closed', 'archived'], true) && may_view_results($election, $voter, false)): ?>
                <a class="btn btn--secondary" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>">Final summary <?= sv_icon('arrow-right', 16) ?></a>
              <?php endif; ?>
              <a class="btn btn--ghost" href="<?= e(url('voter/elections.php?election_id=' . (int) $election['id'])) ?>">Election details</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="election-explorer__empty" data-election-empty hidden>
        <?= sv_icon('search', 25) ?>
        <h3>No elections match</h3>
        <p>Try another search or view all elections.</p>
        <button type="button" class="btn btn--secondary" data-election-reset>Clear filters</button>
      </div>
    </section>
  <?php endif; ?>

  <!-- Security Guarantee Pillar -->
  <section class="card" aria-labelledby="how-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="how-heading">How your vote is protected</h2>
        <p class="card__subtitle">Your ballot is private, and you receive a receipt after voting.</p>
      </div>
    </div>
    <div class="card__body">
      <ul class="stack-sm">
        <li class="text-sm" style="display:flex; gap:8px; align-items:center;">
          <?= sv_icon('google', 16) ?>
          <span><strong>Ways to sign in:</strong> Sign in with Google OAuth 2.0, Database Email/Password, or your personal Voter Code.</span>
        </li>
        <li class="text-sm" style="display:flex; gap:8px; align-items:center;">
          <?= sv_icon('shield-check', 16) ?>
          <span><strong>One ballot per voter:</strong> Database unique constraint strictly blocks duplicate ballots from the same voter account.</span>
        </li>
        <li class="text-sm" style="display:flex; gap:8px; align-items:center;">
          <?= sv_icon('copy', 16) ?>
          <span><strong>Your receipt:</strong> Receive an immutable receipt code (e.g. <code>SV-2026-XXXXXX</code>) to verify your ballot was counted.</span>
        </li>
      </ul>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
