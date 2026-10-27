<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
demo_check_csrf();
$_SESSION = [];
session_regenerate_id(true);
demo_redirect('login.php');
