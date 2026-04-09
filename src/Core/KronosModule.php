<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * Abstract base class for all KronosCMS modules.
 * Each module must implement boot() to register its routes/hooks.
 */
abstract class KronosModule
{
    protected KronosApp $app;

    public function __construct(KronosApp $app)
    {
        $this->app = $app;
    }

    /**
     * Called after all modules are loaded.
     * Register routes, hooks, and initialization logic here.
     */
    abstract public function boot(): void;

    /**
     * Optional: called by the installer. Override to create module-specific tables.
     */
    public function install(): void {}

    /**
     * Return the module's machine-readable name. Used for dependency checks.
     */
    abstract public function getName(): string;
}
