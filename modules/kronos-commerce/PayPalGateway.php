<?php
declare(strict_types=1);

namespace Kronos\Commerce;

/**
 * PayPalGateway — integrates with PayPal Orders API v2 via raw cURL.
 *
 * Avoids pulling in the bloated PayPal SDK; uses the REST API directly.
 *
 * Environment variables:
 *  PAYPAL_CLIENT_ID     — required
 *  PAYPAL_CLIENT_SECRET — required
 *  PAYPAL_MODE          — "sandbox" (default) or "live"
 */
class PayPalGateway implements PaymentGatewayInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId     = (string) getenv('PAYPAL_CLIENT_ID');
        $this->clientSecret = (string) getenv('PAYPAL_CLIENT_SECRET');
        $mode               = strtolower((string) getenv('PAYPAL_MODE') ?: 'sandbox');
        $this->baseUrl      = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function createIntent(int $amountCents, string $currency = 'usd', array $meta = []): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'PayPal authentication failed.'];
        }

        $amount = number_format($amountCents / 100, 2, '.', '');

        $body = [
            'intent'        => 'CAPTURE',
            'purchase_units' => [[
                'amount'      => [
                    'currency_code' => strtoupper($currency),
                    'value'         => $amount,
                ],
                'custom_id'   => $meta['order_number'] ?? '',
            ]],
        ];

        [$status, $response] = $this->request('POST', '/v2/checkout/orders', $body, $token);

        if ($status === 201 && isset($response['id'])) {
            // Find the approval link
            $approveUrl = null;
            foreach ($response['links'] ?? [] as $link) {
                if ($link['rel'] === 'approve') {
                    $approveUrl = $link['href'];
                    break;
                }
            }

            return [
                'success'      => true,
                'order_id'     => $response['id'],
                'approve_url'  => $approveUrl,
            ];
        }

        $message = $response['message'] ?? 'PayPal order creation failed.';
        return ['success' => false, 'message' => $message];
    }

    public function confirmPayment(array $payload): array
    {
        $orderId = $payload['orderID'] ?? $payload['token'] ?? '';

        if (!$orderId) {
            return ['success' => false, 'message' => 'Missing PayPal order ID.'];
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'PayPal authentication failed.'];
        }

        [$status, $response] = $this->request('POST', "/v2/checkout/orders/{$orderId}/capture", [], $token);

        if ($status === 201 && ($response['status'] ?? '') === 'COMPLETED') {
            return [
                'success'           => true,
                'payment_intent_id' => $orderId,
                'capture_id'        => $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? '',
            ];
        }

        $message = $response['message'] ?? 'PayPal capture failed.';
        return ['success' => false, 'message' => $message];
    }

    // ── Private ────────────────────────────────────────────────────

    private function getAccessToken(): ?string
    {
        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_USERPWD        => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$raw || $code !== 200) return null;

        $data = json_decode((string) $raw, true);
        return $data['access_token'] ?? null;
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function request(string $method, string $path, array $body, string $token): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $body ? json_encode($body) : '{}',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) ($raw ?: '{}'), true);
        return [$code, is_array($data) ? $data : []];
    }
}
