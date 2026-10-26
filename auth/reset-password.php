<?php
require_once __DIR__.'/../includes/bootstrap.php';header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$token=input('token','');$success=false;$error='';$valid=(bool)preg_match('/^[a-f0-9]{64}$/D',$token);
if(is_post()){require_csrf();$password=is_string($_POST['password']??null)?$_POST['password']:'';$confirm=is_string($_POST['confirm']??null)?$_POST['confirm']:'';
 if(!sv_feature_allow('password-reset-consume',10,900))$error='Too many attempts. Wait 15 minutes and try again.';
 elseif(strlen($password)<12||strlen($password)>72)$error='Use a password of 12–72 characters.';
 elseif($password!==$confirm)$error='The passwords do not match.';
 elseif(!sv_reset_password($token,$password))$error='This reset link is invalid or expired. Request a new link.';
 else $success=true;
}
$pageTitle='Set a new password';$layout='auth';require __DIR__.'/../includes/header.php';
?>
<section class="recovery-panel"><h1>Set a new password</h1><?php if($success): ?><p role="status">Your password has been changed. Earlier sessions have been signed out.</p><a class="btn btn--primary" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
<?php elseif(!$valid): ?><p>This reset link is invalid.</p><a class="link" href="<?= e(url('auth/forgot-password.php')) ?>">Request a new link</a>
<?php else: ?><?php if($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><label for="new-password">New password (12–72 characters)</label><input class="input" type="password" id="new-password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required><label for="confirm-password">Confirm password</label><input class="input" type="password" id="confirm-password" name="confirm" autocomplete="new-password" required><button class="btn btn--primary">Save new password</button></form><a class="link" href="<?= e(url('auth/forgot-password.php')) ?>">Request a new link</a><?php endif; ?></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
