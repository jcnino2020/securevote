<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * privacy.php
 *
 * Public Privacy Policy.
 * Complies with institutional data privacy regulations and Google OAuth 2.0
 * API Services User Data Policy (including Limited Use requirements).
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle   = 'Privacy Policy';
$activeNav   = 'privacy.php';
$layout      = 'portal';
$breadcrumb   = [
    ['label' => 'Home', 'href' => 'index.php'],
    ['label' => 'Privacy Policy'],
];

$pageActions = '<a class="btn btn--sm btn--primary" href="' . e(url('auth/login.php')) . '">'
    . sv_icon('lock', 16, 'btn__icon') . '<span>Sign In</span></a>';

require __DIR__ . '/includes/header.php';
?>

<!-- Privacy Policy Header Card -->
<section class="card" style="margin-bottom: var(--sp-6); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);">
  <div class="card__body" style="padding: clamp(var(--sp-6), 3vw, var(--sp-8));">
    <h1 style="font-size: clamp(1.75rem, 2.8vw, 2.25rem); font-weight: 800; color: var(--text); letter-spacing: -0.03em; margin-bottom: var(--sp-2);">
      SecureVote Privacy Policy
    </h1>
    <p class="text-muted" style="font-size: 0.95rem; max-width: 70ch; line-height: 1.6;">
      Last updated: <strong>September 2026</strong> &middot; Applies to all authorized students, faculty, and election participants using the SecureVote online voting platform.
    </p>
  </div>
</section>

<div class="grid-3" style="align-items: start; gap: var(--sp-6);">
  
  <!-- Left Side: Table of Contents & Quick Summary -->
  <aside class="policy-sidebar">
    <div class="card" style="margin-bottom: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div class="card__header" style="border-bottom: 1px solid var(--border); padding: var(--sp-4) var(--sp-5);">
        <h3 class="card__title" style="font-size: 0.95rem; font-weight: 700;"><?= sv_icon('file', 16, 'card__title-icon') ?> Policy Summary</h3>
      </div>
      <div class="card__body" style="padding: var(--sp-5);">
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: var(--sp-3); font-size: 0.875rem;">
          <li style="display: flex; gap: var(--sp-2); align-items: flex-start;">
            <span style="color: var(--success); flex-shrink: 0; margin-top: 2px;"><?= sv_icon('check-circle', 16) ?></span>
            <span><strong>Secret Ballot Guaranteed:</strong> Who voted is strictly separated from how you voted in counting queries.</span>
          </li>
          <li style="display: flex; gap: var(--sp-2); align-items: flex-start;">
            <span style="color: var(--success); flex-shrink: 0; margin-top: 2px;"><?= sv_icon('check-circle', 16) ?></span>
            <span><strong>Minimal Data:</strong> Only institutional identity details required for election verification are processed.</span>
          </li>
          <li style="display: flex; gap: var(--sp-2); align-items: flex-start;">
            <span style="color: var(--success); flex-shrink: 0; margin-top: 2px;"><?= sv_icon('check-circle', 16) ?></span>
            <span><strong>No Commercialization:</strong> We never sell, monetize, or share your data or Google information with advertisers.</span>
          </li>
          <li style="display: flex; gap: var(--sp-2); align-items: flex-start;">
            <span style="color: var(--success); flex-shrink: 0; margin-top: 2px;"><?= sv_icon('check-circle', 16) ?></span>
            <span><strong>Digital Receipts:</strong> Unique anonymous receipt tokens allow you to audit that your vote was cast.</span>
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
          <a href="#ballot-secrecy" class="link" style="text-decoration: none;">1. Secret Ballot Principle</a>
          <a href="#information-collected" class="link" style="text-decoration: none;">2. Information We Collect</a>
          <a href="#google-data" class="link" style="text-decoration: none;">3. Google OAuth 2.0 User Data</a>
          <a href="#how-we-use" class="link" style="text-decoration: none;">4. How We Use Information</a>
          <a href="#data-sharing" class="link" style="text-decoration: none;">5. Data Sharing & Third Parties</a>
          <a href="#security-safeguards" class="link" style="text-decoration: none;">6. Security Safeguards</a>
          <a href="#retention-rights" class="link" style="text-decoration: none;">7. Retention & Voter Rights</a>
          <a href="#contact" class="link" style="text-decoration: none;">8. Contact Information</a>
        </nav>
      </div>
    </div>
  </aside>

  <!-- Right Side: Detailed Policy Content -->
  <div class="policy-content" style="grid-column: span 2; display: flex; flex-direction: column; gap: var(--sp-6);">
    
    <!-- Section 1: Secret Ballot -->
    <section class="card" id="ballot-secrecy" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(37,99,235,0.1); color: var(--blue);">
          <?= sv_icon('shield', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">1. The Secret Ballot Principle</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; margin-bottom: var(--sp-4);">
        SecureVote is engineered with privacy-by-design at the database layer. In civic and university elections, ballot secrecy is an inviolable constitutional principle:
      </p>
      <div style="background: var(--surface-2); border-left: 4px solid var(--blue); padding: var(--sp-4); border-radius: var(--radius-xs); margin-bottom: var(--sp-4);">
        <p style="margin: 0; font-size: 0.9375rem; color: var(--text); line-height: 1.6;">
          <strong>Separation of Identity and Ballot Content:</strong> When you cast a ballot, the system verifies your eligibility and flags your account as having voted to prevent duplicate voting (1-person-1-vote). However, election tallying queries are aggregate-only. The system never publishes, links in results, or reconstructs which voter selected which candidate.
        </p>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem;">
        You receive an anonymous digital verification receipt (e.g. <code>SV-2026-000001</code>). This receipt confirms that a ballot was accepted into the digital ballot box without disclosing the candidates selected.
      </p>
    </section>

    <!-- Section 2: Information Collected -->
    <section class="card" id="information-collected" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(13,148,136,0.1); color: var(--teal);">
          <?= sv_icon('id-card', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">2. Information We Collect</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; margin-bottom: var(--sp-4);">
        To authenticate voters and guarantee 1-person-1-vote compliance, SecureVote processes the following categories of data:
      </p>
      <ul style="display: flex; flex-direction: column; gap: var(--sp-3); padding-left: var(--sp-5); color: var(--text-secondary); line-height: 1.6; font-size: 0.9375rem;">
        <li>
          <strong>Account & Institutional Identity:</strong> Full name, institutional university email address, assigned personal Voter Code (e.g. <code>VC-2026-XXXX</code>), username, and account status.
        </li>
        <li>
          <strong>Authentication Credentials:</strong> Passwords are cryptographically hashed using standard one-way algorithms (<code>bcrypt</code> with automated cost scaling). Plaintext passwords are never stored or logged.
        </li>
        <li>
          <strong>Participation Records:</strong> The election ID, submission timestamp, ballot reference token, and registration status.
        </li>
        <li>
          <strong>Security & Audit Logs:</strong> Client IP address (stored in masked format for user protection), browser User-Agent string, and security-relevant event timestamps (such as login attempts, password updates, and session terminations) to protect against brute-force attacks and session hijacking.
        </li>
      </ul>
    </section>

    <!-- Section 3: Google OAuth 2.0 User Data -->
    <section class="card" id="google-data" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(37,99,235,0.1); color: var(--blue);">
          <?= sv_icon('google', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">3. Google OAuth 2.0 User Data Policy</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; margin-bottom: var(--sp-4);">
        SecureVote offers single-sign-on (SSO) via Google OAuth 2.0 to simplify authentication for university students and staff.
      </p>
      
      <div style="background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius); padding: var(--sp-4); margin-bottom: var(--sp-4);">
        <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: var(--sp-2); color: var(--text);">Requested Google Scopes:</h4>
        <ul style="margin: 0; padding-left: var(--sp-5); font-size: 0.875rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 4px;">
          <li><code>openid</code> &mdash; Verifies your digital identity through OpenID Connect.</li>
          <li><code>email</code> &mdash; Obtains your primary email address for voter verification.</li>
          <li><code>profile</code> &mdash; Retrieves your basic name and avatar for display on your voter profile card.</li>
        </ul>
      </div>

      <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: var(--sp-2); color: var(--text);">Google Limited Use Compliance:</h4>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem; margin-bottom: var(--sp-4);">
        SecureVote's use and transfer of information received from Google APIs adheres to the 
        <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener noreferrer" class="link"><strong>Google API Services User Data Policy</strong></a>, including the Limited Use requirements:
      </p>
      <ul style="display: flex; flex-direction: column; gap: var(--sp-2); padding-left: var(--sp-5); color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6;">
        <li>We only use Google data to authenticate your account and provision your voter credentials.</li>
        <li>We do not transfer or disclose Google user data to external third parties.</li>
        <li>We do not use Google user data to serve advertisements, train artificial intelligence models, or build consumer profiles.</li>
        <li>Human staff cannot view your Google password, tokens, or external Google Drive/Gmail contents.</li>
      </ul>
    </section>

    <!-- Section 4: How We Use Information -->
    <section class="card" id="how-we-use" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(5,150,105,0.1); color: var(--success);">
          <?= sv_icon('check-circle', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">4. How We Use Your Information</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; margin-bottom: var(--sp-3);">
        All data collected by SecureVote is used exclusively for legitimate civic election operations:
      </p>
      <ol style="display: flex; flex-direction: column; gap: var(--sp-2); padding-left: var(--sp-5); color: var(--text-secondary); line-height: 1.6; font-size: 0.9375rem;">
        <li><strong>Voter Authentication:</strong> Confirming your identity before presenting the election ballot.</li>
        <li><strong>Enforcing One-Person-One-Vote:</strong> Ensuring each eligible voter can submit only one ballot per election.</li>
        <li><strong>Receipt Issuance & Verification:</strong> Providing you with verifiable confirmation of participation.</li>
        <li><strong>Security & Abuse Prevention:</strong> Detecting brute-force login attempts, cross-site request forgery (CSRF), and unauthorized admin access.</li>
        <li><strong>Auditing & Election Certification:</strong> Producing anonymized turnout statistics for election inspectors and committees.</li>
      </ol>
    </section>

    <!-- Section 5: Data Sharing -->
    <section class="card" id="data-sharing" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(217,119,6,0.1); color: var(--warning);">
          <?= sv_icon('ban', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">5. Data Sharing & Third Parties</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem;">
        <strong>We do not sell, rent, license, or monetize your personal information.</strong> Data is never shared with commercial brokers, advertising networks, or third-party marketing services. Information is shared only under strict institutional necessity:
      </p>
      <ul style="display: flex; flex-direction: column; gap: var(--sp-2); padding-left: var(--sp-5); color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6; margin-top: var(--sp-3);">
        <li><strong>University Election Committee:</strong> Certified aggregate tallies and voter participation lists (confirming turnout without candidate link).</li>
        <li><strong>Legal & Compliance Obligations:</strong> In response to official university disciplinary proceedings or court orders concerning election fraud.</li>
      </ul>
    </section>

    <!-- Section 6: Security Safeguards -->
    <section class="card" id="security-safeguards" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(79,70,229,0.1); color: var(--indigo);">
          <?= sv_icon('lock', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">6. Security Safeguards</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem; margin-bottom: var(--sp-3);">
        SecureVote employs state-of-the-art technical security controls to safeguard data integrity:
      </p>
      <div class="grid-2" style="gap: var(--sp-3);">
        <div style="padding: var(--sp-3) var(--sp-4); background: var(--surface-2); border-radius: var(--radius-sm); border: 1px solid var(--border);">
          <strong style="display:block; font-size: 0.875rem; color: var(--text); margin-bottom: 2px;">Transport Encryption</strong>
          <span style="font-size: 0.8125rem; color: var(--text-muted);">HTTPS with TLS 1.3 encryption on all connections.</span>
        </div>
        <div style="padding: var(--sp-3) var(--sp-4); background: var(--surface-2); border-radius: var(--radius-sm); border: 1px solid var(--border);">
          <strong style="display:block; font-size: 0.875rem; color: var(--text); margin-bottom: 2px;">SQL Injection Immune</strong>
          <span style="font-size: 0.8125rem; color: var(--text-muted);">Strict PDO prepared statements across 100% of queries.</span>
        </div>
        <div style="padding: var(--sp-3) var(--sp-4); background: var(--surface-2); border-radius: var(--radius-sm); border: 1px solid var(--border);">
          <strong style="display:block; font-size: 0.875rem; color: var(--text); margin-bottom: 2px;">Session Hardening</strong>
          <span style="font-size: 0.8125rem; color: var(--text-muted);">HttpOnly, SameSite=Lax cookies with 30-min idle timeout.</span>
        </div>
        <div style="padding: var(--sp-3) var(--sp-4); background: var(--surface-2); border-radius: var(--radius-sm); border: 1px solid var(--border);">
          <strong style="display:block; font-size: 0.875rem; color: var(--text); margin-bottom: 2px;">Atomic Ballot Locking</strong>
          <span style="font-size: 0.8125rem; color: var(--text-muted);">Database row locks prevent race-condition double-voting.</span>
        </div>
      </div>
    </section>

    <!-- Section 7: Retention & Rights -->
    <section class="card" id="retention-rights" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(13,148,136,0.1); color: var(--teal);">
          <?= sv_icon('archive', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">7. Data Retention & Voter Rights</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem; margin-bottom: var(--sp-3);">
        Election records and audit trails are retained in accordance with university record-retention policies (typically 1 academic year following election certification to allow for official appeals and audits).
      </p>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem;">
        <strong>Your Rights:</strong> You have the right to inspect your account registration status, review your personal participation history in your voter profile, and request account de-linking from Google OAuth.
      </p>
    </section>

    <!-- Section 8: Contact -->
    <section class="card" id="contact" style="padding: var(--sp-6); border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface);">
      <div style="display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-3);">
        <span style="padding: 8px; border-radius: var(--radius-sm); background: rgba(37,99,235,0.1); color: var(--blue);">
          <?= sv_icon('mail', 20) ?>
        </span>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text);">8. Contact Information</h2>
      </div>
      <p style="color: var(--text-secondary); line-height: 1.65; font-size: 0.9375rem; margin-bottom: var(--sp-4);">
        If you have questions regarding this Privacy Policy or wish to inquire about your voter data, please contact the election administrator:
      </p>
      <div style="background: var(--surface-2); padding: var(--sp-4); border-radius: var(--radius-sm); border: 1px solid var(--border); font-size: 0.875rem;">
        <p style="margin: 0 0 6px 0;"><strong>SecureVote University Election Committee</strong></p>
        <p style="margin: 0 0 4px 0; color: var(--text-muted);">Email: <a href="mailto:elections@university.edu.ph" class="link">elections@university.edu.ph</a></p>
        <p style="margin: 0; color: var(--text-muted);">Institution: University Campus Student Electoral Commission</p>
      </div>
    </section>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
