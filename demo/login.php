<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
if (demo_user()) demo_redirect('dashboard.php');
$error = '';
$identifier = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    demo_check_csrf();
    $identifier = strtolower(trim((string) ($_POST['identifier'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if ($identifier === '' || $password === '') {
        $error = 'Enter your username or email and password.';
    } else {
        $pdo = demo_db();
        $hash = hash('sha256', $identifier);
        $pdo->prepare('DELETE FROM demo_login_attempts WHERE attempted_at < ?')->execute([time() - 900]);
        $count = $pdo->prepare('SELECT COUNT(*) FROM demo_login_attempts WHERE identifier_hash = ? AND attempted_at >= ?');
        $count->execute([$hash, time() - 900]);
        if ((int) $count->fetchColumn() >= 5) {
            $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
        } else {
            $find = $pdo->prepare('SELECT id, password_hash FROM demo_users WHERE username = ? OR email = ? LIMIT 1');
            $find->execute([$identifier, $identifier]);
            $user = $find->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                $pdo->prepare('DELETE FROM demo_login_attempts WHERE identifier_hash = ?')->execute([$hash]);
                session_regenerate_id(true);
                $_SESSION['demo_user_id'] = (int) $user['id'];
                $_SESSION['demo_login_at'] = time();
                demo_redirect('dashboard.php');
            }
            $pdo->prepare('INSERT INTO demo_login_attempts (identifier_hash,attempted_at) VALUES (?,?)')->execute([$hash, time()]);
            $error = 'Those account details do not match.';
        }
    }
}
demo_header('Sign in', 'login');
?>
<div class="auth-layout"><aside class="auth-story"><span class="eyebrow">WELCOME BACK</span><h1>Your dashboard is one sign-in away.</h1><p>Enter the username or email and password you used when you registered.</p><div class="story-steps"><span>Account lookup</span><span>Password verification</span><span>Personal dashboard</span></div></aside><section class="auth-card"><span class="form-mark" aria-hidden="true">↗</span><h2>Sign in</h2><p>Access your SecureVote account.</p><?php if ($error !== ''): ?><div class="alert" role="alert"><?= demo_e($error) ?></div><?php endif; ?><form method="post" action="login.php" novalidate><input type="hidden" name="csrf" value="<?= demo_e(demo_csrf()) ?>"><label>Username or email<input name="identifier" autocomplete="username" required value="<?= demo_e($identifier) ?>"></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><button class="btn btn-primary form-submit" type="submit">Sign in to dashboard <span aria-hidden="true">→</span></button></form><p class="auth-switch">New to SecureVote? <a href="register.php">Create an account</a></p></section></div>
<?php demo_footer(); ?>
