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
    <a href="<?= kronos_url('/dashboard') ?>" class="nav-item <?= $currentUri === '/dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">🏠</span> Overview
    </a>

    <?php if ($mode === 'cms'): ?>
    <a href="<?= kronos_url('/dashboard/content') ?>" class="nav-item <?= $nav_active('/dashboard/content') ?>">
      <span class="nav-icon">📄</span> Content
    </a>
    <?php endif; ?>

    <?php if ($mode === 'ecommerce'): ?>
    <a href="<?= kronos_url('/dashboard/products') ?>" class="nav-item <?= $nav_active('/dashboard/products') ?>">
      <span class="nav-icon">📦</span> Products
    </a>
    <a href="<?= kronos_url('/dashboard/orders') ?>" class="nav-item <?= $nav_active('/dashboard/orders') ?>">
      <span class="nav-icon">🧾</span> Orders
    </a>
    <?php endif; ?>

    <a href="<?= kronos_url('/dashboard/builder/1') ?>" class="nav-item <?= $nav_active('/dashboard/builder') ?>">
      <span class="nav-icon">🎨</span> Builder
    </a>
    <a href="<?= kronos_url('/dashboard/analytics') ?>" class="nav-item <?= $nav_active('/dashboard/analytics') ?>">
      <span class="nav-icon">📊</span> Analytics
    </a>
    <a href="<?= kronos_url('/dashboard/ai') ?>" class="nav-item <?= $nav_active('/dashboard/ai') ?>">
      <span class="nav-icon">🤖</span> AI Chat
    </a>
    <a href="<?= kronos_url('/dashboard/marketplace') ?>" class="nav-item <?= $nav_active('/dashboard/marketplace') ?>">
      <span class="nav-icon">🛍️</span> Marketplace
    </a>

    <?php if (kronos_user_can('app_manager')): ?>
    <a href="<?= kronos_url('/dashboard/users') ?>" class="nav-item <?= $nav_active('/dashboard/users') ?>">
      <span class="nav-icon">👥</span> Users
    </a>
    <a href="<?= kronos_url('/dashboard/settings') ?>" class="nav-item <?= $nav_active('/dashboard/settings') ?>">
      <span class="nav-icon">⚙️</span> Settings
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <span class="sidebar-user-info">
      <strong><?= kronos_e($user['display_name'] ?? $user['username'] ?? '') ?></strong><br>
      <small><?= kronos_e(ucfirst(str_replace('app_', '', $user['role'] ?? ''))) ?></small>
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
