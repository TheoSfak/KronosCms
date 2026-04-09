<?php
declare(strict_types=1);

namespace Kronos\Auth;

use Kronos\Core\KronosApp;

/**
 * KronosMiddleware — JWT authentication middleware.
 *
 * Reads the JWT from the `kronos_token` httpOnly cookie.
 * On success, injects the decoded user data into $_REQUEST['_kronos_user'].
 * On failure, sends a 401 JSON response.
 */
class KronosMiddleware
{
    private KronosJWT $jwt;

    public function __construct(KronosJWT $jwt)
    {
        $this->jwt = $jwt;
    }

    /**
     * Middleware callable for use with KronosRouter.
     * Usage: $router->get('/protected', $handler, [$middleware->handle(...)]);
     */
    public function handle(): callable
    {
        return function (array $params, callable $next): void {
            $token = $this->extractToken();

            if ($token === null) {
                $this->sendUnauthorized('Authentication required.');
                return;
            }

            try {
                $payload = $this->jwt->decode($token);
            } catch (\RuntimeException $e) {
                $this->sendUnauthorized($e->getMessage());
                return;
            }

            // Inject user into request scope (safe — not from user input)
            $_REQUEST['_kronos_user'] = $payload['data'] ?? [];

            $next();
        };
    }

    /**
     * Role-gate middleware: requires the authenticated user to have at least $role.
     * Must be used AFTER handle() in the middleware chain.
     */
    public function requireRole(string $role): callable
    {
        return function (array $params, callable $next) use ($role): void {
            if (!kronos_user_can($role)) {
                $this->sendForbidden('Insufficient permissions.');
                return;
            }
            $next();
        };
    }

    /**
     * Issue a JWT and set it as an httpOnly cookie, then return it in the response body too.
     *
     * @param array<string, mixed> $userData
     */
    public function issueToken(array $userData): string
    {
        $token = $this->jwt->encode($userData);

        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie('kronos_token', $token, [
            'expires'  => time() + (int) KronosApp::getInstance()->env('JWT_EXPIRY', 86400),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => $secure,
        ]);

        return $token;
    }

    /**
     * Refresh the token from the current cookie.
     */
    public function refreshToken(): ?string
    {
        $token = $this->extractToken();
        if ($token === null) {
            return null;
        }

        try {
            $newToken = $this->jwt->refresh($token);
            $this->issueTokenRaw($newToken);
            return $newToken;
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Clear the auth cookie (logout).
     */
    public function clearToken(): void
    {
        setcookie('kronos_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function extractToken(): ?string
    {
        // 1. httpOnly cookie (preferred)
        if (!empty($_COOKIE['kronos_token'])) {
            return $_COOKIE['kronos_token'];
        }

        // 2. Authorization: Bearer header (for API clients)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return null;
    }

    private function issueTokenRaw(string $token): void
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('kronos_token', $token, [
            'expires'  => time() + (int) KronosApp::getInstance()->env('JWT_EXPIRY', 86400),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => $secure,
        ]);
    }

    private function sendUnauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message, 'code' => 401]);
        exit;
    }

    private function sendForbidden(string $message): void
    {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message, 'code' => 403]);
        exit;
    }
}
