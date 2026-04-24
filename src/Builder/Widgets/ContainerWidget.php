<?php
declare(strict_types=1);

namespace Kronos\Builder\Widgets;

use Kronos\Builder\WidgetBase;

class ContainerWidget extends WidgetBase
{
    public function getType(): string { return 'container'; }

    public function render(array $attrs, string $innerHtml = ''): string
    {
        $style = '';

        // Optional CSS class passthrough
        $cls = preg_replace('/[^a-zA-Z0-9 _-]/', '', (string) $this->attr($attrs, 'class', $this->attr($attrs, 'className', '')));

        // Optional background colour (CSS hex/named)
        $bg = preg_replace('/[^a-zA-Z0-9#(). ,]/', '', (string) $this->attr($attrs, 'background', ''));
        if ($bg) $style .= "background:{$bg};";

        // Optional padding
        $pad = preg_replace('/[^0-9a-z%px ]/', '', (string) $this->attr($attrs, 'padding', ''));
        if ($pad) $style .= "padding:{$pad};";

        $rawStyle = (string) $this->attr($attrs, 'style', '');
        if ($rawStyle !== '') {
            $style .= preg_replace('/[<>"\']/', '', $rawStyle);
        }

        $styleAttr = $style ? " style=\"{$style}\"" : '';
        $classAttr = $cls   ? " class=\"" . htmlspecialchars($cls, ENT_QUOTES) . "\"" : '';

        return "<div{$classAttr}{$styleAttr}>{$innerHtml}</div>\n";
    }

    public function getControls(): array
    {
        return [
            ['key' => 'class',      'label' => 'CSS Class',  'type' => 'text', 'default' => ''],
            ['key' => 'background', 'label' => 'Background',  'type' => 'text', 'default' => ''],
            ['key' => 'padding',    'label' => 'Padding',     'type' => 'text', 'default' => ''],
        ];
    }
}
