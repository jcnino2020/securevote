<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * index.php
 *
 * Welcome Portal & Voter Gateway (2026 Edition):
 *   - Signed-in admin -> redirects to admin/dashboard.php
 *   - Signed-in voter -> redirects to voter/dashboard.php
 *   - Public / Students -> Welcome gateway with Google OAuth 2.0,
 *     Email/Password sign-in, Voter Code authentication, and live election status.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/voting.php';

if (is_admin()) {
    redirect('admin/dashboard.php');
}
if (is_voter()) {
    redirect('voter/dashboard.php');
}

$pdo = db();

// Fetch active open elections
$openStmt = $pdo->query(
    "SELECT e.*,
            (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.status = 'active') AS candidate_count,
            (SELECT COUNT(*) FROM ballots b WHERE b.election_id = e.id AND b.status = 'submitted') AS ballot_count
       FROM elections e
      WHERE e.status = 'open'
      ORDER BY e.start_datetime DESC"
);
$openElections = $openStmt->fetchAll();

// Fetch concluded elections
$closedStmt = $pdo->query(
    "SELECT e.*,
            (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.status = 'active') AS candidate_count,
            (SELECT COUNT(*) FROM ballots b WHERE b.election_id = e.id AND b.status = 'submitted') AS ballot_count
       FROM elections e
      WHERE e.status = 'closed'
      ORDER BY e.end_datetime DESC
      LIMIT 6"
);
$closedElections = $closedStmt->fetchAll();

$pageTitle    = 'Welcome to SecureVote';
$pageSubtitle = 'University Online Voting Portal with Google OAuth & Voter Code Authentication.';
$activeNav    = 'dashboard.php';
$breadcrumb   = [];

require __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner (2026 Glassmorphic Card) -->
<section class="card" style="background: linear-gradient(135deg, rgba(37,99,235,0.07) 0%, rgba(30,58,138,0.03) 100%); border: 1px solid rgba(59,130,246,0.22); margin-bottom: var(--sp-8);">
  <div class="card__body" style="padding: var(--sp-8);">
    <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:var(--sp-6);">
      <div style="max-width: 660px;">
        <div style="display:inline-flex; align-items:center; gap:6px; background:var(--blue-50); color:var(--blue); padding:4px 14px; border-radius:999px; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:var(--sp-4); border:1px solid rgba(37,99,235,0.2);">
          <?= sv_icon('shield-check', 14) ?> Official University Voting System
        </div>
        <h1 style="font-size:clamp(1.8rem, 3vw, 2.4rem); font-weight:800; color:var(--text); line-height:1.2; margin-bottom:var(--sp-3); letter-spacing:-0.02em;">
          University Student Council Online Voting
        </h1>
        <p style="font-size:1.05rem; color:var(--text-muted); line-height:1.6; margin-bottom:var(--sp-6);">
          Authenticate securely with your <strong>Google Account</strong>, <strong>Personal Voter Code</strong>, or <strong>Email/Password</strong>. Ballots are linked with strict 1-person-1-vote database constraints and verifiable digital receipts.
        </p>
        
        <!-- Multi-Method Action Buttons -->
        <div style="display:flex; gap:var(--sp-3); flex-wrap:wrap; align-items:center;">
          <a class="btn btn--google btn--lg" href="<?= e(url('auth/google-login.php')) ?>" id="btn-hero-google">
            <?= sv_icon('google', 20, 'btn__icon') ?>
            <span>Continue with Google</span>
          </a>
          <a class="btn btn--primary btn--lg" href="<?= e(url('auth/login.php')) ?>">
            <?= sv_icon('lock', 18, 'btn__icon') ?>
            <span>Sign In with Voter Code</span>
          </a>
          <a class="btn btn--secondary btn--lg" href="<?= e(url('auth/register.php')) ?>">
            <?= sv_icon('user', 18, 'btn__icon') ?>
            <span>Register New Account</span>
          </a>
        </div>
      </div>

      <!-- Quick Security Badge Panel -->
      <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:var(--sp-6); width:100%; max-width:320px; box-shadow:var(--shadow-md);">
        <h3 style="font-size:0.85rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted); margin-bottom:var(--sp-4);">
          Voting Integrity Pillars
        </h3>
        <ul style="display:flex; flex-direction:column; gap:var(--sp-4); font-size:0.875rem; padding:0; margin:0; list-style:none;">
          <li style="display:flex; gap:10px; align-items:flex-start;">
            <span style="color:var(--blue); margin-top:2px; flex:none;"><?= sv_icon('google', 16) ?></span>
            <span><strong>Google OAuth 2.0:</strong> One-click institutional sign-in.</span>
          </li>
          <li style="display:flex; gap:10px; align-items:flex-start;">
            <span style="color:var(--blue); margin-top:2px; flex:none;"><?= sv_icon('key', 16) ?></span>
            <span><strong>Personal Voter Code:</strong> Unique passcode for every voter.</span>
          </li>
          <li style="display:flex; gap:10px; align-items:flex-start;">
            <span style="color:var(--success); margin-top:2px; flex:none;"><?= sv_icon('shield-check', 16) ?></span>
            <span><strong>1 Person, 1 Vote:</strong> Database-enforced unique constraints.</span>
          </li>
          <li style="display:flex; gap:10px; align-items:flex-start;">
            <span style="color:var(--teal); margin-top:2px; flex:none;"><?= sv_icon('copy', 16) ?></span>
            <span><strong>Digital Receipts:</strong> Verifiable cryptographic token.</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- Active Elections -->
<section style="margin-bottom: var(--sp-8);">
  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:var(--sp-4);">
    <div>
      <h2 style="font-size:1.35rem; font-weight:800; margin:0;">Active Elections (Open for Voting)</h2>
      <p style="color:var(--text-muted); font-size:0.9rem; margin-top:2px;">Sign in to cast your verified ballot in open university elections.</p>
    </div>
    <span class="badge badge--success" style="font-size:0.85rem; padding:5px 12px;"><?= count($openElections) ?> Open</span>
  </div>

  <?php if ($openElections === []): ?>
    <div class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('clock', 28) ?></span>
        <p class="empty-state__title">No elections currently open for voting</p>
        <p class="empty-state__text">Check back soon or view finalized results from past elections below.</p>
      </div>
    </div>
  <?php else: ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:var(--sp-5);">
      <?php foreach ($openElections as $election): ?>
        <div class="card card--interactive" style="display:flex; flex-direction:column; justify-content:space-between; border-top: 4px solid var(--blue);">
          <div class="card__header">
            <div class="card__headings">
              <h3 class="card__title" style="font-size:1.15rem;"><?= e($election['title']) ?></h3>
              <p class="card__subtitle"><?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?></p>
            </div>
            <span class="badge badge--success"><?= sv_icon('vote', 12) ?> Open</span>
          </div>

          <div class="card__body">
            <?php if (!empty($election['description'])): ?>
              <p class="text-sm text-muted" style="margin-bottom:var(--sp-4); line-height:1.5;">
                <?= e(str_limit((string) $election['description'], 160)) ?>
              </p>
            <?php endif; ?>

            <dl class="kv" style="font-size:0.85rem;">
              <div class="kv__row">
                <dt class="kv__key text-muted">Contested Seats</dt>
                <dd class="kv__value" style="font-weight:600;"><?= e((string) $election['position_count']) ?> Positions</dd>
              </div>
              <div class="kv__row">
                <dt class="kv__key text-muted">Active Candidates</dt>
                <dd class="kv__value" style="font-weight:600;"><?= e((string) $election['candidate_count']) ?> Candidates</dd>
              </div>
              <div class="kv__row">
                <dt class="kv__key text-muted">Closes In</dt>
                <dd class="kv__value" style="color:var(--warning); font-weight:700;"><?= e(countdown_text($election)) ?></dd>
              </div>
            </dl>
          </div>

          <div class="card__footer" style="display:flex; gap:var(--sp-2);">
            <a class="btn btn--primary" style="flex:1;" href="<?= e(url('auth/login.php')) ?>">
              <?= sv_icon('vote', 16, 'btn__icon') ?><span>Sign In to Vote</span>
            </a>
            <a class="btn btn--secondary" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>" title="View tallies">
              <?= sv_icon('results', 16) ?>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- Concluded Elections & Results -->
<?php if ($closedElections !== []): ?>
  <section style="margin-bottom: var(--sp-8);">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:var(--sp-4);">
      <div>
        <h2 style="font-size:1.3rem; font-weight:800; margin:0;">Concluded Elections & Results</h2>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-top:2px;">Public tallies and finalized winners.</p>
      </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:var(--sp-5);">
      <?php foreach ($closedElections as $election): ?>
        <div class="card">
          <div class="card__header">
            <div class="card__headings">
              <h3 class="card__title" style="font-size:1.05rem;"><?= e($election['title']) ?></h3>
              <p class="card__subtitle">Ended on <?= e(fmt_datetime($election['end_datetime'])) ?></p>
            </div>
            <span class="badge badge--neutral"><?= sv_icon('check-circle', 12) ?> Closed</span>
          </div>
          <div class="card__body">
            <p class="text-sm text-muted">
              Total ballots certified: <strong><?= e((string) $election['ballot_count']) ?> votes</strong>
            </p>
          </div>
          <div class="card__footer">
            <a class="btn btn--secondary btn--sm btn--block" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>">
              <?= sv_icon('results', 14, 'btn__icon') ?><span>View Certified Results</span>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- 3-Step Guide to Voting -->
<section class="card" style="margin-bottom: var(--sp-6);">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title">How to Cast Your Ballot</h2>
      <p class="card__subtitle">3 simple steps to participate in university elections</p>
    </div>
  </div>
  <div class="card__body">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:var(--sp-5);">
      <div style="padding:var(--sp-5); background:var(--surface-2); border-radius:var(--radius); border:1px solid var(--border);">
        <div style="font-size:1.6rem; font-weight:800; color:var(--blue); margin-bottom:var(--sp-2);">01</div>
        <h3 style="font-size:1.05rem; font-weight:700; margin-bottom:var(--sp-1);">Sign In</h3>
        <p class="text-sm text-muted">Use 1-click Google OAuth, your database Email/Password, or your personal Voter Code (<code>VC-2026-XXXX</code>).</p>
      </div>

      <div style="padding:var(--sp-5); background:var(--surface-2); border-radius:var(--radius); border:1px solid var(--border);">
        <div style="font-size:1.6rem; font-weight:800; color:var(--blue); margin-bottom:var(--sp-2);">02</div>
        <h3 style="font-size:1.05rem; font-weight:700; margin-bottom:var(--sp-1);">Select Candidates</h3>
        <p class="text-sm text-muted">Review platforms and select candidates for each position with live selection counters and limits.</p>
      </div>

      <div style="padding:var(--sp-5); background:var(--surface-2); border-radius:var(--radius); border:1px solid var(--border);">
        <div style="font-size:1.6rem; font-weight:800; color:var(--blue); margin-bottom:var(--sp-2);">03</div>
        <h3 style="font-size:1.05rem; font-weight:700; margin-bottom:var(--sp-1);">Get Digital Receipt</h3>
        <p class="text-sm text-muted">Submit your ballot and receive an official verification token (<code>SV-2026-XXXXXX</code>) to verify your vote was counted.</p>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
