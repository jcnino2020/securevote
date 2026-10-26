<?php
require_once __DIR__.'/includes/bootstrap.php';
if (!is_post()) { http_response_code(405); header('Allow: POST'); exit; }
require_csrf(); $lang=input('language','en');
if (in_array($lang,['en','fil'],true)) $_SESSION['language']=$lang;
// Only return to known local routes. Never accept an external redirect.
$return=input('return','index.php');
$allowed=['guide.php','forum.php','changes.php','admin/resources.php','auth/forgot-password.php','auth/reset-password.php','index.php','timeline.php','candidate-qa.php','announcements.php','practice.php','help.php','verify.php','auth/login.php','auth/register.php','voter/dashboard.php','voter/elections.php','voter/compare.php','voter/ballot.php','voter/review.php','voter/confirmation.php','voter/results.php','voter/profile.php','voter/reminders.php','admin/engagement.php','admin/dashboard.php','admin/elections.php','admin/announcements.php','admin/readiness.php','admin/positions.php','admin/candidates.php','admin/voters.php','admin/results.php','admin/settings.php','admin/traffic.php','admin/support.php','admin/import-voters.php','admin/audit-logs.php','admin/security-testing.php'];
$parts=parse_url($return); $path=$parts['path']??'';
if (!in_array($path,$allowed,true) || isset($parts['host']) || isset($parts['scheme'])) $return='index.php';
redirect($return);
