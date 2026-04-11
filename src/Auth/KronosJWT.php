<?php
declare(strict_types=1);

namespace Kronos\Auth;

/**
 * KronosJWT — Pure PHP JWT engine using HMAC-SHA256.
 * No external library dependency.
 * Tokens are structured as: base64url(header).base64url(payload).base64url(signature)
 */
class KronosJWT
{
    private string $secret;
    private int $expiry; // seconds

    public function __construct(string $secret, int $expiry = 86400)
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT secret must be at least 32 characters.');
        }
        $this->secret = $secret;
        $this->expiry  = $expiry;
    }

    /**
     * Generate a signed JWT token.
     *
     * @param array<string, mixed> $userData Data embedded in the payload (user id, role, etc.)
     * @return string Signed JWT string
     */
    public function encode(array $userData): string
    {
        $now = time();

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'iss'  => 'kronos-cms',
            'iat'  => $now,
            'exp'  => $now + $this->expiry,
            'data' => $userData,
        ], JSON_THROW_ON_ERROR));

        $signature = $this->sign($header . '.' . $payload);

        return $header . '.' . $payload . '.' . $signature;
    }

    /**
     * Verify and decode a JWT token.
     *
     * @return array<string, mixed> Decoded payload (including 'data' key with user info)
     * @throws \RuntimeException on invalid or expired token
     */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid JWT format.');
        }

        [$header, $payload, $signature] = $parts;

        // Validate header algorithm BEFORE checking signature — prevents alg=none attacks
        try {
            $headerData = json_decode($this->base64UrlDecode($header), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('JWT header is not valid JSON.');
        }
        if (!is_array($headerData) || ($headerData['alg'] ?? '') !== 'HS256') {
            throw new \RuntimeException('JWT algorithm mismatch — only HS256 is accepted.');
        }

        // Verify signature
        $expectedSig = $this->sign($header . '.' . $payload);
        if (!hash_equals($expectedSig, $signature)) {
            throw new \RuntimeException('JWT signature verification failed.');
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \RuntimeException('JWT payload is not a valid JSON object.');
        }

        // Check expiry
        if (!isset($decoded['exp']) || $decoded['exp'] < time()) {
            throw new \RuntimeException('JWT has expired.');
        }

        // Verify issuer
        if (($decoded['iss'] ?? '') !== 'kronos-cms') {
            throw new \RuntimeException('JWT issuer mismatch.');
        }

        return $decoded;
    }

    /**
     * Issue a refreshed token from an existing valid token.
     * Extends expiry from now without requiring re-login.
     *
     * @throws \RuntimeException if the existing token is invalid or expired
     */
    public function refresh(string $token): string
    {
        $payload = $this->decode($token);
        return $this->encode($payload['data'] ?? []);
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $data, $this->secret, true)
        );
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
