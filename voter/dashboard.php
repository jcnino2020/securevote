<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * voter/dashboard.php
 *
 * The voter's home screen. Kept deliberately simple: what you can vote
 * in, what you have already voted in, and how voting works. No
 * administrative statistics, no charts, no clutter.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/voting.php';

$voter  = require_voter();
$pdo    = db();
$userId = (int) $voter['id'];

/* =====================================================================
 | The elections this voter is registered for
 * =================================================================== */

$stmt = $pdo->prepare(
    "SELECT e.*,
            ve.has_voted,
            ve.voted_at,
            b.ballot_reference,
            b.submitted_at AS ballot_submitted_at
       FROM voter_eligibility ve
       JOIN elections e ON e.id = ve.election_id
       LEFT JOIN ballots b ON b.election_id = e.id AND b.voter_id = ve.user_id
      WHERE ve.user_id = :user_id
      ORDER BY FIELD(e.status, 'open', 'scheduled', 'draft', 'closed', 'archived'),
               e.start_datetime DESC"
);
$stmt->execute([':user_id' => $userId]);
$elections = $stmt->fetchAll();

$awaiting = [];
$voted    = [];

foreach ($elections as $election) {
    $status = election_effective_status($election);

    if ((int) $election['has_voted'] === 1) {
        $voted[] = $election;
    } elseif ($status === 'open') {
        $awaiting[] = $election;
    }
}

/* =====================================================================
 | Header
 * =================================================================== */

$pageTitle    = 'Welcome, ' . (string) $voter['first_name'];
$pageSubtitle = 'Your elections, your ballot status and how voting works.';
$activeNav    = 'dashboard.php';
$breadcrumb   = [['label' => 'Dashboard', 'href' => 'voter/dashboard.php']];

require __DIR__ . '/../includes/header.php';
?>

<section class="stat-grid" aria-label="Your voting summary">
  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--navy"><?= sv_icon('vote', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Elections for you</p>
      <p class="stat-card__value"><?= e((string) count($elections)) ?></p>
      <p class="stat-card__meta">On your voter list</p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--warning"><?= sv_icon('clock', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Waiting for your vote</p>
      <p class="stat-card__value"><?= e((string) count($awaiting)) ?></p>
      <p class="stat-card__meta">Open right now</p>
    </div>
  </div>

  <div class="stat-card">
    <span class="stat-card__icon stat-card__icon--teal"><?= sv_icon('check-circle', 20) ?></span>
    <div class="stat-card__body">
      <p class="stat-card__label">Ballots you submitted</p>
      <p class="stat-card__value"><?= e((string) count($voted)) ?></p>
      <p class="stat-card__meta">Each one has a reference</p>
    </div>
  </div>
</section>

<?php if ($awaiting !== []): ?>
  <section class="card" aria-labelledby="awaiting-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="awaiting-heading">Open for your vote</h2>
        <p class="card__subtitle">These elections are accepting ballots right now.</p>
      </div>
      <span class="badge badge--success"><?= sv_icon('play', 14) ?>Open</span>
    </div>
    <div class="card__body stack">
      <?php foreach ($awaiting as $election): ?>
        <article class="review-item">
          <div class="row row--between">
            <div>
              <span class="review-item__choice"><?= e($election['title']) ?></span>
              <span class="review-item__meta">
                <?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?>
                &middot; <?= e(countdown_text($election)) ?>
              </span>
            </div>
            <div class="btn-group">
              <a class="btn btn--secondary btn--sm"
                 href="<?= e(url('voter/elections.php?election_id=' . (int) $election['id'])) ?>">
                Instructions
              </a>
              <a class="btn btn--primary"
                 href="<?= e(url('voter/ballot.php?election_id=' . (int) $election['id'])) ?>">
                <?= sv_icon('vote', 16, 'btn__icon') ?><span>Vote now</span>
              </a>
            </div>
          </div>
          <?php if (($election['description'] ?? '') !== ''): ?>
            <span class="review-item__meta"><?= e(str_limit((string) $election['description'], 200)) ?></span>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($voted !== []): ?>
  <section class="card" aria-labelledby="voted-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="voted-heading">Your participation</h2>
        <p class="card__subtitle">Ballots recorded on your account. The choice itself stays secret.</p>
      </div>
      <span class="badge badge--teal"><?= sv_icon('check-circle', 14) ?>Recorded</span>
    </div>
    <div class="card__body">
      <div class="table-wrap">
        <table class="table">
          <caption class="visually-hidden">Elections you have voted in, with the ballot reference and time</caption>
          <thead>
            <tr>
              <th scope="col">Election</th>
              <th scope="col">Reference</th>
              <th scope="col">Submitted</th>
              <th scope="col" class="table__actions-col">Receipt</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($voted as $election): ?>
              <tr>
                <td>
                  <span class="table__cell-strong"><?= e($election['title']) ?></span>
                  <span class="table__cell-sub"><?= e(ucfirst(election_effective_status($election))) ?></span>
                </td>
                <td class="mono"><?= e((string) $election['ballot_reference']) ?></td>
                <td>
                  <?= e(fmt_datetime((string) $election['ballot_submitted_at'])) ?>
                  <span class="table__cell-sub"><?= e(relative_time((string) $election['ballot_submitted_at'])) ?></span>
                </td>
                <td class="table__actions-col">
                  <div class="table__actions">
                    <a class="btn btn--secondary btn--sm" href="<?= e(url('voter/confirmation.php')) ?>">
                      <?= sv_icon('file', 14, 'btn__icon') ?><span>View</span>
                    </a>
                    <?php if (may_view_results($election, $voter, true)): ?>
                      <a class="btn btn--secondary btn--sm"
                         href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>">
                        <?= sv_icon('results', 14, 'btn__icon') ?><span>Results</span>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php endif; ?>

<div class="grid-2">
  <section class="card" aria-labelledby="instructions-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="instructions-heading">How to vote</h2>
        <p class="card__subtitle">Five steps, about two minutes.</p>
      </div>
    </div>
    <div class="card__body">
      <ol class="stack-sm">
        <li class="text-sm"><strong>1. Open the ballot.</strong> Pick an election that is open and press "Vote now".</li>
        <li class="text-sm"><strong>2. Select your candidates.</strong> Each position says how many you may choose.</li>
        <li class="text-sm"><strong>3. Press "Review my ballot".</strong> Nothing is recorded at this point.</li>
        <li class="text-sm"><strong>4. Check the review screen</strong> and correct anything that looks wrong.</li>
        <li class="text-sm"><strong>5. Submit the final ballot.</strong> After this it cannot be changed.</li>
      </ol>

      <div class="notice" style="margin-top:var(--sp-4)">
        <span class="notice__icon" aria-hidden="true"><?= sv_icon('info', 18) ?></span>
        <span>
          You may leave a position blank. A blank position is recorded as an abstention and does
          not affect your other choices.
        </span>
      </div>
    </div>
  </section>

  <section class="card" aria-labelledby="secret-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="secret-heading">Your ballot is secret</h2>
        <p class="card__subtitle">What SecureVote records about you.</p>
      </div>
      <span class="badge badge--teal"><?= sv_icon('lock', 14) ?>Confidential</span>
    </div>
    <div class="card__body stack-sm">
      <p class="text-sm">
        SecureVote records that your account was allowed to vote, and it records the selections
        that were counted. The results screens read only aggregates, so no page in this system
        can show who you voted for.
      </p>
      <ul class="stack-sm">
        <li class="text-sm"><?= sv_icon('check', 14) ?>One ballot per voter, enforced by a unique database constraint.</li>
        <li class="text-sm"><?= sv_icon('check', 14) ?>Your ballot is written inside a database transaction.</li>
        <li class="text-sm"><?= sv_icon('check', 14) ?>Your password is stored as a bcrypt hash, never as text.</li>
        <li class="text-sm"><?= sv_icon('check', 14) ?>Every sign-in and submitted ballot is written to the audit log.</li>
      </ul>
    </div>
    <div class="card__footer">
      <a class="btn btn--secondary btn--sm" href="<?= e(url('voter/profile.php')) ?>">
        <?= sv_icon('user', 14, 'btn__icon') ?><span>My profile and activity</span>
      </a>
    </div>
  </section>
</div>

<?php if ($elections === []): ?>
  <section class="card">
    <div class="empty-state">
      <span class="empty-state__icon"><?= sv_icon('vote', 26) ?></span>
      <p class="empty-state__title">You are not registered for any election yet</p>
      <p class="empty-state__text">
        The election administrator decides which elections each voter may take part in.
        If you expected to see an election here, please contact them.
      </p>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
