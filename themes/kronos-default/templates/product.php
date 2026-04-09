<?php
// Product page template (ecommerce mode)
/** @var array $post   Post / product record */
/** @var string $html  Builder HTML */

$app     = \Kronos\Core\KronosApp::getInstance();
$product = $app->db()->getRow(
    'SELECT * FROM kronos_products WHERE id = ? LIMIT 1',
    [(int) ($post['id'] ?? 0)]
);

ob_start();
?>
<div class="container mt-24">
  <div class="product-layout">
    <div class="product-images">
      <!-- Placeholder: builder layout can render images -->
      <?= $html ?? '' ?>
    </div>
    <div class="product-info">
      <h1><?= kronos_e($post['title'] ?? '') ?></h1>

      <?php if ($product): ?>
      <div class="product-price">
        <?php if (!empty($product['sale_price'])): ?>
        <span class="price-original" style="text-decoration:line-through;color:var(--text-muted)">
          $<?= number_format((float)$product['price'], 2) ?>
        </span>
        <span class="price-sale" style="color:var(--danger);font-weight:700;font-size:1.5rem">
          $<?= number_format((float)$product['sale_price'], 2) ?>
        </span>
        <?php else: ?>
        <span class="price" style="font-weight:700;font-size:1.5rem">
          $<?= number_format((float)($product['price'] ?? 0), 2) ?>
        </span>
        <?php endif; ?>
      </div>

      <?php if ((int)($product['manage_stock'] ?? 0) && (int)($product['stock'] ?? 0) <= 0): ?>
      <p style="color:var(--danger)">Out of stock</p>
      <?php else: ?>
      <form id="add-to-cart-form">
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
          <label style="font-size:.875rem">Qty</label>
          <input type="number" name="quantity" value="1" min="1" style="width:70px">
        </div>
        <button type="submit" class="btn btn-primary">Add to Cart 🛒</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.getElementById('add-to-cart-form')?.addEventListener('submit', async function(e){
  e.preventDefault();
  const data = Object.fromEntries(new FormData(this).entries());
  data.quantity = parseInt(data.quantity, 10) || 1;

  const res = await fetch('/api/kronos/v1/commerce/cart', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(data)
  });
  const json = await res.json();
  if (json.success) {
    alert('Added to cart!');
  } else {
    alert(json.message || 'Failed to add to cart.');
  }
});
</script>
<?php
$content   = ob_get_clean();
$title     = ($post['title'] ?? '') . ' — ' . kronos_option('app_name', 'KronosCMS');
$bodyClass = 'product';

include __DIR__ . '/base.php';
