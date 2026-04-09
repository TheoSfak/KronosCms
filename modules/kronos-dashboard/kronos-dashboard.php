<?php
declare(strict_types=1);

use Kronos\Core\KronosModule;
use Kronos\Core\KronosApp;

/**
 * KronosDashboardModule — Registers all /dashboard/* routes.
 * All routes validate the JWT cookie before rendering.
 */
class KronosDashboardModule extends KronosModule
{
    public function getName(): string
    {
        return 'kronos-dashboard';
    }

    public function boot(): void
    {
        $router = $this->app->router();
        $auth   = fn(array $p, callable $next) => $this->requireAuth($p, $next);

        $router->get('/dashboard',                  fn($p) => $this->page('home', $p),       [$auth]);
        $router->get('/dashboard/content',          fn($p) => $this->page('content', $p),    [$auth]);
        $router->get('/dashboard/content/new',      fn($p) => $this->page('content-edit', $p), [$auth]);
        $router->get('/dashboard/content/{id:\d+}', fn($p) => $this->page('content-edit', $p), [$auth]);
        $router->get('/dashboard/builder/{id:\d+}', fn($p) => $this->page('builder', $p),    [$auth]);
        $router->get('/dashboard/products',         fn($p) => $this->page('products', $p),   [$auth]);
        $router->get('/dashboard/orders',           fn($p) => $this->page('orders', $p),     [$auth]);
        $router->get('/dashboard/analytics',        fn($p) => $this->page('analytics', $p),  [$auth]);
        $router->get('/dashboard/users',            fn($p) => $this->page('users', $p),      [$auth]);
        $router->get('/dashboard/marketplace',      fn($p) => $this->page('marketplace', $p),[$auth]);
        $router->get('/dashboard/ai',               fn($p) => $this->page('ai', $p),         [$auth]);
        $router->get('/dashboard/settings',         fn($p) => $this->page('settings', $p),   [$auth]);

        // Login page (public)
        $router->get('/dashboard/login',  fn($p) => $this->loginPage());
        $router->post('/dashboard/login', fn($p) => $this->loginPage());

        // Logout
        $router->get('/dashboard/logout', fn($p) => $this->logout());
    }

    // ------------------------------------------------------------------
    // Auth guard
    // ------------------------------------------------------------------

    private function requireAuth(array $params, callable $next): void
    {
        // Check for kronos_token cookie
        if (empty($_COOKIE['kronos_token'])) {
            kronos_redirect('/dashboard/login');
        }

        // Verify the token
        try {
            $app    = $this->app;
            $secret = (string) $app->env('JWT_SECRET', '');
            $expiry = (int)   $app->env('JWT_EXPIRY', 86400);
            $jwt    = new \Kronos\Auth\KronosJWT($secret, $expiry);
            $payload = $jwt->decode($_COOKIE['kronos_token']);
            $_REQUEST['_kronos_user'] = $payload['data'] ?? [];
        } catch (\RuntimeException) {
            setcookie('kronos_token', '', ['expires' => time() - 3600, 'path' => '/']);
            kronos_redirect('/dashboard/login');
        }

        $next();
    }

    // ------------------------------------------------------------------
    // Page dispatcher
    // ------------------------------------------------------------------

    private function page(string $name, array $params = []): void
    {
        $file = __DIR__ . '/pages/' . $name . '.php';
        if (!file_exists($file)) {
            kronos_abort(404, 'Dashboard page not found.');
        }
        // Make app & params available in page views
        $app = $this->app;
        require $file;
    }

    private function loginPage(): void
    {
        require __DIR__ . '/pages/login.php';
    }

    private function logout(): void
    {
        setcookie('kronos_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        kronos_redirect('/dashboard/login');
    }
}
