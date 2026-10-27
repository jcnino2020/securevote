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
            ve.id AS eligibility_id,
            b.id AS ballot_id,
            b.ballot_reference,
            b.submitted_at AS ballot_submitted_at,
            (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS position_count,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.status = 'active') AS candidate_count
       FROM elections e
       LEFT JOIN voter_eligibility ve ON ve.election_id = e.id AND ve.user_id = :eligibility_user_id
       LEFT JOIN ballots b ON b.election_id = e.id AND b.voter_id = :user_id AND b.status = 'submitted'
      WHERE e.status IN ('open', 'scheduled', 'closed', 'archived')
      ORDER BY FIELD(e.status, 'open', 'scheduled', 'closed', 'archived'),
               e.start_datetime DESC"
);
$stmt->execute([':eligibility_user_id' => $userId, ':user_id' => $userId]);
$elections = $stmt->fetchAll();

$metadataRows=sv_next_db()->query("SELECT m.* FROM election_metadata m JOIN elections e ON e.id=m.election_id WHERE e.status IN ('scheduled','open','closed','archived')")->fetchAll();$metadata=[];foreach($metadataRows as $row)$metadata[(int)$row['election_id']]=$row;
$filters=[];foreach(['organization','department','school_year'] as $key){$filters[$key]=input($key,'');}
$search=input('q','');
$elections=array_values(array_filter($elections,static function($e)use($metadata,$filters,$search){$m=$metadata[(int)$e['id']]??[];foreach($filters as $k=>$value)if($value!==''&&($m[$k]??'')!==$value)return false;return $search===''||mb_stripos($e['title'].' '.($e['description']??''),$search)!==false;}));
$focus = null;
if ($focusId > 0) {
    $focusStmt = $pdo->prepare(
        'SELECT e.*, ve.id AS eligibility_id, b.id AS ballot_id, b.ballot_reference, b.submitted_at AS ballot_submitted_at
           FROM elections e
           LEFT JOIN voter_eligibility ve ON ve.election_id = e.id AND ve.user_id = :eligibility_user_id
           LEFT JOIN ballots b ON b.election_id = e.id AND b.voter_id = :user_id AND b.status = \'submitted\'
          WHERE e.id = :id LIMIT 1'
    );
    $focusStmt->execute([':eligibility_user_id' => $userId, ':user_id' => $userId, ':id' => $focusId]);
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

<?php if($focus===null): ?><form method="get" class="election-filters"><div><label for="filter-search"><?= e(sv_t("Search elections")) ?></label><input class="input" id="filter-search" type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(sv_t("Election name or description")) ?>"></div><details class="advanced-filters" <?= array_filter($filters)?'open':'' ?>><summary><?= e(sv_t("More election filters")) ?></summary><div class="filter-fields"><?php foreach(['organization'=>'Organization','department'=>'Department','school_year'=>'School year'] as $key=>$label): $values=array_unique(array_filter(array_column($metadataRows,$key)));sort($values); ?><div><label for="filter-<?= e($key) ?>"><?= e(sv_t($label)) ?></label><select class="input" id="filter-<?= e($key) ?>" name="<?= e($key) ?>"><option value=""><?= e(sv_t("All")) ?></option><?php foreach($values as $value): ?><option <?= $filters[$key]===$value?'selected':'' ?>><?= e($value) ?></option><?php endforeach; ?></select></div><?php endforeach; ?></div></details><button class="btn btn--primary"><?= e(sv_t("Apply filters")) ?></button><a class="link" href="<?= e(url('voter/elections.php')) ?>"><?= e(sv_t("Clear filters")) ?></a></form><?php endif; ?>
<?php if ($focus !== null): ?>
  <?php
    $focusStatus = election_effective_status($focus);
    $focusVoted  = !empty($focus['ballot_reference']);
    $focusEligible = !empty($focus['eligibility_id']);
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
          <span class="badge badge--success"><?= sv_icon('check-circle', 12) ?> <?= e(sv_t("Ballot Cast")) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="card__body stack">
      <?php if (($focus['description'] ?? '') !== ''): ?>
        <p><?= e((string) $focus['description']) ?></p>
      <?php endif; ?>

      <dl class="kv">
        <div class="kv__row">
          <dt class="kv__key"><?= e(sv_t("Voting opens")) ?></dt>
          <dd class="kv__value"><?= e(fmt_datetime($focus['start_datetime'])) ?></dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key"><?= e(sv_t("Voting closes")) ?></dt>
          <dd class="kv__value">
            <?= e(fmt_datetime($focus['end_datetime'])) ?>
            <span class="text-muted text-sm">(<?= e(countdown_text($focus)) ?>)</span>
          </dd>
        </div>
        <div class="kv__row">
          <dt class="kv__key"><?= e(sv_t("Your Voting Status")) ?></dt>
          <dd class="kv__value">
            <?php if ($focusVoted): ?>
              <span class="badge badge--success" style="font-size:0.85rem;">
                <?= sv_icon('check-circle', 14) ?> <?= sprintf(sv_t('Submitted on %s'), e(fmt_datetime((string) $focus['ballot_submitted_at']))) ?>
              </span>
              <span class="text-sm text-muted" style="margin-left:8px;"><?= e(sv_t("Receipt:")) ?> <code class="mono"><?= e((string) $focus['ballot_reference']) ?></code></span>
            <?php elseif ($focusStatus === 'open' && $focusEligible): ?>
              <span class="badge badge--primary" style="font-size:0.85rem;">
                <?= sv_icon('shield-check', 14) ?> <?= e(sv_t("Eligible & Ready to Vote")) ?>
              </span>
            <?php elseif (!$focusEligible): ?>
              <span class="badge badge--neutral"><?= e(sv_t("Not on voter list")) ?></span>
            <?php else: ?>
              <span class="badge badge--neutral"><?= e(sv_t(ucfirst($focusStatus))) ?></span>
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
        <?php if ($focusStatus === 'open' && $focusEligible && !$focusVoted): ?>
          <a class="btn btn--primary"
             href="<?= e(url('voter/ballot.php?election_id=' . (int) $focus['id'])) ?>">
            <?= sv_icon('vote', 16, 'btn__icon') ?><span><?= e(sv_t("Cast Your Ballot Now")) ?></span>
          </a>
        <?php elseif ($focusVoted): ?>
          <a class="btn btn--secondary"
             href="<?= e(url('voter/confirmation.php?election_id=' . (int)$focus['id'])) ?>">
            <?= sv_icon('file', 16, 'btn__icon') ?><span><?= e(sv_t("View My Ballot Receipt")) ?></span>
          </a>
        <?php endif; ?>

        <a class="btn btn--secondary"
           href="<?= e(url('voter/results.php?election_id=' . (int) $focus['id'])) ?>">
          <?= sv_icon('results', 16, 'btn__icon') ?><span><?= e(sv_t("View Results Tally")) ?></span>
        </a>

        <a class="btn btn--ghost" href="<?= e(url('guide.php?election_id='.(int)$focus['id'])) ?>"><?= e(sv_t("Election guide")) ?></a>
      </div>
    </div>
  </section>

  <!-- Contested Positions & Candidates -->
  <section class="card" aria-labelledby="positions-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="positions-heading"><?= e(sv_t("Contested Positions and Candidates")) ?></h2>
        <p class="card__subtitle"><?= e(sv_t("Candidate roster for this election.")) ?></p>
      </div>
    </div>
    <div class="card__body">
      <?php if ($focusBallot === []): ?>
        <div class="empty-state empty-state--inline">
          <span class="empty-state__icon"><?= sv_icon('positions', 24) ?></span>
          <p class="empty-state__title"><?= e(sv_t("No positions published yet")) ?></p>
          <p class="empty-state__text">
            <?= e(sv_t("An administrator is currently configuring candidates for this election.")) ?>
          </p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th scope="col">#</th>
                <th scope="col"><?= e(sv_t("Position")) ?></th>
                <th scope="col"><?= e(sv_t("Description")) ?></th>
                <th scope="col"><?= e(sv_t("Max Selections")) ?></th>
                <th scope="col"><?= e(sv_t("Active Candidates")) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($focusBallot as $index => $entry): ?>
                <tr>
                  <td class="mono"><?= e((string) ($index + 1)) ?></td>
                  <td><span class="table__cell-strong"><?= e($entry['position']['name']) ?></span></td>
                  <td><?= e($entry['position']['description'] ?: '—') ?></td>
                  <td>
                    <?= e((string) $entry['position']['max_selections']) ?>
                    <span class="table__cell-sub">
                      <?= (int) $entry['position']['max_selections'] === 1
                          ? e(sv_t('Single choice'))
                          : sprintf(sv_t('Choose up to %d'), (int) $entry['position']['max_selections']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge badge--neutral"><?= sprintf(sv_t('%d candidates'), count($entry['candidates'])) ?></span>
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
        <p class="empty-state__title"><?= e(sv_t("No active elections found")) ?></p>
        <p class="empty-state__text">
          <?= e(sv_t("There are currently no active or scheduled elections on the university registry.")) ?>
        </p>
      </div>
    </section>
  <?php else: ?>
    <section class="student-section" aria-labelledby="all-elections-heading">
      <div class="student-section__heading">
        <div>
          <h2 id="all-elections-heading"><?= e(sv_t("All elections")) ?></h2>
          <p><?= sprintf(count($elections) === 1 ? sv_t('%d election on the registry.') : sv_t('%d elections on the registry.'), count($elections)) ?></p>
        </div>
      </div>
      <div class="election-explorer" data-election-explorer>
        <div class="election-explorer__filters" role="group" aria-label="<?= e(sv_t("Filter elections by status")) ?>">
          <button type="button" data-election-filter="all" aria-pressed="true"><?= e(sv_t("All")) ?></button>
          <button type="button" data-election-filter="open" aria-pressed="false"><?= e(sv_t("Open now")) ?></button>
          <button type="button" data-election-filter="scheduled" aria-pressed="false"><?= e(sv_t("Upcoming")) ?></button>
          <button type="button" data-election-filter="past" aria-pressed="false"><?= e(sv_t("Past")) ?></button>
        </div>
      </div>
      <div class="voter-election-list" data-election-list>
        <?php foreach ($elections as $election): ?>
          <?php
            $status   = election_effective_status($election);
            $hasVoted = !empty($election['ballot_reference']);
            $canVote  = ($status === 'open' && !empty($election['eligibility_id']) && !$hasVoted);
          ?>
          <article class="voter-election" data-election-card data-status="<?= e($status) ?>" data-title="<?= e($election['title']) ?>">
            <div class="voter-election__body">
              <?= election_badge($status) ?>
              <h3><?= e($election['title']) ?></h3>
              <p><?= e(fmt_range($election['start_datetime'], $election['end_datetime'])) ?></p>
              <span class="voter-election__meta">
                <?= sprintf(sv_t('%d positions'), (int) $election['position_count']) ?> &middot;
                <?= sprintf(sv_t('%d candidates'), (int) $election['candidate_count']) ?>
                <?php if ($hasVoted): ?> &middot; <?= e(sv_t("Ballot submitted")) ?><?php elseif ($status === 'open' && empty($election['eligibility_id'])): ?> &middot; <?= e(sv_t("Not on voter list")) ?><?php elseif ($status === 'open'): ?> &middot; <?= e(countdown_text($election)) ?><?php endif; ?>
              </span>
            </div>
            <div class="voter-election__actions">
              <?php if ($canVote): ?>
                <a class="btn btn--primary" href="<?= e(url('voter/ballot.php?election_id=' . (int) $election['id'])) ?>"><?= e(sv_t("Open ballot")) ?> <?= sv_icon('arrow-right', 16) ?></a>
              <?php elseif ($hasVoted): ?>
                <a class="btn btn--secondary" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>"><?= e(sv_t("View results")) ?> <?= sv_icon('arrow-right', 16) ?></a>
              <?php elseif (in_array($status, ['closed', 'archived'], true) && may_view_results($election, $voter, false)): ?>
                <a class="btn btn--secondary" href="<?= e(url('voter/results.php?election_id=' . (int) $election['id'])) ?>"><?= e(sv_t("Final summary")) ?> <?= sv_icon('arrow-right', 16) ?></a>
              <?php endif; ?>
              <a class="btn btn--ghost" href="<?= e(url('voter/elections.php?election_id=' . (int) $election['id'])) ?>"><?= e(sv_t("Election details")) ?></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="election-explorer__empty" data-election-empty hidden>
        <?= sv_icon('search', 25) ?>
        <h3><?= e(sv_t("No elections match")) ?></h3>
        <p><?= e(sv_t("Try another search or view all elections.")) ?></p>
        <button type="button" class="btn btn--secondary" data-election-reset><?= e(sv_t("Clear filters")) ?></button>
      </div>
    </section>
  <?php endif; ?>

  <!-- Security Guarantee Pillar -->
  <section class="card" aria-labelledby="how-heading">
    <div class="card__header">
      <div class="card__headings">
        <h2 class="card__title" id="how-heading"><?= e(sv_t("How your vote is protected")) ?></h2>
        <p class="card__subtitle"><?= e(sv_t("Review your ballot status and receipt after voting.")) ?></p>
      </div>
    </div>
    <div class="card__body">
      <ul class="stack-sm">
        <li class="text-sm" style="display:flex; gap:8px; align-items:center;">
          <?= sv_icon('google', 16) ?>
          <span><strong><?= e(sv_t("Ways to sign in:")) ?></strong> <?= e(sv_t("Sign in with Google OAuth 2.0, Database Email/Password, or your personal Voter Code.")) ?></span>
        </li>
        <li class="text-sm" style="display:flex; gap:8px; align-items:center;">
          <?= sv_icon('shield-check', 16) ?>
          <span><strong><?= e(sv_t("One ballot per voter:")) ?></strong> <?= e(sv_t("Database unique constraint strictly blocks duplicate ballots from the same voter account.")) ?></span>
        </li>
        <li class="text-sm" style="display:flex; gap:8px; align-items:center;">
          <?= sv_icon('copy', 16) ?>
          <span><strong><?= e(sv_t("Your receipt:")) ?></strong> <?= sprintf(sv_t("Receive an immutable receipt code (e.g. %s) to verify your ballot was counted."), '<code>SV-2026-XXXXXX</code>') ?></span>
        </li>
      </ul>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
