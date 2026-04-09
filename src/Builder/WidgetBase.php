<?php
declare(strict_types=1);

namespace Kronos\Builder;

/**
 * Abstract base class for all Kronos builder widgets.
 * Server-side counterpart of the JS KronosAPI.Widgets.register() definition.
 */
abstract class WidgetBase
{
    /**
     * Return the unique widget type identifier (e.g. "heading").
     */
    abstract public function getType(): string;

    /**
     * Render the widget to HTML.
     *
     * @param  array<string, mixed> $attrs     Block attributes from the AST
     * @param  string               $innerHtml Already-rendered children HTML
     * @return string
     */
    abstract public function render(array $attrs, string $innerHtml = ''): string;

    /**
     * Return the widget's control definitions (mirrors JS getControls()).
     * Used by future server-side form renderers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getControls(): array
    {
        return [];
    }

    // ── Helpers ───────────────────────────────────────────────────

    protected function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function attr(array $attrs, string $key, mixed $default = ''): mixed
    {
        return $attrs[$key] ?? $default;
    }
}
