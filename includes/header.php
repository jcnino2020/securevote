<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/header.php
 *
 * Opens the HTML document and renders the application shell (sidebar +
 * topbar for signed-in areas, a centred card for the login page).
 *
 * A page sets these before including it:
 *   $pageTitle    (string)  required - shown in <title> and the topbar
 *   $pageSubtitle (string)  optional - one line under the title
 *   $activeNav    (string)  optional - file name of the active nav item
 *   $pageActions  (string)  optional - HTML for topbar buttons
 *   $layout       (string)  optional - 'app' (default) or 'auth'
 *   $bodyClass    (string)  optional - extra class on <body>
 *   $breadcrumb   (array)   optional - [['label' => .., 'href' => ..], ..]
 *
 * Escaping contract: $pageTitle, $pageSubtitle and every nav label are
 * escaped by this file with e(). $pageActions is the one slot that is
 * deliberately printed as raw HTML, because pages put markup in it. Pages
 * must therefore build $pageActions from escaped pieces only (wrap any
 * dynamic value in e()); never put user input in it unescaped.
 */

if (!defined('APP_NAME')) {
    // A page reached header.php without bootstrap.php; refuse rather than leak paths.
    http_response_code(500);
    exit('Configuration not loaded.');
}

$layout       = $layout       ?? 'app';
$pageTitle    = $pageTitle    ?? 'Untitled';
$pageSubtitle = $pageSubtitle ?? '';
$activeNav    = $activeNav    ?? '';
$pageActions  = $pageActions  ?? '';
$bodyClass    = $bodyClass    ?? '';
$breadcrumb   = $breadcrumb   ?? [];
$user = current_user();

if ($user === null && $layout === 'app') {
    $layout = 'portal';
}

$hasSidebar   = ($layout === 'app' && $user !== null);
$shellClass   = 'app-shell app-shell--' . ($layout === 'auth' ? 'auth' : ($hasSidebar ? 'app' : 'portal'));

/* ---------------------------------------------------------------------
 | Sidebar navigation. One definition, so admin and voter never drift
 | apart in style or wording.
 * ------------------------------------------------------------------- */
$navGroups = [];

if ($user !== null && ($user['role'] ?? '') === 'admin') {
    $navGroups[] = [
        'label' => 'Overview',
        'items' => [
            ['file' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => 'admin/dashboard.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'Election setup',
        'items' => [
            ['file' => 'elections.php',  'label' => 'Elections',  'icon' => 'vote',       'href' => 'admin/elections.php'],
            ['file' => 'positions.php',  'label' => 'Positions',  'icon' => 'positions',  'href' => 'admin/positions.php'],
            ['file' => 'candidates.php', 'label' => 'Candidates', 'icon' => 'users',      'href' => 'admin/candidates.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'People and results',
        'items' => [
            ['file' => 'voters.php',     'label' => 'Voters',     'icon' => 'user-check', 'href' => 'admin/voters.php'],
            ['file' => 'results.php',    'label' => 'Results',    'icon' => 'results',    'href' => 'admin/results.php'],
            ['file' => 'audit-logs.php', 'label' => 'Audit logs', 'icon' => 'audit',      'href' => 'admin/audit-logs.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'System',
        'items' => [
            ['file' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings', 'href' => 'admin/settings.php'],
        ],
    ];
} elseif ($user !== null) {
    $navGroups[] = [
        'label' => 'Voting',
        'items' => [
            ['file' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => 'voter/dashboard.php'],
            ['file' => 'elections.php', 'label' => 'Elections', 'icon' => 'vote',      'href' => 'voter/elections.php'],
            ['file' => 'results.php',   'label' => 'Results',   'icon' => 'results',   'href' => 'voter/results.php'],
        ],
    ];
    $navGroups[] = [
        'label' => 'My account',
        'items' => [
            ['file' => 'profile.php', 'label' => 'My profile', 'icon' => 'user', 'href' => 'voter/profile.php'],
        ],
    ];
}

$flashes = take_flashes();

// The same markup is used in both layouts so a flash message can never look
// like a different component depending on which page queued it.
ob_start();
if ($flashes !== []) {
    echo '<div class="flash-stack">';
    foreach ($flashes as $flashMessage) {
        $type = (string) $flashMessage['type'];
        echo '<div class="alert alert--' . e($type) . '" role="' . ($type === 'danger' ? 'alert' : 'status') . '">'
            . '<span class="alert__icon" aria-hidden="true">' . sv_icon(flash_icon($type), 18) . '</span>'
            . '<p class="alert__text">' . e((string) $flashMessage['message']) . '</p>'
            . '</div>';
    }
    echo '</div>';
}
$flashHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> &middot; <?= e(APP_NAME) ?></title>
<meta name="description" content="<?= e(APP_TAGLINE) ?>">
<meta name="color-scheme" content="light dark">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<!-- Loaded in the head, without defer, so the saved theme is applied before
     the first paint and the page never flashes the wrong colour. -->
<script src="<?= e(asset('js/app.js')) ?>"></script>
</head>
<body class="<?= e($shellClass . ($bodyClass !== '' ? ' ' . $bodyClass : '')) ?>">

<a class="skip-link" href="#main-content">Skip to main content</a>

<?php if ($layout !== 'auth'): ?>

<?php if ($hasSidebar): ?>
<div class="sidebar-backdrop" data-sidebar-close hidden></div>

<aside class="sidebar" id="sidebar" aria-label="Main navigation">
  <div class="sidebar__head">
    <a class="brand" href="<?= e(url(dashboard_path_for_current_user())) ?>">
      <?= sv_logo_mark(30) ?>
      <span class="brand__word">Secure<span>Vote</span></span>
    </a>
    <button class="icon-btn sidebar__close" type="button" data-sidebar-close
            aria-label="Close navigation"><?= sv_icon('x', 18) ?></button>
  </div>

  <nav class="nav" aria-label="Sections">
    <?php foreach ($navGroups as $group): ?>
      <p class="nav__group" id="nav-<?= e(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $group['label']))) ?>">
        <?= e($group['label']) ?>
      </p>
      <ul class="nav__list" aria-labelledby="nav-<?= e(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $group['label']))) ?>">
        <?php foreach ($group['items'] as $item): ?>
          <?php $isActive = $activeNav === $item['file']; ?>
          <li>
            <a class="nav__item<?= $isActive ? ' is-active' : '' ?>"
               href="<?= e(url($item['href'])) ?>"
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
        <span class="user-chip__meta"><?= e(ucfirst((string) ($user['role'] ?? ''))) ?></span>
      </span>
    </div>
    <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="logout-form">
      <?= csrf_field() ?>
      <button class="nav__item nav__item--button" type="submit">
        <?= sv_icon('logout', 18, 'nav__icon') ?>
        <span class="nav__label">Log out</span>
      </button>
    </form>
  </div>
</aside>
<?php endif; ?>

<div class="shell">
  <header class="topbar">
    <?php if ($hasSidebar): ?>
      <button class="icon-btn topbar__menu" type="button" data-sidebar-toggle
              aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation">
        <?= sv_icon('menu', 20) ?>
      </button>
    <?php else: ?>
      <a class="brand topbar__brand" href="<?= e(url('index.php')) ?>">
        <?= sv_logo_mark(30) ?>
        <span class="brand__word">Secure<span>Vote</span></span>
      </a>
    <?php endif; ?>

    <div class="topbar__titles">
      <?php if (count($breadcrumb) > 1): ?>
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <ol>
            <?php foreach ($breadcrumb as $index => $crumb): ?>
              <li>
                <?php if (isset($crumb['href']) && $index < count($breadcrumb) - 1): ?>
                  <a href="<?= e(url($crumb['href'])) ?>"><?= e($crumb['label']) ?></a>
                  <?= sv_icon('chevron-right', 14) ?>
                <?php else: ?>
                  <span aria-current="page"><?= e($crumb['label']) ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </nav>
      <?php endif; ?>

      <h1 class="page-title"><?= e($pageTitle) ?></h1>
      <?php if ($pageSubtitle !== ''): ?>
        <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
      <?php endif; ?>
    </div>

    <div class="topbar__actions">
      <?= $pageActions ?>

      <span class="session-chip" title="Session security status">
        <?= sv_icon('shield-check', 16) ?>
        <span class="session-chip__text">Secure session</span>
      </span>

      <button class="icon-btn" type="button" data-theme-toggle
              aria-label="Switch between light and dark appearance" aria-pressed="false">
        <?= sv_icon('sun', 18, 'theme-icon theme-icon--sun') ?>
        <?= sv_icon('moon', 18, 'theme-icon theme-icon--moon') ?>
      </button>
    </div>
  </header>

  <main class="content" id="main-content" tabindex="-1">
<?= $flashHtml ?>
<?php else: ?>
<main class="auth-layout" id="main-content">
<?= $flashHtml ?>
<?php endif; ?>
