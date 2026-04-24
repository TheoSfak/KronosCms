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
    'SELECT id, name, slug, sku, price, sale_price, stock, status, created_at FROM kronos_products ORDER BY created_at DESC LIMIT 100'
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
      <tr id="product-row-<?= (int)$p['id'] ?>">
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
          <button class="action-btn danger"
                  data-delete-url="/api/kronos/v1/commerce/products/<?= (int)$p['id'] ?>"
                  data-delete-target="#product-row-<?= (int)$p['id'] ?>"
                  data-confirm-label="<?= kronos_e($p['name']) ?>">
            Delete
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="product-modal" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3 id="product-modal-title">Add Product</h3>
      <button class="modal-close" id="close-product-modal" aria-label="Close">&times;</button>
    </div>
    <form id="product-form" novalidate>
      <input type="hidden" name="id" id="product-id">
      <div class="form-group">
        <label for="product-name">Name <span class="required">*</span></label>
        <input type="text" id="product-name" name="name" required>
      </div>
      <div class="form-group">
        <label for="product-sku">SKU</label>
        <input type="text" id="product-sku" name="sku">
      </div>
      <div class="form-group">
        <label for="product-short-desc">Short Description</label>
        <textarea id="product-short-desc" name="short_desc" rows="2"></textarea>
      </div>
      <div class="form-group">
        <label for="product-description">Description</label>
        <textarea id="product-description" name="description" rows="4"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        <div class="form-group">
          <label for="product-price">Price</label>
          <input type="number" id="product-price" name="price" min="0" step="0.01" value="0">
        </div>
        <div class="form-group">
          <label for="product-sale-price">Sale Price</label>
          <input type="number" id="product-sale-price" name="sale_price" min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="product-stock">Stock</label>
          <input type="number" id="product-stock" name="stock" min="0" step="1" value="0">
        </div>
      </div>
      <div class="form-group">
        <label for="product-status">Status</label>
        <select id="product-status" name="status">
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </div>
      <div class="form-error" id="product-error" style="display:none"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" id="cancel-product">Cancel</button>
        <button type="submit" class="btn btn-primary" id="save-product">Save Product</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('product-modal');
  const form = document.getElementById('product-form');
  const title = document.getElementById('product-modal-title');
  const error = document.getElementById('product-error');
  const submit = document.getElementById('save-product');

  function openModal(product) {
    form.reset();
    error.style.display = 'none';
    document.getElementById('product-id').value = product?.id || '';
    document.getElementById('product-name').value = product?.name || '';
    document.getElementById('product-sku').value = product?.sku || '';
    document.getElementById('product-short-desc').value = product?.short_desc || '';
    document.getElementById('product-description').value = product?.description || '';
    document.getElementById('product-price').value = product?.price || '0';
    document.getElementById('product-sale-price').value = product?.sale_price || '';
    document.getElementById('product-stock').value = product?.stock || '0';
    document.getElementById('product-status').value = product?.status || 'draft';
    title.textContent = product?.id ? 'Edit Product' : 'Add Product';
    modal.style.display = 'flex';
  }

  function closeModal() {
    modal.style.display = 'none';
  }

  document.getElementById('open-add-product').addEventListener('click', () => openModal(null));
  document.getElementById('close-product-modal').addEventListener('click', closeModal);
  document.getElementById('cancel-product').addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

  document.querySelectorAll('[data-edit-product]').forEach(btn => {
    btn.addEventListener('click', async function () {
      btn.disabled = true;
      const res = await window.KronosDash.api('/commerce/products/' + btn.dataset.editProduct);
      btn.disabled = false;
      if (res && res.data) {
        openModal(res.data);
      } else {
        window.KronosDash.showToast((res && res.message) || 'Could not load product.', 'error');
      }
    });
  });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    error.style.display = 'none';
    submit.disabled = true;
    submit.textContent = 'Saving...';

    const data = Object.fromEntries(new FormData(form));
    const id = data.id;
    delete data.id;
    data.price = Number(data.price || 0);
    data.stock = Number(data.stock || 0);
    if (data.sale_price === '') delete data.sale_price;
    else data.sale_price = Number(data.sale_price);

    const res = await window.KronosDash.api(
      id ? '/commerce/products/' + id : '/commerce/products',
      id ? 'PUT' : 'POST',
      data
    );

    submit.disabled = false;
    submit.textContent = 'Save Product';

    if (res && res.success) {
      closeModal();
      location.reload();
      return;
    }

    error.textContent = (res && res.message) || 'Product could not be saved.';
    error.style.display = 'block';
  });
}());
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
