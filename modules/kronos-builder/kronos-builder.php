<?php
declare(strict_types=1);

use Kronos\Core\KronosModule;
use Kronos\Core\KronosApp;

/**
 * KronosBuilderModule — page builder engine.
 *
 * Responsibilities:
 *  - Registers the RenderEngine with the front-end router
 *  - Exposes the KronosAPI.Widgets.register() extension point via a hook
 *  - Provides apply_filters('kronos/builder/render_block', ...) for output
 */
class KronosBuilderModule extends KronosModule
{
    public function getName(): string
    {
        return 'kronos-builder';
    }

    public function boot(): void
    {
        $app    = KronosApp::getInstance();
        $router = $app->router();
        $pageBase = kronos_permalink_base('page');
        $postBase = kronos_permalink_base('post');

        // Rendered content output (public-facing, no auth)
        $router->get('/' . $pageBase . '/{slug}', [$this, 'renderPage']);
        $router->get('/' . $postBase . '/{slug}', [$this, 'renderPost']);
        $router->post('/' . $postBase . '/{slug}/comment', [$this, 'submitComment']);
        if ($pageBase !== 'page') {
            $router->get('/page/{slug}', [$this, 'renderPage']);
        }
        if ($postBase !== 'post') {
            $router->get('/post/{slug}', [$this, 'renderPost']);
            $router->post('/post/{slug}/comment', [$this, 'submitComment']);
        }

        // Register built-in widgets via hook
        do_action('kronos/builder/register_widgets');
    }

    public function install(): void
    {
        // All builder tables are created by KronosInstaller; nothing extra needed.
    }

    // ── Route handler ───────────────────────────────────────────────

    public function renderPage(array $params): void
    {
        $this->renderContent($params, 'page');
    }

    public function renderPost(array $params): void
    {
        $this->renderContent($params, 'post');
    }

    public function submitComment(array $params): void
    {
        kronos_verify_csrf();

        $slug = kronos_sanitize_slug($params['slug'] ?? '');
        if (!$slug) {
            kronos_abort(404);
        }

        $app = KronosApp::getInstance();
        kronos_ensure_comment_tables();

        $post = $app->db()->getRow(
            "SELECT id, slug, post_type FROM kronos_posts
             WHERE slug = ? AND post_type = 'post'
               AND (status = 'published' OR (status = 'scheduled' AND published_at IS NOT NULL AND published_at <= NOW()))
             LIMIT 1",
            [$slug]
        );
        if (!$post) {
            kronos_abort(404);
        }

        $name = trim((string) ($_POST['author_name'] ?? ''));
        $email = trim((string) ($_POST['author_email'] ?? ''));
        $url = trim((string) ($_POST['author_url'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $postPath = kronos_public_content_path($post);

        if ($name === '' || $content === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            kronos_redirect($postPath . '?comment_error=1#comments');
        }

        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = '';
        }

        $app->db()->insert('kronos_comments', [
            'post_id' => (int) $post['id'],
            'parent_id' => null,
            'author_name' => substr($name, 0, 191),
            'author_email' => substr($email, 0, 191),
            'author_url' => substr($url, 0, 500),
            'author_ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
            'content' => substr($content, 0, 5000),
            'status' => 'pending',
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        kronos_redirect($postPath . '?comment_sent=1#comments');
    }

    private function renderContent(array $params, string $postType): void
    {
        $slug = kronos_sanitize_slug($params['slug'] ?? '');
        if (!$slug) {
            kronos_abort(404);
        }

        $app     = KronosApp::getInstance();
        $preview = isset($_GET['preview']) && $_GET['preview'] === '1';

        // Preview mode: allow draft posts for authenticated editors
        if ($preview) {
            $user = null;
            $token = $_COOKIE['kronos_token'] ?? '';

            if ($token !== '') {
                try {
                    $secret = (string) $app->env('JWT_SECRET', '');
                    $expiry = (int) $app->env('JWT_EXPIRY', 86400);
                    $jwt = new \Kronos\Auth\KronosJWT($secret, $expiry);
                    $payload = $jwt->decode($token);
                    $user = $payload['data'] ?? null;
                } catch (\Throwable) {
                    $user = null;
                }
            }

            if (!$user || !in_array($user['role'] ?? '', ['app_manager', 'app_editor'], true)) {
                $preview = false; // Unauthorised — fall back to published-only
            }
        }

        $statusClause = $preview
            ? "p.status IN ('published','draft','scheduled','private','archived')"
            : "(p.status = 'published' OR (p.status = 'scheduled' AND p.published_at IS NOT NULL AND p.published_at <= NOW()))";

        $post = $app->db()->getRow(
            "SELECT p.*, l.json_data AS layout_json
             FROM kronos_posts p
             LEFT JOIN kronos_builder_layouts l ON l.id = p.layout_id
             WHERE p.slug = ? AND p.post_type = ? AND {$statusClause}
             LIMIT 1",
            [$slug, $postType]
        );

        if (!$post) {
            kronos_abort(404);
        }

        // Track analytics
        try {
            $app->db()->insert('kronos_analytics', [
                'event_type' => 'page_view',
                'entity_id'  => (int) $post['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Non-fatal
        }

        $engine = new \Kronos\Builder\RenderEngine();
        $html   = $engine->render((string) ($post['layout_json'] ?? '[]'));

        // Preview mode banner
        $previewBanner = '';
        if ($preview) {
            $status = kronos_e($post['status'] ?? 'draft');
            $previewBanner = '<div style="background:#fef9c3;border-bottom:2px solid #fde68a;padding:.75rem 1.25rem;font-size:.9rem;font-weight:600;text-align:center">'
                           . '&#128064; Preview Mode — Status: ' . $status . ' — <a href="' . kronos_url('/dashboard/content/' . (int) $post['id']) . '" style="color:#92400e">Back to Editor</a></div>';
        }

        $appName = kronos_option('app_name', 'KronosCMS');
        $title   = kronos_e($post['title']);

        // Apply theme if available; otherwise output a minimal wrapper
        $theme = apply_filters('kronos/theme/template', null, $post);
        if ($theme !== null && is_callable($theme)) {
            if ($previewBanner) echo $previewBanner;
            call_user_func($theme, $post, $html);
            return;
        }

        // Minimal fallback output
        echo '<!DOCTYPE html><html lang="en"><head>';
        echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo "<title>{$title} — " . kronos_e($appName) . '</title>';
        echo '<link rel="stylesheet" href="' . kronos_asset('css/theme.css') . '">';
        echo '</head><body>';
        if ($previewBanner) echo $previewBanner;
        echo "<main>{$html}</main>";
        echo '</body></html>';
    }
}
