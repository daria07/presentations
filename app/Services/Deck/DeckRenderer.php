<?php

namespace App\Services\Deck;

use App\Models\Presentation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Собирает PDF из структуры слайдов: сначала Blade рисует HTML,
 * затем headless-браузер печатает его в PDF.
 *
 * Тот же HTML используется для превью на сайте — вёрстка одна,
 * расхождений между просмотром и файлом не будет.
 */
class DeckRenderer
{
    public function html(
        Presentation $presentation,
        ?string $theme = null,
        bool $forScreen = false,
        ?string $palette = null,
    ): string {
        $outline = $presentation->outline ?? [];

        if (empty($outline['slides'])) {
            throw new RuntimeException('У презентации нет слайдов — сначала нужно собрать структуру.');
        }

        $themeKey = $this->themeKey($theme ?? $presentation->theme);
        $paletteKey = $this->paletteKey($palette ?? $presentation->palette);
        $theme = config('deck.themes')[$themeKey];

        return View::make('deck.deck', [
            'title' => $outline['title'] ?? $presentation->topic,
            'slides' => $outline['slides'],
            'theme' => $theme,
            'themeKey' => $themeKey,
            'paletteKey' => $paletteKey,
            // Все гаммы и все темы разом: на экране переключение должно быть
            // мгновенным, без запроса к серверу и перерисовки
            'themeVars' => $this->cssVars(),
            // Шрифты тоже всех тем — иначе при смене темы текст на секунду
            // съезжает на системный, пока догружается нужная гарнитура
            'fontCss' => FontLoader::css($this->allFamilies()),
            'width' => config('deck.width'),
            'height' => config('deck.height'),
            // Файл стилей темы: разметка общая, характер разный
            'style' => $theme['style'],
            'styles' => $this->styleKeys(),
            // На экране слайды нужно уместить по ширине колонки,
            // в печати — оставить натуральный размер
            'forScreen' => $forScreen,
        ])->render();
    }

    /**
     * Ключи файлов стилей — по одному на тему.
     *
     * @return array<int, string>
     */
    private function styleKeys(): array
    {
        return collect(config('deck.themes'))
            ->pluck('style')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Все семейства шрифтов, встречающиеся в темах.
     *
     * @return array<int, string>
     */
    private function allFamilies(): array
    {
        return collect(config('deck.themes'))
            ->flatMap(fn (array $t) => [$t['font_display'] ?? null, $t['font_body'] ?? null])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * CSS-переменные: цвета по гаммам, шрифты по темам.
     * Вёрстка обращается к ним через var(), поэтому смена гаммы или
     * темы — это смена атрибута на корне документа, без перерисовки.
     */
    private function cssVars(): string
    {
        $blocks = [];

        foreach (config('deck.palettes') as $key => $palette) {
            $vars = [];

            foreach ($palette as $name => $value) {
                if (in_array($name, ['name', 'note'], true)) {
                    continue;
                }

                $vars[] = '--'.str_replace('_', '-', $name).':'.$value;
            }

            // Заголовок на обложечном фоне. У светлых гамм cover_accent
            // светлее самой подложки и читается около 1.5:1 — тогда берём
            // cover_ink. Считаем здесь, чтобы вёрстка не знала о гаммах.
            $vars[] = '--cover-title:'.$this->onCover(
                $palette['cover_bg'],
                $palette['cover_accent'],
                $palette['cover_ink'],
            );

            $blocks[] = sprintf('[data-palette="%s"]{%s}', $key, implode(';', $vars));
        }

        foreach (config('deck.themes') as $key => $theme) {
            $blocks[] = sprintf(
                '[data-theme="%s"]{--font-display:"%s";--font-body:"%s"}',
                $key,
                $theme['font_display'],
                $theme['font_body'],
            );
        }

        return implode("\n", $blocks);
    }

    /**
     * Печатает PDF и возвращает путь на диске.
     */
    public function pdf(Presentation $presentation, ?string $theme = null, ?string $palette = null): string
    {
        $disk = Storage::disk(config('deck.disk'));
        $relative = config('deck.path')."/{$presentation->id}-{$presentation->share_token}.pdf";

        // Browsershot умеет писать только в реальный путь, поэтому
        // печатаем во временный файл и уже его кладём на диск.
        // Расширение обязательно, иначе Chrome не поймёт формат — но
        // дописывать его к результату tempnam() нельзя: исходный файл
        // тогда останется в /tmp навсегда.
        $temp = tempnam(sys_get_temp_dir(), 'deck');
        $pdf = $temp.'.pdf';

        try {
            $this->browser($presentation, $theme, $palette)->savePdf($pdf);
            $disk->put($relative, file_get_contents($pdf));
        } catch (Throwable $e) {
            throw new RuntimeException('Не удалось напечатать PDF: '.$e->getMessage(), previous: $e);
        } finally {
            foreach ([$temp, $pdf] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        return $relative;
    }

    private function browser(Presentation $presentation, ?string $theme, ?string $palette = null): Browsershot
    {
        $shot = Browsershot::html($this->html($presentation, $theme, palette: $palette))
            ->showBackground()
            ->margins(0, 0, 0, 0)
            ->paperSize(config('deck.width'), config('deck.height'), 'mm')
            ->timeout(120);

        // На сервере Chrome обычно запускается от пользователя без прав
        if (! app()->environment('local')) {
            $shot->noSandbox();
        }

        if ($binary = config('deck.chrome_path')) {
            $shot->setChromePath($binary);
        }

        return $shot;
    }

    /**
     * Имя темы, гарантированно существующее в конфиге.
     */
    private function themeKey(?string $name): string
    {
        $themes = config('deck.themes');

        return isset($themes[$name]) ? $name : config('deck.default_theme');
    }

    /**
     * Читаемый цвет на подложке обложки: предпочитаем акцент, но если
     * контраст ниже 4.5:1 — отступаем к основному цвету текста.
     */
    private function onCover(string $bg, string $preferred, string $fallback): string
    {
        return $this->contrast($bg, $preferred) >= 4.5 ? $preferred : $fallback;
    }

    /**
     * Коэффициент контраста по WCAG 2.1.
     */
    private function contrast(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $c = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Имя гаммы, гарантированно существующее в конфиге.
     */
    private function paletteKey(?string $name): string
    {
        $palettes = config('deck.palettes');

        return isset($palettes[$name]) ? $name : config('deck.default_palette');
    }
}
