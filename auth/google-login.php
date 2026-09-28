<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * auth/google-login.php
 *
 * Initiates the Google OAuth 2.0 authentication flow.
 * Redirects to Google's OAuth 2.0 authorization endpoint, or provides
 * instant developer simulation if Google API credentials are not yet set.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_guest();

// Generate CSRF state token
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$redirectUri = (sv_is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('auth/google-callback.php');

$clientId = defined('GOOGLE_CLIENT_ID') ? trim(GOOGLE_CLIENT_ID) : '';

// If real Google Client ID is configured, redirect directly to Google OAuth 2.0
if ($clientId !== '' && !str_starts_with($clientId, 'YOUR_')) {
    $params = [
        'client_id'             => $clientId,
        'redirect_uri'          => $redirectUri,
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'state'                 => $state,
        'access_type'           => 'online',
        'prompt'                => 'select_account',
        'include_granted_scopes' => 'true',
    ];

    $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    header('Location: ' . $googleAuthUrl);
    exit;
}

// ---------------------------------------------------------------------
// Developer / Lab Test Simulation Mode
// ---------------------------------------------------------------------
$layout       = 'auth';
$pageTitle    = 'Google Sign-In';
$bodyClass    = 'page-login';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-card" style="max-width: 480px; margin: 0 auto; text-align: center;">
  <div class="auth-card__head">
    <div style="display:inline-flex; align-items:center; justify-content:center; width:48px; height:48px; border-radius:50%; background:rgba(37,99,235,0.1); margin-bottom:var(--sp-2);">
      <?= sv_icon('google', 24) ?>
    </div>
    <h1 class="auth-card__title">Google Authentication</h1>
    <p class="auth-card__text">
      Select an authorized university Google account to sign in or register automatically.
    </p>
  </div>

  <div class="card" style="background:var(--bg-card); text-align:left; border:1px solid var(--border-color); box-shadow:var(--shadow-sm); margin-bottom:clamp(8px, 1.4vh, 16px);">
    <div class="card__header" style="border-bottom:1px solid var(--border-color); padding:clamp(8px, 1.2vh, 12px) 14px;">
      <span class="card__title" style="font-size:0.82rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">
        Choose Google Account
      </span>
    </div>
    <div class="card__body" style="padding:var(--sp-3);">
      <div style="display:flex; flex-direction:column; gap:var(--sp-2);">
        <!-- Sample Student Profile 1 -->
        <a href="<?= e(url('auth/google-callback.php?sim=1&email=juan.delacruz@university.edu.ph&first_name=Juan&last_name=Dela+Cruz&state=' . $state)) ?>"
           class="choice choice--interactive" style="display:flex; align-items:center; gap:var(--sp-3); padding:var(--sp-3); border-radius:8px; border:1px solid var(--border-color); text-decoration:none; color:inherit; transition:all 0.2s ease;">
          <div style="width:40px; height:40px; border-radius:50%; background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">
            JD
          </div>
          <div style="flex:1;">
            <div style="font-weight:600; font-size:0.95rem;">Juan Dela Cruz</div>
            <div style="font-size:0.8rem; color:var(--text-muted);">juan.delacruz@university.edu.ph</div>
          </div>
          <span class="badge badge--primary" style="font-size:0.75rem;">Verified</span>
        </a>

        <!-- Sample Student Profile 2 -->
        <a href="<?= e(url('auth/google-callback.php?sim=1&email=maria.santos@university.edu.ph&first_name=Maria&last_name=Santos&state=' . $state)) ?>"
           class="choice choice--interactive" style="display:flex; align-items:center; gap:var(--sp-3); padding:var(--sp-3); border-radius:8px; border:1px solid var(--border-color); text-decoration:none; color:inherit; transition:all 0.2s ease;">
          <div style="width:40px; height:40px; border-radius:50%; background:#7c3aed; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">
            MS
          </div>
          <div style="flex:1;">
            <div style="font-weight:600; font-size:0.95rem;">Maria Santos</div>
            <div style="font-size:0.8rem; color:var(--text-muted);">maria.santos@university.edu.ph</div>
          </div>
          <span class="badge badge--primary" style="font-size:0.75rem;">Verified</span>
        </a>

        <!-- Sample Student Profile 3 -->
        <a href="<?= e(url('auth/google-callback.php?sim=1&email=christian.nino@university.edu.ph&first_name=Christian&last_name=Nino&state=' . $state)) ?>"
           class="choice choice--interactive" style="display:flex; align-items:center; gap:var(--sp-3); padding:var(--sp-3); border-radius:8px; border:1px solid var(--border-color); text-decoration:none; color:inherit; transition:all 0.2s ease;">
          <div style="width:40px; height:40px; border-radius:50%; background:#059669; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">
            CN
          </div>
          <div style="flex:1;">
            <div style="font-weight:600; font-size:0.95rem;">Christian Nino</div>
            <div style="font-size:0.8rem; color:var(--text-muted);">christian.nino@university.edu.ph</div>
          </div>
          <span class="badge badge--primary" style="font-size:0.75rem;">Verified</span>
        </a>
      </div>
    </div>
  </div>

  <!-- Custom Google Email Form -->
  <form method="get" action="<?= e(url('auth/google-callback.php')) ?>" class="form" style="text-align:left; margin-bottom:clamp(8px, 1.4vh, 16px);">
    <input type="hidden" name="sim" value="1">
    <input type="hidden" name="state" value="<?= e($state) ?>">

    <div class="form-field">
      <label class="label" for="custom_email">Or sign in with any Google Email:</label>
      <div style="display:flex; gap:var(--sp-2);">
        <input type="email" id="custom_email" name="email" class="input" placeholder="student@university.edu" required style="flex:1;">
        <button type="submit" class="btn btn--primary">
          <?= sv_icon('google', 16, 'btn__icon') ?>
          <span>Continue</span>
        </button>
      </div>
    </div>
  </form>

  <div class="text-sm text-muted">
    <a href="<?= e(url('auth/login.php')) ?>" class="link">
      <?= sv_icon('arrow-left', 14) ?> Back to standard sign in
    </a>
  </div>

  <p class="text-muted text-xs auth-footnote-sub">
    <a href="<?= e(url('terms.php')) ?>" class="link" style="color:inherit; text-decoration:underline;">Terms of Service</a> &middot; 
    <a href="<?= e(url('privacy.php')) ?>" class="link" style="color:inherit; text-decoration:underline;">Privacy Policy</a>
  </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
