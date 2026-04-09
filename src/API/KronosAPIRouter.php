<?php
declare(strict_types=1);

namespace Kronos\API;

use Kronos\Auth\KronosJWT;
use Kronos\Auth\KronosMiddleware;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosRouter;

/**
 * KronosAPIRouter — Registers all /api/kronos/v1/* endpoints
 * onto the main KronosRouter and provides shared API utilities.
 */
class KronosAPIRouter
{
    private KronosRouter $router;
    private KronosMiddleware $middleware;
    public KronosJWT $jwt;

    public function __construct(KronosApp $app)
    {
        $this->router = $app->router();

        $secret = (string) $app->env('JWT_SECRET', '');
        $expiry = (int)   $app->env('JWT_EXPIRY', 86400);
        $this->jwt        = new KronosJWT($secret, $expiry);
        $this->middleware = new KronosMiddleware($this->jwt);
    }

    /**
     * Register all API routes. Called from kronos-core module boot().
     */
    public function registerRoutes(): void
    {
        $auth       = [new Endpoints\AuthEndpoint($this), 'handle'];
        $builder    = [new Endpoints\BuilderEndpoint($this), 'handle'];
        $ai         = [new Endpoints\AIEndpoint($this), 'handle'];
        $stream     = [new Endpoints\StreamEndpoint($this), 'handle'];
        $system     = [new Endpoints\SystemEndpoint($this), 'handle'];
        $commerce   = [new Endpoints\CommerceEndpoint($this), 'handle'];
        $marketplace = [new Endpoints\MarketplaceEndpoint($this), 'handle'];

        $authenticated = [$this->middleware->handle()];
        $managerOnly   = [$this->middleware->handle(), $this->middleware->requireRole('app_manager')];

        // Auth (public)
        $this->router->post('/api/kronos/v1/auth/login',   $auth);
        $this->router->post('/api/kronos/v1/auth/refresh',  $auth);
        $this->router->post('/api/kronos/v1/auth/logout',   $auth);

        // Builder (authenticated)
        $this->router->get('/api/kronos/v1/builder/layouts',          $builder, $authenticated);
        $this->router->get('/api/kronos/v1/builder/layouts/{id:\d+}', $builder, $authenticated);
        $this->router->post('/api/kronos/v1/builder/layouts',         $builder, $authenticated);
        $this->router->put('/api/kronos/v1/builder/layouts/{id:\d+}', $builder, $authenticated);
        $this->router->delete('/api/kronos/v1/builder/layouts/{id:\d+}', $builder, $managerOnly);

        // AI (authenticated)
        $this->router->post('/api/kronos/v1/ai/chat', $ai, $authenticated);

        // SSE Stream (authenticated)
        $this->router->get('/api/kronos/v1/stream', $stream, $authenticated);

        // System / update (manager only)
        $this->router->get('/api/kronos/v1/system/update/check',  $system, $managerOnly);
        $this->router->post('/api/kronos/v1/system/update/apply', $system, $managerOnly);

        // Commerce (authenticated)
        $this->router->get('/api/kronos/v1/commerce/products',          $commerce, $authenticated);
        $this->router->post('/api/kronos/v1/commerce/products',          $commerce, $managerOnly);
        $this->router->put('/api/kronos/v1/commerce/products/{id:\d+}',  $commerce, $managerOnly);
        $this->router->delete('/api/kronos/v1/commerce/products/{id:\d+}', $commerce, $managerOnly);
        $this->router->get('/api/kronos/v1/commerce/cart',               $commerce, $authenticated);
        $this->router->post('/api/kronos/v1/commerce/cart',               $commerce, $authenticated);
        $this->router->delete('/api/kronos/v1/commerce/cart/{item_id:\d+}', $commerce, $authenticated);
        $this->router->post('/api/kronos/v1/commerce/orders',             $commerce, $authenticated);
        $this->router->get('/api/kronos/v1/commerce/orders',              $commerce, $authenticated);
        $this->router->get('/api/kronos/v1/commerce/orders/{id:\d+}',     $commerce, $authenticated);
        $this->router->put('/api/kronos/v1/commerce/orders/{id:\d+}/status', $commerce, $managerOnly);

        // Marketplace (manager only)
        $this->router->get('/api/kronos/v1/marketplace/directory',    $marketplace, $managerOnly);
        $this->router->post('/api/kronos/v1/marketplace/install',     $marketplace, $managerOnly);
    }

    public function getMiddleware(): KronosMiddleware
    {
        return $this->middleware;
    }

    public function getJwt(): KronosJWT
    {
        return $this->jwt;
    }
}
