<?php
declare(strict_types=1);

namespace Kronos\Commerce;

/**
 * PaymentManager — dispatches payment processing to the configured gateway.
 *
 * Gateway selection:
 *  - If STRIPE_SECRET_KEY is set in env → StripeGateway
 *  - If PAYPAL_CLIENT_ID is set in env  → PayPalGateway
 *  - Otherwise                          → NullGateway (test / COD mode)
 */
class PaymentManager
{
    private PaymentGatewayInterface $gateway;

    public function __construct()
    {
        $this->gateway = $this->resolveGateway();
    }

    /**
     * Create a payment intent / order preparation for a given amount.
     *
     * @param  int    $amountCents Amount in smallest currency unit (e.g. cents for USD)
     * @param  string $currency    ISO 4217 code (e.g. "usd", "eur")
     * @param  array<string, mixed> $meta  Driver-specific metadata
     * @return array{success: bool, client_secret?: string, order_id?: string, message?: string}
     */
    public function createIntent(int $amountCents, string $currency = 'usd', array $meta = []): array
    {
        return $this->gateway->createIntent($amountCents, $currency, $meta);
    }

    /**
     * Verify a completed payment using gateway-specific confirmation data.
     *
     * @param  array<string, mixed> $payload  Raw POST data / webhook payload
     * @return array{success: bool, payment_intent_id?: string, message?: string}
     */
    public function confirmPayment(array $payload): array
    {
        return $this->gateway->confirmPayment($payload);
    }

    /**
     * Return the name of the active gateway for display purposes.
     */
    public function getGatewayName(): string
    {
        return $this->gateway->getName();
    }

    // ── Private ────────────────────────────────────────────────────

    private function resolveGateway(): PaymentGatewayInterface
    {
        if ((string) getenv('STRIPE_SECRET_KEY') !== '') {
            return new StripeGateway();
        }

        if ((string) getenv('PAYPAL_CLIENT_ID') !== '') {
            return new PayPalGateway();
        }

        return new NullGateway();
    }
}
