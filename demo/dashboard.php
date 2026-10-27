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
<div class="dashboard"><div class="dashboard-intro"><div><span class="eyebrow">YOUR DEMO SPACE</span><h1>Hi, <?= demo_e($user['first_name']) ?>.</h1><p>You are signed in to your SecureVote demo account. This dashboard shows details for your session only.</p></div><span class="active-badge">● &nbsp; Active demo account</span></div><div class="dashboard-grid"><section class="dashboard-card profile-card"><div class="card-icon" aria-hidden="true">◈</div><div><span class="eyebrow">ACCOUNT PROFILE</span><h2><?= demo_e($user['first_name'] . ' ' . $user['last_name']) ?></h2><p>@<?= demo_e($user['username']) ?></p></div><dl><div><dt>Email</dt><dd><?= demo_e($user['email']) ?></dd></div><div><dt>Joined</dt><dd><?= demo_e(date('M j, Y · g:i A', strtotime($user['created_at']))) ?></dd></div><div><dt>Session</dt><dd>Authenticated</dd></div></dl></section><section class="dashboard-card status-card"><span class="eyebrow">ELECTION ACCESS</span><h2>No elections assigned</h2><p>This is an authentication demo. Demo accounts do not receive live voter eligibility and cannot cast ballots.</p><div class="status-line"><span>Account created</span><strong>Complete ✓</strong></div><div class="status-line"><span>Password verified</span><strong>Complete ✓</strong></div><div class="status-line"><span>Live election eligibility</span><strong>Not assigned</strong></div></section></div><section class="next-card"><div><span class="eyebrow">NEXT STEP</span><h2>Explore the full SecureVote website.</h2><p>The main site has election information and the official voter experience. Your demo login remains separate.</p></div><a class="btn btn-outline" href="../" target="_blank" rel="noopener">Open main site ↗</a></section></div>
<?php demo_footer(); ?>
