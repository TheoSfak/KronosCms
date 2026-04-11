<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosDB;
use Kronos\Core\KronosApp;

/**
 * AuthEndpoint — Handles login, refresh, and logout.
 * Routes: POST /api/kronos/v1/auth/{action}
 */
class AuthEndpoint extends ApiEndpoint
{
    private KronosAPIRouter $api;
    private KronosDB $db;

    public function __construct(KronosAPIRouter $api)
    {
        $this->api = $api;
        $this->db  = KronosApp::getInstance()->db();
    }

    public function handle(array $params): void
    {
        $action = basename($_SERVER['REQUEST_URI'] ?? '');
        // Strip query string
        $action = strtok($action, '?') ?: 'login';

        match ($action) {
            'login'   => $this->login(),
            'refresh' => $this->refresh(),
            'logout'  => $this->logout(),
            default   => kronos_abort(404, 'Unknown auth action'),
        };
    }

    // ------------------------------------------------------------------
    // Login
    // ------------------------------------------------------------------

    private function login(): void
    {
        $body = $this->getJsonBody();

        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            kronos_abort(422, 'Username and password are required.');
        }

        // Fetch user — parameterised, safe from SQL injection
        $user = $this->db->getRow(
            'SELECT id, username, email, password_hash, role, display_name FROM kronos_users
             WHERE (username = ? OR email = ?) LIMIT 1',
            [$username, $username]
        );

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            // Use constant-time comparison to prevent timing attacks
            kronos_abort(401, 'Invalid credentials.');
        }

        // Update last_login_at
        $this->db->update('kronos_users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        $userData = [
            'id'           => $user['id'],
            'username'     => $user['username'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'display_name' => $user['display_name'],
        ];

        $token = $this->api->getMiddleware()->issueToken($userData);

        kronos_json([
            'success' => true,
            'token'   => $token,
            'user'    => $userData,
        ]);
    }

    // ------------------------------------------------------------------
    // Refresh
    // ------------------------------------------------------------------

    private function refresh(): void
    {
        $newToken = $this->api->getMiddleware()->refreshToken();

        if ($newToken === null) {
            kronos_abort(401, 'Invalid or expired token. Please log in again.');
        }

        kronos_json(['success' => true, 'token' => $newToken]);
    }

    // ------------------------------------------------------------------
    // Logout
    // ------------------------------------------------------------------

    private function logout(): void
    {
        $this->api->getMiddleware()->clearToken();
        kronos_json(['success' => true, 'message' => 'Logged out.']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

}
