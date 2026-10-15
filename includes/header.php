<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/header.php
 *
 * Opens the HTML document and renders the modern 2026 application shell
 * (responsive sidebar rail + elevated topbar for signed-in areas, a focused
 * centered card for authentication, and a civic gateway for the portal).
 *
 * Page variables contract:
 *   $pageTitle     (string)  required - rendered in <title> and topbar <h1> (if shown)
 *   $showPageTitle (bool)    optional - whether to show <h1> in topbar (defaults to false for portal/landing pages)
 *   $activeNav     (string)  optional - filename of the active navigation item
 *   $pageActions   (string)  optional - escaped HTML buttons for topbar action area
 *   $layout        (string)  optional - 'app' (default), 'auth', or 'portal'
 *   $bodyClass     (string)  optional - additional CSS class for <body>
 *   $breadcrumb    (array)   optional - [['label' => .., 'href' => ..], ..]
 */

if (!defined('APP_NAME')) {
    http_response_code(500);
    exit('Configuration not loaded.');
}

$layout       = $layout       ?? 'app';
$pageTitle    = $pageTitle    ?? 'SecureVote';
$activeNav    = $activeNav    ?? '';
$pageActions  = $pageActions  ?? '';
$bodyClass    = $bodyClass    ?? '';
$breadcrumb   = $breadcrumb   ?? [];
$user         = current_user();

if ($user === null && $layout === 'app') {
    $layout = 'portal';
}

$showPageTitle = $showPageTitle ?? ($layout !== 'portal');

$hasSidebar = ($layout === 'app' && $user !== null);
$shellClass = 'app-shell app-shell--' . ($layout === 'auth' ? 'auth' : ($hasSidebar ? 'app' : 'portal'));
if ($hasSidebar && ($user['role'] ?? '') === 'admin') {
    $shellClass .= ' app-shell--admin';
}

/* ---------------------------------------------------------------------
 | Sidebar Navigation Specification
 * ------------------------------------------------------------------- */
$navGroups = [];

if ($user !== null && ($user['role'] ?? '') === 'admin') {
    $navGroups[] = [
        'label' => 'Command',
        'items' => [
            ['file' => 'dashboard.php', 'label' => 'Operations center', 'icon' => 'dashboard', 'href' => 'admin/dashboard.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'Election operations',
        'items' => [
            ['file' => 'elections.php',  'label' => 'Elections',  'icon' => 'vote',       'href' => 'admin/elections.php'],
            ['file' => 'positions.php',  'label' => 'Positions',  'icon' => 'positions',  'href' => 'admin/positions.php'],
            ['file' => 'candidates.php', 'label' => 'Candidates', 'icon' => 'users',      'href' => 'admin/candidates.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'People & evidence',
        'items' => [
            ['file' => 'voters.php',     'label' => 'Voter Registry', 'icon' => 'user-check', 'href' => 'admin/voters.php'],
            ['file' => 'results.php',    'label' => 'Certified Tallies', 'icon' => 'results', 'href' => 'admin/results.php'],
            ['file' => 'audit-logs.php', 'label' => 'Audit Trail',    'icon' => 'audit',      'href' => 'admin/audit-logs.php'],
            ['file' => 'traffic.php',    'label' => 'Site Traffic',   'icon' => 'chart',      'href' => 'admin/traffic.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'Assurance',
        'items' => [
            ['file' => 'security-testing.php', 'label' => 'Security Checks', 'icon' => 'shield-check', 'href' => 'admin/security-testing.php'],
            ['file' => 'settings.php',         'label' => 'System Settings',    'icon' => 'settings',     'href' => 'admin/settings.php'],
        ],
    ];
} elseif ($user !== null) {
    $navGroups[] = [
        'label' => 'Your voting space',
        'items' => [
            ['file' => 'dashboard.php', 'label' => 'Home', 'icon' => 'dashboard', 'href' => 'voter/dashboard.php'],
            ['file' => 'elections.php', 'label' => 'Elections', 'icon' => 'vote',  'href' => 'voter/elections.php'],
            ['file' => 'results.php',   'label' => 'Results', 'icon' => 'results', 'href' => 'voter/results.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'Account',
        'items' => [
            ['file' => 'profile.php', 'label' => 'My profile', 'icon' => 'user', 'href' => 'voter/profile.php'],
        ],
    ];
}

$flashes = take_flashes();

ob_start();
if ($flashes !== []) {
    echo '<div class="flash-stack" role="region" aria-label="System notifications">';
    foreach ($flashes as $flashMessage) {
        $type = (string) $flashMessage['type'];
        echo '<div class="alert alert--' . e($type) . '" role="' . ($type === 'danger' ? 'alert' : 'status') . '">'
            . '<span class="alert__icon" aria-hidden="true">' . sv_icon(flash_icon($type), 18) . '</span>'
            . '<div class="alert__text">' . e((string) $flashMessage['message']) . '</div>'
            . '<button type="button" class="alert__close" data-dismiss-alert aria-label="Dismiss notification">'
            . sv_icon('x', 14)
            . '</button>'
            . '</div>';
    }
    echo '</div>';
}
$flashHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?= e($pageTitle) ?> &middot; <?= e(APP_NAME) ?></title>
<meta name="description" content="<?= e(APP_TAGLINE) ?>">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f6f7ff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#131a38" media="(prefers-color-scheme: dark)">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('images/favicon-32.png')) ?>" type="image/png" sizes="32x32">
<link rel="shortcut icon" href="<?= e(url('favicon.ico')) ?>" type="image/x-icon">
<link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>" sizes="180x180">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/redesign.css')) ?>">
<?php if ($bodyClass === 'page-admin-traffic'): ?><link rel="stylesheet" href="<?= e(asset('css/traffic.css')) ?>"><?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/navigation.js')) ?>" defer></script>
<script src="<?= e(asset('js/traffic.js')) ?>" defer></script>
</head>
<body class="<?= e($shellClass . ($bodyClass !== '' ? ' ' . $bodyClass : '')) ?>">
<div id="traffic-config" data-endpoint="<?= e(post_url('includes/traffic-event.php')) ?>" data-token="<?= e(csrf_token()) ?>" hidden></div>

<a class="skip-link" href="#main-content">Skip to main content</a>

<?php if ($layout !== 'auth'): ?>

<?php if ($hasSidebar): ?>
<div class="sidebar-backdrop" data-sidebar-close hidden aria-hidden="true"></div>

<aside class="sidebar" id="sidebar" aria-label="Main navigation">
  <div class="sidebar__head">
    <a class="brand" href="<?= e(url(dashboard_path_for_current_user())) ?>" title="SecureVote Home">
      <?= sv_logo_mark(32) ?>
      <span class="brand__word">Secure<span>Vote</span></span>
    </a>
    <button class="icon-btn sidebar__close" type="button" data-sidebar-close
            aria-label="Close navigation" title="Close navigation"><?= sv_icon('x', 18) ?></button>
    <button class="icon-btn sidebar__collapse" type="button" data-sidebar-collapse
            aria-controls="sidebar" aria-expanded="true" aria-label="Collapse navigation" title="Collapse navigation"><?= sv_icon('chevron-left', 18) ?></button>
  </div>

  <nav class="nav" aria-label="Application sections">
    <?php foreach ($navGroups as $group): ?>
      <?php $groupId = 'nav-' . e(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $group['label']))); ?>
      <p class="nav__group" id="<?= $groupId ?>">
        <?= e($group['label']) ?>
      </p>
      <ul class="nav__list" aria-labelledby="<?= $groupId ?>">
        <?php foreach ($group['items'] as $item): ?>
          <?php $isActive = ($activeNav === $item['file']); ?>
          <li>
            <a class="nav__item<?= $isActive ? ' is-active' : '' ?>"
               href="<?= e(url($item['href'])) ?>"
               aria-label="<?= e($item['label']) ?>"
               title="<?= e($item['label']) ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
              <?= sv_icon($item['icon'], 18, 'nav__icon') ?>
              <span class="nav__label"><?= e($item['label']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__foot">
    <div class="user-chip">
      <span class="avatar avatar--sm" aria-hidden="true"><?= e(initials($user['first_name'] ?? '', $user['last_name'] ?? '')) ?></span>
      <span class="user-chip__text">
        <span class="user-chip__name"><?= e(user_full_name($user)) ?></span>
        <span class="user-chip__meta">
          <span class="status-dot status-dot--online" aria-hidden="true"></span>
          <?= e(ucfirst((string) ($user['role'] ?? ''))) ?>
        </span>
      </span>
    </div>
    <form method="post" action="<?= e(post_url('auth/logout.php')) ?>" class="logout-form">
      <?= csrf_field() ?>
      <button class="nav__item nav__item--button" type="submit" title="Sign out of your session">
        <?= sv_icon('logout', 18, 'nav__icon') ?>
        <span class="nav__label">Sign Out</span>
      </button>
    </form>
  </div>
</aside>
<?php endif; ?>

<div class="shell">
  <header class="topbar">
    <div class="topbar__left">
      <?php if ($hasSidebar): ?>
        <button class="icon-btn topbar__menu" type="button" data-sidebar-toggle
                aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation menu">
          <?= sv_icon('menu', 20) ?>
        </button>
      <?php else: ?>
        <a class="brand topbar__brand" href="<?= e(url('index.php')) ?>">
          <?= sv_logo_mark(30) ?>
          <span class="brand__word">Secure<span>Vote</span></span>
        </a>
        <nav class="public-nav" aria-label="Explore SecureVote">
          <a href="<?= e($bodyClass === 'page-home' ? '#open-elections' : url('index.php') . '#open-elections') ?>">Elections</a>
          <a href="<?= e($bodyClass === 'page-home' ? '#how-it-works' : url('index.php') . '#how-it-works') ?>">How it works</a>
          <a href="<?= e(url('privacy.php')) ?>">Privacy</a>
        </nav>
      <?php endif; ?>

      <?php if ($showPageTitle || count($breadcrumb) > 1): ?>
        <div class="topbar__titles">
          <?php if ($hasSidebar && ($user['role'] ?? '') === 'admin'): ?>
            <span class="admin-topbar__eyebrow">SECUREVOTE / ADMIN OPERATIONS</span>
          <?php endif; ?>
          <?php if (count($breadcrumb) > 1): ?>
            <nav class="breadcrumb" aria-label="Breadcrumb navigation">
              <ol>
                <?php foreach ($breadcrumb as $index => $crumb): ?>
                  <li>
                    <?php if (isset($crumb['href']) && $index < count($breadcrumb) - 1): ?>
                      <a href="<?= e(url($crumb['href'])) ?>"><?= e($crumb['label']) ?></a>
                      <span class="breadcrumb__separator" aria-hidden="true"><?= sv_icon('chevron-right', 12) ?></span>
                    <?php else: ?>
                      <span aria-current="page"><?= e($crumb['label']) ?></span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ol>
            </nav>
          <?php endif; ?>

          <?php if ($showPageTitle): ?>
            <h1 class="page-title"><?= e($pageTitle) ?></h1>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="topbar__actions">
      <?php if (!$hasSidebar): ?>
        <div class="public-menu" data-public-menu>
          <button class="public-menu__toggle" type="button" data-public-menu-toggle
                  aria-label="Open navigation menu" aria-controls="public-menu-panel" aria-expanded="false">
            <?= sv_icon('menu', 19, 'public-menu__icon--open') ?>
            <?= sv_icon('x', 19, 'public-menu__icon--close') ?>
            <span>Menu</span>
          </button>
          <nav class="public-menu__panel" id="public-menu-panel" aria-label="Explore SecureVote on mobile" hidden>
            <a href="<?= e($bodyClass === 'page-home' ? '#open-elections' : url('index.php') . '#open-elections') ?>">Elections</a>
            <a href="<?= e($bodyClass === 'page-home' ? '#how-it-works' : url('index.php') . '#how-it-works') ?>">How it works</a>
            <a href="<?= e(url('privacy.php')) ?>">Privacy</a>
          </nav>
        </div>
      <?php endif; ?>
      <?= $pageActions ?>

      <button class="icon-btn theme-toggle-btn" type="button" data-theme-toggle
              aria-label="Switch between light and dark appearance" aria-pressed="false"
              title="Toggle color theme">
        <?= sv_icon('sun', 18, 'theme-icon theme-icon--sun') ?>
        <?= sv_icon('moon', 18, 'theme-icon theme-icon--moon') ?>
      </button>
    </div>
  </header>

  <main class="content" id="main-content" tabindex="-1">
<?= $flashHtml ?>
<?php else: ?>
<main class="auth-layout" id="main-content" tabindex="-1">
<?= $flashHtml ?>
<?php endif; ?>
