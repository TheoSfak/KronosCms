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
            COALESCE(c.email, u.email) AS customer_email
     FROM kronos_orders o
     LEFT JOIN kronos_customers c ON c.id = o.customer_id OR c.user_id = o.customer_id
     LEFT JOIN kronos_users u ON u.id = o.customer_id
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

<div class="modal-overlay" id="order-modal" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3 id="order-modal-title">Order Details</h3>
      <button class="modal-close" id="close-order-modal" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body" id="order-modal-body">
      <p class="text-muted">Loading order…</p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" id="dismiss-order-modal">Close</button>
    </div>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('order-modal');
  const title = document.getElementById('order-modal-title');
  const body = document.getElementById('order-modal-body');

  function esc(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function money(value, currency) {
    const amount = Number(value || 0).toFixed(2);
    return `${esc(currency || 'USD')} ${amount}`;
  }

  function renderOrder(order) {
    const items = Array.isArray(order.items) ? order.items : [];
    const addresses = Array.isArray(order.addresses) ? order.addresses : [];
    const itemRows = items.length
      ? items.map(item => `
          <tr>
            <td>${esc(item.product_name)}</td>
            <td>${Number(item.qty || 0)}</td>
            <td>${money(item.unit_price, order.currency)}</td>
            <td>${money(item.total_price, order.currency)}</td>
          </tr>
        `).join('')
      : '<tr><td colspan="4" class="text-muted text-center">No items recorded.</td></tr>';

    const addressBlocks = addresses.length
      ? addresses.map(addr => `
          <div class="card" style="margin-top:12px">
            <div class="card-body">
              <strong>${esc(addr.address_type || 'address')}</strong>
              <p class="text-muted" style="margin:.5rem 0 0">
                ${esc([addr.first_name, addr.last_name].filter(Boolean).join(' '))}<br>
                ${esc(addr.address_1)} ${esc(addr.address_2)}<br>
                ${esc([addr.city, addr.state, addr.postcode].filter(Boolean).join(', '))}<br>
                ${esc(addr.country)}<br>
                ${esc(addr.email)} ${addr.phone ? ' · ' + esc(addr.phone) : ''}
              </p>
            </div>
          </div>
        `).join('')
      : '<p class="text-muted">No addresses recorded.</p>';

    body.innerHTML = `
      <div class="order-summary-grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px">
        <div><strong>Status</strong><br><span class="badge badge-${esc(order.status)}">${esc(order.status)}</span></div>
        <div><strong>Total</strong><br>${money(order.total, order.currency)}</div>
        <div><strong>Payment</strong><br>${esc(order.payment_method || '—')}</div>
        <div><strong>Created</strong><br>${esc(order.created_at || '—')}</div>
      </div>
      <h4>Items</h4>
      <table class="data-table">
        <thead><tr><th>Product</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
        <tbody>${itemRows}</tbody>
      </table>
      <h4 style="margin-top:18px">Addresses</h4>
      ${addressBlocks}
    `;
  }

  function openModal() {
    modal.style.display = 'flex';
  }

  function closeModal() {
    modal.style.display = 'none';
  }

  document.getElementById('close-order-modal').addEventListener('click', closeModal);
  document.getElementById('dismiss-order-modal').addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

  document.querySelectorAll('[data-view-order]').forEach(btn => {
    btn.addEventListener('click', async function () {
      const orderId = this.dataset.viewOrder;
      if (!orderId) return;

      title.textContent = 'Order Details';
      body.innerHTML = '<p class="text-muted">Loading order…</p>';
      openModal();

      const res = await window.KronosDash.api('/commerce/orders/' + orderId);
      if (res && res.data) {
        title.textContent = 'Order ' + (res.data.order_number || ('#' + orderId));
        renderOrder(res.data);
      } else {
        body.innerHTML = '<p class="text-danger">' + esc((res && res.message) || 'Could not load order.') + '</p>';
      }
    });
  });
}());
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
