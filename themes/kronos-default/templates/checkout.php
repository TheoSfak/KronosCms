<?php
/**
 * Template: Checkout
 * Billing/shipping form + Stripe Elements or PayPal button.
 * Submits order to POST /api/kronos/v1/commerce/orders.
 */
defined('KRONOS_ROOT') || exit;

$cart    = $_SESSION['kronos_cart'] ?? [];
$appUrl  = rtrim(kronos_option('app_url', ''), '/');
$stripeKey = kronos_option('stripe_public_key', '');
$paypalEnabled = !empty(kronos_option('paypal_client_id', ''));
$gateway = kronos_option('payment_gateway', 'cod');
?>
<?php require __DIR__ . '/base.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Checkout — <?= kronos_e(kronos_option('site_name', 'KronosCMS')) ?></title>
    <link rel="stylesheet" href="<?= kronos_asset('css/theme.css') ?>">
    <?php if ($stripeKey): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>
    <style>
        .checkout-wrapper { max-width: 760px; margin: 2rem auto; padding: 0 1rem; }
        .checkout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 600px) { .checkout-grid { grid-template-columns: 1fr; } }
        fieldset { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem 1.25rem; margin: 0; }
        legend { font-weight: 600; padding: 0 .5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: .25rem; font-size: .875rem; font-weight: 500; }
        input, select { width: 100%; padding: .5rem .75rem; border: 1px solid #d1d5db;
                         border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        #stripe-card-element { border: 1px solid #d1d5db; border-radius: 6px; padding: .625rem .75rem; }
        #stripe-card-errors { color: #ef4444; margin-top: .5rem; font-size: .875rem; }
        .order-summary { background: #f9fafb; border-radius: 8px; padding: 1rem 1.25rem; }
        .order-summary table { width: 100%; border-collapse: collapse; }
        .order-summary td { padding: .4rem 0; font-size: .9rem; }
        .order-summary .total-row td { font-weight: 700; border-top: 1px solid #d1d5db;
                                        padding-top: .75rem; margin-top: .25rem; }
        .place-order-btn { margin-top: 1.5rem; width: 100%; padding: .9rem;
                            background: #16a34a; color: #fff; border: none; border-radius: 8px;
                            font-size: 1.1rem; font-weight: 700; cursor: pointer; }
        .place-order-btn:disabled { opacity: .6; cursor: not-allowed; }
        .notice { background: #fef9c3; border: 1px solid #fde68a; border-radius: 6px;
                   padding: .75rem 1rem; margin-bottom: 1rem; font-size: .9rem; }
    </style>
</head>
<body>
<?php do_action('kronos/theme/header'); ?>

<div class="checkout-wrapper">
    <h1>Checkout</h1>

    <?php if (empty($cart)): ?>
        <p>Your cart is empty. <a href="<?= $appUrl ?>">Continue shopping</a></p>
    <?php else: ?>

    <?php if ($gateway === 'cod'): ?>
    <p class="notice">Payment method: <strong>Cash on Delivery</strong> — no card details needed.</p>
    <?php endif; ?>

    <form id="checkout-form" novalidate>
        <input type="hidden" name="kronos_csrf" value="<?= kronos_e(kronos_csrf_token()) ?>">

        <div class="checkout-grid">
            <!-- Billing -->
            <fieldset>
                <legend>Billing Address</legend>
                <div class="form-group">
                    <label for="billing_name">Full name *</label>
                    <input type="text" id="billing_name" name="billing_name" required>
                </div>
                <div class="form-group">
                    <label for="billing_email">Email *</label>
                    <input type="email" id="billing_email" name="billing_email" required>
                </div>
                <div class="form-group">
                    <label for="billing_phone">Phone</label>
                    <input type="tel" id="billing_phone" name="billing_phone">
                </div>
                <div class="form-group">
                    <label for="billing_address">Street address *</label>
                    <input type="text" id="billing_address" name="billing_address" required>
                </div>
                <div class="form-group">
                    <label for="billing_city">City *</label>
                    <input type="text" id="billing_city" name="billing_city" required>
                </div>
                <div class="form-group">
                    <label for="billing_postcode">Postcode</label>
                    <input type="text" id="billing_postcode" name="billing_postcode">
                </div>
                <div class="form-group">
                    <label for="billing_country">Country *</label>
                    <input type="text" id="billing_country" name="billing_country" value="US" required>
                </div>
            </fieldset>

            <!-- Order summary -->
            <div>
                <div class="order-summary">
                    <h3 style="margin-top:0">Order Summary</h3>
                    <table>
                        <?php
                        $total = 0.0;
                        foreach ($cart as $productId => $item):
                            $sub   = (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1);
                            $total += $sub;
                        ?>
                        <tr>
                            <td><?= kronos_e($item['name'] ?? 'Product') ?> &times;<?= (int)($item['quantity'] ?? 1) ?></td>
                            <td style="text-align:right"><?= kronos_e(number_format($sub, 2)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td>Total</td>
                            <td style="text-align:right"><?= kronos_e(number_format($total, 2)) ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Payment method -->
                <?php if ($stripeKey && $gateway === 'stripe'): ?>
                <fieldset style="margin-top:1rem">
                    <legend>Card Payment</legend>
                    <div id="stripe-card-element"></div>
                    <div id="stripe-card-errors" role="alert"></div>
                </fieldset>
                <?php elseif ($paypalEnabled && $gateway === 'paypal'): ?>
                <div id="paypal-button-container" style="margin-top:1rem"></div>
                <script src="https://www.paypal.com/sdk/js?client-id=<?= kronos_e(kronos_option('paypal_client_id', '')) ?>&currency=USD"></script>
                <?php endif; ?>
            </div>
        </div><!-- /.checkout-grid -->

        <button type="submit" class="place-order-btn" id="place-order-btn">Place Order</button>
    </form>

    <div id="checkout-result" style="margin-top:1rem;display:none"></div>

    <?php endif; ?>
</div>

<?php do_action('kronos/theme/footer'); ?>

<script src="<?= kronos_asset('js/theme.js') ?>"></script>
<script>
(function () {
    var apiBase  = '<?= $appUrl ?>/api/kronos/v1';
    var gateway  = '<?= kronos_e($gateway) ?>';
    var stripeKey = '<?= kronos_e($stripeKey) ?>';
    var stripe, cardElement;

    // ── Stripe setup ──────────────────────────────────────────────
    if (gateway === 'stripe' && stripeKey) {
        stripe      = Stripe(stripeKey);
        var elements = stripe.elements();
        cardElement  = elements.create('card');
        cardElement.mount('#stripe-card-element');
        cardElement.on('change', function (e) {
            document.getElementById('stripe-card-errors').textContent = e.error ? e.error.message : '';
        });
    }

    // ── PayPal setup ──────────────────────────────────────────────
    if (gateway === 'paypal' && typeof paypal !== 'undefined') {
        paypal.Buttons({
            createOrder: function () {
                return fetch(apiBase + '/commerce/orders', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(collectFormData('paypal'))
                }).then(function (r) { return r.json(); })
                  .then(function (d) { return d.paypal_order_id; });
            },
            onApprove: function (data) {
                showResult('success', 'Payment approved! Order ID: ' + data.orderID);
            },
            onError: function (err) {
                showResult('error', 'PayPal error: ' + err);
            }
        }).render('#paypal-button-container');
    }

    // ── Form submit (Stripe / COD) ─────────────────────────────────
    document.getElementById('checkout-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        var btn = document.getElementById('place-order-btn');
        btn.disabled = true;

        try {
            var payload = collectFormData(gateway);

            // Stripe: get payment method first
            if (gateway === 'stripe' && stripe) {
                var result = await stripe.createPaymentMethod({ type: 'card', card: cardElement });
                if (result.error) {
                    document.getElementById('stripe-card-errors').textContent = result.error.message;
                    btn.disabled = false;
                    return;
                }
                payload.stripe_payment_method_id = result.paymentMethod.id;
            }

            var res  = await fetch(apiBase + '/commerce/orders', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var data = await res.json();

            if (data.success) {
                showResult('success', 'Order placed! Order #' + data.order_id + '. Thank you.');
                document.getElementById('checkout-form').reset();
            } else {
                showResult('error', data.message || 'Order failed.');
                btn.disabled = false;
            }
        } catch (err) {
            showResult('error', 'Unexpected error: ' + err.message);
            btn.disabled = false;
        }
    });

    function collectFormData(gw) {
        var f = document.getElementById('checkout-form');
        var fd = new FormData(f);
        var data = {};
        fd.forEach(function (v, k) { data[k] = v; });
        data.gateway = gw;
        return data;
    }

    function showResult(type, msg) {
        var el = document.getElementById('checkout-result');
        el.style.display = 'block';
        el.style.padding  = '1rem';
        el.style.borderRadius = '8px';
        el.style.background = type === 'success' ? '#dcfce7' : '#fee2e2';
        el.style.color      = type === 'success' ? '#166534' : '#991b1b';
        el.textContent = msg;
    }
}());
</script>
</body>
</html>
