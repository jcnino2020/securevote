<?php
/**
 * =========================================================================
 * HOSTINGER APPLICATION PORTAL & DYNAMIC WORKSPACE HUB
 * =========================================================================
 * 
 * Auto-discovering workspace launcher for Hostinger web hosting.
 * Automatically scans and displays all current and future subfolders (e.g.,
 * /securevote/, /aims/, /your-next-app/) with zero manual updates.
 *
 * Designed with the Impeccable Craft Standard.
 */

// 1. ENVIRONMENT & HOST TELEMETRY
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$baseUrl = $protocol . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
$phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Hostinger Web Server';
$serverNameClean = preg_replace('/^([a-zA-Z0-9_\-\.]+?)(?:\/.*)?$/', '$1', $serverSoftware);

// 2. HELPER UTILITIES
function formatTimeAgo($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    if ($diff < 604800) return floor($diff / 86400) . "d ago";
    return date("M j, Y", $timestamp);
}

function formatAcronym($slug) {
    // Acronym dictionary for tech and school systems
    $acronyms = [
        'aims' => 'AIMS',
        'api'  => 'API',
        'cms'  => 'CMS',
        'erp'  => 'ERP',
        'lms'  => 'LMS',
        'hrm'  => 'HRM',
        'pos'  => 'POS',
        'crm'  => 'CRM',
        'msme' => 'MSME',
        'itp'  => 'ITP',
        'ui'   => 'UI',
        'ux'   => 'UX',
        'db'   => 'DB',
        'sql'  => 'SQL',
        'iot'  => 'IoT',
        'ai'   => 'AI',
        'app'  => 'App',
        'voter'=> 'Voter'
    ];

    // Check specific known names
    $lower = strtolower($slug);
    if ($lower === 'securevote') return 'SecureVote';
    if ($lower === 'aims') return 'AIMS Portal';

    $words = preg_split('/[-_\s]+/', $slug);
    $formatted = array_map(function($w) use ($acronyms) {
        $lw = strtolower($w);
        if (isset($acronyms[$lw])) {
            return $acronyms[$lw];
        }
        return ucfirst($lw);
    }, $words);

    return implode(' ', $formatted);
}

// 3. DYNAMIC SUBFOLDER SCANNER
function scanWorkspaceProjects($rootDir, $baseUrl) {
    $ignoredDirectories = [
        '.', '..', '.well-known', '.git', '.github', '.idea', '.vscode', 
        '.gemini', '.agents', 'cgi-bin', 'logs', 'tmp', 'temp', 'cache', 
        'session', 'storage', 'vendor', 'node_modules', 'scratch', 
        'assets', 'includes', 'config', 'database'
    ];

    $projects = [];
    if (!is_dir($rootDir) || !is_readable($rootDir)) {
        return $projects;
    }

    $entries = scandir($rootDir);
    if (!$entries) {
        return $projects;
    }

    foreach ($entries as $entry) {
        if (in_array($entry, $ignoredDirectories) || str_starts_with($entry, '.')) {
            continue;
        }

        $fullPath = $rootDir . '/' . $entry;
        if (!is_dir($fullPath)) {
            continue;
        }

        // Project defaults
        $mtime = filemtime($fullPath);
        $project = [
            'slug'        => $entry,
            'name'        => formatAcronym($entry),
            'url'         => $baseUrl . rawurlencode($entry) . '/',
            'relativeUrl' => './' . rawurlencode($entry) . '/',
            'description' => 'Autonomous service environment mounted in /' . htmlspecialchars($entry) . '/',
            'tags'        => ['Web Application'],
            'stack'       => [],
            'entryPoint'  => null,
            'icon'        => null,
            'initials'    => strtoupper(substr($entry, 0, min(2, strlen($entry)))),
            'badge'       => null,
            'accent'      => null,
            'mtime'       => $mtime,
            'updatedAgo'  => formatTimeAgo($mtime),
            'updatedDate' => date('M j, Y', $mtime),
            'fileCount'   => 0,
            'hue'         => abs(crc32($entry)) % 360
        ];

        // Custom initials formatting
        $words = preg_split('/[-_\s]+/', $entry);
        if (count($words) >= 2) {
            $project['initials'] = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        } else {
            // If single word with camelCase, split
            if (preg_match('/^([a-z]+)([A-Z].*)$/', $entry, $m)) {
                $project['initials'] = strtoupper(substr($m[1], 0, 1) . substr($m[2], 0, 1));
            } else {
                $project['initials'] = strtoupper(substr($entry, 0, 2));
            }
        }

        // A. Check for custom meta.json / project.json
        $jsonConfigs = ['meta.json', 'project.json', 'manifest.json'];
        foreach ($jsonConfigs as $jfile) {
            $jPath = $fullPath . '/' . $jfile;
            if (file_exists($jPath) && is_readable($jPath)) {
                $raw = @file_get_contents($jPath);
                $data = @json_decode($raw, true);
                if (is_array($data)) {
                    if (!empty($data['title'])) $project['name'] = $data['title'];
                    elseif (!empty($data['name'])) $project['name'] = $data['name'];

                    if (!empty($data['description'])) $project['description'] = $data['description'];
                    if (!empty($data['badge'])) $project['badge'] = $data['badge'];
                    if (!empty($data['accent'])) $project['accent'] = $data['accent'];
                    if (!empty($data['tags']) && is_array($data['tags'])) $project['tags'] = $data['tags'];
                    if (!empty($data['icon'])) $project['icon'] = $data['icon'];
                    if (isset($data['hidden']) && $data['hidden'] === true) {
                        continue 2; // Skip hidden project
                    }
                }
                break;
            }
        }

        // B. Check for documentation (PRODUCT.md / README.md)
        if (empty($data['description'])) {
            if (file_exists($fullPath . '/PRODUCT.md') && is_readable($fullPath . '/PRODUCT.md')) {
                $prodText = @file_get_contents($fullPath . '/PRODUCT.md');
                if (preg_match('/## Product Purpose\s*\n+([^\n#]+)/i', $prodText, $m)) {
                    $project['description'] = trim($m[1]);
                }
            } elseif (file_exists($fullPath . '/README.md') && is_readable($fullPath . '/README.md')) {
                $readmeText = @file_get_contents($fullPath . '/README.md');
                // Extract first substantive heading or paragraph
                if (preg_match('/^#\s+([^\n]+)/m', $readmeText, $m)) {
                    if (empty($data['title']) && empty($data['name'])) {
                        $project['name'] = trim($m[1]);
                    }
                }
                if (preg_match('/^(?:#[^\n]+\n+)+([A-Z][^\n#]+)/m', $readmeText, $m)) {
                    $project['description'] = trim($m[1]);
                }
            }
        }

        // C. Check entry point files
        $candidateEntries = ['index.php', 'index.html', 'default.php', 'home.php', 'home.html'];
        foreach ($candidateEntries as $ce) {
            if (file_exists($fullPath . '/' . $ce)) {
                $project['entryPoint'] = $ce;
                break;
            }
        }

        // D. Inspect internal structure for Tech Stack detection
        $subFiles = @scandir($fullPath);
        $hasPhp = false;
        $hasSql = false;
        $hasNode = false;
        $hasComposer = false;
        $hasHtml = false;
        $count = 0;

        if ($subFiles) {
            foreach ($subFiles as $sf) {
                if ($sf === '.' || $sf === '..') continue;
                $count++;
                if (str_ends_with($sf, '.php')) $hasPhp = true;
                if (str_ends_with($sf, '.html')) $hasHtml = true;
                if (str_ends_with($sf, '.sql') || $sf === 'database') $hasSql = true;
                if ($sf === 'package.json') $hasNode = true;
                if ($sf === 'composer.json') $hasComposer = true;
            }
        }
        $project['fileCount'] = $count;

        if ($hasPhp) $project['stack'][] = 'PHP';
        if ($hasSql) $project['stack'][] = 'MySQL';
        if ($hasNode) $project['stack'][] = 'Node';
        if ($hasComposer) $project['stack'][] = 'Composer';
        if ($hasHtml && !$hasPhp) $project['stack'][] = 'HTML5';
        if (empty($project['stack'])) $project['stack'][] = 'Web App';

        // E. Look for custom logo/icon file in subfolder
        if (empty($project['icon'])) {
            $iconCandidates = [
                'logo.svg', 'logo.png', 'favicon.ico', 'favicon.png', 
                'assets/images/logo.png', 'assets/images/favicon.ico', 'assets/logo.png'
            ];
            foreach ($iconCandidates as $ic) {
                if (file_exists($fullPath . '/' . $ic)) {
                    $project['icon'] = $project['relativeUrl'] . $ic;
                    break;
                }
            }
        }

        // Truncate description gracefully if needed
        if (strlen($project['description']) > 160) {
            $project['description'] = substr($project['description'], 0, 157) . '...';
        }

        $projects[] = $project;
    }

    // Sort by last modified date desc
    usort($projects, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });

    return $projects;
}

$scannedProjects = scanWorkspaceProjects(__DIR__, $baseUrl);
$totalProjects = count($scannedProjects);
$allTags = [];
foreach ($scannedProjects as $p) {
    foreach ($p['tags'] as $t) $allTags[$t] = true;
    foreach ($p['stack'] as $s) $allTags[$s] = true;
}
$availableTags = array_slice(array_keys($allTags), 0, 8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#080b11">
    <title>Cluster Hub &bull; <?= htmlspecialchars($host) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* -------------------------------------------------------------------------
         * DESIGN SYSTEM & TOKENS (Impeccable Craft Standard)
         * ---------------------------------------------------------------------- */
        :root {
            --bg-base: #080b11;
            --bg-surface: #0f1420;
            --bg-surface-elevated: #161c2c;
            --bg-surface-hover: #1c2438;
            --border-subtle: rgba(255, 255, 255, 0.07);
            --border-highlight: rgba(56, 189, 248, 0.28);
            --border-active: rgba(56, 189, 248, 0.6);
            
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            
            --accent-primary: #38bdf8;
            --accent-glow: rgba(56, 189, 248, 0.16);
            --accent-emerald: #10b981;
            --accent-emerald-glow: rgba(16, 185, 129, 0.16);
            --accent-amber: #f59e0b;
            
            --shadow-subtle: 0 4px 16px -2px rgba(0, 0, 0, 0.4);
            --shadow-card: 0 12px 36px -8px rgba(0, 0, 0, 0.65), 0 0 1px 1px rgba(255, 255, 255, 0.06);
            --shadow-card-hover: 0 20px 48px -10px rgba(0, 0, 0, 0.8), 0 0 20px -2px rgba(56, 189, 248, 0.25);
            
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 22px;
            --radius-full: 9999px;
            --transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Reset & Base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        ::selection {
            background: var(--accent-primary);
            color: #030712;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-primary);
            font-family: var(--font-sans);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Ambient subtle backdrop grid & glow */
        .ambient-canvas {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .ambient-canvas::before {
            content: '';
            position: absolute;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 900px;
            height: 600px;
            background: radial-gradient(ellipse at center, rgba(56, 189, 248, 0.12) 0%, rgba(14, 165, 233, 0.04) 45%, transparent 70%);
            filter: blur(40px);
        }

        .ambient-canvas::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px);
            background-size: 32px 32px;
            opacity: 0.8;
            mask-image: radial-gradient(ellipse at 50% 20%, black 40%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 20%, black 40%, transparent 80%);
        }

        /* Layout Container */
        .app-shell {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 4rem;
            display: flex;
            flex-direction: column;
            gap: 2.25rem;
            flex: 1;
        }

        /* Top Header Strip */
        .top-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.25rem;
            padding-bottom: 1.75rem;
            border-bottom: 1px solid var(--border-subtle);
        }

        .brand-cluster {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            text-decoration: none;
            color: inherit;
        }

        .brand-emblem {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px -2px rgba(2, 132, 199, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
        }

        .brand-emblem svg {
            width: 22px;
            height: 22px;
            stroke: #ffffff;
            stroke-width: 2;
            fill: none;
        }

        .brand-meta {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--accent-emerald);
            box-shadow: 0 0 10px var(--accent-emerald);
            animation: pulse-dot 2.4s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.45; transform: scale(0.85); }
        }

        .brand-domain {
            font-size: 0.825rem;
            font-family: var(--font-mono);
            color: var(--text-muted);
            letter-spacing: -0.01em;
        }

        /* Server Telemetry Pill */
        .telemetry-strip {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-family: var(--font-mono);
            color: var(--text-secondary);
        }

        .telemetry-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .telemetry-sep {
            color: var(--border-subtle);
        }

        .badge-live {
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            padding: 0.15rem 0.55rem;
            border-radius: var(--radius-full);
            font-size: 0.725rem;
            font-weight: 600;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        /* Hero / Introduction */
        .hero-section {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-width: 780px;
            margin-top: 0.5rem;
        }

        .hero-heading {
            font-size: clamp(2rem, 3.5vw, 2.75rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            line-height: 1.15;
            color: var(--text-primary);
        }

        .hero-desc {
            font-size: 1.05rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Interactive Filter & Command Bar */
        .command-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 0.75rem 1rem;
            box-shadow: var(--shadow-subtle);
        }

        .search-box-wrapper {
            position: relative;
            flex: 1;
            min-width: 260px;
        }

        .search-icon {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            stroke: var(--text-muted);
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            background: var(--bg-base);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 0.65rem 3.5rem 0.65rem 2.45rem;
            font-family: var(--font-sans);
            font-size: 0.9rem;
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }

        .search-input:focus {
            border-color: var(--border-active);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }

        .search-kbd-hint {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            font-family: var(--font-mono);
            font-size: 0.725rem;
            color: var(--text-muted);
            background: var(--bg-surface-elevated);
            padding: 0.2rem 0.45rem;
            border-radius: 4px;
            border: 1px solid var(--border-subtle);
            pointer-events: none;
        }

        .filter-controls {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        /* Tag chips */
        .tag-pill-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.25rem;
            scrollbar-width: none;
        }
        .tag-pill-row::-webkit-scrollbar { display: none; }

        .tag-pill {
            background: var(--bg-surface-elevated);
            border: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: var(--transition);
        }

        .tag-pill:hover, .tag-pill.active {
            background: var(--accent-glow);
            color: var(--accent-primary);
            border-color: var(--border-highlight);
        }

        /* View Mode & Deploy Action */
        .view-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-view-toggle, .btn-action {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            background: var(--bg-surface-elevated);
            border: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            padding: 0.55rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.825rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-view-toggle:hover, .btn-action:hover {
            color: var(--text-primary);
            border-color: rgba(255, 255, 255, 0.18);
            background: var(--bg-surface-hover);
        }

        .btn-action.primary {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .btn-action.primary:hover {
            box-shadow: 0 4px 16px -2px rgba(2, 132, 199, 0.5);
            transform: translateY(-1px);
        }

        .btn-view-toggle svg, .btn-action svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        /* -------------------------------------------------------------------------
         * PROJECTS GRID & CARDS
         * ---------------------------------------------------------------------- */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.5rem;
            transition: var(--transition);
        }

        /* In List Mode */
        .projects-grid.list-mode {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .project-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            position: relative;
            box-shadow: var(--shadow-card);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }

        .project-card:hover {
            border-color: var(--border-highlight);
            transform: translateY(-3px);
            box-shadow: var(--shadow-card-hover);
            background: var(--bg-surface-elevated);
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .card-identity {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }

        .card-avatar {
            width: 52px;
            height: 52px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-mono);
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffffff;
            flex-shrink: 0;
            box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.12);
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        .card-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-title-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .card-name {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-slug {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--accent-primary);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .card-badge {
            background: var(--accent-glow);
            color: var(--accent-primary);
            border: 1px solid var(--border-highlight);
            font-size: 0.725rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-full);
            white-space: nowrap;
        }

        .card-description {
            font-size: 0.915rem;
            color: var(--text-secondary);
            line-height: 1.55;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.85rem;
        }

        .card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .tech-tag {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-subtle);
            color: var(--text-muted);
            font-size: 0.75rem;
            font-family: var(--font-mono);
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
        }

        .tech-tag.highlight {
            color: var(--accent-primary);
            border-color: rgba(56, 189, 248, 0.2);
            background: rgba(56, 189, 248, 0.05);
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid var(--border-subtle);
            margin-top: auto;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .card-meta-time {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .card-meta-time svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .card-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-copy-url {
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-muted);
            padding: 0.35rem;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-copy-url:hover {
            color: var(--text-primary);
            background: var(--bg-surface-hover);
            border-color: var(--border-subtle);
        }

        .btn-copy-url svg {
            width: 15px;
            height: 15px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .btn-launch {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
            font-size: 0.825rem;
            color: var(--accent-primary);
            text-decoration: none;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            background: var(--accent-glow);
            border: 1px solid var(--border-highlight);
            transition: var(--transition);
        }

        .btn-launch:hover {
            background: var(--accent-primary);
            color: #020617;
            transform: translateX(2px);
        }

        .btn-launch svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            stroke-width: 2.2;
            fill: none;
            transition: transform 0.18s ease;
        }

        .btn-launch:hover svg {
            transform: translate(2px, -2px);
        }

        /* List Mode View Styling */
        .projects-grid.list-mode .project-card {
            flex-direction: row;
            align-items: center;
            padding: 1rem 1.25rem;
            gap: 1.5rem;
            border-radius: var(--radius-lg);
        }

        .projects-grid.list-mode .card-header {
            flex: 1;
            align-items: center;
        }

        .projects-grid.list-mode .card-description {
            display: none;
        }

        .projects-grid.list-mode .card-footer {
            border-top: none;
            margin-top: 0;
            padding-top: 0;
            gap: 1.5rem;
        }

        /* -------------------------------------------------------------------------
         * EMPTY & SEARCH NO MATCH STATE
         * ---------------------------------------------------------------------- */
        .state-empty {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 4.5rem 1.5rem;
            background: var(--bg-surface);
            border: 1px dashed var(--border-subtle);
            border-radius: var(--radius-xl);
            gap: 1rem;
        }

        .state-empty.visible {
            display: flex;
        }

        .empty-icon-box {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--bg-surface-elevated);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-subtle);
            color: var(--text-muted);
        }

        .empty-icon-box svg {
            width: 28px;
            height: 28px;
            stroke: currentColor;
            stroke-width: 1.75;
            fill: none;
        }

        .empty-heading {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .empty-text {
            color: var(--text-secondary);
            max-width: 420px;
            font-size: 0.95rem;
        }

        /* -------------------------------------------------------------------------
         * MODAL / DEPLOYMENT GUIDE DRAWER
         * ---------------------------------------------------------------------- */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .modal-backdrop.open {
            display: flex;
            opacity: 1;
        }

        .modal-window {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 580px;
            box-shadow: 0 24px 60px -12px rgba(0, 0, 0, 0.9);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: modal-pop 0.26s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modal-pop {
            from { transform: scale(0.96) translateY(8px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 1.15rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-modal-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 6px;
        }

        .btn-modal-close:hover {
            color: var(--text-primary);
        }

        .modal-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            font-size: 0.925rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .step-list {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .step-num {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--accent-glow);
            color: var(--accent-primary);
            border: 1px solid var(--border-highlight);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            font-family: var(--font-mono);
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        .code-snippet {
            background: #04060a;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 0.85rem 1rem;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: #38bdf8;
            overflow-x: auto;
            position: relative;
        }

        /* Toast feedback */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #0f172a;
            color: #ffffff;
            border: 1px solid var(--border-highlight);
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.7);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 200;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Footer */
        .portal-footer {
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-subtle);
            font-size: 0.825rem;
            color: var(--text-muted);
        }

        .footer-links {
            display: flex;
            gap: 1.25rem;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: var(--accent-primary);
        }

        @media (max-width: 768px) {
            .app-shell {
                padding: 1.5rem 1rem 3rem;
                gap: 1.5rem;
            }
            .projects-grid {
                grid-template-columns: 1fr;
            }
            .command-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }
            .view-actions {
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>

    <!-- Ambient background layer -->
    <div class="ambient-canvas" aria-hidden="true"></div>

    <main class="app-shell">
        <!-- Top Navigation Bar -->
        <header class="top-nav">
            <a href="./" class="brand-cluster" title="Refresh Portal">
                <div class="brand-emblem">
                    <svg viewBox="0 0 24 24">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                </div>
                <div class="brand-meta">
                    <div class="brand-title">
                        <span>Workspace Hub</span>
                        <span class="brand-status-dot" title="Cluster Active"></span>
                    </div>
                    <div class="brand-domain"><?= htmlspecialchars($host) ?></div>
                </div>
            </a>

            <!-- Server & Hostinger Telemetry -->
            <div class="telemetry-strip">
                <div class="telemetry-item">
                    <span class="badge-live">LIVE</span>
                </div>
                <div class="telemetry-sep">&bull;</div>
                <div class="telemetry-item" title="PHP Engine">
                    <span>PHP <?= htmlspecialchars($phpVersion) ?></span>
                </div>
                <div class="telemetry-sep">&bull;</div>
                <div class="telemetry-item" title="Active Applications">
                    <strong id="stat-count"><?= $totalProjects ?></strong> <?= $totalProjects === 1 ? 'App' : 'Apps' ?>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="hero-section">
            <h1 class="hero-heading">Projects &amp; Deployments</h1>
            <p class="hero-desc">
                Centralized gateway for all web applications, student portals, and system microservices hosted on this domain.
            </p>
        </section>

        <!-- Command & Filter Bar -->
        <section class="command-bar" aria-label="Project search and filters">
            <div class="search-box-wrapper">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input 
                    type="search" 
                    id="project-search" 
                    class="search-input" 
                    placeholder="Search by name, path, or technology..." 
                    autocomplete="off"
                    spellcheck="false"
                >
                <kbd class="search-kbd-hint">/</kbd>
            </div>

            <div class="filter-controls">
                <!-- Tag Pills -->
                <div class="tag-pill-row" role="tablist">
                    <button type="button" class="tag-pill active" data-tag="all">All</button>
                    <?php foreach ($availableTags as $tag): ?>
                        <button type="button" class="tag-pill" data-tag="<?= htmlspecialchars(strtolower($tag)) ?>">
                            <?= htmlspecialchars($tag) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- View Switch & Add Project Info -->
                <div class="view-actions">
                    <button type="button" id="toggle-view-btn" class="btn-view-toggle" title="Toggle Grid / List View">
                        <svg id="view-icon" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        <span id="view-text">Grid</span>
                    </button>

                    <button type="button" id="btn-deploy-info" class="btn-action primary">
                        <svg viewBox="0 0 24 24">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Deploy Guide</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- Project Grid -->
        <section id="projects-container" class="projects-grid" aria-live="polite">
            <?php if (empty($scannedProjects)): ?>
                <!-- Fresh Zero-Project State -->
                <div class="state-empty visible" style="grid-column: 1 / -1;">
                    <div class="empty-icon-box">
                        <svg viewBox="0 0 24 24">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <h2 class="empty-heading">No Subfolders Detected Yet</h2>
                    <p class="empty-text">
                        Create or upload any subfolder into your Hostinger <code>public_html/</code> directory (such as <code>/securevote/</code> or <code>/aims/</code>). This hub will automatically detect and link them!
                    </p>
                    <button type="button" class="btn-action primary" onclick="openDeployModal()">
                        View Setup Instructions
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($scannedProjects as $proj): 
                    // Calculate unique monogram gradient
                    $hue = $proj['hue'];
                    $avatarBg = "hsl({$hue}, 68%, 22%)";
                    $avatarBorder = "hsl({$hue}, 80%, 45%)";
                    $tagSearchStr = strtolower(implode(' ', array_merge([$proj['name'], $proj['slug']], $proj['tags'], $proj['stack'])));
                ?>
                    <article 
                        class="project-card" 
                        data-tags="<?= htmlspecialchars($tagSearchStr) ?>"
                        data-slug="<?= htmlspecialchars(strtolower($proj['slug'])) ?>"
                        data-name="<?= htmlspecialchars(strtolower($proj['name'])) ?>"
                    >
                        <div class="card-header">
                            <div class="card-identity">
                                <div class="card-avatar" style="background-color: <?= $avatarBg ?>; border-color: <?= $avatarBorder ?>;">
                                    <?php if (!empty($proj['icon'])): ?>
                                        <img src="<?= htmlspecialchars($proj['icon']) ?>" alt="<?= htmlspecialchars($proj['name']) ?> Icon" loading="lazy" onerror="this.remove()">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($proj['initials']) ?></span>
                                </div>
                                <div class="card-title-group">
                                    <h2 class="card-name" title="<?= htmlspecialchars($proj['name']) ?>">
                                        <?= htmlspecialchars($proj['name']) ?>
                                    </h2>
                                    <div class="card-slug">
                                        <span>/<?= htmlspecialchars($proj['slug']) ?>/</span>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($proj['badge'])): ?>
                                <span class="card-badge"><?= htmlspecialchars($proj['badge']) ?></span>
                            <?php endif; ?>
                        </div>

                        <p class="card-description">
                            <?= htmlspecialchars($proj['description']) ?>
                        </p>

                        <div class="card-tags">
                            <?php foreach ($proj['stack'] as $tech): ?>
                                <span class="tech-tag highlight"><?= htmlspecialchars($tech) ?></span>
                            <?php endforeach; ?>
                            <?php foreach (array_slice($proj['tags'], 0, 3) as $tg): ?>
                                <span class="tech-tag"><?= htmlspecialchars($tg) ?></span>
                            <?php endforeach; ?>
                        </div>

                        <footer class="card-footer">
                            <div class="card-meta-time">
                                <svg viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <span><?= htmlspecialchars($proj['updatedAgo']) ?></span>
                            </div>

                            <div class="card-actions">
                                <button 
                                    type="button" 
                                    class="btn-copy-url" 
                                    title="Copy direct link"
                                    data-url="<?= htmlspecialchars($proj['url']) ?>"
                                    onclick="copyProjectLink(this)"
                                >
                                    <svg viewBox="0 0 24 24">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>

                                <a href="<?= htmlspecialchars($proj['relativeUrl']) ?>" class="btn-launch">
                                    <span>Launch</span>
                                    <svg viewBox="0 0 24 24">
                                        <line x1="7" y1="17" x2="17" y2="7"></line>
                                        <polyline points="7 7 17 7 17 17"></polyline>
                                    </svg>
                                </a>
                            </div>
                        </footer>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- No Filter Results State -->
        <div id="no-search-results" class="state-empty" style="grid-column: 1 / -1;">
            <div class="empty-icon-box">
                <svg viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </div>
            <h2 class="empty-heading">No matching applications</h2>
            <p class="empty-text">No subfolders match your current query. Try adjusting your search keywords or clearing tag filters.</p>
            <button type="button" class="btn-action" onclick="resetSearchFilters()">Clear Filters</button>
        </div>

        <!-- Footer -->
        <footer class="portal-footer">
            <div>
                <span>Hosted on Hostinger &bull; LiteSpeed Server &bull; Auto-detecting <code>public_html/</code> subdirectories</span>
            </div>
            <div class="footer-links">
                <a href="javascript:void(0)" onclick="openDeployModal()">How it works</a>
                <a href="https://hpanel.hostinger.com" target="_blank" rel="noopener">Hostinger hPanel</a>
            </div>
        </footer>
    </main>

    <!-- Deploy Guide Modal -->
    <div id="deploy-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-window">
            <header class="modal-header">
                <h2 id="modal-title" class="modal-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                    How Auto-Discovery Works
                </h2>
                <button type="button" class="btn-modal-close" onclick="closeDeployModal()" aria-label="Close dialog">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </header>

            <div class="modal-body">
                <p>
                    This portal dynamically monitors your Hostinger root folder (<code>public_html/</code>). Whenever you upload a new project, it appears here automatically without touching any code!
                </p>

                <div class="step-list">
                    <div class="step-item">
                        <div class="step-num">1</div>
                        <div>
                            <strong>Create or Upload a Subfolder:</strong>
                            <p>In Hostinger File Manager or FTP, upload your project folder into <code>public_html/</code> (e.g., <code>public_html/securevote/</code> or <code>public_html/aims/</code>).</p>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-num">2</div>
                        <div>
                            <strong>Zero Configuration Required:</strong>
                            <p>The system automatically reads directory names, entry points (<code>index.php</code> / <code>index.html</code>), detects PHP/SQL, and formats titles.</p>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-num">3</div>
                        <div>
                            <strong>(Optional) Rich Customization via <code>meta.json</code>:</strong>
                            <p>To set custom titles, badges, and tags, simply place a <code>meta.json</code> inside your subfolder:</p>
                        </div>
                    </div>
                </div>

                <div class="code-snippet">
<pre>{
  "title": "SecureVote",
  "description": "Student council voting and election verification portal.",
  "badge": "Active Election",
  "tags": ["PHP", "MySQL", "Authentication"]
}</pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast" role="status" aria-live="polite">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2">
            <path d="M20 6L9 17l-5-5"></path>
        </svg>
        <span id="toast-text">Link copied to clipboard!</span>
    </div>

    <script>
        // ---------------------------------------------------------------------
        // CLIENT INTERACTIVITY & INSTANT SEARCH
        // ---------------------------------------------------------------------
        const searchInput = document.getElementById('project-search');
        const cards = Array.from(document.querySelectorAll('.project-card'));
        const noResults = document.getElementById('no-search-results');
        const tagPills = document.querySelectorAll('.tag-pill');
        const container = document.getElementById('projects-container');
        const toggleViewBtn = document.getElementById('toggle-view-btn');
        const viewIcon = document.getElementById('view-icon');
        const viewText = document.getElementById('view-text');
        const statCount = document.getElementById('stat-count');
        const toast = document.getElementById('toast');
        const toastText = document.getElementById('toast-text');
        const deployModal = document.getElementById('deploy-modal');
        const btnDeployInfo = document.getElementById('btn-deploy-info');

        let currentTag = 'all';
        let currentQuery = '';
        let isListMode = false;

        function filterProjects() {
            let visibleCount = 0;
            const q = currentQuery.toLowerCase().trim();

            cards.forEach(card => {
                const cardTags = (card.getAttribute('data-tags') || '').toLowerCase();
                const cardName = (card.getAttribute('data-name') || '').toLowerCase();
                const cardSlug = (card.getAttribute('data-slug') || '').toLowerCase();

                const matchesQuery = !q || cardTags.includes(q) || cardName.includes(q) || cardSlug.includes(q);
                const matchesTag = (currentTag === 'all') || cardTags.includes(currentTag.toLowerCase());

                if (matchesQuery && matchesTag) {
                    card.style.display = isListMode ? 'flex' : 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (statCount) {
                statCount.textContent = visibleCount;
            }

            if (noResults) {
                if (visibleCount === 0 && cards.length > 0) {
                    noResults.classList.add('visible');
                } else {
                    noResults.classList.remove('visible');
                }
            }
        }

        // Search Input Listener
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                currentQuery = e.target.value;
                filterProjects();
            });

            // Focus search on "/" key
            window.addEventListener('keydown', (e) => {
                if (e.key === '/' && document.activeElement !== searchInput) {
                    e.preventDefault();
                    searchInput.focus();
                }
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.value = '';
                    currentQuery = '';
                    filterProjects();
                    searchInput.blur();
                }
            });
        }

        // Tag Pills Listener
        tagPills.forEach(pill => {
            pill.addEventListener('click', () => {
                tagPills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                currentTag = pill.getAttribute('data-tag');
                filterProjects();
            });
        });

        // View Mode Toggle (Grid <-> List)
        if (toggleViewBtn) {
            toggleViewBtn.addEventListener('click', () => {
                isListMode = !isListMode;
                if (isListMode) {
                    container.classList.add('list-mode');
                    viewText.textContent = 'List';
                    viewIcon.innerHTML = `
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    `;
                } else {
                    container.classList.remove('list-mode');
                    viewText.textContent = 'Grid';
                    viewIcon.innerHTML = `
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    `;
                }
                filterProjects();
            });
        }

        // Copy Project Link
        window.copyProjectLink = function(button) {
            const url = button.getAttribute('data-url');
            if (!url) return;

            navigator.clipboard.writeText(url).then(() => {
                showToast('Copied ' + url);
            }).catch(() => {
                showToast('Copied to clipboard!');
            });
        };

        function showToast(message) {
            if (!toast) return;
            toastText.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Reset Filter Helper
        window.resetSearchFilters = function() {
            if (searchInput) searchInput.value = '';
            currentQuery = '';
            currentTag = 'all';
            tagPills.forEach(p => p.classList.toggle('active', p.getAttribute('data-tag') === 'all'));
            filterProjects();
        };

        // Modal Controls
        window.openDeployModal = function() {
            if (deployModal) deployModal.classList.add('open');
        };

        window.closeDeployModal = function() {
            if (deployModal) deployModal.classList.remove('open');
        };

        if (btnDeployInfo) {
            btnDeployInfo.addEventListener('click', openDeployModal);
        }

        if (deployModal) {
            deployModal.addEventListener('click', (e) => {
                if (e.target === deployModal) closeDeployModal();
            });
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeDeployModal();
            });
        }
    </script>
</body>
</html>
