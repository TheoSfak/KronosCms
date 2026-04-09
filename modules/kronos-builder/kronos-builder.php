<?php
declare(strict_types=1);

namespace Kronos;

use Kronos\Core\KronosModule;
use Kronos\Core\KronosApp;

/**
 * KronosBuilderModule — page builder engine.
 *
 * Responsibilities:
 *  - Registers the RenderEngine with the front-end router (GET /page/{slug})
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

        // Rendered page output (public-facing, no auth)
        $router->add('GET', '/page/{slug}', [$this, 'renderPage']);

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
        $slug = kronos_sanitize_slug($params['slug'] ?? '');
        if (!$slug) {
            kronos_abort(404);
        }

        $app     = KronosApp::getInstance();
        $preview = isset($_GET['preview']) && $_GET['preview'] === '1';

        // Preview mode: allow draft posts for authenticated editors
        if ($preview) {
            $middleware = new \Kronos\Auth\KronosMiddleware();
            $user = $middleware->currentUser();
            if (!$user || !in_array($user['role'] ?? '', ['admin', 'editor'], true)) {
                $preview = false; // Unauthorised — fall back to published-only
            }
        }

        $statusClause = $preview ? "p.status IN ('published','draft')" : "p.status = 'published'";

        $post = $app->db()->getRow(
            "SELECT p.*, l.content AS layout_json
             FROM kronos_posts p
             LEFT JOIN kronos_builder_layouts l ON l.id = p.layout_id
             WHERE p.slug = ? AND {$statusClause}
             LIMIT 1",
            [$slug]
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
                           . '&#128064; Preview Mode — Status: ' . $status . ' — <a href="' . kronos_url('/dashboard/content') . '" style="color:#92400e">Back to Dashboard</a></div>';
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
