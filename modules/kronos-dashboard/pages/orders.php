<?php
declare(strict_types=1);

if (!kronos_is_ecommerce()) {
    kronos_redirect('/dashboard');
}

$pageTitle = 'Orders';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';

$db     = $app->db();
$orders = $db->getResults(
    'SELECT o.id, o.order_number, o.status, o.total, o.payment_method, o.created_at,
            c.email AS customer_email
     FROM kronos_orders o
     LEFT JOIN kronos_customers c ON c.id = o.customer_id
     ORDER BY o.created_at DESC LIMIT 100'
);

$statuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];
?>

<div class="card">
  <table class="data-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Customer</th>
        <th>Status</th>
        <th>Total</th>
        <th>Payment</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
      <tr><td colspan="7" class="text-center text-muted">No orders yet.</td></tr>
      <?php else: ?>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><strong><?= kronos_e($o['order_number']) ?></strong></td>
        <td><?= kronos_e($o['customer_email'] ?? '—') ?></td>
        <td>
          <select class="status-select" data-order-id="<?= (int)$o['id'] ?>">
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $s === $o['status'] ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>$<?= number_format((float)$o['total'], 2) ?></td>
        <td><?= kronos_e(ucfirst($o['payment_method'])) ?></td>
        <td><?= kronos_e(date('Y-m-d', strtotime($o['created_at']))) ?></td>
        <td><button class="action-btn" data-view-order="<?= (int)$o['id'] ?>">View</button></td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
document.querySelectorAll('.status-select').forEach(function(sel) {
  sel.addEventListener('change', async function() {
    const orderId = this.dataset.orderId;
    const status  = this.value;
    await window.KronosDash.api(`/commerce/orders/${orderId}/status`, 'PUT', { status });
  });
});
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
