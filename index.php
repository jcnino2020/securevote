<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * index.php
 *
 * Welcome Portal & Civic Gateway (2026 Edition):
 *   - Signed-in admin -> redirects to admin/dashboard.php
 *   - Signed-in voter -> redirects to voter/dashboard.php
 *   - Public / Students -> High-trust civic gateway with Google OAuth 2.0,
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
$pageSubtitle = 'Official University Online Voting Portal & Digital Ballot Gateway.';
$activeNav    = '';
$breadcrumb   = [];
$layout       = 'portal';
$pageActions  = '<a class="btn btn--sm btn--primary" href="' . e(url('auth/login.php')) . '">'
    . sv_icon('lock', 16, 'btn__icon') . '<span>Sign In</span></a>'
    . '<a class="btn btn--sm btn--secondary topbar__register-btn" href="' . e(url('auth/register.php')) . '">'
    . sv_icon('user', 16, 'btn__icon') . '<span>Register</span></a>';

require __DIR__ . '/includes/header.php';
?>

<!-- Hero Application Panel (2026 Wide Modern Surface) -->
<section class="card hero-card">
  <div class="card__body hero-card__body">
    <div class="hero-grid">
      <div class="hero-main">
        <div class="hero-badge">
          <?= sv_icon('shield-check', 14) ?> Official University Voting System
        </div>
        <h1 class="hero-title">
          University Student Council Online Voting
        </h1>
        <p class="hero-desc">
          Authenticate securely with your <strong>Google Account</strong>, <strong>Personal Voter Code</strong>, or <strong>Email/Password</strong>. Ballots are recorded with cryptographic isolation, strict 1-person-1-vote constraints, and verifiable digital receipts.
        </p>
        
        <!-- Multi-Method Action Buttons (Curved Squares) -->
        <div class="hero-actions">
          <a class="btn btn--google btn--lg" href="<?= e(url('auth/google-login.php')) ?>" id="btn-hero-google">
            <?= sv_icon('google', 20, 'btn__icon') ?>
            <span>Continue with Google</span>
          </a>
          <a class="btn btn--primary btn--lg" href="<?= e(url('auth/login.php')) ?>">
            <?= sv_icon('lock', 18, 'btn__icon') ?>
            <span>Sign In to Vote</span>
          </a>
          <a class="btn btn--secondary btn--lg" href="<?= e(url('auth/register.php')) ?>">
            <?= sv_icon('user-plus', 18, 'btn__icon') ?>
            <span>Register Account</span>
          </a>
        </div>
      </div>

      <!-- Quick Security Pillars (Curved Panel) -->
      <div class="hero-pillars">
        <h2 class="hero-pillars__title">
          Voting Integrity Pillars
        </h2>
        <ul class="hero-pillars__list">
          <li>
            <span class="hero-pillars__icon" style="color: var(--primary);"><?= sv_icon('google', 18) ?></span>
            <span><strong>Google OAuth 2.0:</strong> Fast 1-click institutional verification.</span>
          </li>
          <li>
            <span class="hero-pillars__icon" style="color: var(--accent);"><?= sv_icon('key', 18) ?></span>
            <span><strong>Personal Voter Code:</strong> Unique passcode issued to every registered voter.</span>
          </li>
          <li>
            <span class="hero-pillars__icon" style="color: var(--success);"><?= sv_icon('shield-check', 18) ?></span>
            <span><strong>1 Person, 1 Vote:</strong> Database-enforced unique ballot constraints.</span>
          </li>
          <li>
            <span class="hero-pillars__icon" style="color: var(--primary);"><?= sv_icon('copy', 18) ?></span>
            <span><strong>Digital Receipts:</strong> Cryptographic token to verify your ballot was counted.</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- Active Elections -->
<section style="margin-bottom: var(--sp-8);">
  <div class="section-heading-row">
    <div>
      <h2 style="font-size: 1.35rem; font-weight: 800; margin: 0; letter-spacing: -0.02em;">Active Elections (Open for Voting)</h2>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 3px;">Sign in to cast your verified ballot in open university elections.</p>
    </div>
    <span class="badge badge--success" style="font-size: 0.84rem; padding: 5px 12px;"><?= count($openElections) ?> Open</span>
  </div>

  <?php if ($openElections === []): ?>
    <div class="card">
      <div class="empty-state">
        <span class="empty-state__icon"><?= sv_icon('clock', 30) ?></span>
        <h3 class="empty-state__title">No elections currently open for voting</h3>
        <p class="empty-state__text">Check back soon or view finalized results from past elections below.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="grid-3">
      <?php foreach ($openElections as $election): ?>
        <div class="card card--interactive" style="display: flex; flex-direction: column; justify-content: space-between;">
          <div class="card__header">
            <div class="card__headings">
              <h3 class="card__title" style="font-size: 1.1rem;"><?= e($election['title']) ?></h3>
              <p class="card__subtitle"><?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?></p>
            </div>
            <span class="badge badge--success"><?= sv_icon('vote', 12) ?> Open</span>
          </div>

          <div class="card__body">
            <?php if (!empty($election['description'])): ?>
              <p class="text-sm text-muted" style="margin-bottom: var(--sp-4); line-height: 1.55;">
                <?= e(str_limit((string) $election['description'], 160)) ?>
              </p>
            <?php endif; ?>

            <dl class="kv" style="font-size: 0.85rem;">
              <div class="kv__row">
                <dt class="kv__key text-muted">Contested Seats</dt>
                <dd class="kv__value" style="font-weight: 600;"><?= e((string) $election['position_count']) ?> Positions</dd>
              </div>
              <div class="kv__row">
                <dt class="kv__key text-muted">Active Candidates</dt>
                <dd class="kv__value" style="font-weight: 600;"><?= e((string) $election['candidate_count']) ?> Candidates</dd>
              </div>
              <div class="kv__row">
                <dt class="kv__key text-muted">Closes In</dt>
                <dd class="kv__value" style="color: var(--warning); font-weight: 700;"><?= e(countdown_text($election)) ?></dd>
              </div>
            </dl>
          </div>

          <div class="card__footer" style="display: flex; gap: var(--sp-3);">
            <a class="btn btn--primary" style="flex: 1;" href="<?= e(url('auth/login.php')) ?>">
              <?= sv_icon('vote', 16, 'btn__icon') ?><span>Sign In to Vote</span>
            </a>
            <a class="btn btn--secondary" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>" title="View tallies" aria-label="View tallies for <?= e($election['title']) ?>">
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
    <div class="section-heading-row">
      <div>
        <h2 style="font-size: 1.35rem; font-weight: 800; margin: 0; letter-spacing: -0.02em;">Concluded Elections & Results</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 3px;">Public tallies and certified winners.</p>
      </div>
    </div>

    <div class="grid-3">
      <?php foreach ($closedElections as $election): ?>
        <div class="card">
          <div class="card__header">
            <div class="card__headings">
              <h3 class="card__title" style="font-size: 1.05rem;"><?= e($election['title']) ?></h3>
              <p class="card__subtitle">Ended on <?= e(fmt_datetime($election['end_datetime'])) ?></p>
            </div>
            <span class="badge badge--neutral"><?= sv_icon('check-circle', 12) ?> Closed</span>
          </div>
          <div class="card__body">
            <p class="text-sm text-muted">
              Total ballots certified: <strong style="color: var(--text);"><?= e((string) $election['ballot_count']) ?> votes</strong>
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

<!-- 3-Step Guide to Voting (Curved Rectangles) -->
<section class="card" style="margin-bottom: var(--sp-6);">
  <div class="card__header">
    <div class="card__headings">
      <h2 class="card__title">How to Cast Your Ballot</h2>
      <p class="card__subtitle">3 simple steps to participate in university elections</p>
    </div>
  </div>
  <div class="card__body">
    <div class="grid-3">
      <div class="step-guide-card">
        <div class="step-guide-num">01</div>
        <h3 class="step-guide-title">Sign In</h3>
        <p class="text-sm text-muted" style="line-height: 1.55;">Use 1-click Google OAuth, your university Email/Password, or your personal Voter Code (<code>VC-2026-XXXX</code>).</p>
      </div>

      <div class="step-guide-card">
        <div class="step-guide-num">02</div>
        <h3 class="step-guide-title">Select Candidates</h3>
        <p class="text-sm text-muted" style="line-height: 1.55;">Review candidate platforms, inspect biographies, and select candidates with real-time selection limits.</p>
      </div>

      <div class="step-guide-card">
        <div class="step-guide-num">03</div>
        <h3 class="step-guide-title">Get Digital Receipt</h3>
        <p class="text-sm text-muted" style="line-height: 1.55;">Submit your ballot and receive an official verification token (<code>SV-2026-XXXXXX</code>) to prove your ballot was counted.</p>
      </div>
    </div>
  </div>
</section>

<!-- Verification Guarantee Callout -->
<div class="security-note" style="margin-bottom: var(--sp-6);">
  <?= sv_icon('shield-check', 20) ?>
  <div>
    <strong>Civic Trust &amp; Secret Ballot Guarantee:</strong>
    SecureVote uses database-level decoupling to ensure true ballot secrecy. Your candidate selections are stored anonymously in the digital ballot box, while your eligibility record confirms that you participated.
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
