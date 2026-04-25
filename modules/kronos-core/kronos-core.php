<?php
declare(strict_types=1);

use Kronos\Core\KronosApp;
use Kronos\Core\KronosModule;
use Kronos\API\KronosAPIRouter;

/**
 * KronosCoreModule — Bootstraps API routes, mode switcher, and core hooks.
 */
class KronosCoreModule extends KronosModule
{
    public function getName(): string
    {
        return 'kronos-core';
    }

    public function boot(): void
    {
        $app = $this->app;

        // Register API routes
        $apiRouter = new KronosAPIRouter($app);
        $apiRouter->registerRoutes();

        $router = $app->router();

        // Debug inspector (only in APP_DEBUG=true)
        $router->get('/debug', function (array $params) use ($app): void {
            require __DIR__ . '/debug.php';
        });

        // Front-page route — serve active theme home template
        $router->get('/', function (array $params) use ($app): void {
            if (kronos_render_cms_page_by_slug('home', true)) {
                return;
            }

            $themeManager = $app->themeManager();
            $theme        = $themeManager->getActiveTheme();
            $slug         = $themeManager->getActiveSlug();

            $homeTpl = null;
            if ($theme && !empty($theme['templates']['home'])) {
                $path = $app->rootDir() . '/themes/' . $slug . '/' . $theme['templates']['home'];
                if (file_exists($path)) {
                    $homeTpl = $path;
                }
            }

            if ($homeTpl) {
                require $homeTpl;
            } else {
                // Fallback: redirect to dashboard
                $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
                header('Location: ' . $base . '/dashboard/');
                exit;
            }
        });

        // Fire init hook for other modules to attach to
        do_action('kronos/core/init', $app);
    }
}
