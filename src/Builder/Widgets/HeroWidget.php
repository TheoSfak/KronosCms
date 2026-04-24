<?php
declare(strict_types=1);

namespace Kronos\Builder\Widgets;

use Kronos\Builder\WidgetBase;

class HeroWidget extends WidgetBase
{
    public function getType(): string { return 'hero-block'; }

    public function render(array $attrs, string $innerHtml = ''): string
    {
        $layout = (string) $this->attr($attrs, 'imageLayout', 'split-right');
        if (!in_array($layout, ['split-right', 'split-left', 'background'], true)) {
            $layout = 'split-right';
        }

        $title     = $this->e($this->attr($attrs, 'title', 'Build a page people remember'));
        $subtitle  = $this->e($this->attr($attrs, 'subtitle', ''));
        $pretitle  = $this->e($this->attr($attrs, 'pretitle', ''));
        $imageUrl  = $this->safeUrl((string) $this->attr($attrs, 'imageUrl', ''));
        $imageAlt  = $this->e($this->attr($attrs, 'imageAlt', ''));
        $btnLabel  = $this->e($this->attr($attrs, 'btnLabel', ''));
        $btnUrl    = $this->safeUrl((string) $this->attr($attrs, 'btnUrl', '#')) ?: '#';
        $btn2Label = $this->e($this->attr($attrs, 'secondBtnLabel', ''));
        $btn2Url   = $this->safeUrl((string) $this->attr($attrs, 'secondBtnUrl', '#')) ?: '#';

        $bg          = $this->safeCss((string) $this->attr($attrs, 'bg', 'linear-gradient(135deg,#111827,#312e81)'));
        $titleColor  = $this->safeCss((string) $this->attr($attrs, 'titleColor', '#ffffff'));
        $bodyColor   = $this->safeCss((string) $this->attr($attrs, 'bodyColor', 'rgba(255,255,255,.78)'));
        $btnColor    = $this->safeCss((string) $this->attr($attrs, 'btnColor', '#ffffff'));
        $btnText     = $this->safeCss((string) $this->attr($attrs, 'btnText', '#111827'));
        $alignValue  = (string) ($attrs['_align'] ?? 'left');
        $align       = in_array($alignValue, ['left', 'center', 'right'], true) ? $alignValue : 'left';
        $pad         = $this->intRange($this->attr($attrs, 'pad', 72), 20, 200);
        $minHeight   = $this->intRange($this->attr($attrs, 'minHeight', 420), 260, 820);
        $radius      = $this->intRange($this->attr($attrs, 'radius', 18), 0, 48);
        $imageRadius = $this->intRange($this->attr($attrs, 'imageRadius', 18), 0, 48);
        $overlay     = $this->intRange($this->attr($attrs, 'overlay', 45), 0, 90);

        $justify = match ($align) {
            'center' => 'center',
            'right' => 'flex-end',
            default => 'flex-start',
        };

        $buttons = '';
        if ($btnLabel !== '') {
            $buttons .= '<a href="' . $this->e($btnUrl) . '" style="display:inline-block;background:' . $btnColor . ';color:' . $btnText . ';padding:13px 26px;border-radius:999px;text-decoration:none;font-weight:800;font-size:.92rem">' . $btnLabel . '</a>';
        }
        if ($btn2Label !== '') {
            $buttons .= '<a href="' . $this->e($btn2Url) . '" style="display:inline-block;border:1px solid rgba(255,255,255,.5);color:#fff;padding:12px 24px;border-radius:999px;text-decoration:none;font-weight:750;font-size:.92rem">' . $btn2Label . '</a>';
        }

        $copy = '<div style="position:relative;z-index:2;max-width:' . ($layout === 'background' ? '680' : '560') . 'px;text-align:' . $align . '">'
            . ($pretitle !== '' ? '<p style="font-size:.78rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.68);margin:0 0 12px">' . $pretitle . '</p>' : '')
            . '<h1 style="font-size:3.75rem;font-weight:850;color:' . $titleColor . ';line-height:1.02;margin:0 0 18px;letter-spacing:0">' . $title . '</h1>'
            . ($subtitle !== '' ? '<p style="font-size:1.12rem;color:' . $bodyColor . ';max-width:620px;margin:' . ($align === 'center' ? '0 auto 30px' : '0 0 30px') . ';line-height:1.65">' . $subtitle . '</p>' : '')
            . ($buttons !== '' ? '<div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:' . $justify . '">' . $buttons . '</div>' : '')
            . '</div>';

        if ($layout === 'background') {
            $bgStyle = $imageUrl !== ''
                ? "background-image:linear-gradient(90deg,rgba(0,0,0," . ($overlay / 100) . "),rgba(0,0,0," . (max($overlay - 15, 0) / 100) . ")),url('" . $this->e($imageUrl) . "');background-size:cover;background-position:center;"
                : 'background:' . $bg . ';';

            return '<section class="kronos-hero-block" style="' . $bgStyle . 'min-height:' . $minHeight . 'px;padding:' . $pad . 'px 48px;border-radius:' . $radius . 'px;display:flex;align-items:center;justify-content:' . $justify . ';overflow:hidden">' . $copy . '</section>';
        }

        $image = $imageUrl !== ''
            ? '<img src="' . $this->e($imageUrl) . '" alt="' . $imageAlt . '" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:' . $imageRadius . 'px">'
            : '<div style="width:100%;height:100%;min-height:260px;border-radius:' . $imageRadius . 'px;background:linear-gradient(135deg,rgba(255,255,255,.18),rgba(255,255,255,.05)),linear-gradient(135deg,#6366f1,#06b6d4);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.82);font-weight:800;letter-spacing:.08em;text-transform:uppercase">Image</div>';

        $imageWrap = '<div style="position:relative;z-index:1;min-height:300px">' . $image . '</div>';
        $content = $layout === 'split-left' ? $imageWrap . $copy : $copy . $imageWrap;

        return '<section class="kronos-hero-block" style="background:' . $bg . ';min-height:' . $minHeight . 'px;padding:' . $pad . 'px 48px;border-radius:' . $radius . 'px;display:grid;grid-template-columns:minmax(0,1fr) minmax(260px,.92fr);gap:42px;align-items:center;overflow:hidden">' . $content . '</section>';
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || $url === '#') return $url;
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) return $url;
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    private function safeCss(string $value): string
    {
        return str_replace(['<', '>', '"', "'"], '', $value);
    }

    private function intRange(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }
}
