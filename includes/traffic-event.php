<?php
/** Same-origin beacon endpoint for link clicks. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload) || !csrf_valid($payload['token'] ?? null)) {
    http_response_code(403);
    exit;
}
$page = sv_traffic_path((string) ($payload['page'] ?? ''));
$target = sv_traffic_path((string) ($payload['target'] ?? ''), true);
if (!$page || !$target) {
    http_response_code(422);
    exit;
}
sv_record_traffic_event('link_click', $page, $target);
http_response_code(204);
