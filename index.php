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
      WHERE e.status IN ('open', 'scheduled')
        AND e.start_datetime <= NOW()
        AND e.end_datetime > NOW()
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
      WHERE e.status IN ('closed', 'archived')
         OR (e.status IN ('open', 'scheduled') AND e.end_datetime <= NOW())
      ORDER BY e.end_datetime DESC
      LIMIT 6"
);
$closedElections = $closedStmt->fetchAll();

$pageTitle   = 'Welcome to SecureVote';
$activeNav   = '';
$breadcrumb  = [];
$layout      = 'portal';
$bodyClass   = 'page-home';
$pageActions  = '<a class="btn btn--sm btn--primary" href="' . e(url('auth/login.php')) . '">'
    . sv_icon('lock', 16, 'btn__icon') . '<span>' . e(sv_t('Sign In')) . '</span></a>'
    . '<a class="btn btn--sm btn--secondary topbar__register-btn" href="' . e(url('auth/register.php')) . '">'
    . sv_icon('user', 16, 'btn__icon') . '<span>' . e(sv_t('Register')) . '</span></a>';

require __DIR__ . '/includes/header.php';
?>

<section class="campus-hero" aria-labelledby="campus-hero-title">
  <div class="campus-hero__copy">
    <h1 id="campus-hero-title"><?= sv_t("Your campus.<br><span>Your say.</span>") ?></h1>
    <p><?= e(sv_t("Choose the students who will represent you. SecureVote makes it easy to explore an election, make your choices, and keep a receipt after you vote.")) ?></p>
    <div class="campus-hero__actions">
      <a class="btn btn--primary btn--lg" href="<?= e(url('auth/login.php')) ?>">
        <?= sv_icon('vote', 18, 'btn__icon') ?><span><?= e(sv_t("Sign in to vote")) ?></span>
      </a>
      <a class="btn btn--secondary btn--lg" href="#open-elections"><?= e(sv_t("Explore elections")) ?> <?= sv_icon('arrow-right', 17) ?></a>
    </div>
    <p class="campus-hero__join"><?= e(sv_t("New to SecureVote?")) ?> <a href="<?= e(url('auth/register.php')) ?>"><?= e(sv_t("Create your voter account")) ?></a></p>
  </div>
  <div class="campus-hero__art" aria-hidden="true">
    <div class="campus-hero__orbit campus-hero__orbit--one"></div>
    <div class="campus-hero__orbit campus-hero__orbit--two"></div>
    <div class="ballot-illustration">
      <div class="ballot-illustration__head">
        <?= sv_logo_mark(28) ?>
        <span><?= e(sv_t("YOUR VOTE")) ?></span>
      </div>
      <div class="ballot-illustration__title"></div>
      <div class="ballot-illustration__line ballot-illustration__line--short"></div>
      <div class="ballot-illustration__option"><i></i><b></b></div>
      <div class="ballot-illustration__option is-selected"><i><?= sv_icon('check', 16) ?></i><b></b></div>
      <div class="ballot-illustration__option"><i></i><b></b></div>
      <div class="ballot-illustration__foot"><span></span><span></span></div>
    </div>
    <div class="campus-hero__sticker"><?= sv_icon('check-circle', 22) ?><span><?= e(sv_t("Choice made!")) ?></span></div>
  </div>
</section>

<section class="student-steps" id="how-it-works" aria-labelledby="how-it-works-heading">
  <h2 id="how-it-works-heading"><?= e(sv_t("How it works")) ?></h2>
  <div><span>1</span><div><strong><?= e(sv_t("Explore")) ?></strong><small><?= e(sv_t("See what is on the ballot")) ?></small></div></div>
  <div><span>2</span><div><strong><?= e(sv_t("Choose")) ?></strong><small><?= e(sv_t("Pick your candidates")) ?></small></div></div>
  <div><span>3</span><div><strong><?= e(sv_t("Confirm")) ?></strong><small><?= e(sv_t("Review and get a receipt")) ?></small></div></div>
</section>

<section class="home-section" id="open-elections" aria-labelledby="open-elections-heading">
  <div class="home-section__head">
    <div>
      <h2 id="open-elections-heading"><?= e(sv_t("Elections open now")) ?></h2>
      <p><?= e(sv_t("Explore what is happening and sign in when you are ready to vote.")) ?></p>
    </div>
    <span class="home-count"><?= sprintf(sv_t('%d open'), count($openElections)) ?></span>
  </div>

  <?php if ($openElections === []): ?>
    <div class="home-empty">
      <span class="home-empty__icon" aria-hidden="true"><?= sv_icon('clock', 28) ?></span>
      <div>
        <h3><?= e(sv_t("No election is open right now")) ?></h3>
        <p><?= e(sv_t("When voting starts, you will find the election here. You can still create an account ahead of time.")) ?></p>
      </div>
      <a class="btn btn--secondary" href="<?= e(url('auth/register.php')) ?>"><?= e(sv_t("Create an account")) ?></a>
    </div>
  <?php else: ?>
    <div class="election-grid">
      <?php foreach ($openElections as $election): ?>
        <article class="election-tile">
          <div class="election-tile__top">
            <span class="badge badge--success"><?= sv_icon('vote', 13) ?> <?= e(sv_t("Voting open")) ?></span>
            <span class="election-tile__countdown"><?= e(countdown_text($election)) ?></span>
          </div>
          <h3><?= e($election['title']) ?></h3>
          <p class="election-tile__date"><?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?></p>
          <?php if (!empty($election['description'])): ?>
            <p class="election-tile__description"><?= e(str_limit((string) $election['description'], 160)) ?></p>
          <?php endif; ?>
          <div class="election-tile__facts">
            <span><?= sprintf(sv_t('%d positions'), (int) $election['position_count']) ?></span>
            <span><?= sprintf(sv_t('%d candidates'), (int) $election['candidate_count']) ?></span>
          </div>
          <div class="election-tile__actions">
            <a class="btn btn--primary" href="<?= e(url('auth/login.php')) ?>"><?= e(sv_t("Sign in to vote")) ?> <?= sv_icon('arrow-right', 16) ?></a>
            <a class="btn btn--ghost" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>" aria-label="<?= sprintf(sv_t('View results for %s'), $election['title']) ?>"><?= e(sv_t("Results")) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($closedElections !== []): ?>
<section class="home-section" aria-labelledby="past-elections-heading">
  <div class="home-section__head">
    <div>
      <h2 id="past-elections-heading"><?= e(sv_t("Past elections")) ?></h2>
      <p><?= e(sv_t("Look back at completed elections and published results.")) ?></p>
    </div>
  </div>
  <div class="past-election-list">
    <?php foreach ($closedElections as $election): ?>
      <article class="past-election">
        <div>
          <h3><?= e($election['title']) ?></h3>
          <p><?= sprintf(sv_t('Ended %s · %d ballots'), fmt_datetime($election['end_datetime']), (int) $election['ballot_count']) ?></p>
        </div>
        <a class="btn btn--secondary btn--sm" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>"><?= e(sv_t("View results")) ?> <?= sv_icon('arrow-right', 15) ?></a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="home-help" aria-labelledby="home-help-heading">
  <div>
    <h2 id="home-help-heading"><?= e(sv_t("New to voting here?")) ?></h2>
    <p><?= e(sv_t("Sign in with Google, a voter code, or your account details. Read about candidates, make your selections, and review everything before submitting.")) ?></p>
    <a href="<?= e(url('auth/register.php')) ?>" class="btn btn--secondary"><?= e(sv_t("Get started")) ?> <?= sv_icon('arrow-right', 16) ?></a>
  </div>
  <div class="home-help__trust">
    <?= sv_icon('shield-check', 24) ?>
    <div>
      <strong><?= e(sv_t("Your choices stay private")) ?></strong>
      <p><?= e(sv_t("SecureVote records your participation separately from your candidate selections. After you vote, you receive a receipt code for your records.")) ?></p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
