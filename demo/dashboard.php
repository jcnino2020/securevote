<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$user = demo_require_user();
if (time() - (int) ($_SESSION['demo_login_at'] ?? 0) > 7200) {
    unset($_SESSION['demo_user_id'], $_SESSION['demo_login_at']);
    demo_redirect('login.php');
}
demo_header('Dashboard', 'dashboard');
?>
<div class="dashboard"><div class="dashboard-intro"><div><span class="eyebrow">YOUR ACCOUNT</span><h1>Hi, <?= demo_e($user['first_name']) ?>.</h1><p>You are signed in. Your personal account details are shown below.</p></div><span class="active-badge">● &nbsp; Active account</span></div><div class="dashboard-grid"><section class="dashboard-card profile-card"><div class="card-icon" aria-hidden="true">◈</div><div><span class="eyebrow">ACCOUNT PROFILE</span><h2><?= demo_e($user['first_name'] . ' ' . $user['last_name']) ?></h2><p>@<?= demo_e($user['username']) ?></p></div><dl><div><dt>Email</dt><dd><?= demo_e($user['email']) ?></dd></div><div><dt>Joined</dt><dd><?= demo_e(date('M j, Y · g:i A', strtotime($user['created_at']))) ?></dd></div><div><dt>Session</dt><dd>Authenticated</dd></div></dl></section><section class="dashboard-card status-card"><span class="eyebrow">ACCOUNT STATUS</span><h2>Ready to go</h2><p>Your account details are available whenever you sign in.</p><div class="status-line"><span>Account created</span><strong>Complete ✓</strong></div><div class="status-line"><span>Password verified</span><strong>Complete ✓</strong></div><div class="status-line"><span>Current session</span><strong>Active ✓</strong></div></section></div><section class="next-card"><div><span class="eyebrow">YOUR NEXT STEP</span><h2>You're all set.</h2><p>You can sign out and return to your dashboard anytime with your username or email and password.</p></div></section></div>
<?php demo_footer(); ?>
