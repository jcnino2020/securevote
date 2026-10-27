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

$guidePages = [
 'guide.php'=>'Election Guide','forum.php'=>'Candidate Forum','timeline.php'=>'Election Timeline',
 'candidate-qa.php'=>'Candidate Q&A','changes.php'=>'Change History','compare.php'=>'Compare Candidates',
 'announcements.php'=>'Announcements','practice.php'=>'Practice Voting','verify.php'=>'Verify Receipt',
 'help.php'=>'Help and Support','reminders.php'=>'Reminders',
];
$resourceScript = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
$scriptPath = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME'] ?? '');
$isAdminPage = str_contains($scriptPath,'/admin/');
$home = $user === null ? 'index.php' : dashboard_path_for_current_user();
$homeCrumb = ['label'=>'Home','href'=>$home];
if (!$isAdminPage && isset($guidePages[$resourceScript])) {
    $breadcrumb = [$homeCrumb];
    $guidePath = 'guide.php';
    if (int_input('election_id')>0) $guidePath .= '?election_id='.int_input('election_id');
    if ($resourceScript !== 'guide.php') $breadcrumb[] = ['label'=>'Election Guide','href'=>$guidePath];
    $breadcrumb[] = ['label'=>$guidePages[$resourceScript]];
} elseif ($resourceScript === 'dashboard.php') {
    $breadcrumb = $isAdminPage ? [$homeCrumb,['label'=>'Administration']] : [['label'=>'Home']];
} elseif ($resourceScript === 'index.php') {
    $breadcrumb = [['label'=>'Home']];
} else {
    $existing = array_values(array_filter($breadcrumb,static fn($c)=>!in_array(strtolower($c['label']),['home','dashboard'],true)));
    $breadcrumb = [$homeCrumb];
    if ($isAdminPage) $breadcrumb[] = ['label'=>'Administration','href'=>'admin/dashboard.php'];
    foreach ($existing as $crumb) $breadcrumb[] = $crumb;
    if (!$existing) $breadcrumb[] = ['label'=>$pageTitle];
}
$showPageTitle = false;
require_once __DIR__.'/breadcrumbs.php';

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
            ['file'=>'resources.php','label'=>'Forum & election filters','icon'=>'file','href'=>'admin/resources.php'],
            ['file'=>'engagement.php','label'=>'Timeline & candidate Q&A','icon'=>'file','href'=>'admin/engagement.php'],
            ['file' => 'readiness.php', 'label' => 'Readiness checklist', 'icon' => 'check-circle', 'href' => 'admin/readiness.php'],
            ['file' => 'announcements.php', 'label' => 'Announcements', 'icon' => 'file', 'href' => 'admin/announcements.php'],
            ['file' => 'positions.php',  'label' => 'Positions',  'icon' => 'positions',  'href' => 'admin/positions.php'],
            ['file' => 'candidates.php', 'label' => 'Candidates', 'icon' => 'users',      'href' => 'admin/candidates.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'People & evidence',
        'items' => [
            ['file' => 'voters.php',     'label' => 'Voter Registry', 'icon' => 'user-check', 'href' => 'admin/voters.php'],
            ['file' => 'import-voters.php', 'label' => 'Import voters', 'icon' => 'users', 'href' => 'admin/import-voters.php'],
            ['file' => 'support.php', 'label' => 'Issue reports', 'icon' => 'file', 'href' => 'admin/support.php'],
            ['file' => 'results.php',    'label' => 'Live & Final Results', 'icon' => 'results', 'href' => 'admin/results.php'],
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
            ['file'=>'guide.php','label'=>'Election guide','icon'=>'file','href'=>'guide.php'],
            ['file'=>'reminders.php','label'=>'Reminders','icon'=>'file','href'=>'voter/reminders.php'],
            ['file' => 'results.php',   'label' => 'Results', 'icon' => 'results', 'href' => 'voter/results.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'Account',
        'items' => [
            ['file' => 'help.php', 'label' => 'Help and support', 'icon' => 'file', 'href' => 'help.php'],
            ['file' => 'profile.php', 'label' => 'My profile', 'icon' => 'user', 'href' => 'voter/profile.php'],
        ],
    ];
}

if (in_array($activeNav,['timeline.php','candidate-qa.php','compare.php','practice.php','verify.php','announcements.php'],true) && ($user['role']??'')==='voter') $activeNav='guide.php';
$flashes = take_flashes();

ob_start();
if ($flashes !== []) {
    echo '<div class="flash-stack" role="region" aria-label="' . e(sv_t('System notifications')) . '">';
    foreach ($flashes as $flashMessage) {
        $type = (string) $flashMessage['type'];
        echo '<div class="alert alert--' . e($type) . '" role="' . ($type === 'danger' ? 'alert' : 'status') . '">'
            . '<span class="alert__icon" aria-hidden="true">' . sv_icon(flash_icon($type), 18) . '</span>'
            . '<div class="alert__text">' . e(sv_t((string) $flashMessage['message'])) . '</div>'
            . '<button type="button" class="alert__close" data-dismiss-alert aria-label="' . e(sv_t('Dismiss notification')) . '">'
            . sv_icon('x', 14)
            . '</button>'
            . '</div>';
    }
    echo '</div>';
}
$flashHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="<?= e(sv_language()) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?= e(sv_t($pageTitle)) ?> &middot; <?= e(APP_NAME) ?></title>
<meta name="description" content="<?= e(APP_TAGLINE) ?>">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f6f7ff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#131a38" media="(prefers-color-scheme: dark)">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('images/favicon-32.png')) ?>" type="image/png" sizes="32x32">
<link rel="shortcut icon" href="<?= e(url('favicon.ico')) ?>" type="image/x-icon">
<link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>" sizes="180x180">
<link rel="preload" href="<?= e(asset('fonts/dm-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/redesign.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/community.css')) ?>">
<?php if ($bodyClass === 'page-admin-traffic'): ?><link rel="stylesheet" href="<?= e(asset('css/traffic.css')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/next-features.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
<?php if ($bodyClass === 'page-verify-registration'): ?><link rel="stylesheet" href="<?= e(asset('css/registration-otp.css')) ?>"><?php endif; ?>
<script src="<?= e(asset('js/accessibility.js')) ?>"></script>
<script src="<?= e(asset('js/theme.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/navigation.js')) ?>" defer></script>
<script src="<?= e(asset('js/engagement.js')) ?>" defer></script>
<script src="<?= e(asset('js/traffic.js')) ?>" defer></script>
<?php if ($bodyClass === 'page-verify-registration'): ?><script src="<?= e(asset('js/registration-otp.js')) ?>" defer></script><?php endif; ?>
</head>
<body class="<?= e($shellClass . ($bodyClass !== '' ? ' ' . $bodyClass : '')) ?>">
<div id="traffic-config" data-endpoint="<?= e(post_url('includes/traffic-event.php')) ?>" data-token="<?= e(csrf_token()) ?>" hidden></div>

<a class="skip-link" href="#main-content"><?= e(sv_t("Skip to main content")) ?></a>

<?php if ($layout !== 'auth'): ?>

<?php if ($hasSidebar): ?>
<div class="sidebar-backdrop" data-sidebar-close hidden aria-hidden="true"></div>

<aside class="sidebar" id="sidebar" aria-label="<?= e(sv_t('Main navigation')) ?>">
  <div class="sidebar__head">
    <a class="brand" href="<?= e(url(dashboard_path_for_current_user())) ?>" title="<?= e(sv_t('SecureVote Home')) ?>">
      <?= sv_logo_mark(32) ?>
      <span class="brand__word">Secure<span>Vote</span></span>
    </a>
    <button class="icon-btn sidebar__close" type="button" data-sidebar-close
            aria-label="<?= e(sv_t('Close navigation')) ?>" title="<?= e(sv_t('Close navigation')) ?>"><?= sv_icon('x', 18) ?></button>
    <button class="icon-btn sidebar__collapse" type="button" data-sidebar-collapse
            aria-controls="sidebar" aria-expanded="true" aria-label="<?= e(sv_t('Collapse navigation')) ?>" title="<?= e(sv_t('Collapse navigation')) ?>"><?= sv_icon('chevron-left', 18) ?></button>
  </div>

  <nav class="nav" aria-label="<?= e(sv_t('Application sections')) ?>">
    <?php foreach ($navGroups as $group): ?>
      <?php $groupId = 'nav-' . e(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $group['label']))); ?>
      <p class="nav__group" id="<?= $groupId ?>">
        <?= e(sv_t($group['label'])) ?>
      </p>
      <ul class="nav__list" aria-labelledby="<?= $groupId ?>">
        <?php foreach ($group['items'] as $item): ?>
          <?php $isActive = ($activeNav === $item['file']); ?>
          <li>
            <a class="nav__item<?= $isActive ? ' is-active' : '' ?>"
               href="<?= e(url($item['href'])) ?>"
               aria-label="<?= e(sv_t($item['label'])) ?>"
               title="<?= e(sv_t($item['label'])) ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
              <?= sv_icon($item['icon'], 18, 'nav__icon') ?>
              <span class="nav__label"><?= e(sv_t($item['label'])) ?></span>
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
          <?= e(sv_t(ucfirst((string) ($user['role'] ?? '')))) ?>
        </span>
      </span>
    </div>
    <form method="post" action="<?= e(post_url('auth/logout.php')) ?>" class="logout-form">
      <?= csrf_field() ?>
      <button class="nav__item nav__item--button" type="submit" title="<?= e(sv_t('Sign out of your session')) ?>">
        <?= sv_icon('logout', 18, 'nav__icon') ?>
        <span class="nav__label"><?= e(sv_t("Sign Out")) ?></span>
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
                aria-controls="sidebar" aria-expanded="false" aria-label="<?= e(sv_t('Open navigation menu')) ?>">
          <?= sv_icon('menu', 20) ?>
        </button>
        <div class="topbar__titles"><?= sv_breadcrumbs($breadcrumb) ?></div>
      <?php else: ?>
        <a class="brand topbar__brand" href="<?= e(url('index.php')) ?>">
          <?= sv_logo_mark(30) ?>
          <span class="brand__word">Secure<span>Vote</span></span>
        </a>
        <nav class="public-nav" aria-label="<?= e(sv_t('Explore SecureVote')) ?>">
          <a href="<?= e(url('index.php')) ?>"><?= e(sv_t("Home")) ?></a>
          <a href="<?= e($bodyClass === 'page-home' ? '#open-elections' : url('index.php') . '#open-elections') ?>"><?= e(sv_t("Elections")) ?></a>
          <a href="<?= e($bodyClass === 'page-home' ? '#how-it-works' : url('index.php') . '#how-it-works') ?>"><?= e(sv_t("How it works")) ?></a>
          <a href="<?= e(url('announcements.php')) ?>"><?= e(sv_t("Announcements")) ?></a>
          <a href="<?= e(url('help.php')) ?>"><?= e(sv_t("Help")) ?></a>
        </nav>
      <?php endif; ?>


    </div>

    <div class="topbar__actions">
      <?php if (!$hasSidebar): ?>
        <div class="public-menu" data-public-menu>
          <button class="public-menu__toggle" type="button" data-public-menu-toggle
                  aria-label="<?= e(sv_t('Open navigation menu')) ?>" aria-controls="public-menu-panel" aria-expanded="false">
            <?= sv_icon('menu', 19, 'public-menu__icon--open') ?>
            <?= sv_icon('x', 19, 'public-menu__icon--close') ?>
            <span><?= e(sv_t("Menu")) ?></span>
          </button>
          <nav class="public-menu__panel" id="public-menu-panel" aria-label="<?= e(sv_t('Explore SecureVote on mobile')) ?>" hidden>
            <a href="<?= e(url('index.php')) ?>"><?= e(sv_t("Home")) ?></a>
            <a href="<?= e($bodyClass === 'page-home' ? '#open-elections' : url('index.php') . '#open-elections') ?>"><?= e(sv_t("Elections")) ?></a>
            <a href="<?= e($bodyClass === 'page-home' ? '#how-it-works' : url('index.php') . '#how-it-works') ?>"><?= e(sv_t("How it works")) ?></a>
            <a href="<?= e(url('announcements.php')) ?>"><?= e(sv_t("Announcements")) ?></a>
          <a href="<?= e(url('help.php')) ?>"><?= e(sv_t("Help")) ?></a>
          </nav>
        </div>
      <?php endif; ?>
<?php require __DIR__ . '/display-settings.php'; ?>
      <?= $pageActions ?>

    </div>
  </header>

  <main class="content" id="main-content" tabindex="-1">
<?php
// These templates already own a primary heading. Other account pages retain
// their existing visual headings and gain a screen-reader page title here.
$pageOwnsHeading = in_array($resourceScript, [
    'traffic.php', 'support.php', 'import-voters.php', 'readiness.php',
    'announcements.php', 'confirmation.php', 'compare.php', 'guide.php',
    'help.php', 'timeline.php', 'candidate-qa.php', 'changes.php', 'verify.php',
    'practice.php', 'forum.php', 'privacy.php', 'terms.php',
], true);
if ($hasSidebar && !$pageOwnsHeading): ?>
<h1 class="visually-hidden"><?= e($pageTitle) ?></h1>
<?php endif; ?>
<?php if (!$hasSidebar && $resourceScript !== 'index.php'): ?>
<?= str_replace('breadcrumb--header', 'breadcrumb--content', sv_breadcrumbs($breadcrumb)) ?>
<?php endif; ?>
<?= $flashHtml ?>
<?php else: ?>
<?php if (!in_array($resourceScript, ['login.php', 'register.php', 'verify-registration.php'], true)): ?>
<header class="auth-breadcrumb-bar"><?= sv_breadcrumbs($breadcrumb) ?></header>
<?php endif; ?>
<main class="auth-layout" id="main-content" tabindex="-1"><?php require __DIR__ . '/display-settings.php'; ?>

<?= $flashHtml ?>
<?php endif; ?>
