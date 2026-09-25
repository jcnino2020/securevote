<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * terms.php
 *
 * Public Terms of Service.
 * Defines the terms and conditions governing the use of the SecureVote
 * voting platform, voter eligibility, and election integrity rules.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle    = 'Terms of Service';
$pageSubtitle = 'Rules and legal conditions governing voting, platform integrity, and digital ballots.';
$activeNav    = 'terms.php';
$layout       = 'portal';
$breadcrumb   = [
    ['label' => 'Home', 'href' => 'index.php'],
    ['label' => 'Terms of Service'],
];

$pageActions = '<a class="btn btn--sm btn--primary" href="' . e(url('auth/login.php')) . '">'
    . sv_icon('lock', 16, 'btn__icon') . '<span>Sign In</span></a>';

require __DIR__ . '/includes/header.php';
?>

<!-- Terms Header Card -->
<section class="card" style="margin-bottom: var(--sp-6); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);">
  <div class="card__body" style="padding: clamp(var(--sp-6), 3vw, var(--sp-8));">
    <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(5,150,105,0.08); color: var(--success); padding: 4px 14px; border-radius: var(--radius-full); font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--sp-3); border: 1px solid rgba(5,150,105,0.2);">
      <?= sv_icon('shield-check', 15) ?> Institutional Election Agreement
    </div>
    <h1 style="font-size: clamp(1.75rem, 2.8vw, 2.25rem); font-weight: 800; color: var(--text); letter-spacing: -0.03em; margin-bottom: var(--sp-2);">
      SecureVote Terms of Service
    </h1>
    <p class="text-muted" style="font-size: 0.95rem; max-width: 70ch; line-height: 1.6;">
      Last updated: <strong>September 2026</strong> &middot; Please review these terms carefully before accessing the platform, signing in with Google, or casting an official ballot.
    </p>
  </div>
</section>

<div class="grid-3" style="align-items: start; gap: var(--sp-6);">
  
  <!-- Left Side: Table of Contents & Quick Summary -->
  <aside class="policy-sidebar">
    <div class="card" style="margin-bottom: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div class="card__header" style="border-bottom: 1px solid var(--border); padding: var(--sp-4) var(--sp-5);">
        <h3 class="card__title" style="font-size: 0.95rem; font-weight: 700;"><?= sv_icon('file', 16, 'card__title-icon') ?> Key Highlights</h3>
      </div>
      <div class="card__body" style="padding: var(--sp-5);">
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: var(--sp-3); font-size: 0.875rem;">
          <li style="display: flex; gap: var(--sp-2); align-items: flex-start;">
            <span style="color: var(--blue); flex-shrink: 0; margin-top: 2px;"><?= sv_icon('vote', 16) ?></span>
            <span><strong>Strict 1-Vote Rule:</strong> Exactly one ballot per registered voter per election is permitted.</span>
          </li>
          <li style="display: flex; gap: var(--sp-2); align-items: flex-start;">
            <span style="color: var(--blue); flex-shrink: 0; margin-top: 2px;"><?= sv_icon('lock', 16) ?></span>
            <span><strong>Ballot Finality:</strong> Once submitted, a ballot cannot be recalled, edited, or re-voted.</span>
          </li>
          <li style="display: flex; gap: var(--sp-2); align-items: flex-start;">
            <span style="color: var(--danger); flex-shrink: 0; margin-top: 2px;"><?= sv_icon('ban', 16) ?></span>
            <span><strong>No Impersonation:</strong> Using another student's Google account or Voter Code is strictly prohibited.</span>
          </li>
          <li style="display: flex; gap: var(--sp-2); align-items: flex-start;">
            <span style="color: var(--teal); flex-shrink: 0; margin-top: 2px;"><?= sv_icon('shield-check', 16) ?></span>
            <span><strong>Receipt Verification:</strong> Digital tokens prove participation without revealing your selections.</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Quick Navigation -->
    <div class="card" style="border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div class="card__header" style="border-bottom: 1px solid var(--border); padding: var(--sp-3) var(--sp-5);">
        <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">Sections</span>
      </div>
      <div class="card__body" style="padding: var(--sp-3) var(--sp-5);">
        <nav style="display: flex; flex-direction: column; gap: 8px; font-size: 0.875rem;">
          <a href="#acceptance" class="link" style="text-decoration: none;">1. Acceptance of Terms</a>
          <a href="#eligibility" class="link" style="text-decoration: none;">2. Voter Eligibility & 1-Person-1-Vote</a>
          <a href="#accounts" class="link" style="text-decoration: none;">3. Accounts & Authentication</a>
          <a href="#conduct" class="link" style="text-decoration: none;">4. Prohibited Conduct</a>
          <a href="#finality" class="link" style="text-decoration: none;">5. Ballot Finality & Receipts</a>
          <a href="#policies" class="link" style="text-decoration: none;">6. Results Policy & Auditing</a>
          <a href="#availability" class="link" style="text-decoration: none;">7. System Availability</a>
          <a href="#liability" class="link" style="text-decoration: none;">8. Limitation of Liability</a>
          <a href="#contact" class="link" style="text-decoration: none;">9. Governing Body & Contact</a>
        </nav>
      </div>
    </div>
  </aside>

  <!-- Right Side: Detailed Terms Content -->
  <main style="grid-column: span 2; display: flex; flex-direction: column; gap: var(--sp-6);">
    
    <!-- Section 1: Acceptance -->
    <section class="card" id="acceptance" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(37,99,235,0.1); color: var(--blue);">
          <?= sv_icon('file', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">1. Acceptance of Terms</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem;">
        By accessing or using the SecureVote online voting platform, signing in with your Google account, personal Voter Code, or credentials, or submitting an official digital ballot, you acknowledge that you have read, understood, and agreed to be legally bound by these Terms of Service and the university election guidelines. If you do not agree to these terms, you must refrain from using the platform.
      </p>
    </section>

    <!-- Section 2: Eligibility & 1-Person-1-Vote -->
    <section class="card" id="eligibility" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(5,150,105,0.1); color: var(--success);">
          <?= sv_icon('user-check', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">2. Voter Eligibility & One-Person-One-Vote</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; margin-bottom: var(--sp-3); font-size: 0.9375rem;">
        Participation in university elections conducted via SecureVote is governed by strict democratic and cryptographic rules:
      </p>
      <ul style="display: flex; flex-direction: column; gap: var(--sp-3); padding-left: var(--sp-5); color: var(--text-secondary); line-height: 1.6; font-size: 0.9375rem;">
        <li>
          <strong>Registry Verification:</strong> Only authorized members who are listed on the official voter eligibility roll maintained by the election administrator are permitted to access and vote in given elections.
        </li>
        <li>
          <strong>Strict One-Person-One-Vote:</strong> Each eligible voter is entitled to submit exactly one (1) ballot per election. SecureVote enforces this rule cryptographically and through atomic database row locks and unique constraints (<code>uq_ballots_election_voter</code>).
        </li>
        <li>
          <strong>Status Restrictions:</strong> Accounts flagged as suspended, inactive, or archived cannot cast ballots.
        </li>
      </ul>
    </section>

    <!-- Section 3: Accounts & Authentication -->
    <section class="card" id="accounts" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(79,70,229,0.1); color: var(--indigo);">
          <?= sv_icon('lock', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">3. Accounts, Voter Codes & Google OAuth</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem; margin-bottom: var(--sp-3);">
        Voters may access the platform using approved university authentication methods:
      </p>
      <ul style="display: flex; flex-direction: column; gap: var(--sp-2); padding-left: var(--sp-5); color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6; margin-bottom: var(--sp-4);">
        <li><strong>Google OAuth 2.0:</strong> Single sign-on using your authorized institutional Google account.</li>
        <li><strong>Personal Voter Code:</strong> A confidential, system-issued verification token (e.g. <code>VC-2026-XXXX</code>).</li>
        <li><strong>Email/Username & Password:</strong> Standard institutional login credentials.</li>
      </ul>
      <div style="background: var(--surface-2); border-left: 4px solid var(--danger); padding: var(--sp-4); border-radius: var(--radius-xs);">
        <strong style="color: var(--danger); display: block; font-size: 0.9rem; margin-bottom: 2px;">Confidentiality Duty:</strong>
        <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.55;">
          You are solely responsible for safeguarding your credentials, Voter Code, and Google session. You must immediately notify the election committee if you suspect any unauthorized access to your account.
        </p>
      </div>
    </section>

    <!-- Section 4: Prohibited Conduct -->
    <section class="card" id="conduct" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(220,38,38,0.1); color: var(--danger);">
          <?= sv_icon('ban', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">4. Prohibited Conduct & Penalties</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem; margin-bottom: var(--sp-3);">
        Any attempt to compromise the fairness, security, or integrity of elections is strictly prohibited:
      </p>
      <ul style="display: flex; flex-direction: column; gap: var(--sp-2); padding-left: var(--sp-5); color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6;">
        <li><strong>Proxy Voting & Coercion:</strong> Voting on behalf of another student or coercing another voter into revealing their selections.</li>
        <li><strong>Automated Scripts & Bots:</strong> Using automated crawlers, bots, or scripts to submit ballots or flood the system.</li>
        <li><strong>System Tampering:</strong> Attempting SQL injection, cross-site scripting (XSS), credential stuffing, or brute-force password cracking.</li>
        <li><strong>Administrative Interference:</strong> Attempting to access the administrative dashboard, modify candidate records, or manipulate election tallies.</li>
      </ul>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem; margin-top: var(--sp-3);">
        Violations will result in immediate disqualification, account suspension, and referral to the University Disciplinary Board for academic disciplinary action.
      </p>
    </section>

    <!-- Section 5: Ballot Finality -->
    <section class="card" id="finality" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(13,148,136,0.1); color: var(--teal);">
          <?= sv_icon('check-circle', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">5. Ballot Finality & Digital Receipts</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem; margin-bottom: var(--sp-3);">
        Before casting your ballot, the platform presents an explicit <strong>Review & Confirmation</strong> screen displaying all selected candidates.
      </p>
      <ul style="display: flex; flex-direction: column; gap: var(--sp-2); padding-left: var(--sp-5); color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6;">
        <li><strong>Irrevocable Submission:</strong> Once you click "Submit Official Ballot", your choices are permanently sealed into the digital ballot box. Ballots cannot be modified, deleted, or re-voted under any circumstances.</li>
        <li><strong>Official Receipt Token:</strong> The system automatically produces an immutable digital receipt (e.g. <code>SV-2026-000001</code>). Retain this receipt as verifiable proof that your ballot was counted in the official tally.</li>
      </ul>
    </section>

    <!-- Section 6: Results Policy -->
    <section class="card" id="policies" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(37,99,235,0.1); color: var(--blue);">
          <?= sv_icon('results', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">6. Results Policy & Auditing</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem;">
        Results are released in accordance with the election's configured visibility policy:
      </p>
      <ul style="display: flex; flex-direction: column; gap: var(--sp-2); padding-left: var(--sp-5); color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6; margin-top: var(--sp-2);">
        <li><strong>After Close:</strong> Tallies are withheld until the voting window officially closes to prevent bandwagon effects or voter suppression.</li>
        <li><strong>After Vote:</strong> Tallies become available to individual voters once they have submitted their ballot.</li>
        <li><strong>Hidden:</strong> Results are kept confidential until officially certified by the student electoral commission.</li>
      </ul>
    </section>

    <!-- Section 7: System Availability -->
    <section class="card" id="availability" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(217,119,6,0.1); color: var(--warning);">
          <?= sv_icon('clock', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">7. System Availability & Service Levels</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem;">
        SecureVote operates on high-availability cloud infrastructure during scheduled election windows. However, the university election committee reserves the right to declare an emergency maintenance freeze if network security threats or DDoS incidents are detected. In such circumstances, voting window extensions may be authorized by official proclamation.
      </p>
    </section>

    <!-- Section 8: Liability -->
    <section class="card" id="liability" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(100,116,139,0.1); color: var(--text-muted);">
          <?= sv_icon('shield', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">8. Disclaimer & Limitation of Liability</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem;">
        SecureVote is provided "as is" and "as available" for authorized university student elections. While rigorous security mechanisms are deployed, the platform and its developers disclaim any liability for local client device malfunctions, browser extensions that tamper with web pages, or local internet connectivity dropouts during voting.
      </p>
    </section>

    <!-- Section 9: Governing Body -->
    <section class="card" id="contact" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(37,99,235,0.1); color: var(--blue);">
          <?= sv_icon('mail', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">9. Governing Body & Questions</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem; margin-bottom: var(--sp-4);">
        These terms are governed by the rules and regulations of the University Student Electoral Commission. For inquiries or dispute resolution:
      </p>
      <div style="background: var(--surface-2); padding: var(--sp-4); border-radius: var(--radius-sm); border: 1px solid var(--border); font-size: 0.875rem;">
        <p style="margin: 0 0 6px 0;"><strong>Office of the Student Electoral Commission</strong></p>
        <p style="margin: 0 0 4px 0; color: var(--text-muted);">Email: <a href="mailto:elections@university.edu.ph" class="link">elections@university.edu.ph</a></p>
        <p style="margin: 0; color: var(--text-muted);">Portal: <a href="<?= e(url('index.php')) ?>" class="link">SecureVote Public Gateway</a></p>
      </div>
    </main>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
