<?php
declare(strict_types=1);

namespace Kronos\Commerce;

/**
 * Shared contract for all payment gateways.
 */
interface PaymentGatewayInterface
{
    /** Human-readable gateway name. */
    public function getName(): string;

    /**
     * Prepare a payment intent/order with the gateway.
     *
     * @param  int    $amountCents Amount in smallest currency unit
     * @param  string $currency    ISO 4217 currency code
     * @param  array<string, mixed> $meta
     * @return array{success: bool, client_secret?: string, order_id?: string, message?: string}
     */
    public function createIntent(int $amountCents, string $currency, array $meta = []): array;

    /**
     * Confirm/verify a webhook or return-URL callback.
     *
     * @param  array<string, mixed> $payload
     * @return array{success: bool, payment_intent_id?: string, message?: string}
     */
    public function confirmPayment(array $payload): array;
}
