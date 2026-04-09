<?php
declare(strict_types=1);

namespace Kronos\Core;

use Dotenv\Dotenv;

/**
 * KronosApp — Application bootstrap and service container.
 * Initializes all core services and dispatches the HTTP request.
 */
class KronosApp
{
    private static ?KronosApp $instance = null;

    private KronosDB $db;
    private KronosRouter $router;
    private KronosHooks $hooks;
    private KronosConfig $config;
    private KronosModuleLoader $moduleLoader;
    private KronosThemeManager $themeManager;

    private string $rootDir = '';

    /** @var array<string, mixed> Loaded .env values */
    private array $env = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bootstrap the application.
     * Call once from public/index.php.
     *
     * @param string $rootDir Absolute path to project root (one level above /public)
     */
    public function bootstrap(string $rootDir): void
    {
        $rootDir = rtrim($rootDir, '/\\');
        $this->rootDir = $rootDir;

        // 1. Load environment variables
        $this->loadEnv($rootDir);

        // 2. Check if installed; redirect to installer if not
        $this->checkInstalled($rootDir);

        // 3. Initialise database
        $this->db = KronosDB::init(
            host:   $this->env('DB_HOST', '127.0.0.1'),
            port:   (int) $this->env('DB_PORT', '3306'),
            dbName: $this->env('DB_NAME', ''),
            user:   $this->env('DB_USER', ''),
            pass:   $this->env('DB_PASS', '')
        );

        // 4. Core services
        $this->hooks        = new KronosHooks();
        $this->router       = new KronosRouter();
        $this->config       = new KronosConfig($this->db);
        $this->config->preload();

        // 5. Load modules
        $this->moduleLoader = new KronosModuleLoader($this);
        $this->moduleLoader->loadAll($rootDir . '/modules');
        $this->moduleLoader->bootAll();

        // 5b. Boot theme manager (after modules so filters are registered)
        $this->themeManager = new KronosThemeManager($this);
        $this->themeManager->boot();

        // 6. Dispatch request
        $matched = $this->router->dispatch();

        if (!$matched) {
            $this->send404($rootDir);
        }
    }

    // ------------------------------------------------------------------
    // Service accessors
    // ------------------------------------------------------------------

    public function db(): KronosDB
    {
        return $this->db;
    }

    public function router(): KronosRouter
    {
        return $this->router;
    }

    public function hooks(): KronosHooks
    {
        return $this->hooks;
    }

    public function config(): KronosConfig
    {
        return $this->config;
    }

    public function themeManager(): KronosThemeManager
    {
        return $this->themeManager;
    }

    public function rootDir(): string
    {
        return $this->rootDir;
    }

    // ------------------------------------------------------------------
    // Env helper
    // ------------------------------------------------------------------

    public function env(string $key, mixed $default = null): mixed
    {
        return $this->env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function loadEnv(string $rootDir): void
    {
        $envFile = $rootDir . '/.env';
        if (file_exists($envFile)) {
            $dotenv = Dotenv::createImmutable($rootDir);
            $dotenv->safeLoad();
        }

        // Cache locally so env() works without calling getenv() repeatedly
        $this->env = $_ENV;
    }

    private function checkInstalled(string $rootDir): void
    {
        $configFile = $rootDir . '/config/app.php';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        // Allow the install wizard to run without a config file
        if (!file_exists($configFile) && !str_contains($requestUri, '/install')) {
            header('Location: /install/');
            exit;
        }
    }

    private function send404(string $rootDir): void
    {
        http_response_code(404);
        $custom404 = $rootDir . '/themes/kronos-default/templates/404.php';
        if (file_exists($custom404)) {
            require $custom404;
        } else {
            echo '<h1>404 — Page Not Found</h1>';
        }
    }
}
