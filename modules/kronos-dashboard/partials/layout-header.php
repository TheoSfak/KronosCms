<?php
declare(strict_types=1);
/**
 * Dashboard shell layout.
 * Usage: pass $pageTitle and $bodyContent variables before requiring this file.
 * Or just render it directly around page-specific content.
 */
$user    = kronos_current_user() ?? ['display_name' => 'Guest', 'role' => 'app_user'];
$mode    = kronos_mode();
$appName = kronos_option('app_name', 'KronosCMS');
$fullUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
// Strip the app base path so $nav_active() compares /dashboard/* correctly
$_basePath = rtrim(parse_url(kronos_option('app_url', '/'), PHP_URL_PATH) ?? '', '/');
$currentUri = str_starts_with($fullUri, $_basePath)
    ? substr($fullUri, strlen($_basePath))
    : $fullUri;
if ($currentUri === '' || $currentUri === false) { $currentUri = '/'; }

$nav_active = function(string $path) use ($currentUri): string {
    return str_starts_with($currentUri, $path) ? 'active' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= kronos_e($pageTitle ?? 'Dashboard') ?> — <?= kronos_e($appName) ?></title>
<link rel="stylesheet" href="<?= kronos_asset('css/dashboard.css') ?>">
<script>
window.KronosConfig = {
  appUrl:  <?= json_encode(kronos_option('app_url', '/')) ?>,
  apiBase: <?= json_encode(kronos_option('app_url', '/') . '/api/kronos/v1') ?>,
  mode:    <?= json_encode(kronos_mode()) ?>,
  user:    <?= json_encode(kronos_current_user()) ?>,
  csrf:    <?= json_encode(kronos_csrf_token()) ?>,
};
</script>
<script src="<?= kronos_asset('js/dashboard.js') ?>"></script>
</head>
<body class="dashboard-body<?= !empty($builderPage) ? ' builder-page' : '' ?>">

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span class="sidebar-logo">⚡ <?= kronos_e($appName) ?></span>
    <span class="sidebar-mode-badge <?= $mode === 'ecommerce' ? 'badge-commerce' : 'badge-cms' ?>">
      <?= $mode === 'ecommerce' ? '🛒 Shop' : '📝 CMS' ?>
    </span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Dashboard</div>
    <a href="<?= kronos_url('/dashboard') ?>" class="nav-item <?= $currentUri === '/dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">⌂</span> Home
    </a>

    <?php if ($mode === 'cms'): ?>
    <div class="nav-section-label">Content</div>
    <a href="<?= kronos_url('/dashboard/posts') ?>" class="nav-item <?= $nav_active('/dashboard/posts') ?>">
      <span class="nav-icon">▤</span> Posts
    </a>
    <a href="<?= kronos_url('/dashboard/pages') ?>" class="nav-item <?= $nav_active('/dashboard/pages') ?>">
      <span class="nav-icon">▣</span> Pages
    </a>
    <a href="<?= kronos_url('/dashboard/media') ?>" class="nav-item <?= $nav_active('/dashboard/media') ?>">
      <span class="nav-icon">▧</span> Media
    </a>
    <a href="<?= kronos_url('/dashboard/comments') ?>" class="nav-item <?= $nav_active('/dashboard/comments') ?>">
      <span class="nav-icon">☰</span> Comments
    </a>
    <a href="<?= kronos_url('/dashboard/taxonomies?taxonomy=category') ?>" class="nav-item <?= $nav_active('/dashboard/taxonomies') ?>">
      <span class="nav-icon">#</span> Categories & Tags
    </a>
    <?php do_action('kronos/dashboard/nav/content', $currentUri); ?>
    <?php endif; ?>

    <?php if ($mode === 'ecommerce'): ?>
    <div class="nav-section-label">Commerce</div>
    <a href="<?= kronos_url('/dashboard/products') ?>" class="nav-item <?= $nav_active('/dashboard/products') ?>">
      <span class="nav-icon">□</span> Products
    </a>
    <a href="<?= kronos_url('/dashboard/orders') ?>" class="nav-item <?= $nav_active('/dashboard/orders') ?>">
      <span class="nav-icon">≡</span> Orders
    </a>
    <?php endif; ?>

    <div class="nav-section-label">Appearance</div>
    <a href="<?= kronos_url('/dashboard/builder/1') ?>" class="nav-item <?= $nav_active('/dashboard/builder') ?>">
      <span class="nav-icon">✎</span> Page Builder
    </a>
    <a href="<?= kronos_url('/dashboard/menus') ?>" class="nav-item <?= $nav_active('/dashboard/menus') ?>">
      <span class="nav-icon">☷</span> Menus
    </a>
    <a href="<?= kronos_url('/dashboard/templates') ?>" class="nav-item <?= $nav_active('/dashboard/templates') ?>">
      <span class="nav-icon">▦</span> Templates
    </a>
    <a href="<?= kronos_url('/dashboard/marketplace') ?>" class="nav-item <?= $nav_active('/dashboard/marketplace') ?>">
      <span class="nav-icon">◈</span> Plugins
    </a>

    <div class="nav-section-label">Tools</div>
    <a href="<?= kronos_url('/dashboard/analytics') ?>" class="nav-item <?= $nav_active('/dashboard/analytics') ?>">
      <span class="nav-icon">↗</span> Analytics
    </a>
    <a href="<?= kronos_url('/dashboard/ai') ?>" class="nav-item <?= $nav_active('/dashboard/ai') ?>">
      <span class="nav-icon">AI</span> AI Assistant
    </a>
    <?php do_action('kronos/dashboard/nav/tools', $currentUri); ?>

    <?php if (kronos_user_can('app_manager')): ?>
    <div class="nav-section-label">Manage</div>
    <a href="<?= kronos_url('/dashboard/users') ?>" class="nav-item <?= $nav_active('/dashboard/users') ?>">
      <span class="nav-icon">◎</span> Users
    </a>
    <a href="<?= kronos_url('/dashboard/settings') ?>" class="nav-item <?= $nav_active('/dashboard/settings') ?>">
      <span class="nav-icon">⚙</span> Settings
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <span class="sidebar-user-info">
      <strong><?= kronos_e($user['display_name'] ?? $user['username'] ?? '') ?></strong><br>
      <small><?= kronos_e(kronos_role_label((string) ($user['role'] ?? 'subscriber'))) ?></small>
    </span>
    <a href="<?= kronos_url('/dashboard/logout') ?>" class="logout-btn" title="Logout">⎋</a>
  </div>
</aside>

<div class="main-content">
  <header class="topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')" aria-label="Toggle sidebar">☰</button>
    <h1 class="page-title"><?= kronos_e($pageTitle ?? 'Dashboard') ?></h1>
    <div class="topbar-actions">
      <?= $topbarExtra ?? '' ?>
      <a href="<?= kronos_url('/') ?>" target="_blank" class="topbar-btn" title="View site">↗ Site</a>
    </div>
  </header>

  <main class="page-content">
