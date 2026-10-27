<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
if (demo_user()) demo_redirect('dashboard.php');
$values = ['first_name' => '', 'last_name' => '', 'username' => '', 'email' => ''];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    demo_check_csrf();
    foreach ($values as $name => $_) $values[$name] = trim((string) ($_POST[$name] ?? ''));
    $values['username'] = strtolower($values['username']);
    $values['email'] = strtolower($values['email']);
    $password = (string) ($_POST['password'] ?? '');
    if (mb_strlen($values['first_name']) < 1 || mb_strlen($values['first_name']) > 80) $errors['first_name'] = 'Enter your first name (1 to 80 characters).';
    if (mb_strlen($values['last_name']) < 1 || mb_strlen($values['last_name']) > 80) $errors['last_name'] = 'Enter your last name (1 to 80 characters).';
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $values['username'])) $errors['username'] = 'Use 3 to 50 letters, numbers, dots, underscores, or hyphens.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 150) $errors['email'] = 'Enter a valid email address.';
    if (strlen($password) < 8 || strlen($password) > 200 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) $errors['password'] = 'Use 8 to 200 characters with at least one letter and number.';
    if ($password !== (string) ($_POST['password_confirm'] ?? '')) $errors['password_confirm'] = 'The passwords do not match.';
    if (!$errors) {
        $pdo = demo_db();
        $check = $pdo->prepare('SELECT username, email FROM demo_users WHERE username = :username OR email = :email');
        $check->execute([':username' => $values['username'], ':email' => $values['email']]);
        foreach ($check->fetchAll() as $existing) {
            if (strcasecmp($existing['username'], $values['username']) === 0) $errors['username'] = 'That username is already registered.';
            if (strcasecmp($existing['email'], $values['email']) === 0) $errors['email'] = 'That email is already registered.';
        }
        if (!$errors) {
            try {
                $insert = $pdo->prepare('INSERT INTO demo_users (first_name,last_name,username,email,password_hash,created_at) VALUES (?,?,?,?,?,?)');
                $insert->execute([$values['first_name'], $values['last_name'], $values['username'], $values['email'], password_hash($password, PASSWORD_DEFAULT), date('c')]);
                session_regenerate_id(true);
                $_SESSION['demo_user_id'] = (int) $pdo->lastInsertId();
                $_SESSION['demo_login_at'] = time();
                demo_redirect('dashboard.php');
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') $errors['general'] = 'This username or email is already registered.';
                else throw $error;
            }
        }
    }
}
demo_header('Register', 'register');
?>
<div class="auth-layout"><aside class="auth-story"><span class="eyebrow">CREATE YOUR ACCOUNT</span><h1>Be part of what comes next.</h1><p>Set up your personal account. After registration, you will go directly to your dashboard.</p><div class="story-steps"><span>01 &nbsp; Create account</span><span>02 &nbsp; Sign in</span><span>03 &nbsp; View dashboard</span></div></aside><section class="auth-card"><span class="form-mark" aria-hidden="true">✓</span><h2>Join SecureVote</h2><p>Fill in your details to create an account for this section.</p><?php if (isset($errors['general'])): ?><div class="alert" role="alert"><?= demo_e($errors['general']) ?></div><?php endif; ?><form method="post" action="register.php" novalidate><input type="hidden" name="csrf" value="<?= demo_e(demo_csrf()) ?>"><div class="field-pair"><label>First name<input name="first_name" autocomplete="given-name" required maxlength="80" value="<?= demo_e($values['first_name']) ?>" aria-invalid="<?= isset($errors['first_name']) ? 'true' : 'false' ?>"><?php if (isset($errors['first_name'])): ?><small class="field-error"><?= demo_e($errors['first_name']) ?></small><?php endif; ?></label><label>Last name<input name="last_name" autocomplete="family-name" required maxlength="80" value="<?= demo_e($values['last_name']) ?>" aria-invalid="<?= isset($errors['last_name']) ? 'true' : 'false' ?>"><?php if (isset($errors['last_name'])): ?><small class="field-error"><?= demo_e($errors['last_name']) ?></small><?php endif; ?></label></div><label>Username<input name="username" autocomplete="username" required maxlength="50" value="<?= demo_e($values['username']) ?>" aria-invalid="<?= isset($errors['username']) ? 'true' : 'false' ?>"><?php if (isset($errors['username'])): ?><small class="field-error"><?= demo_e($errors['username']) ?></small><?php endif; ?></label><label>Email address<input name="email" type="email" autocomplete="email" required maxlength="150" value="<?= demo_e($values['email']) ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"><?php if (isset($errors['email'])): ?><small class="field-error"><?= demo_e($errors['email']) ?></small><?php endif; ?></label><div class="field-pair"><label>Password<input name="password" type="password" autocomplete="new-password" required aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>"><?php if (isset($errors['password'])): ?><small class="field-error"><?= demo_e($errors['password']) ?></small><?php endif; ?></label><label>Confirm password<input name="password_confirm" type="password" autocomplete="new-password" required aria-invalid="<?= isset($errors['password_confirm']) ? 'true' : 'false' ?>"><?php if (isset($errors['password_confirm'])): ?><small class="field-error"><?= demo_e($errors['password_confirm']) ?></small><?php endif; ?></label></div><p class="field-hint">At least 8 characters, including a letter and a number.</p><button class="btn btn-primary form-submit" type="submit">Create account <span aria-hidden="true">→</span></button></form><p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p></section></div>
<?php demo_footer(); ?>
