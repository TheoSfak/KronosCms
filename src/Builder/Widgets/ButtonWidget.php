<?php
declare(strict_types=1);

namespace Kronos\Builder\Widgets;

use Kronos\Builder\WidgetBase;

class ButtonWidget extends WidgetBase
{
    public function getType(): string { return 'button'; }

    public function render(array $attrs, string $innerHtml = ''): string
    {
        $label = $this->e($this->attr($attrs, 'label', 'Click Me'));
        $rawUrl = (string) $this->attr($attrs, 'url', '#');
        // Only allow safe URL schemes
        $url = filter_var($rawUrl, FILTER_VALIDATE_URL) ? $this->e($rawUrl) : '#';

        return "<a href=\"{$url}\" class=\"btn btn-primary\">{$label}</a>\n";
    }

    public function getControls(): array
    {
        return [
            ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Click Me'],
            ['key' => 'url',   'label' => 'URL',   'type' => 'url',  'default' => '#'],
        ];
    }
}
