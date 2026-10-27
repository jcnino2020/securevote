<?php
require_once __DIR__.'/../includes/bootstrap.php';
header('Cache-Control: no-store');$sent=false;$error='';
if(is_post()) {require_csrf();$email=input('email','');if(!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Enter a valid email address.';else{$allowed=sv_feature_allow('password-reset-request',5,900);if($allowed)sv_issue_password_reset($email);$sent=true;}}
$pageTitle='Recover your account';$layout='auth';require __DIR__.'/../includes/header.php';
?>
<section class="recovery-panel"><h1><?= e(sv_t('Forgot your password?')) ?></h1><p><?= e(sv_t('Enter the email address on your SecureVote account.')) ?></p>
<?php if($sent): ?><div class="notice" role="status"><?= e(sv_t('If an active account matches that address, a reset link will be sent. Check your inbox and spam folder. Links expire in 30 minutes.')) ?></div><?php else: ?>
<?php if($error): ?><p role="alert"><?= e(sv_t($error)) ?></p><?php endif; ?>
<form method="post" class="stack"><?= csrf_field() ?><label for="recovery-email"><?= e(sv_t('Account email')) ?></label><input class="input" id="recovery-email" name="email" type="email" autocomplete="email" maxlength="150" required><button class="btn btn--primary"><?= e(sv_t('Send reset link')) ?></button></form><?php endif; ?><p><a class="link" href="<?= e(url('auth/login.php')) ?>"><?= e(sv_t('Back to sign in')) ?></a></p><p class="text-sm"><?= e(sv_t('Need help accessing your email?')) ?> <a class="link" href="<?= e(url('help.php')) ?>"><?= e(sv_t('Contact election support')) ?></a>.</p></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
