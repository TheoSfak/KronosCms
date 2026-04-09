<?php
/**
 * Template: Cart
 * Displays items currently in the session-based cart.
 * Cart data is stored in $_SESSION['kronos_cart'] by the Commerce module.
 */
defined('KRONOS_ROOT') || exit;

$cart  = $_SESSION['kronos_cart'] ?? [];
$appUrl = rtrim(kronos_option('app_url', ''), '/');
?>
<?php require __DIR__ . '/base.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cart — <?= kronos_e(kronos_option('site_name', 'KronosCMS')) ?></title>
    <link rel="stylesheet" href="<?= kronos_asset('css/theme.css') ?>">
    <style>
        .cart-wrapper { max-width: 860px; margin: 2rem auto; padding: 0 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .75rem 1rem; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f9fafb; font-weight: 600; }
        .qty-input { width: 60px; padding: .25rem .5rem; border: 1px solid #d1d5db; border-radius: 4px; }
        .remove-btn { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.2rem; }
        .cart-total { text-align: right; margin-top: 1rem; font-size: 1.25rem; font-weight: 700; }
        .checkout-btn { display: inline-block; margin-top: 1rem; background: #2563eb; color: #fff;
                        padding: .75rem 2rem; border-radius: 6px; text-decoration: none; font-weight: 600; }
        .checkout-btn:hover { background: #1d4ed8; }
        .empty-msg { text-align: center; padding: 4rem 1rem; color: #6b7280; font-size: 1.125rem; }
    </style>
</head>
<body>
<?php do_action('kronos/theme/header'); ?>

<div class="cart-wrapper">
    <h1>Your Cart</h1>

    <?php if (empty($cart)): ?>
        <p class="empty-msg">Your cart is empty. <a href="<?= $appUrl ?>">Continue shopping</a></p>
    <?php else: ?>
        <table id="cart-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $total = 0.0;
            foreach ($cart as $productId => $item):
                $subtotal = (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1);
                $total   += $subtotal;
            ?>
                <tr data-product-id="<?= (int)$productId ?>">
                    <td><?= kronos_e($item['name'] ?? 'Product') ?></td>
                    <td><?= kronos_e(number_format((float)($item['price'] ?? 0), 2)) ?></td>
                    <td>
                        <input type="number" class="qty-input"
                               value="<?= (int)($item['quantity'] ?? 1) ?>"
                               min="1"
                               data-product-id="<?= (int)$productId ?>">
                    </td>
                    <td class="subtotal"><?= kronos_e(number_format($subtotal, 2)) ?></td>
                    <td>
                        <button class="remove-btn" data-product-id="<?= (int)$productId ?>"
                                title="Remove">&times;</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="cart-total">
            Total: <span id="cart-total"><?= kronos_e(number_format($total, 2)) ?></span>
        </div>

        <div style="text-align:right">
            <a class="checkout-btn" href="<?= $appUrl ?>/checkout">Proceed to Checkout &rarr;</a>
        </div>
    <?php endif; ?>
</div>

<?php do_action('kronos/theme/footer'); ?>

<script src="<?= kronos_asset('js/theme.js') ?>"></script>
<script>
(function () {
    var apiBase = '<?= $appUrl ?>/api/kronos/v1';

    async function updateCart(productId, quantity) {
        var res = await fetch(apiBase + '/commerce/cart', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, quantity: quantity })
        });
        return res.json();
    }

    // Quantity change
    document.querySelectorAll('.qty-input').forEach(function (input) {
        input.addEventListener('change', async function () {
            var productId = parseInt(this.dataset.productId);
            var qty       = Math.max(1, parseInt(this.value) || 1);
            this.value    = qty;

            var row = this.closest('tr');
            var price = parseFloat(row.querySelector('td:nth-child(2)').textContent);
            row.querySelector('.subtotal').textContent = (price * qty).toFixed(2);

            await updateCart(productId, qty);
            recalcTotal();
        });
    });

    // Remove item
    document.querySelectorAll('.remove-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var productId = parseInt(this.dataset.productId);
            await updateCart(productId, 0);
            this.closest('tr').remove();
            recalcTotal();
        });
    });

    function recalcTotal() {
        var total = 0;
        document.querySelectorAll('#cart-table .subtotal').forEach(function (cell) {
            total += parseFloat(cell.textContent) || 0;
        });
        document.getElementById('cart-total').textContent = total.toFixed(2);
    }
}());
</script>
</body>
</html>
