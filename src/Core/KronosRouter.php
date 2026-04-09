<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * KronosRouter — Custom URL router with middleware support.
 * Routes are matched against the request method and URI path.
 */
class KronosRouter
{
    /** @var array<array{method: string, pattern: string, handler: callable, middleware: callable[]}> */
    private array $routes = [];

    /** @var callable[] Global middleware applied to every route */
    private array $globalMiddleware = [];

    /**
     * Register a global middleware (runs before every matched route).
     */
    public function use(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    public function any(string $pattern, callable $handler, array $middleware = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $this->add($method, $pattern, $handler, $middleware);
        }
    }

    private function add(string $method, string $pattern, callable $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Dispatch the current HTTP request to the matching route.
     * Returns false if no route matched (caller should send 404).
     */
    public function dispatch(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Support method override via POST field or X-HTTP-Method-Override header
        if ($method === 'POST') {
            $override = $_POST['_method'] ?? ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '');
            if (in_array(strtoupper($override), ['PUT', 'DELETE', 'PATCH'], true)) {
                $method = strtoupper($override);
            }
        }

        $uri = $this->normalizeUri($_SERVER['REQUEST_URI'] ?? '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            if ($this->match($route['pattern'], $uri, $params)) {
                // Build middleware chain: global → route-specific → handler
                $handler = $route['handler'];
                $middlewares = array_merge($this->globalMiddleware, $route['middleware']);

                $next = function () use ($handler, $params) {
                    return $handler($params);
                };

                foreach (array_reverse($middlewares) as $mw) {
                    $innerNext = $next;
                    $next = function () use ($mw, $params, $innerNext) {
                        return $mw($params, $innerNext);
                    };
                }

                $next();
                return true;
            }
        }

        return false;
    }

    /**
     * Match a route pattern against a URI, extracting named parameters.
     * Pattern syntax: /posts/{id} or /posts/{id:\d+}
     *
     * @param array<string, string> $params Output named captures
     */
    private function match(string $pattern, string $uri, array &$params): bool
    {
        // Convert {name} and {name:regex} to named capture groups
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            function ($m) {
                $name  = $m[1];
                $regex = $m[2] ?? '[^/]+';
                return "(?P<{$name}>{$regex})";
            },
            $pattern
        );

        $regex = '#^' . $regex . '$#u';

        if (preg_match($regex, $uri, $matches)) {
            // Keep only string-keyed named captures
            $params = array_filter(
                $matches,
                fn($k) => is_string($k),
                ARRAY_FILTER_USE_KEY
            );
            return true;
        }

        return false;
    }

    /**
     * Strip query string and normalize slashes from the URI.
     */
    private function normalizeUri(string $uri): string
    {
        // Remove query string
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Strip script subfolder from path (when running in a subdirectory)
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = '/' . ltrim($path, '/');
        // Remove trailing slash unless root
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}
