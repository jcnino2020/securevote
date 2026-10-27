<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_guest();
header('Cache-Control: no-store');

$pending = sv_registration_pending();
if (!sv_registration_otp_enabled() || $pending === null) redirect('auth/register.php');

$error = '';
$notice = '';
if (is_post()) {
    require_csrf();
    $action = (string) input('action', 'verify');
    if ($action === 'resend') {
        if (time() - (int) $pending['last_sent'] < SV_REGISTRATION_OTP_RESEND_WAIT) {
            $error = sv_t('Please wait one minute before requesting another code.');
        } elseif (!sv_feature_allow('registration-otp-resend', 8, 3600)
            || !sv_feature_allow('registration-otp-email:' . hash('sha256', (string) $pending['form']['email']), 3, 3600)) {
            $error = sv_t('Too many verification emails requested. Please try again in an hour.');
        } elseif (!sv_registration_otp_resend()) {
            $error = sv_t('We could not send another code. Please try again.');
        } else {
            $notice = sv_t('A new code is on its way. Use the newest code in your inbox.');
        }
    } elseif ($action === 'verify') {
        $result = sv_registration_otp_check(trim((string) ($_POST['code'] ?? '')));
        if ($result === 'valid') {
            $form = $pending['form'];
            $created = sv_create_registered_voter($form, (string) $pending['password_hash']);
            if (!$created['ok']) {
                unset($_SESSION['pending_registration']);
                flash_error(sv_t($created['error']));
                redirect('auth/register.php');
            }
            unset($_SESSION['pending_registration']);
            if ($created['user']) {
                login_user($created['user']);
                flash_success(sprintf(sv_t('Welcome to SecureVote, %s! Your account is ready. An election administrator will assign the elections you can vote in. Your personal Voter Code is: %s'), $form['first_name'], $created['voter_code']));
                redirect('voter/dashboard.php');
            }
            flash_success(sv_t('Registration successful! Please sign in to your new voter account.'));
            redirect('auth/login.php');
        }
        $error = match ($result) {
            'expired' => sv_t('This code has expired. Request a new code below.'),
            'locked' => sv_t('Too many incorrect codes. Request a new code below.'),
            'missing' => sv_t('Your registration session ended. Please start again.'),
            default => sv_t('That code is incorrect. Check your email and try again.'),
        };
    } else {
        $error = sv_t('Choose a valid verification action.');
    }
    $pending = sv_registration_pending();
    if ($pending === null) redirect('auth/register.php');
}

$resendWait = max(0, SV_REGISTRATION_OTP_RESEND_WAIT - (time() - (int) $pending['last_sent']));
$layout = 'auth';
$pageTitle = 'Verify your email';
$bodyClass = 'page-verify-registration';
require __DIR__ . '/../includes/header.php';
?>

<section class="auth-story" aria-labelledby="auth-story-title">
  <a class="brand auth-story__brand" href="<?= e(url('index.php')) ?>"><?= sv_logo_mark(34) ?><span class="brand__word">Secure<span>Vote</span></span></a>
  <div class="auth-story__content">
    <h2 id="auth-story-title"><?= e(sv_t('One more step before your dashboard.')) ?></h2>
    <p><?= e(sv_t('We sent a short code to your email. Enter it to finish creating your voter account.')) ?></p>
    <div class="auth-story__steps"><span><?= e(sv_t('Create account')) ?></span><span><?= e(sv_t('Verify email')) ?></span><span><?= e(sv_t('View dashboard')) ?></span></div>
  </div>
  <div class="auth-story__mark" aria-hidden="true"><?= sv_icon('shield-check', 86) ?></div>
</section>

<div class="auth-main">
  <div class="auth-card registration-otp">
    <div class="auth-card__head">
      <a class="brand" href="<?= e(url('index.php')) ?>"><?= sv_logo_mark(32) ?><span class="brand__word">Secure<span>Vote</span></span></a>
      <div class="registration-otp__mark" aria-hidden="true"><?= sv_icon('mail', 25) ?></div>
      <h1 class="auth-card__title"><?= e(sv_t('Verify your email')) ?></h1>
      <p class="auth-card__text"><?= e(sv_t('Enter the 6-digit code we sent to')) ?> <strong class="registration-otp__email"><?= e((string) $pending['form']['email']) ?></strong>.</p>
    </div>

    <?php if ($error !== ''): ?><div class="alert alert--danger" role="alert"><span class="alert__icon" aria-hidden="true"><?= sv_icon('alert-triangle', 18) ?></span><div class="alert__text"><?= e($error) ?></div></div><?php endif; ?>
    <?php if ($notice !== ''): ?><div class="alert alert--success" role="status"><span class="alert__icon" aria-hidden="true"><?= sv_icon('check-circle', 18) ?></span><div class="alert__text"><?= e($notice) ?></div></div><?php endif; ?>

    <form class="form registration-otp__form" method="post" action="<?= e(post_url('auth/verify-registration.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">
      <div class="form-field">
        <label class="label" for="verification-code"><?= e(sv_t('Verification code')) ?></label>
        <input class="input registration-otp__code" id="verification-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required aria-describedby="registration-code-help" placeholder="000000">
        <p class="help" id="registration-code-help"><?= e(sv_t('The code expires in 10 minutes. Keep this page open while checking your email.')) ?></p>
      </div>
      <button class="btn btn--primary btn--lg btn--block" type="submit" data-submit-once data-submitting-label="<?= e(sv_t('Verifying…')) ?>"><?= sv_icon('shield-check', 18, 'btn__icon') ?><span><?= e(sv_t('Verify email')) ?></span></button>
    </form>

    <div class="registration-otp__resend">
      <p><?= e(sv_t('No email yet? Check spam or request another code.')) ?></p>
      <form method="post" action="<?= e(post_url('auth/verify-registration.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="resend">
        <button class="btn btn--secondary" type="submit" data-resend-code data-wait="<?= $resendWait ?>" data-countdown-label="<?= e(sv_t('Resend in %d seconds')) ?>"><?= e(sv_t('Resend code')) ?></button>
      </form>
    </div>
    <a class="registration-otp__change" href="<?= e(url('auth/register.php')) ?>"><?= e(sv_t('Use a different email address')) ?></a>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
