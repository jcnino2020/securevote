<?php
declare(strict_types=1);

/** Email verification for password-based voter registration. */
const SV_REGISTRATION_OTP_TTL = 600;
const SV_REGISTRATION_OTP_ATTEMPTS = 5;
const SV_REGISTRATION_OTP_RESEND_WAIT = 60;

function sv_registration_otp_enabled(): bool
{
    return sv_config('SV_REGISTRATION_OTP_ENABLED', '0') === '1';
}

function sv_registration_mail_ready(): bool
{
    $port = (int) sv_config('SV_SMTP_PORT');
    return filter_var(sv_config('SV_MAIL_FROM'), FILTER_VALIDATE_EMAIL) !== false
        && sv_config('SV_SMTP_HOST') !== ''
        && sv_config('SV_SMTP_USER') !== ''
        && sv_config('SV_SMTP_PASS') !== ''
        && $port >= 1 && $port <= 65535
        && in_array(strtolower(sv_config('SV_SMTP_SECURE', 'ssl')), ['ssl', 'tls'], true);
}

/** An optional transport makes the OTP lifecycle testable without sending email. */
function sv_registration_send_code(string $email, string $name, string $code, ?callable $transport = null): bool
{
    if ($transport !== null) return (bool) $transport($email, $name, $code);
    if (!sv_registration_mail_ready()) return false;

    require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/Exception.php';
    require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/SMTP.php';

    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host = sv_config('SV_SMTP_HOST');
        $mailer->Port = (int) sv_config('SV_SMTP_PORT');
        $mailer->SMTPAuth = true;
        $mailer->Username = sv_config('SV_SMTP_USER');
        $mailer->Password = sv_config('SV_SMTP_PASS');
        $mailer->SMTPSecure = strtolower(sv_config('SV_SMTP_SECURE', 'ssl')) === 'tls'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mailer->Timeout = 15;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom(sv_config('SV_MAIL_FROM'), 'SecureVote');
        $mailer->addAddress($email, $name);
        $mailer->Subject = 'Your SecureVote verification code';
        $mailer->isHTML(true);
        $mailer->Body = '<!doctype html><html><body style="margin:0;padding:32px 16px;background:#f3f5ff;color:#1c285e;font-family:Arial,sans-serif">'
            . '<div style="max-width:520px;margin:auto;background:#fff;border:1px solid #d5ddf5;border-radius:16px;padding:32px">'
            . '<p style="margin:0 0 26px;font-size:21px;font-weight:800;color:#3647b7">SecureVote</p>'
            . '<h1 style="margin:0 0 14px;font-size:28px;line-height:1.2">Verify your email</h1>'
            . '<p style="line-height:1.6">Hi ' . $safeName . ', enter this code to finish creating your voter account:</p>'
            . '<p style="margin:25px 0;padding:18px;background:#e5eaff;border-radius:10px;text-align:center;font-size:36px;font-weight:800;letter-spacing:8px;color:#1c285e">' . $safeCode . '</p>'
            . '<p style="line-height:1.6">This code expires in 10 minutes. If you did not request an account, you can ignore this message.</p>'
            . '<p style="margin:28px 0 0;font-size:12px;color:#536384">SecureVote · Academic civic voting platform</p>'
            . '</div></body></html>';
        $mailer->AltBody = "SecureVote email verification\n\nHi {$name},\n\nYour verification code is {$code}.\nIt expires in 10 minutes.\n\nIf you did not request an account, ignore this email.";
        return $mailer->send();
    } catch (\Throwable $error) {
        error_log('SecureVote registration email could not be sent: ' . $error->getMessage());
        return false;
    }
}

function sv_registration_pending(): ?array
{
    $pending = $_SESSION['pending_registration'] ?? null;
    if (!is_array($pending)) return null;
    if (time() - (int) ($pending['created_at'] ?? 0) > 1800) {
        unset($_SESSION['pending_registration']);
        return null;
    }
    return $pending;
}

function sv_registration_otp_begin(array $form, string $passwordHash, ?callable $transport = null): bool
{
    $code = sprintf('%06d', random_int(0, 999999));
    $name = trim((string) $form['first_name'] . ' ' . (string) $form['last_name']);
    if (!sv_registration_send_code((string) $form['email'], $name, $code, $transport)) return false;
    $now = time();
    $_SESSION['pending_registration'] = [
        'form' => $form,
        'password_hash' => $passwordHash,
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'created_at' => $now,
        'expires_at' => $now + SV_REGISTRATION_OTP_TTL,
        'last_sent' => $now,
        'attempts' => 0,
    ];
    return true;
}

function sv_registration_otp_resend(?callable $transport = null): bool
{
    $pending = sv_registration_pending();
    if ($pending === null || time() - (int) $pending['last_sent'] < SV_REGISTRATION_OTP_RESEND_WAIT) return false;
    $code = sprintf('%06d', random_int(0, 999999));
    $form = $pending['form'];
    $name = trim((string) $form['first_name'] . ' ' . (string) $form['last_name']);
    if (!sv_registration_send_code((string) $form['email'], $name, $code, $transport)) return false;
    $_SESSION['pending_registration']['code_hash'] = password_hash($code, PASSWORD_DEFAULT);
    $_SESSION['pending_registration']['expires_at'] = time() + SV_REGISTRATION_OTP_TTL;
    $_SESSION['pending_registration']['last_sent'] = time();
    $_SESSION['pending_registration']['attempts'] = 0;
    return true;
}

/** Returns valid, invalid, expired, locked, or missing. */
function sv_registration_otp_check(string $code): string
{
    $pending = sv_registration_pending();
    if ($pending === null) return 'missing';
    if (time() > (int) $pending['expires_at']) return 'expired';
    if ((int) $pending['attempts'] >= SV_REGISTRATION_OTP_ATTEMPTS) return 'locked';
    $_SESSION['pending_registration']['attempts']++;
    if (!preg_match('/^[0-9]{6}$/D', $code) || !password_verify($code, (string) $pending['code_hash'])) {
        return (int) $_SESSION['pending_registration']['attempts'] >= SV_REGISTRATION_OTP_ATTEMPTS ? 'locked' : 'invalid';
    }
    return 'valid';
}

/** Complete registration only after validation or a verified OTP. */
function sv_create_registered_voter(array $form, string $passwordHash): array
{
    $pdo = db();
    $duplicate = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $duplicate->execute([$form['username'], $form['email']]);
    if ($duplicate->fetchColumn() !== false) return ['ok' => false, 'error' => 'That username or email is already registered. Please start again.'];

    $voterCode = sv_generate_voter_code();
    for ($i = 1; $i <= 20; $i++) {
        $check = $pdo->prepare('SELECT id FROM users WHERE voter_code = ? LIMIT 1');
        $check->execute([$voterCode]);
        if (!$check->fetchColumn()) break;
        $voterCode = sv_generate_voter_code();
    }
    try {
        $insert = $pdo->prepare("INSERT INTO users (username,email,voter_code,password_hash,first_name,last_name,role,status,created_at)
            VALUES (?,?,?,?,?,?,'voter','active',NOW())");
        $insert->execute([$form['username'], $form['email'], $voterCode, $passwordHash, $form['first_name'], $form['last_name']]);
    } catch (\PDOException $error) {
        if ($error->getCode() === '23000') return ['ok' => false, 'error' => 'That username or email is already registered. Please start again.'];
        throw $error;
    }
    $id = (int) $pdo->lastInsertId();
    audit_log('voter_registered', 'New voter account self-registered: "' . $form['username'] . '" (Voter Code: ' . $voterCode . ').', $id);
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return ['ok' => true, 'user' => $stmt->fetch(), 'voter_code' => $voterCode];
}
