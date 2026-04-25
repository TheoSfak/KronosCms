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
    $assetPath = ltrim($path, '/');
    $url = kronos_url('assets/' . $assetPath);
    try {
        $file = rtrim(KronosApp::getInstance()->rootDir(), '/\\') . '/public/assets/' . $assetPath;
        if (is_file($file)) {
            $url .= '?v=' . filemtime($file);
        }
    } catch (Throwable) {
        // Asset URLs should still resolve during early install/bootstrap.
    }
    return $url;
}

function kronos_public_url_for_post(array $post): string
{
    $slug = (string) ($post['slug'] ?? '');
    $type = (string) ($post['post_type'] ?? 'post');
    return kronos_url(($type === 'page' ? '/page/' : '/post/') . $slug);
}

/**
 * Send mail through the active mail transport.
 *
 * Plugins can fully handle delivery by returning a boolean from
 * `kronos/mail/send`; otherwise Kronos falls back to PHP's mail().
 *
 * @param array<int, string> $headers
 */
function kronos_mail(string $to, string $subject, string $message, array $headers = []): bool
{
    $handled = apply_filters('kronos/mail/send', null, $to, $subject, $message, $headers);
    if (is_bool($handled)) {
        return $handled;
    }

    $headerText = implode("\r\n", $headers);
    return $headerText !== ''
        ? @mail($to, $subject, $message, $headerText)
        : @mail($to, $subject, $message);
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
            `slug` VARCHAR(191) NULL,
            `content` LONGTEXT NOT NULL,
            `status` VARCHAR(40) NULL,
            `published_at` DATETIME NULL,
            `layout_id` INT UNSIGNED NULL,
            `meta` JSON NULL,
            `terms_json` LONGTEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_revision_post` (`post_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE `kronos_post_revisions` ADD COLUMN `slug` VARCHAR(191) NULL AFTER `title`",
        "ALTER TABLE `kronos_post_revisions` ADD COLUMN `status` VARCHAR(40) NULL AFTER `content`",
        "ALTER TABLE `kronos_post_revisions` ADD COLUMN `published_at` DATETIME NULL AFTER `status`",
        "ALTER TABLE `kronos_post_revisions` ADD COLUMN `layout_id` INT UNSIGNED NULL AFTER `published_at`",
        "ALTER TABLE `kronos_post_revisions` ADD COLUMN `terms_json` LONGTEXT NULL AFTER `meta`",
    ]);

    $done = true;
}

function kronos_create_post_revision(int $postId, ?int $userId = null): void
{
    if ($postId <= 0) {
        return;
    }

    kronos_ensure_editor_tables();
    kronos_ensure_taxonomy_tables();

    $db = KronosApp::getInstance()->db();
    $post = $db->getRow('SELECT * FROM kronos_posts WHERE id = ? LIMIT 1', [$postId]);
    if (!$post) {
        return;
    }

    $terms = $db->getResults(
        'SELECT t.id, t.slug, t.taxonomy
         FROM kronos_terms t
         INNER JOIN kronos_term_relationships tr ON tr.term_id = t.id
         WHERE tr.post_id = ?
         ORDER BY t.taxonomy ASC, t.slug ASC',
        [$postId]
    );

    $db->insert('kronos_post_revisions', [
        'post_id' => $postId,
        'user_id' => $userId ?: null,
        'title' => (string) ($post['title'] ?? ''),
        'slug' => (string) ($post['slug'] ?? ''),
        'content' => (string) ($post['content'] ?? ''),
        'status' => (string) ($post['status'] ?? 'draft'),
        'published_at' => $post['published_at'] ?: null,
        'layout_id' => !empty($post['layout_id']) ? (int) $post['layout_id'] : null,
        'meta' => $post['meta'] ?: null,
        'terms_json' => json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function kronos_promote_due_scheduled_posts(): int
{
    $app = KronosApp::getInstance();
    $result = (new \Kronos\Content\ScheduledPublisher($app->db(), $app->config()))->run();
    return (int) $result['published'];
}

function kronos_hex_to_rgb_csv(string $hex, string $fallback = '79,70,229'): string
{
    $hex = ltrim(trim($hex), '#');
    if (!preg_match('/^[a-f0-9]{6}$/i', $hex)) {
        return $fallback;
    }

    return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
}

/**
 * Create the default site pages as CMS records so they are visible in Pages and editable in Builder.
 */
function kronos_ensure_default_site_pages(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $app = KronosApp::getInstance();
    $db = $app->db();
    $themeLayoutDir = rtrim($app->rootDir(), '/\\') . '/themes/kronos-default/layouts';

    $loadLayout = function(string $file, array $fallback) use ($themeLayoutDir): array {
        $path = $themeLayoutDir . '/' . $file;
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded['blocks'] ?? $decoded;
            }
        }
        return $fallback;
    };

    $makeHero = function(string $title, string $subtitle, string $button = '', string $url = '#'): array {
        return [[
            'id' => 'hero-' . kronos_sanitize_slug($title),
            'type' => 'hero-block',
            'attrs' => [
                'pretitle' => 'KronosCMS',
                'title' => $title,
                'subtitle' => $subtitle,
                'btnLabel' => $button,
                'btnUrl' => $url,
                'imageLayout' => 'split-right',
                'bg' => 'linear-gradient(135deg,#111827,#1d4ed8)',
                'pad' => 72,
                'minHeight' => 420,
            ],
            'children' => [],
        ]];
    };

    $pages = [
        'home' => [
            'title' => 'Home',
            'layout_name' => 'Homepage',
            'layout' => $loadLayout('home.json', $makeHero('Welcome to KronosCMS', 'Build beautiful websites with a visual builder, CMS tools, and a clean admin experience.', 'Start editing', '/dashboard/pages')),
            'content' => '',
            'meta' => ['hide_title' => true, 'seeded_page' => true],
        ],
        'about' => [
            'title' => 'About',
            'layout_name' => 'About Page',
            'layout' => $loadLayout('about.json', $makeHero('About KronosCMS', 'A lightweight CMS and builder made for clean publishing workflows.', 'Contact us', '/contact')),
            'content' => '',
            'meta' => ['seeded_page' => true],
        ],
        'services' => [
            'title' => 'Services',
            'layout_name' => 'Services Page',
            'layout' => [
                ...$makeHero('Services', 'Present your core services with flexible builder sections and reusable content blocks.', 'Get in touch', '/contact'),
                [
                    'id' => 'services-grid',
                    'type' => 'container',
                    'attrs' => ['style' => 'max-width:1100px;margin:48px auto;padding:0 24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:22px'],
                    'children' => [
                        ['id' => 'service-a', 'type' => 'text', 'attrs' => ['text' => 'Website design, landing pages, and content structures built around your brand.'], 'children' => []],
                        ['id' => 'service-b', 'type' => 'text', 'attrs' => ['text' => 'CMS setup, navigation, media organization, and publishing workflows.'], 'children' => []],
                        ['id' => 'service-c', 'type' => 'text', 'attrs' => ['text' => 'Builder-powered sections that your team can edit without touching code.'], 'children' => []],
                    ],
                ],
            ],
            'content' => '',
            'meta' => ['seeded_page' => true],
        ],
        'contact' => [
            'title' => 'Contact',
            'layout_name' => 'Contact Page',
            'layout' => $makeHero('Contact', 'Use this page as the editable introduction to your contact form and business details.', 'Send a message', '/contact'),
            'content' => "Email: hello@example.com\nPhone: +1 555 0100",
            'meta' => ['seeded_page' => true],
        ],
    ];

    foreach ($pages as $slug => $page) {
        $existing = $db->getRow("SELECT id, layout_id FROM kronos_posts WHERE slug = ? AND post_type = 'page' LIMIT 1", [$slug]);
        $layoutId = (int) ($existing['layout_id'] ?? 0);
        if ($layoutId <= 0) {
            $layoutId = (int) $db->insert('kronos_builder_layouts', [
                'layout_name' => (string) $page['layout_name'],
                'layout_type' => 'page',
                'json_data' => json_encode($page['layout'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($existing) {
            if (empty($existing['layout_id']) && $layoutId > 0) {
                $db->update('kronos_posts', [
                    'layout_id' => $layoutId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $existing['id']]);
            }
            continue;
        }

        $db->insert('kronos_posts', [
            'title' => (string) $page['title'],
            'slug' => $slug,
            'content' => (string) $page['content'],
            'post_type' => 'page',
            'status' => 'published',
            'layout_id' => $layoutId ?: null,
            'meta' => json_encode($page['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'published_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    $done = true;
}

function kronos_get_public_page_by_slug(string $slug, bool $preview = false): ?array
{
    $slug = kronos_sanitize_slug($slug);
    if ($slug === '') {
        return null;
    }

    $statusClause = $preview
        ? "p.status IN ('published','draft','scheduled','private','archived')"
        : "(p.status = 'published' OR (p.status = 'scheduled' AND p.published_at IS NOT NULL AND p.published_at <= NOW()))";

    return KronosApp::getInstance()->db()->getRow(
        "SELECT p.*, l.json_data AS layout_json
         FROM kronos_posts p
         LEFT JOIN kronos_builder_layouts l ON l.id = p.layout_id
         WHERE p.slug = ? AND p.post_type = 'page' AND {$statusClause}
         LIMIT 1",
        [$slug]
    );
}

/**
 * Return the public URL path for a post/page record.
 *
 * @param array<string, mixed> $post
 */
function kronos_public_content_path(array $post): string
{
    $slug = kronos_sanitize_slug((string) ($post['slug'] ?? ''));
    if (($post['post_type'] ?? 'post') === 'page') {
        return $slug === 'home' ? '/' : '/page/' . $slug;
    }
    return '/post/' . $slug;
}

function kronos_render_cms_page_by_slug(string $slug, bool $asHome = false, ?bool $preview = null): bool
{
    kronos_ensure_default_site_pages();
    $post = kronos_get_public_page_by_slug($slug, $preview ?? isset($_GET['preview']));
    if (!$post) {
        return false;
    }

    $engine = new \Kronos\Builder\RenderEngine();
    $html = $engine->render((string) ($post['layout_json'] ?? '[]'));

    if ($asHome) {
        $app = KronosApp::getInstance();
        $theme = $app->themeManager()->getActiveTheme();
        $themeSlug = $app->themeManager()->getActiveSlug();
        $baseRel = $theme['templates']['base'] ?? 'templates/base.php';
        $baseTpl = $app->rootDir() . '/themes/' . $themeSlug . '/' . $baseRel;
        if (!is_file($baseTpl)) {
            return false;
        }
        $content = $html;
        $title = kronos_option('app_name', 'KronosCMS');
        $bodyClass = 'home';
        require $baseTpl;
        return true;
    }

    $theme = apply_filters('kronos/theme/template', null, $post);
    if ($theme !== null && is_callable($theme)) {
        call_user_func($theme, $post, $html);
        return true;
    }

    return false;
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
    static $issuedToken = null;
    if ($issuedToken !== null) {
        return $issuedToken;
    }

    if (empty($_COOKIE['kronos_csrf'])) {
        $issuedToken = bin2hex(random_bytes(24));
        setcookie('kronos_csrf', $issuedToken, [
            'httponly' => false, // must be readable by JS for fetch headers
            'samesite' => 'Strict',
            'secure'   => isset($_SERVER['HTTPS']),
            'path'     => '/',
        ]);
        return $issuedToken;
    }
    $issuedToken = (string) $_COOKIE['kronos_csrf'];
    return $issuedToken;
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
