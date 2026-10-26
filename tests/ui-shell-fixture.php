<?php
/** CLI-only, database-free layout fixture. Never deployed to production. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$fixtureRole = $argv[1] ?? 'voter';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/' . $fixtureRole . '/dashboard.php';
$_SESSION = ['user_role' => $fixtureRole];
require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/locale.php';
require dirname(__DIR__) . '/includes/icons.php';
require dirname(__DIR__) . '/includes/csrf.php';
function current_user(): array { global $fixtureRole; return ['id'=>1,'role'=>$fixtureRole,'username'=>'layout-test','first_name'=>'Layout','last_name'=>'Test']; }
function user_full_name(array $user): string { return $user['first_name'] . ' ' . $user['last_name']; }
function is_voter(): bool { global $fixtureRole; return $fixtureRole === 'voter'; }
function take_flashes(): array { return [['type'=>'danger','message'=>'A sample validation message with a very long identifier: '.str_repeat('StudentIdentifier',10)]]; }
function flash_icon(string $type): string { return 'alert-triangle'; }
$pageTitle = 'Layout fixture'; $layout = 'app'; $activeNav = 'dashboard.php';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="card"><div class="card__body stack">
<h2>Responsive layout fixture</h2>
<p>Fictional content. This fixture tests the real shared voter/admin shell without authentication or database writes.</p>
<form class="form-grid" action="#"><div class="form-field"><label class="label" for="fixture-email">Email address</label><input class="input is-invalid" id="fixture-email" type="email" autocomplete="email"><p class="field-error">Enter a valid email address.</p></div><div class="form-field"><label class="label" for="fixture-date">Election date</label><input class="input" id="fixture-date" type="datetime-local"></div></form>
<div class="table-wrap"><table class="table"><caption>Fictional voter roster</caption><thead><tr><th>Name</th><th>Email</th><th>Election</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr><td>Sample Student</td><td>long-student-identifier@example.test</td><td>University Student Council Election</td><td>Active</td><td><button type="button" class="btn btn--secondary">Review registration details</button></td></tr></tbody></table></div>
<div class="form-grid"><div class="card"><div class="card__body"><h2>Long content</h2><p><?= str_repeat('LongUnbrokenCandidateName', 20) ?></p></div></div><div><h2>Keyboard controls</h2><button class="btn btn--primary" type="button">Primary action</button><details><summary>More information</summary><p>Additional details.</p></details></div></div>
</div></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
