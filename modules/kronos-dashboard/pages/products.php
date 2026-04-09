<?php
declare(strict_types=1);

// products.php — E-Commerce product manager
if (!kronos_is_ecommerce()) {
    kronos_redirect('/dashboard');
}

$pageTitle = 'Products';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';

$db       = $app->db();
$products = $db->getResults(
    'SELECT id, name, slug, price, sale_price, stock, status, created_at FROM kronos_products ORDER BY created_at DESC LIMIT 100'
);
?>

<div class="toolbar">
  <button class="btn btn-primary" id="open-add-product">+ Add Product</button>
</div>

<div class="card">
  <table class="data-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>SKU</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($products)): ?>
      <tr><td colspan="6" class="text-center text-muted">No products yet.</td></tr>
      <?php else: ?>
      <?php foreach ($products as $p): ?>
      <tr>
        <td><strong><?= kronos_e($p['name']) ?></strong></td>
        <td><?= kronos_e($p['sku'] ?? '—') ?></td>
        <td>
          $<?= number_format((float)$p['price'], 2) ?>
          <?php if ($p['sale_price']): ?><br><small class="text-success">Sale: $<?= number_format((float)$p['sale_price'], 2) ?></small><?php endif; ?>
        </td>
        <td><?= (int)$p['stock'] ?></td>
        <td><span class="badge badge-<?= kronos_e($p['status']) ?>"><?= kronos_e($p['status']) ?></span></td>
        <td>
          <button class="action-btn" data-edit-product="<?= (int)$p['id'] ?>">Edit</button>
          <button class="action-btn danger" data-delete-product="<?= (int)$p['id'] ?>">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
