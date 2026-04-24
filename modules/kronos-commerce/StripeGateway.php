<?php
declare(strict_types=1);

namespace Kronos\Commerce;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;

/**
 * StripeGateway — wraps the official stripe/stripe-php SDK.
 *
 * Environment variables:
 *  STRIPE_SECRET_KEY     — required (sk_test_… or sk_live_…)
 *  STRIPE_WEBHOOK_SECRET — optional, used to verify webhook signatures
 */
class StripeGateway implements PaymentGatewayInterface
{
    private string $secretKey;
    private string $webhookSecret;

    public function __construct()
    {
        $this->secretKey     = (string) ($_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?: '');
        $this->webhookSecret = (string) ($_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET') ?: '');

        Stripe::setApiKey($this->secretKey);
        Stripe::setAppInfo('KronosCMS', \Kronos\Core\KronosVersion::VERSION, 'https://github.com/TheoSfak/KronosCms');
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function createIntent(int $amountCents, string $currency = 'usd', array $meta = []): array
    {
        try {
            $intent = PaymentIntent::create([
                'amount'               => $amountCents,
                'currency'             => strtolower($currency),
                'automatic_payment_methods' => ['enabled' => true],
                'metadata'             => array_filter([
                    'order_number' => $meta['order_number'] ?? null,
                    'customer_id'  => $meta['customer_id']  ?? null,
                ]),
            ]);

            return [
                'success'       => true,
                'client_secret' => $intent->client_secret,
                'intent_id'     => $intent->id,
            ];
        } catch (ApiErrorException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function confirmPayment(array $payload): array
    {
        // Verify webhook signature when the secret is configured
        if ($this->webhookSecret !== '') {
            $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            $rawBody   = file_get_contents('php://input') ?: '';

            try {
                \Stripe\Webhook::constructEvent($rawBody, $sigHeader, $this->webhookSecret);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                return ['success' => false, 'message' => 'Invalid webhook signature.'];
            }
        }

        $type = $payload['type'] ?? '';
        if ($type === 'payment_intent.succeeded') {
            $intentId = $payload['data']['object']['id'] ?? '';
            return [
                'success'           => true,
                'payment_intent_id' => $intentId,
            ];
        }

        return ['success' => false, 'message' => "Unhandled Stripe event: {$type}"];
    }
}
