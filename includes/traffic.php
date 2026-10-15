<?php
/** First-party navigation telemetry. Never records form values, query strings, or ballot choices. */
declare(strict_types=1);

function sv_traffic_path(string $value, bool $allowExternal = false): ?string
{
    if (trim($value) === '') return null;
    if ($allowExternal && preg_match('/^external: ([a-z0-9.-]{1,180})$/i', $value)) return strtolower($value);
    $parsed = parse_url($value);
    if ($parsed === false) return null;
    if (isset($parsed['scheme']) && !in_array(strtolower((string) $parsed['scheme']), ['http', 'https'], true)) return null;
    if (isset($parsed['host'])) {
        $host = strtolower((string) $parsed['host']);
        $ourHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $ourHost = explode(':', $ourHost)[0];
        if ($host !== $ourHost) {
            return $allowExternal && preg_match('/^[a-z0-9.-]+$/', $host) ? 'external: ' . substr($host, 0, 180) : null;
        }
    }
    $path = (string) ($parsed['path'] ?? '/');
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    if (str_contains($path, "\0") || str_contains($path, '..')) return null;
    return substr($path, 0, 255);
}

function sv_traffic_device(string $agent): string
{
    if (preg_match('/ipad|tablet|kindle/i', $agent)) return 'Tablet';
    if (preg_match('/mobile|iphone|android/i', $agent)) return 'Mobile';
    return 'Desktop';
}

function sv_traffic_browser(string $agent): string
{
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $needle => $name) {
        if (str_contains($agent, $needle)) return $name;
    }
    return 'Other';
}

function sv_traffic_os(string $agent): string
{
    foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $needle => $name) {
        if (str_contains($agent, $needle)) return $name;
    }
    return 'Other';
}

function sv_traffic_create_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_traffic_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        visitor_key CHAR(32) NOT NULL,
        user_id INT UNSIGNED NULL,
        event_type ENUM('page_view','link_click') NOT NULL,
        page_path VARCHAR(255) NOT NULL,
        target_path VARCHAR(255) NULL,
        referrer_path VARCHAR(255) NULL,
        ip_address VARCHAR(45) NULL,
        device_type VARCHAR(16) NOT NULL,
        browser VARCHAR(32) NOT NULL,
        operating_system VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_traffic_created (created_at),
        KEY idx_traffic_visitor (visitor_key, created_at),
        KEY idx_traffic_user (user_id, created_at),
        KEY idx_traffic_page (page_path, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function sv_record_traffic_event(string $type, string $page, ?string $target = null): void
{
    if (PHP_SAPI === 'cli' || !in_array($type, ['page_view', 'link_click'], true)) return;
    $page = sv_traffic_path($page) ?? '';
    if ($page === '') return;
    if (empty($_SESSION['_traffic_visitor_key'])) $_SESSION['_traffic_visitor_key'] = bin2hex(random_bytes(16));
    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
    $referrer = sv_traffic_path((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null;
    $pdo = db(false);
    if (!$pdo) return;
    $values = [
        ':visitor' => $_SESSION['_traffic_visitor_key'], ':user' => !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        ':type' => $type, ':page' => $page, ':target' => $target, ':referrer' => $referrer,
        ':ip' => $ip, ':device' => sv_traffic_device($agent), ':browser' => sv_traffic_browser($agent), ':os' => sv_traffic_os($agent),
    ];
    $insert = static function (PDO $connection, array $data): void {
        $stmt = $connection->prepare('INSERT INTO site_traffic_events
            (visitor_key,user_id,event_type,page_path,target_path,referrer_path,ip_address,device_type,browser,operating_system)
            VALUES (:visitor,:user,:type,:page,:target,:referrer,:ip,:device,:browser,:os)');
        $stmt->execute($data);
    };
    try {
        $insert($pdo, $values);
    } catch (PDOException $error) {
        if (($error->getCode() === '42S02' || str_contains($error->getMessage(), "doesn't exist"))
            && (time() - (int) ($_SESSION['_traffic_schema_retry'] ?? 0)) > 300) {
            $_SESSION['_traffic_schema_retry'] = time();
            try {
                sv_traffic_create_table($pdo);
                $insert($pdo, $values);
                unset($_SESSION['_traffic_schema_retry']);
            } catch (Throwable $setupError) {
                error_log('Traffic table setup failed: ' . $setupError->getMessage());
            }
        } else {
            error_log('Traffic event write failed: ' . $error->getMessage());
        }
    }
}

function sv_track_page_request(): void
{
    if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') return;
    $path = sv_traffic_path((string) ($_SERVER['REQUEST_URI'] ?? ''));
    if ($path === null || !preg_match('~(?:/|\.php)$~i', $path)) return;
    if (str_contains($path, '/_preview_')) return;
    sv_record_traffic_event('page_view', $path);
}
