<?php
declare(strict_types=1);

namespace Kronos\Builder\Widgets;

use Kronos\Builder\WidgetBase;

class TextWidget extends WidgetBase
{
    public function getType(): string { return 'text'; }

    public function render(array $attrs, string $innerHtml = ''): string
    {
        // Allow limited inline HTML (nl2br for plain text, else output as-is)
        $text = (string) $this->attr($attrs, 'text', '');
        $html = nl2br($this->e($text));
        $style = preg_replace('/[<>"\']/', '', (string) $this->attr($attrs, 'style', '')) ?? '';
        $styleAttr = $style !== '' ? " style=\"{$style}\"" : '';
        return "<p{$styleAttr}>{$html}</p>\n";
    }

    public function getControls(): array
    {
        return [
            ['key' => 'text', 'label' => 'Content', 'type' => 'textarea', 'default' => 'Paragraph text.'],
        ];
    }
}
