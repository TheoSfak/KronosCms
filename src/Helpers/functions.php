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

function remove_action(string $hook, callable $callback, int $priority = 10): void
{
    KronosApp::getInstance()->hooks()->removeAction($hook, $callback, $priority);
}

function do_action(string $hook, mixed ...$args): void
{
    KronosApp::getInstance()->hooks()->doAction($hook, ...$args);
}

function add_filter(string $hook, callable $callback, int $priority = 10): void
{
    KronosApp::getInstance()->hooks()->addFilter($hook, $callback, $priority);
}

function remove_filter(string $hook, callable $callback, int $priority = 10): void
{
    KronosApp::getInstance()->hooks()->removeFilter($hook, $callback, $priority);
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
    // If a root-relative path is given, prepend the app base URL so it works
    // correctly in subdirectory installs (e.g. /KronosCMS/public/dashboard/login)
    if (str_starts_with($url, '/')) {
        $url = kronos_url($url);
    }
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

function kronos_public_url_for_post(array $post): string
{
    $slug = (string) ($post['slug'] ?? '');
    $type = (string) ($post['post_type'] ?? 'post');
    return kronos_url(($type === 'page' ? '/page/' : '/post/') . $slug);
}

// ---------------------------------------------------------------------------
// Navigation menus
// ---------------------------------------------------------------------------

function kronos_ensure_menu_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $db = KronosApp::getInstance()->db();
    $db->runSchema([
        "CREATE TABLE IF NOT EXISTS `kronos_menus` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(191) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_menu_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `kronos_menu_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `menu_id` INT UNSIGNED NOT NULL,
            `parent_id` INT UNSIGNED NULL,
            `title` VARCHAR(191) NOT NULL,
            `url` VARCHAR(500) NOT NULL DEFAULT '',
            `item_type` VARCHAR(40) NOT NULL DEFAULT 'custom',
            `object_type` VARCHAR(60) NOT NULL DEFAULT '',
            `object_id` INT UNSIGNED NULL,
            `target` VARCHAR(20) NOT NULL DEFAULT '_self',
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_menu_sort` (`menu_id`, `sort_order`),
            KEY `idx_menu_object` (`object_type`, `object_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ]);

    $done = true;
}

function kronos_ensure_taxonomy_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    KronosApp::getInstance()->db()->runSchema([
        "CREATE TABLE IF NOT EXISTS `kronos_terms` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(191) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `taxonomy` VARCHAR(60) NOT NULL DEFAULT 'category',
            `parent_id` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_term_slug_taxonomy` (`slug`, `taxonomy`),
            KEY `idx_taxonomy` (`taxonomy`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `kronos_term_relationships` (
            `post_id` INT UNSIGNED NOT NULL,
            `term_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`post_id`, `term_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ]);

    $done = true;
}

function kronos_ensure_media_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    KronosApp::getInstance()->db()->runSchema([
        "CREATE TABLE IF NOT EXISTS `kronos_media` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `file_name` VARCHAR(255) NOT NULL,
            `file_path` VARCHAR(500) NOT NULL,
            `file_url` VARCHAR(500) NOT NULL,
            `mime_type` VARCHAR(120) NOT NULL DEFAULT '',
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `width` INT UNSIGNED NULL,
            `height` INT UNSIGNED NULL,
            `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
            `caption` TEXT NULL,
            `uploaded_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_media_file_url` (`file_url`),
            KEY `idx_media_mime` (`mime_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ]);

    $done = true;
}

function kronos_ensure_editor_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    KronosApp::getInstance()->db()->runSchema([
        "ALTER TABLE `kronos_posts` MODIFY `status` ENUM('draft','published','scheduled','private','archived') NOT NULL DEFAULT 'draft'",
        "CREATE TABLE IF NOT EXISTS `kronos_post_revisions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `title` VARCHAR(500) NOT NULL DEFAULT '',
            `content` LONGTEXT NOT NULL,
            `meta` JSON NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_revision_post` (`post_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ]);

    $done = true;
}

/**
 * @return array<int, array<string, mixed>>
 */
function kronos_get_menu_items(string $location): array
{
    kronos_ensure_menu_tables();
    $menuId = (int) kronos_option('menu_' . $location . '_id', 0);
    if ($menuId <= 0) {
        return [];
    }

    return KronosApp::getInstance()->db()->getResults(
        'SELECT * FROM kronos_menu_items WHERE menu_id = ? ORDER BY sort_order ASC, id ASC',
        [$menuId]
    );
}

function kronos_render_menu(string $location, array $fallback = []): string
{
    $items = kronos_get_menu_items($location);
    $current = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    $base = rtrim(parse_url(kronos_option('app_url', '/'), PHP_URL_PATH) ?? '', '/');
    if ($base !== '' && str_starts_with($current, $base)) {
        $current = substr($current, strlen($base)) ?: '/';
    }

    if (!$items) {
        $html = '';
        foreach ($fallback as $href => $label) {
            $active = $current === $href ? ' class="active"' : '';
            $html .= '<div class="menu-item"><a href="' . kronos_e(kronos_url($href)) . '"' . $active . '>' . kronos_e($label) . '</a></div>';
        }
        return $html;
    }

    $children = [];
    foreach ($items as $item) {
        $parentId = (int) ($item['parent_id'] ?? 0);
        $children[$parentId][] = $item;
    }

    $renderBranch = function(int $parentId, int $depth = 0) use (&$renderBranch, $children, $current, $base): string {
        if (empty($children[$parentId])) {
            return '';
        }
        $html = '';
        foreach ($children[$parentId] as $item) {
            $itemId = (int) $item['id'];
            $hasChildren = !empty($children[$itemId]);
        $url = (string) ($item['url'] ?? '#');
        $href = str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '#')
            ? $url
            : kronos_url($url);
        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
            $classes = ['menu-item'];
            if ($hasChildren) $classes[] = 'has-children';
            if ($path === $current) $classes[] = 'active';
            $classAttr = ' class="' . implode(' ', $classes) . '"';
        $target = (($item['target'] ?? '_self') === '_blank') ? ' target="_blank" rel="noopener"' : '';
            $html .= '<div' . $classAttr . '><a href="' . kronos_e($href) . '"' . $target . '>' . kronos_e((string) $item['title']) . '</a>';
            if ($hasChildren) {
                $html .= '<div class="sub-menu depth-' . ($depth + 1) . '">' . $renderBranch($itemId, $depth + 1) . '</div>';
            }
            $html .= '</div>';
        }
        return $html;
    };

    return $renderBranch(0);
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
    $aliases = [
        'app_manager' => 'administrator',
        'app_editor' => 'editor',
        'app_user' => 'subscriber',
    ];
    $hierarchy = [
        'subscriber' => 0,
        'contributor' => 1,
        'author' => 2,
        'editor' => 3,
        'administrator' => 4,
        'app_user' => 0,
        'app_editor' => 3,
        'app_manager' => 4,
    ];
    $userRole = (string) ($user['role'] ?? '');
    $requiredRole = $aliases[$role] ?? $role;
    $userLevel = $hierarchy[$aliases[$userRole] ?? $userRole] ?? -1;
    $required  = $hierarchy[$requiredRole] ?? 999;
    return $userLevel >= $required;
}

function kronos_role_label(string $role): string
{
    return match ($role) {
        'app_manager', 'administrator' => 'Administrator',
        'app_editor', 'editor' => 'Editor',
        'author' => 'Author',
        'contributor' => 'Contributor',
        'app_user', 'subscriber' => 'Subscriber',
        default => ucfirst(str_replace(['app_', '_'], ['', ' '], $role)),
    };
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
    $cookie = $_COOKIE['kronos_csrf'] ?? '';

    // Accept from either the X-Kronos-CSRF header (AJAX/fetch) or the
    // _kronos_csrf form field (standard HTML form POST).
    $token = $_SERVER['HTTP_X_KRONOS_CSRF']
          ?? $_POST['_kronos_csrf']
          ?? '';

    if ($token === '' || $cookie === '' || !hash_equals($cookie, $token)) {
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
