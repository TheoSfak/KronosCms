<?php
declare(strict_types=1);
$pageTitle = 'Overview';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';

// Quick stats from DB
$db         = $app->db();
$postCount  = (int) $db->getVar('SELECT COUNT(*) FROM kronos_posts');
$userCount  = (int) $db->getVar('SELECT COUNT(*) FROM kronos_users');
$layoutCount = (int) $db->getVar('SELECT COUNT(*) FROM kronos_builder_layouts');
$orderCount = (int) $db->getVar('SELECT COUNT(*) FROM kronos_orders');
$revenue    = (float) ($db->getVar("SELECT SUM(total) FROM kronos_orders WHERE status = 'completed'") ?? 0);
$isCommerce = kronos_is_ecommerce();
?>

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
