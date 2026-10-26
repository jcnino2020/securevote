<?php
require_once __DIR__.'/../includes/bootstrap.php';
header('Cache-Control: no-store');$sent=false;$error='';
if(is_post()) {require_csrf();$email=input('email','');if(!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Enter a valid email address.';else{$allowed=sv_feature_allow('password-reset-request',5,900);if($allowed)sv_issue_password_reset($email);$sent=true;}}
$pageTitle='Recover your account';$layout='auth';require __DIR__.'/../includes/header.php';
?>
<section class="recovery-panel"><h1>Forgot your password?</h1><p>Enter the email address on your SecureVote account.</p>
<?php if($sent): ?><div class="notice" role="status">If an active account matches that address, a reset link will be sent. Check your inbox and spam folder. Links expire in 30 minutes.</div><?php else: ?>
<?php if($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="stack"><?= csrf_field() ?><label for="recovery-email">Account email</label><input class="input" id="recovery-email" name="email" type="email" autocomplete="email" maxlength="150" required><button class="btn btn--primary">Send reset link</button></form><?php endif; ?><p><a class="link" href="<?= e(url('auth/login.php')) ?>">Back to sign in</a></p><p class="text-sm">Need help accessing your email? <a class="link" href="<?= e(url('help.php')) ?>">Contact election support</a>.</p></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
