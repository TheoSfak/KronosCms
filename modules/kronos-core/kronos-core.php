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

        // Register the mode switch POST route
        $router = $app->router();
        $router->post('/dashboard/mode-switch', function (array $params) use ($app): void {
            require_once __DIR__ . '/ModeSwitcher.php';
            (new KronosModeSwitcher($app))->handle();
        });

        // Fire init hook for other modules to attach to
        do_action('kronos/core/init', $app);
    }
}
