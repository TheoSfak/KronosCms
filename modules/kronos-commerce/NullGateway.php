<?php
declare(strict_types=1);

namespace Kronos\Commerce;

/**
 * NullGateway — no-op gateway used when no real payment credentials are set.
 * Useful for COD (Cash on Delivery) or local development without Stripe/PayPal.
 */
class NullGateway implements PaymentGatewayInterface
{
    public function getName(): string { return 'none'; }

    public function createIntent(int $amountCents, string $currency = 'usd', array $meta = []): array
    {
        return [
            'success'   => true,
            'order_id'  => 'COD-' . bin2hex(random_bytes(4)),
            'message'   => 'No payment gateway configured. Order recorded as COD.',
        ];
    }

    public function confirmPayment(array $payload): array
    {
        return [
            'success'           => true,
            'payment_intent_id' => $payload['order_id'] ?? 'no-payment',
        ];
    }
}
