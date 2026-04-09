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
$appUrl  = kronos_option('app_url', '/');
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

function nav_active(string $path): string {
    global $currentUri;
    return str_starts_with($currentUri, $path) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= kronos_e($pageTitle ?? 'Dashboard') ?> — <?= kronos_e($appName) ?></title>
<link rel="stylesheet" href="<?= kronos_asset('css/dashboard.css') ?>">
</head>
<body class="dashboard-body">

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span class="sidebar-logo">⚡ <?= kronos_e($appName) ?></span>
    <span class="sidebar-mode-badge <?= $mode === 'ecommerce' ? 'badge-commerce' : 'badge-cms' ?>">
      <?= $mode === 'ecommerce' ? '🛒 Shop' : '📝 CMS' ?>
    </span>
  </div>

  <nav class="sidebar-nav">
    <a href="/dashboard" class="nav-item <?= nav_active('/dashboard') !== '' && $currentUri === '/dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">🏠</span> Overview
    </a>

    <?php if ($mode === 'cms'): ?>
    <a href="/dashboard/content" class="nav-item <?= nav_active('/dashboard/content') ?>">
      <span class="nav-icon">📄</span> Content
    </a>
    <?php endif; ?>

    <?php if ($mode === 'ecommerce'): ?>
    <a href="/dashboard/products" class="nav-item <?= nav_active('/dashboard/products') ?>">
      <span class="nav-icon">📦</span> Products
    </a>
    <a href="/dashboard/orders" class="nav-item <?= nav_active('/dashboard/orders') ?>">
      <span class="nav-icon">🧾</span> Orders
    </a>
    <?php endif; ?>

    <a href="/dashboard/builder/1" class="nav-item <?= nav_active('/dashboard/builder') ?>">
      <span class="nav-icon">🎨</span> Builder
    </a>
    <a href="/dashboard/analytics" class="nav-item <?= nav_active('/dashboard/analytics') ?>">
      <span class="nav-icon">📊</span> Analytics
    </a>
    <a href="/dashboard/ai" class="nav-item <?= nav_active('/dashboard/ai') ?>">
      <span class="nav-icon">🤖</span> AI Chat
    </a>
    <a href="/dashboard/marketplace" class="nav-item <?= nav_active('/dashboard/marketplace') ?>">
      <span class="nav-icon">🛍️</span> Marketplace
    </a>

    <?php if (kronos_user_can('app_manager')): ?>
    <a href="/dashboard/users" class="nav-item <?= nav_active('/dashboard/users') ?>">
      <span class="nav-icon">👥</span> Users
    </a>
    <a href="/dashboard/settings" class="nav-item <?= nav_active('/dashboard/settings') ?>">
      <span class="nav-icon">⚙️</span> Settings
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <span class="sidebar-user-info">
      <strong><?= kronos_e($user['display_name'] ?? $user['username'] ?? '') ?></strong><br>
      <small><?= kronos_e(ucfirst(str_replace('app_', '', $user['role'] ?? ''))) ?></small>
    </span>
    <a href="/dashboard/logout" class="logout-btn" title="Logout">⎋</a>
  </div>
</aside>

<div class="main-content">
  <header class="topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')" aria-label="Toggle sidebar">☰</button>
    <h1 class="page-title"><?= kronos_e($pageTitle ?? 'Dashboard') ?></h1>
    <div class="topbar-actions">
      <a href="<?= kronos_e($appUrl) ?>/" target="_blank" class="topbar-btn" title="View site">↗ Site</a>
    </div>
  </header>

  <main class="page-content">
