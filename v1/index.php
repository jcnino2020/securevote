<?php
declare(strict_types=1);
// Early interface using SecureVote's shared account authentication.
require_once __DIR__ . '/../includes/bootstrap.php';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/v1/index.php')), '/');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
function esc(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function link_to(string $view='home'): string { global $base; return $base.'/?view='.rawurlencode($view); }
$loginError = '';
$identifier = '';
$_SESSION['v1_form_token'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!is_string($_POST['token']??null) || !hash_equals($_SESSION['v1_form_token'],$_POST['token'])) { http_response_code(403); exit('This form expired. Reload the page and try again.'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'enter') {
        $identifier = is_string($_POST['identifier'] ?? null) ? trim($_POST['identifier']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($identifier === '' || $password === '') {
            $loginError = 'Enter your username or email and password.';
        } else {
            $result = attempt_login($identifier, $password);
            if ($result['ok'] ?? false) {
                login_user($result['user']);
                $_SESSION['v1_form_token'] = bin2hex(random_bytes(32));
                header('Location: '.link_to('dashboard'), true, 303); exit;
            }
            $loginError = (string) ($result['error'] ?? 'Sign-in failed. Please try again.');
        }
    } elseif ($action === 'leave') {
        logout_user();
        $_SESSION['v1_form_token'] = bin2hex(random_bytes(32));
        header('Location: '.link_to('home'), true, 303); exit;
    } else { http_response_code(400); exit('Unknown action.'); }
}
$user = current_user();

$view=is_string($_GET['view']??null)?$_GET['view']:'home';
$titles=['home'=>'Your campus. Your choice.','login'=>'Student portal','dashboard'=>'Your dashboard','elections'=>'Explore elections','candidates'=>'Meet the candidates'];
if (!isset($titles[$view])) { http_response_code(404); $view='missing'; }
if ($view==='dashboard' && $user === null) { header('Location: '.link_to('login')); exit; }
$candidates=[['Alex Rivera','President','Student Voice','A more connected campus','Alex is a student volunteer focused on accessible campus services and regular student consultations.'],['Jamie Santos','President','Campus Forward','Practical support for every student','Jamie is a student representative focused on study spaces, campus communication, and student wellbeing.'],['Sam Cruz','Vice President','Student Voice','Make student concerns heard','Sam is a campus organizer interested in clearer feedback channels and more inclusive student activities.'],['Taylor Reyes','Vice President','Campus Forward','Better activities, stronger community','Taylor is a student volunteer interested in organization partnerships and accessible student events.']];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= esc($titles[$view]??'Page not found') ?> · SecureVote</title><meta name="robots" content="noindex,nofollow"><link rel="icon" href="../assets/images/favicon.svg"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&display=swap"><link rel="stylesheet" href="<?= esc($base) ?>/prototype.css"></head>
<body><a class="skip" href="#main">Skip to content</a>
<header class="header"><a class="brand" href="<?= esc(link_to()) ?>"><img src="../assets/images/favicon.svg" width="32" height="32" alt="">SecureVote</a><nav aria-label="Main navigation"><?php foreach (['home'=>'Home','elections'=>'Elections','candidates'=>'Candidates'] as $route=>$label): ?><a href="<?= esc(link_to($route)) ?>" <?= $view===$route?'aria-current="page"':'' ?>><?= esc($label) ?></a><?php endforeach; ?></nav><?php if ($user !== null): ?><a class="button secondary" href="<?= esc(link_to('dashboard')) ?>">My dashboard</a><?php else: ?><a class="button secondary" href="<?= esc(link_to('login')) ?>">Sign in</a><?php endif; ?></header>
<main id="main">
<?php if ($view==='home'): ?>
<section class="welcome"><div><h1>Your campus.<br><em>Your choice.</em></h1><p>Student elections, made simpler. Explore an election and get to know the students who could represent your campus.</p><div class="actions"><a class="button" href="<?= esc(link_to('elections')) ?>">Explore elections <span aria-hidden="true">→</span></a><a class="text-link" href="<?= esc(link_to('login')) ?>">Student portal</a></div></div><aside class="preview"><span class="tag">Student council</span><h2>Student Council<br>Election</h2><p>Get familiar with the candidates and their ideas.</p><dl><div><dt>Positions</dt><dd>President & Vice President</dd></div><div><dt>Stage</dt><dd>Candidate preview</dd></div><div><dt>Voting</dt><dd>Not yet open</dd></div></dl><a class="text-link" href="<?= esc(link_to('candidates')) ?>">Meet the candidates <span aria-hidden="true">→</span></a></aside></section>
<section class="section"><h2>Get to know your student leaders</h2><p>Read each candidate’s background and platform before deciding who should represent your campus.</p><a class="text-link" href="<?= esc(link_to('candidates')) ?>">Browse candidate profiles</a></section>
<?php elseif ($view==='login'): ?>
<section class="signin"><div><h1>Student portal</h1><p>Sign in to your SecureVote account to explore elections and candidate profiles.</p></div><form method="post" action="<?= esc(link_to('login')) ?>" class="signin-form"><h2>Sign in</h2><p>Use your existing SecureVote account.</p>
<?php if ($loginError !== ''): ?><p class="login-error" role="alert"><?= esc($loginError) ?></p><?php endif; ?>
<label for="identifier">Username or email</label><input id="identifier" name="identifier" type="text" autocomplete="username" maxlength="150" required value="<?= esc($identifier) ?>">
<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<input type="hidden" name="token" value="<?= esc($_SESSION['v1_form_token']) ?>"><button class="button" name="action" value="enter">Sign in</button></form></section>
<?php elseif ($view==='dashboard'): ?>
<section class="page-heading"><h1>Welcome, <?= esc((string) $user['first_name']) ?></h1><p>Browse your election and get to know the candidates.</p></section><div class="dashboard-layout"><section><h2>Your election</h2><article class="election"><span class="tag">Candidate preview</span><h3>Student Council Election</h3><p>Meet the candidates running for President and Vice President.</p><a class="button" href="<?= esc(link_to('candidates')) ?>">View candidates</a><button class="button secondary" disabled>Voting not yet open</button></article></section><aside class="account"><h2>Your account</h2><dl><div><dt>Name</dt><dd><?= esc(user_full_name($user)) ?></dd></div><div><dt>Username</dt><dd><?= esc((string) $user['username']) ?></dd></div><div><dt>Voting status</dt><dd>Voting not yet open</dd></div></dl><form method="post" action="<?= esc(link_to('dashboard')) ?>"><input type="hidden" name="token" value="<?= esc($_SESSION['v1_form_token']) ?>"><button class="text-button" name="action" value="leave">Sign out</button></form></aside></div>
<?php elseif ($view==='elections'): ?>
<section class="page-heading"><h1>Explore elections</h1><p>Browse student council elections and candidate information.</p></section><article class="election election-wide"><div><span class="tag">Candidate preview</span><h2>Student Council Election</h2><p>Get to know the students running for President and Vice President and learn about their priorities.</p><p class="small">2 positions · 4 candidates</p></div><div class="actions"><a class="button" href="<?= esc(link_to('candidates')) ?>">View candidates</a><button class="button secondary" disabled>Voting not yet open</button></div></article>
<?php elseif ($view==='candidates'): ?>
<section class="page-heading"><h1>Meet the candidates</h1><p>Student Council Election</p></section>
<?php foreach (['President','Vice President'] as $position): ?><section class="candidate-section"><h2><?= esc($position) ?></h2><div class="candidate-list"><?php foreach ($candidates as [$name,$role,$party,$platform,$bio]): if ($role!==$position) continue; ?><article class="candidate"><div class="candidate-title"><span class="avatar" aria-hidden="true"><?= esc(implode('',array_map(fn($s)=>$s[0],explode(' ',$name)))) ?></span><div><h3><?= esc($name) ?></h3><p><?= esc($party) ?></p></div></div><h4><?= esc($platform) ?></h4><p><?= esc($bio) ?></p><details><summary>Read platform</summary><p><?= $name==='Alex Rivera'?'Hold regular student consultations and make campus service information easier to find.':($name==='Jamie Santos'?'Advocate for better study spaces and clearer updates about student support services.':($name==='Sam Cruz'?'Create consistent feedback channels so student concerns reach the council.':'Support shared activities between student organizations and improve event accessibility.')) ?></p></details></article><?php endforeach; ?></div></section><?php endforeach; ?><p class="notice">Voting is not yet open for this election.</p>
<?php else: ?><section class="page-heading"><h1>Page not found</h1><p>The page you requested could not be found.</p><a class="button" href="<?= esc(link_to()) ?>">Back to home</a></section><?php endif; ?>
</main><footer><a class="brand" href="<?= esc(link_to()) ?>">SecureVote</a><p>Student Council Elections · Sample election data</p><a href="<?= esc(link_to('elections')) ?>">Browse elections</a></footer></body></html>
