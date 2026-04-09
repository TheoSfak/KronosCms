<?php
declare(strict_types=1);
$pageTitle = 'Overview';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';

$db         = $app->db();
$user       = kronos_current_user() ?? [];
$mode       = kronos_mode();
$isCommerce = kronos_is_ecommerce();

// Stats
$postCount   = (int) $db->getVar('SELECT COUNT(*) FROM kronos_posts');
$userCount   = (int) $db->getVar('SELECT COUNT(*) FROM kronos_users');
$layoutCount = (int) $db->getVar('SELECT COUNT(*) FROM kronos_builder_layouts');
$orderCount  = $isCommerce ? (int) $db->getVar('SELECT COUNT(*) FROM kronos_orders') : 0;
$revenue     = $isCommerce ? (float) ($db->getVar("SELECT SUM(total) FROM kronos_orders WHERE status = 'completed'") ?? 0) : 0.0;

// Recent posts
$recentPosts  = $db->getResults('SELECT id, title, status, post_type, created_at FROM kronos_posts ORDER BY created_at DESC LIMIT 6') ?? [];
$recentEvents = $db->getResults('SELECT event_type, entity_id, created_at FROM kronos_analytics ORDER BY created_at DESC LIMIT 10') ?? [];

// Greeting
$hour     = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$name     = kronos_e($user['display_name'] ?? $user['username'] ?? 'there');
$appName  = kronos_e(kronos_option('app_name', 'KronosCMS'));
$isNew    = $postCount === 0 && $layoutCount <= 1;
?>

<!-- Welcome bar -->
<div class="overview-welcome">
  <div>
    <h2 class="overview-greeting"><?= $greeting ?>, <?= $name ?>! 👋</h2>
    <p class="text-muted">Here's what's happening with <strong><?= $appName ?></strong>.</p>
  </div>
  <div class="overview-quick-actions">
    <?php if (!$isCommerce): ?>
    <a href="<?= kronos_url('/dashboard/content/new') ?>" class="btn btn-primary">+ New Post</a>
    <?php else: ?>
    <a href="<?= kronos_url('/dashboard/products') ?>" class="btn btn-primary">+ New Product</a>
    <?php endif; ?>
    <a href="<?= kronos_url('/dashboard/templates') ?>" class="btn btn-secondary">📐 Templates</a>
    <a href="<?= kronos_url('/') ?>" target="_blank" class="btn btn-ghost">↗ View Site</a>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">📄</div>
    <div class="stat-value"><?= $postCount ?></div>
    <div class="stat-label">Posts / Pages</div>
    <div class="stat-sub"><a href="<?= kronos_url('/dashboard/content') ?>">Manage →</a></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🎨</div>
    <div class="stat-value"><?= $layoutCount ?></div>
    <div class="stat-label">Builder Layouts</div>
    <div class="stat-sub"><a href="<?= kronos_url('/dashboard/builder/1') ?>">Open →</a></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= $userCount ?></div>
    <div class="stat-label">Users</div>
    <div class="stat-sub"><a href="<?= kronos_url('/dashboard/users') ?>">Manage →</a></div>
  </div>
  <?php if ($isCommerce): ?>
  <div class="stat-card">
    <div class="stat-icon">🧾</div>
    <div class="stat-value"><?= $orderCount ?></div>
    <div class="stat-label">Orders</div>
    <div class="stat-sub"><a href="<?= kronos_url('/dashboard/orders') ?>">View →</a></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-value">$<?= number_format($revenue, 0) ?></div>
    <div class="stat-label">Revenue</div>
    <div class="stat-sub">Completed orders</div>
  </div>
  <?php endif; ?>
</div>

<!-- Two-column body -->
<div class="overview-cols">

  <!-- Left: Recent Content -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Content</span>
      <a href="<?= kronos_url('/dashboard/content') ?>" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <?php if (empty($recentPosts)): ?>
    <div class="empty-state">
      <p>No content yet.</p>
      <a href="<?= kronos_url('/dashboard/content/new') ?>" class="btn btn-primary btn-sm">Create your first post →</a>
    </div>
    <?php else: ?>
    <table class="data-table">
      <tbody>
        <?php foreach ($recentPosts as $p): ?>
        <tr>
          <td>
            <strong><?= kronos_e($p['title']) ?></strong><br>
            <small class="text-muted"><?= kronos_e($p['post_type']) ?> · <?= kronos_e(date('M j', strtotime($p['created_at']))) ?></small>
          </td>
          <td><span class="badge badge-<?= $p['status'] === 'published' ? 'success' : 'draft' ?>"><?= kronos_e($p['status']) ?></span></td>
          <td><a href="<?= kronos_url('/dashboard/content/' . (int)$p['id']) ?>" class="action-btn">Edit</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Right column -->
  <div class="overview-right">

    <?php if ($isNew): ?>
    <!-- Getting Started checklist -->
    <div class="card getting-started-card">
      <div class="card-header"><span class="card-title">🚀 Getting Started</span></div>
      <ul class="getting-started-list">
        <li>
          <span class="gs-check gs-done">✅</span>
          <div><strong>Install KronosCMS</strong><br><small class="text-muted">You're up and running!</small></div>
        </li>
        <li>
          <span class="gs-check">○</span>
          <div><strong>Import a template</strong><br><small class="text-muted">Start with a ready-made layout</small></div>
          <a href="<?= kronos_url('/dashboard/templates') ?>" class="btn btn-secondary btn-sm">Browse→</a>
        </li>
        <li>
          <span class="gs-check">○</span>
          <div><strong>Create your first post</strong><br><small class="text-muted">Add content to your site</small></div>
          <a href="<?= kronos_url('/dashboard/content/new') ?>" class="btn btn-primary btn-sm">Create→</a>
        </li>
        <li>
          <span class="gs-check">○</span>
          <div><strong>Open the Builder</strong><br><small class="text-muted">Design pages visually</small></div>
          <a href="<?= kronos_url('/dashboard/builder/1') ?>" class="btn btn-ghost btn-sm">Open→</a>
        </li>
        <li>
          <span class="gs-check">○</span>
          <div><strong>Customise settings</strong><br><small class="text-muted">App name, mode, AI model</small></div>
          <a href="<?= kronos_url('/dashboard/settings') ?>" class="btn btn-ghost btn-sm">Settings→</a>
        </li>
      </ul>
    </div>
    <?php else: ?>
    <!-- Recent Events (from DB) -->
    <div class="card">
      <div class="card-header"><span class="card-title">Recent Events</span></div>
      <?php if (empty($recentEvents)): ?>
      <p class="text-muted" style="padding:16px">No events yet.</p>
      <?php else: ?>
      <div class="activity-list">
        <?php foreach ($recentEvents as $ev):
          $icon = match($ev['event_type']) {
            'page_view'    => '👁️',
            'order_update' => '🧾',
            default        => '🔔',
          };
        ?>
        <div class="activity-item">
          <span><?= $icon ?> <?= kronos_e(str_replace('_', ' ', $ev['event_type'])) ?></span>
          <small class="text-muted"><?= kronos_e(date('H:i', strtotime($ev['created_at']))) ?></small>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Live Feed (SSE) -->
    <div class="card" style="margin-top:16px">
      <div class="card-header"><span class="card-title">Live Feed</span></div>
      <div id="activity-feed" class="activity-list">
        <p class="text-muted" style="padding:12px 16px;font-size:.8rem">Waiting for live events…</p>
      </div>
    </div>

    <!-- Quick links -->
    <div class="card quick-links-card" style="margin-top:16px">
      <div class="card-header"><span class="card-title">Quick Links</span></div>
      <div class="quick-links">
        <a href="<?= kronos_url('/dashboard/marketplace') ?>">🛍️ Marketplace</a>
        <a href="<?= kronos_url('/dashboard/ai') ?>">🤖 AI Chat</a>
        <a href="<?= kronos_url('/dashboard/analytics') ?>">📊 Analytics</a>
        <a href="<?= kronos_url('/dashboard/settings') ?>">⚙️ Settings</a>
      </div>
    </div>
  </div>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>

<script>
(function() {
  const feed = document.getElementById('activity-feed');
  if (!feed || !window.KronosConfig || !window.KronosConfig.apiBase) return;
  const source = new EventSource(window.KronosConfig.apiBase + '/stream', { withCredentials: true });

  source.addEventListener('order_update', function(e) {
    try {
      const d = JSON.parse(e.data);
      addFeedItem('🧾', 'Order <strong>#' + d.order_number + '</strong> → ' + d.status);
    } catch(_) {}
  });
  source.addEventListener('notification', function(e) {
    try {
      const d = JSON.parse(e.data);
      addFeedItem('🔔', d.message || JSON.stringify(d));
    } catch(_) {}
  });
  source.addEventListener('ping', function() {});
  source.onerror = function() {
    source.close();
    const p = feed.querySelector('p');
    if (p) p.textContent = 'Live feed disconnected.';
  };

  function addFeedItem(icon, html) {
    const item = document.createElement('div');
    item.className = 'activity-item';
    const now = new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
    item.innerHTML = '<span>' + icon + ' ' + html + '</span><small class="text-muted">' + now + '</small>';
    const placeholder = feed.querySelector('p');
    if (placeholder) placeholder.remove();
    feed.prepend(item);
    if (feed.children.length > 25) feed.lastChild.remove();
  }
})();
</script>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">📄</div>
    <div class="stat-value"><?= $postCount ?></div>
    <div class="stat-label">Posts / Pages</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🎨</div>
    <div class="stat-value"><?= $layoutCount ?></div>
    <div class="stat-label">Builder Layouts</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= $userCount ?></div>
    <div class="stat-label">Users</div>
  </div>
  <?php if ($isCommerce): ?>
  <div class="stat-card">
    <div class="stat-icon">🧾</div>
    <div class="stat-value"><?= $orderCount ?></div>
    <div class="stat-label">Orders</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-value">$<?= number_format($revenue, 2) ?></div>
    <div class="stat-label">Revenue (Completed)</div>
  </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:2rem">
  <h2 class="card-title">Recent Activity</h2>
  <div id="activity-feed" class="activity-feed">
    <p class="text-muted">Loading live events…</p>
  </div>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>

<script>
// SSE: live order updates on overview
(function() {
  const feed = document.getElementById('activity-feed');
  if (!feed || !window.KronosConfig || !window.KronosConfig.apiBase) return;
  const source = new EventSource(window.KronosConfig.apiBase + '/stream');

  source.addEventListener('order_update', function(e) {
    try {
      const d = JSON.parse(e.data);
      const item = document.createElement('div');
      item.className = 'activity-item';
      item.innerHTML = `<span class="activity-icon">🧾</span> Order <strong>#${d.order_number}</strong> — status changed to <strong>${d.status}</strong>`;
      feed.prepend(item);
      if (feed.children.length > 20) feed.lastChild.remove();
    } catch(_) {}
  });

  source.addEventListener('notification', function(e) {
    try {
      const d = JSON.parse(e.data);
      const item = document.createElement('div');
      item.className = 'activity-item';
      item.innerHTML = `<span class="activity-icon">🔔</span> ${d.message || JSON.stringify(d)}`;
      feed.prepend(item);
    } catch(_) {}
  });

  source.onerror = function() {
    source.close();
    feed.innerHTML = '<p class="text-muted">Live feed unavailable.</p>';
  };
})();
</script>
