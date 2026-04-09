<?php
declare(strict_types=1);

/**
 * KronosCMS Global Helper Functions
 * These are procedural shortcuts that delegate to the KronosApp service container.
 */

use Kronos\Core\KronosApp;

// ---------------------------------------------------------------------------
// Config / Options
// ---------------------------------------------------------------------------

function kronos_option(string $key, mixed $default = null): mixed
{
    return KronosApp::getInstance()->config()->get($key, $default);
}

function kronos_set_option(string $key, mixed $value): void
{
    KronosApp::getInstance()->config()->set($key, $value);
}

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------

function add_action(string $hook, callable $callback, int $priority = 10): void
{
    KronosApp::getInstance()->hooks()->addAction($hook, $callback, $priority);
}

function do_action(string $hook, mixed ...$args): void
{
    KronosApp::getInstance()->hooks()->doAction($hook, ...$args);
}

function add_filter(string $hook, callable $callback, int $priority = 10): void
{
    KronosApp::getInstance()->hooks()->addFilter($hook, $callback, $priority);
}

function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    return KronosApp::getInstance()->hooks()->applyFilters($hook, $value, ...$args);
}

// ---------------------------------------------------------------------------
// HTTP helpers
// ---------------------------------------------------------------------------

function kronos_redirect(string $url, int $status = 302): void
{
    http_response_code($status);
    header('Location: ' . $url);
    exit;
}

function kronos_json(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function kronos_abort(int $status, string $message = ''): void
{
    http_response_code($status);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => $message ?: http_response_code()]);
    exit;
}

// ---------------------------------------------------------------------------
// URL helpers
// ---------------------------------------------------------------------------

function kronos_url(string $path = ''): string
{
    $base = rtrim(kronos_option('app_url', $_ENV['APP_URL'] ?? ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function kronos_asset(string $path): string
{
    return kronos_url('assets/' . ltrim($path, '/'));
}

// ---------------------------------------------------------------------------
// Active mode
// ---------------------------------------------------------------------------

function kronos_mode(): string
{
    return kronos_option('kronos_active_mode', 'cms');
}

function kronos_is_ecommerce(): bool
{
    return kronos_mode() === 'ecommerce';
}

function kronos_is_cms(): bool
{
    return kronos_mode() === 'cms';
}

// ---------------------------------------------------------------------------
// Current authenticated user
// ---------------------------------------------------------------------------

function kronos_current_user(): ?array
{
    return $_REQUEST['_kronos_user'] ?? null;
}

function kronos_user_can(string $role): bool
{
    $user = kronos_current_user();
    if ($user === null) {
        return false;
    }
    $hierarchy = ['app_user' => 0, 'app_editor' => 1, 'app_manager' => 2];
    $userLevel = $hierarchy[$user['role']] ?? -1;
    $required  = $hierarchy[$role] ?? 999;
    return $userLevel >= $required;
}

// ---------------------------------------------------------------------------
// CSRF token (simple double-submit cookie pattern for form submissions)
// ---------------------------------------------------------------------------

function kronos_csrf_token(): string
{
    if (empty($_COOKIE['kronos_csrf'])) {
        $token = bin2hex(random_bytes(24));
        setcookie('kronos_csrf', $token, [
            'httponly' => false, // must be readable by JS for fetch headers
            'samesite' => 'Strict',
            'secure'   => isset($_SERVER['HTTPS']),
            'path'     => '/',
        ]);
        return $token;
    }
    return $_COOKIE['kronos_csrf'];
}

function kronos_verify_csrf(): void
{
    $header = $_SERVER['HTTP_X_KRONOS_CSRF'] ?? '';
    $cookie = $_COOKIE['kronos_csrf'] ?? '';
    if (empty($header) || empty($cookie) || !hash_equals($cookie, $header)) {
        kronos_abort(403, 'Invalid CSRF token');
    }
}

// ---------------------------------------------------------------------------
// Sanitization helpers
// ---------------------------------------------------------------------------

function kronos_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function kronos_sanitize_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? '';
    return trim($value, '-');
}
