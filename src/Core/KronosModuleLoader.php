<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * KronosModuleLoader — Discovers and instantiates module classes.
 *
 * Each module lives in /modules/{module-name}/{module-name}.php and must
 * declare a class that extends KronosModule. The class name is derived from
 * the filename using PascalCase convention, e.g.:
 *   modules/kronos-dashboard/kronos-dashboard.php → KronosDashboardModule
 */
class KronosModuleLoader
{
    private KronosApp $app;

    /** @var KronosModule[] */
    private array $loaded = [];

    public function __construct(KronosApp $app)
    {
        $this->app = $app;
    }

    /**
     * Scan the /modules/ directory and load all valid modules.
     * Modules are booted in filesystem order; use hook priorities for ordering.
     */
    public function loadAll(string $modulesDir): void
    {
        if (!is_dir($modulesDir)) {
            return;
        }

        $dirs = glob(rtrim($modulesDir, '/') . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return;
        }

        // Ensure kronos-core loads first
        usort($dirs, function (string $a): int {
            return str_contains($a, 'kronos-core') ? -1 : 1;
        });

        foreach ($dirs as $moduleDir) {
            $this->loadModule($moduleDir);
        }
    }

    private function loadModule(string $moduleDir): void
    {
        $slug = basename($moduleDir);
        $entryFile = $moduleDir . '/' . $slug . '.php';

        if (!file_exists($entryFile)) {
            return;
        }

        require_once $entryFile;

        // Derive class name: "kronos-dashboard" → "KronosDashboardModule"
        $className = $this->slugToClassName($slug);

        if (!class_exists($className)) {
            error_log("[KronosModuleLoader] Expected class {$className} not found in {$entryFile}");
            return;
        }

        $module = new $className($this->app);

        if (!$module instanceof KronosModule) {
            error_log("[KronosModuleLoader] {$className} does not extend KronosModule");
            return;
        }

        $this->loaded[$slug] = $module;
    }

    /**
     * Boot all loaded modules. Call this after loadAll().
     */
    public function bootAll(): void
    {
        foreach ($this->loaded as $module) {
            $module->boot();
        }
    }

    /**
     * Convert a kebab-case slug to a PascalCase class name with "Module" suffix.
     * "kronos-dashboard" → "KronosDashboardModule"
     */
    private function slugToClassName(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $slug))) . 'Module';
    }

    /**
     * @return KronosModule[]
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }
}
