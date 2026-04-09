<?php
declare(strict_types=1);

namespace Kronos\Builder\Widgets;

use Kronos\Builder\WidgetBase;

class ImageWidget extends WidgetBase
{
    public function getType(): string { return 'image'; }

    public function render(array $attrs, string $innerHtml = ''): string
    {
        $src = filter_var((string) $this->attr($attrs, 'src', ''), FILTER_VALIDATE_URL)
               ? $this->e($attrs['src'])
               : '';
        $alt = $this->e($this->attr($attrs, 'alt', ''));

        if ($src === '') {
            return '';
        }

        return "<img src=\"{$src}\" alt=\"{$alt}\" style=\"max-width:100%;height:auto;display:block\">\n";
    }

    public function getControls(): array
    {
        return [
            ['key' => 'src', 'label' => 'Image URL', 'type' => 'url',  'default' => ''],
            ['key' => 'alt', 'label' => 'Alt Text',  'type' => 'text', 'default' => ''],
        ];
    }
}
