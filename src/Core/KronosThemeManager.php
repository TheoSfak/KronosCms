<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * KronosThemeManager — discovers, activates, and loads themes.
 *
 * Themes live in /themes/{slug}/ and declare a theme.json manifest.
 * The active theme is stored in the `active_theme` kronos option.
 *
 * Responsibilities:
 *  - Scan the /themes/ directory for available themes
 *  - Load the active theme's template for public page rendering
 *  - Hook into `kronos/theme/template` filter (used by the builder module)
 *  - Copy active theme assets to /public/assets/ on activation
 */
class KronosThemeManager
{
    private string      $themesDir;
    private string      $publicAssetsDir;
    private KronosApp   $app;

    /** @var array<string, array<string, mixed>> */
    private array $discovered = [];

    public function __construct(KronosApp $app)
    {
        $this->app            = $app;
        $this->themesDir      = rtrim($app->rootDir(), DIRECTORY_SEPARATOR) . '/themes';
        $this->publicAssetsDir = rtrim($app->rootDir(), DIRECTORY_SEPARATOR) . '/public/assets';
    }

    /**
     * Boot the theme manager — discovers themes, registers template filter.
     */
    public function boot(): void
    {
        $this->discoverThemes();

        // Wire the template filter that KronosBuilderModule uses
        add_filter('kronos/theme/template', [$this, 'resolveTemplate'], 10, 2);

        // Hook: allow other code to query the manager via filter
        add_filter('kronos/theme/manager', function () { return $this; });

        do_action('kronos/theme/loaded', $this->getActiveSlug());
    }

    /**
     * Return all discovered themes indexed by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getThemes(): array
    {
        return $this->discovered;
    }

    /**
     * Return the manifest of the currently active theme (or null).
     *
     * @return array<string, mixed>|null
     */
    public function getActiveTheme(): ?array
    {
        $slug = $this->getActiveSlug();
        return $this->discovered[$slug] ?? null;
    }

    public function getActiveSlug(): string
    {
        return (string) $this->app->config()->get('active_theme', 'kronos-default');
    }

    /**
     * Activate a theme by slug.
     * Copies its assets to /public/assets/ and saves the option.
     *
     * @return array{success: bool, message: string}
     */
    public function activate(string $slug): array
    {
        if (!isset($this->discovered[$slug])) {
            return ['success' => false, 'message' => "Theme '{$slug}' not found."];
        }

        $this->app->config()->set('active_theme', $slug);
        $this->copyAssets($slug);

        do_action('kronos/theme/activated', $slug);

        return ['success' => true, 'message' => "Theme '{$slug}' activated."];
    }

    /**
     * Callback for the `kronos/theme/template` filter.
     * Returns a callable that renders a post with the active theme template.
     *
     * @param  mixed $current  Current filter value (null = not yet set)
     * @param  array $post     Post data row from DB
     * @return callable|null
     */
    public function resolveTemplate(mixed $current, array $post): mixed
    {
        if ($current !== null) {
            return $current; // Another filter already resolved it
        }

        $theme = $this->getActiveTheme();
        if (!$theme) return null;

        $templates = $theme['templates'] ?? [];

        // Pick the most specific template
        $postType = $post['post_type'] ?? 'post';
        $keys = [
            $postType,          // 'page', 'product', 'post'
            $postType === 'post' ? 'single' : null,
            'page',
        ];

        $templateFile = null;
        foreach (array_filter($keys) as $key) {
            if (!empty($templates[$key])) {
                $path = $this->themesDir . '/' . $this->getActiveSlug() . '/' . $templates[$key];
                if (file_exists($path)) {
                    $templateFile = $path;
                    break;
                }
            }
        }

        if (!$templateFile) return null;

        return function (array $post, string $html) use ($templateFile) {
            extract(['post' => $post, 'html' => $html], EXTR_SKIP);
            require $templateFile;
        };
    }

    // ── Private ────────────────────────────────────────────────────

    private function discoverThemes(): void
    {
        if (!is_dir($this->themesDir)) return;

        foreach (scandir($this->themesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $dir      = $this->themesDir . '/' . $entry;
            $manifest = $dir . '/theme.json';
            if (!is_dir($dir) || !file_exists($manifest)) continue;

            $data = json_decode((string) file_get_contents($manifest), true);
            if (!is_array($data)) continue;

            $data['_path'] = $dir;
            $this->discovered[$entry] = $data;
        }
    }

    /**
     * Copy active theme CSS/JS assets to /public/assets/
     * so they can be served via kronos_asset().
     */
    private function copyAssets(string $slug): void
    {
        $themeAssets = $this->themesDir . '/' . $slug . '/assets';
        if (!is_dir($themeAssets)) return;

        $cssDir = $this->publicAssetsDir . '/css';
        $jsDir  = $this->publicAssetsDir . '/js';

        foreach (scandir($themeAssets) ?: [] as $file) {
            if ($file === '.' || $file === '..') continue;
            $src = $themeAssets . '/' . $file;
            if (!is_file($src)) continue;

            if (str_ends_with($file, '.css') && is_dir($cssDir)) {
                copy($src, $cssDir . '/' . $file);
            } elseif (str_ends_with($file, '.js') && is_dir($jsDir)) {
                copy($src, $jsDir . '/' . $file);
            }
        }
    }
}
