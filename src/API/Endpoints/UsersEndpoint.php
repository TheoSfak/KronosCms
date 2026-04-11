<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosDB;

/**
 * UsersEndpoint — CRUD for users (manager only).
 * Routes:
 *   GET    /api/kronos/v1/users            → list all users
 *   POST   /api/kronos/v1/users            → create user
 *   PUT    /api/kronos/v1/users/{id}       → update role / display_name
 *   DELETE /api/kronos/v1/users/{id}       → delete user (cannot delete self)
 */
class UsersEndpoint extends ApiEndpoint
{
    private KronosAPIRouter $api;
    private KronosDB $db;

    private const ALLOWED_ROLES = ['app_manager', 'app_editor', 'app_viewer'];

    public function __construct(KronosAPIRouter $api)
    {
        $this->api = $api;
        $this->db  = KronosApp::getInstance()->db();
    }

    public function handle(array $params): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        match ($method) {
            'GET'    => $this->listUsers(),
            'POST'   => $this->createUser(),
            'PUT'    => $this->updateUser((int) ($params['id'] ?? 0)),
            'DELETE' => $this->deleteUser((int) ($params['id'] ?? 0)),
            default  => kronos_abort(405, 'Method not allowed.'),
        };
    }

    // ------------------------------------------------------------------
    // GET /users
    // ------------------------------------------------------------------

    private function listUsers(): void
    {
        $users = $this->db->getResults(
            'SELECT id, username, email, role, display_name, last_login_at, created_at
             FROM kronos_users ORDER BY created_at DESC'
        );
        kronos_json(['data' => $users]);
    }

    // ------------------------------------------------------------------
    // POST /users
    // ------------------------------------------------------------------

    private function createUser(): void
    {
        $body = $this->getJsonBody();

        $username     = trim((string) ($body['username'] ?? ''));
        $email        = trim((string) ($body['email'] ?? ''));
        $password     = (string) ($body['password'] ?? '');
        $role         = (string) ($body['role'] ?? 'app_editor');
        $display_name = trim((string) ($body['display_name'] ?? $username));

        // Validation
        if ($username === '') {
            kronos_abort(422, 'Username is required.');
        }
        if (!preg_match('/^[a-zA-Z0-9_.\-]{3,40}$/', $username)) {
            kronos_abort(422, 'Username may only contain letters, numbers, underscores, hyphens, or dots (3–40 chars).');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            kronos_abort(422, 'Invalid email address.');
        }
        if (strlen($password) < 8) {
            kronos_abort(422, 'Password must be at least 8 characters.');
        }
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            kronos_abort(422, 'Invalid role. Allowed: ' . implode(', ', self::ALLOWED_ROLES));
        }

        // Uniqueness check
        $exists = $this->db->getRow(
            'SELECT id FROM kronos_users WHERE username = ? OR email = ? LIMIT 1',
            [$username, $email !== '' ? $email : null]
        );
        if ($exists !== null) {
            kronos_abort(409, 'Username or email already exists.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $now  = date('Y-m-d H:i:s');

        $newId = $this->db->insert('kronos_users', [
            'username'      => $username,
            'email'         => $email !== '' ? $email : null,
            'password_hash' => $hash,
            'role'          => $role,
            'display_name'  => $display_name !== '' ? $display_name : $username,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        http_response_code(201);
        kronos_json(['success' => true, 'id' => $newId]);
    }

    // ------------------------------------------------------------------
    // PUT /users/{id}
    // ------------------------------------------------------------------

    private function updateUser(int $id): void
    {
        if ($id <= 0) {
            kronos_abort(400, 'Invalid user ID.');
        }

        $body = $this->getJsonBody();
        $data = [];

        if (isset($body['role'])) {
            if (!in_array($body['role'], self::ALLOWED_ROLES, true)) {
                kronos_abort(422, 'Invalid role.');
            }
            $data['role'] = $body['role'];
        }

        if (isset($body['display_name'])) {
            $data['display_name'] = trim((string) $body['display_name']);
        }

        if (isset($body['email'])) {
            $email = trim((string) $body['email']);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                kronos_abort(422, 'Invalid email address.');
            }
            $data['email'] = $email !== '' ? $email : null;
        }

        if (empty($data)) {
            kronos_abort(422, 'Nothing to update. Provide role, display_name, or email.');
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        $updated = $this->db->update('kronos_users', $data, ['id' => $id]);
        if (!$updated) {
            kronos_abort(404, 'User not found.');
        }

        kronos_json(['success' => true]);
    }

    // ------------------------------------------------------------------
    // DELETE /users/{id}
    // ------------------------------------------------------------------

    private function deleteUser(int $id): void
    {
        if ($id <= 0) {
            kronos_abort(400, 'Invalid user ID.');
        }

        // Disallow deleting yourself
        $currentUser = kronos_current_user();
        if ($currentUser && (int) $currentUser['id'] === $id) {
            kronos_abort(403, 'You cannot delete your own account.');
        }

        $deleted = $this->db->delete('kronos_users', ['id' => $id]);
        if (!$deleted) {
            kronos_abort(404, 'User not found.');
        }

        kronos_json(['success' => true]);
    }

    // ------------------------------------------------------------------
}
