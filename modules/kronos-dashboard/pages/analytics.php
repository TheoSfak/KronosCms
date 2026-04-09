<?php
declare(strict_types=1);
$pageTitle = 'Analytics';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';

$db = $app->db();
$events = $db->getResults(
    'SELECT event_type, COUNT(*) AS total FROM kronos_analytics GROUP BY event_type ORDER BY total DESC LIMIT 20'
);
$recentEvents = $db->getResults(
    'SELECT event_type, entity_id, created_at FROM kronos_analytics ORDER BY created_at DESC LIMIT 30'
);
?>

<div class="stats-grid">
  <?php foreach ($events as $ev): ?>
  <div class="stat-card">
    <div class="stat-value"><?= number_format((int)$ev['total']) ?></div>
    <div class="stat-label"><?= kronos_e($ev['event_type']) ?></div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($events)): ?>
  <div class="card" style="grid-column:1/-1"><p class="text-muted text-center">No analytics data yet. Events will appear as users interact with your site.</p></div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:2rem">
  <h2 class="card-title">Recent Events</h2>
  <table class="data-table">
    <thead><tr><th>Event</th><th>Entity</th><th>Time</th></tr></thead>
    <tbody>
      <?php foreach ($recentEvents as $ev): ?>
      <tr>
        <td><?= kronos_e($ev['event_type']) ?></td>
        <td><?= $ev['entity_id'] ? (int)$ev['entity_id'] : '—' ?></td>
        <td><?= kronos_e($ev['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($recentEvents)): ?>
      <tr><td colspan="3" class="text-muted text-center">No events</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
