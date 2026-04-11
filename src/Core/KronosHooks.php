<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * KronosHooks — WordPress-style actions & filters system.
 * Provides add_action, do_action, add_filter, apply_filters.
 */
class KronosHooks
{
    /** @var array<string, array<int, callable[]>> */
    private array $actions = [];

    /** @var array<string, array<int, callable[]>> */
    private array $filters = [];

    /**
     * Register an action callback.
     */
    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][$priority][] = $callback;
    }

    /**
     * Remove a previously registered action callback.
     */
    public function removeAction(string $hook, callable $callback, int $priority = 10): void
    {
        if (empty($this->actions[$hook][$priority])) {
            return;
        }
        $this->actions[$hook][$priority] = array_values(array_filter(
            $this->actions[$hook][$priority],
            static fn(callable $cb) => $cb !== $callback
        ));
    }

    /**
     * Fire all callbacks registered for an action hook.
     */
    public function doAction(string $hook, mixed ...$args): void
    {
        if (empty($this->actions[$hook])) {
            return;
        }

        ksort($this->actions[$hook]);
        foreach ($this->actions[$hook] as $priorityGroup) {
            foreach ($priorityGroup as $callback) {
                $callback(...$args);
            }
        }
    }

    /**
     * Register a filter callback.
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][$priority][] = $callback;
    }

    /**
     * Remove a previously registered filter callback.
     */
    public function removeFilter(string $hook, callable $callback, int $priority = 10): void
    {
        if (empty($this->filters[$hook][$priority])) {
            return;
        }
        $this->filters[$hook][$priority] = array_values(array_filter(
            $this->filters[$hook][$priority],
            static fn(callable $cb) => $cb !== $callback
        ));
    }

    /**
     * Run a value through all filter callbacks registered for a hook.
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (empty($this->filters[$hook])) {
            return $value;
        }

        ksort($this->filters[$hook]);
        foreach ($this->filters[$hook] as $priorityGroup) {
            foreach ($priorityGroup as $callback) {
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }

    /**
     * Check whether any callbacks are registered for a hook.
     */
    public function hasAction(string $hook): bool
    {
        return !empty($this->actions[$hook]);
    }

    public function hasFilter(string $hook): bool
    {
        return !empty($this->filters[$hook]);
    }
}
