<?php
declare(strict_types=1);

namespace Kronos;

use Kronos\Core\KronosModule;
use Kronos\Core\KronosApp;
use Kronos\Commerce\PaymentManager;

/**
 * KronosCommerceModule — wires the commerce payment layer into KronosCMS.
 *
 * This module:
 *  - Only activates when the app is in "ecommerce" mode.
 *  - Hooks into `kronos/commerce/order_created` to initiate payment.
 *  - Registers a /payment/webhook route for gateway callbacks.
 */
class KronosCommerceModule extends KronosModule
{
    public function getName(): string
    {
        return 'kronos-commerce';
    }

    public function boot(): void
    {
        if (!kronos_is_ecommerce()) {
            return;
        }

        $app    = KronosApp::getInstance();
        $router = $app->router();

        // Webhook endpoint (must be unauthenticated for Stripe/PayPal callbacks)
        $router->add('POST', '/payment/webhook/{gateway}', [$this, 'handleWebhook']);

        // Hook: after an order is created, expose the payment intent to the response
        add_action('kronos/commerce/order_created', [$this, 'onOrderCreated'], 10);
    }

    public function install(): void
    {
        // Tables already created by KronosInstaller
    }

    // ── Handlers ───────────────────────────────────────────────────

    public function onOrderCreated(array $orderData): void
    {
        // Fires after order row is inserted. Dispatch payment intent creation.
        // The result is made available via a filter so the API response can include it.
        add_filter('kronos/commerce/payment_intent', function () use ($orderData) {
            $manager = new PaymentManager();
            return $manager->createIntent(
                (int) round(($orderData['total'] ?? 0) * 100),
                (string) kronos_option('currency', 'usd'),
                ['order_number' => $orderData['order_number'] ?? '']
            );
        });
    }

    public function handleWebhook(array $params): void
    {
        $gateway = preg_replace('/[^a-z]/', '', strtolower($params['gateway'] ?? ''));
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];

        $manager = new PaymentManager();

        if ($manager->getGatewayName() !== $gateway) {
            kronos_json(['success' => false, 'message' => 'Gateway mismatch.'], 400);
            return;
        }

        $result = $manager->confirmPayment($payload);

        if ($result['success']) {
            // Update order status if we can match the intent ID
            try {
                $app = KronosApp::getInstance();
                $app->db()->query(
                    "UPDATE kronos_orders SET status = 'processing', payment_intent = ? WHERE payment_intent = ?",
                    [$result['payment_intent_id'] ?? '', $result['payment_intent_id'] ?? '']
                );
            } catch (\Throwable) {
                // Non-fatal — log only
            }
        }

        do_action('kronos/commerce/payment_confirmed', $result, $payload, $gateway);

        kronos_json(['received' => true]);
    }
}
