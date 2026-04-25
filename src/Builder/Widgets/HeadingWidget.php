<?php
declare(strict_types=1);

namespace Kronos\Builder\Widgets;

use Kronos\Builder\WidgetBase;

class HeadingWidget extends WidgetBase
{
    public function getType(): string { return 'heading'; }

    public function render(array $attrs, string $innerHtml = ''): string
    {
        $legacyLevel = (int) ($attrs['level'] ?? 0);
        $tag  = in_array($attrs['tag'] ?? '', ['h1','h2','h3','h4','h5','h6'], true)
                ? $attrs['tag']
                : ($legacyLevel >= 1 && $legacyLevel <= 6 ? 'h' . $legacyLevel : 'h2');
        $text = $this->e($this->attr($attrs, 'text', 'Heading'));
        $style = $this->sanitizeStyle((string) $this->attr($attrs, 'style', ''));
        $styleAttr = $style !== '' ? " style=\"{$style}\"" : '';

        return "<{$tag}{$styleAttr}>{$text}</{$tag}>\n";
    }

    public function getControls(): array
    {
        return [
            ['key' => 'text', 'label' => 'Text', 'type' => 'text',   'default' => 'Heading'],
            ['key' => 'tag',  'label' => 'Tag',  'type' => 'select', 'default' => 'h2',
             'options' => [['value'=>'h1','label'=>'H1'],['value'=>'h2','label'=>'H2'],
                           ['value'=>'h3','label'=>'H3'],['value'=>'h4','label'=>'H4']]],
        ];
    }

    private function sanitizeStyle(string $style): string
    {
        return preg_replace('/[<>"\']/', '', $style) ?? '';
    }
}
