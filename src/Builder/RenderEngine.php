<?php
declare(strict_types=1);

namespace Kronos\Builder;

/**
 * RenderEngine — walks a builder AST JSON array and outputs HTML.
 *
 * Each node in the AST looks like:
 *   { "id": "b_abc123", "type": "heading", "attrs": { "text": "Hello", "tag": "h2" }, "children": [] }
 *
 * Widgets are resolved via a simple registry. Third-party modules can add
 * widgets by calling apply_filters('kronos/builder/widgets', $registry).
 */
class RenderEngine
{
    /** @var array<string, WidgetBase> */
    private array $registry = [];

    public function __construct()
    {
        $this->registerBuiltins();
    }

    /**
     * Render a full AST JSON string into an HTML string.
     */
    public function render(string $layoutJson): string
    {
        $nodes = json_decode($layoutJson, true);
        if (!is_array($nodes)) return '';
        if (isset($nodes['blocks']) && is_array($nodes['blocks'])) {
            $nodes = $nodes['blocks'];
        }

        return $this->renderNodes($nodes);
    }

    /**
     * Render an array of AST nodes.
     *
     * @param  array<int, array<string, mixed>> $nodes
     */
    public function renderNodes(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->renderNode($node);
        }
        return $html;
    }

    // ── Private ────────────────────────────────────────────────────

    private function renderNode(array $node): string
    {
        $type  = (string) ($node['type']  ?? '');
        $attrs = (array)  ($node['attrs'] ?? $node['props'] ?? []);
        $children = (array) ($node['children'] ?? []);

        $widget = $this->registry[$type] ?? null;

        if ($widget === null) {
            return '<!-- unknown widget: ' . htmlspecialchars($type, ENT_QUOTES) . ' -->';
        }

        $innerHtml = $this->renderNodes($children);

        $output = $widget->render($attrs, $innerHtml);

        // Allow third-party filtering of block output
        $output = apply_filters('kronos/builder/render_block', $output, $node);

        return (string) $output;
    }

    private function registerBuiltins(): void
    {
        $this->registry['heading']   = new Widgets\HeadingWidget();
        $this->registry['text']      = new Widgets\TextWidget();
        $this->registry['image']     = new Widgets\ImageWidget();
        $this->registry['button']    = new Widgets\ButtonWidget();
        $this->registry['container'] = new Widgets\ContainerWidget();

        // Allow external modules to add or override widgets
        $this->registry = apply_filters('kronos/builder/widget_registry', $this->registry);
    }
}
