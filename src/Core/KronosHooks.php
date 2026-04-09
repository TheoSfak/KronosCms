<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * KronosHooks — WordPress-style actions & filters system.
 * Provides add_action, do_action, add_filter, apply_filters.
 */
class KronosHooks
{
    /** @var array<string, array<int, array<array{callback: callable, priority: int}>>> */
    private array $actions = [];

    /** @var array<string, array<int, array<array{callback: callable, priority: int}>>> */
    private array $filters = [];

    /**
     * Register an action callback.
     */
    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][$priority][] = ['callback' => $callback, 'priority' => $priority];
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
            foreach ($priorityGroup as $item) {
                ($item['callback'])(...$args);
            }
        }
    }

    /**
     * Register a filter callback.
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][$priority][] = ['callback' => $callback, 'priority' => $priority];
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
            foreach ($priorityGroup as $item) {
                $value = ($item['callback'])($value, ...$args);
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
