<?php

namespace App\Services\Deck;

use App\Models\Presentation;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Печатает речь докладчика: A4 с заметками к каждому слайду.
 *
 * Заметки модель пишет ещё при сборке структуры, так что новый запрос
 * к ней не нужен — мы только раскладываем готовый текст по листу и
 * считаем, сколько он занимает вслух.
 *
 * Файл не храним: он собирается за пару секунд, весит копейки и
 * устаревает при каждой правке структуры. Хранить его — значит завести
 * второй файл, который нужно чистить при удалении презентации и
 * перепечатывать при любой правке; выгоды на этом объёме никакой.
 */
class SpeechRenderer
{
    /** Спокойный темп чтения вслух */
    private const WORDS_PER_MINUTE = 130;

    public function html(Presentation $presentation): string
    {
        $outline = $presentation->outline ?? [];
        $slides = $outline['slides'] ?? [];

        if ($slides === []) {
            throw new RuntimeException('У презентации нет слайдов — речь собирать не из чего.');
        }

        $palette = config('deck.palettes')[$presentation->palette]
            ?? config('deck.palettes')[config('deck.default_palette')];

        $theme = config('deck.themes')[$presentation->theme]
            ?? config('deck.themes')[config('deck.default_theme')];

        $items = [];
        $words = 0;

        foreach ($slides as $i => $slide) {
            $notes = trim((string) ($slide['notes'] ?? ''));
            $count = $notes === '' ? 0 : count(preg_split('/\s+/u', $notes));
            $words += $count;

            $items[] = [
                'number' => $i + 1,
                'heading' => $slide['heading'] ?? $slide['title'] ?? 'Без заголовка',
                'notes' => $notes ?: null,
                // Меньше пяти секунд показывать бессмысленно: это шум
                'seconds' => $count ? max(5, (int) round($count / self::WORDS_PER_MINUTE * 60)) : null,
            ];
        }

        $minutes = max(1, (int) round($words / self::WORDS_PER_MINUTE));

        return View::make('deck.speech', [
            'title' => $outline['title'] ?? $presentation->topic,
            'items' => $items,
            'slideCount' => count($items),
            'slideWord' => $this->plural(count($items), 'слайд', 'слайда', 'слайдов'),
            'totalMinutes' => $minutes,
            'minuteWord' => $this->plural($minutes, 'минута', 'минуты', 'минут'),
            'accent' => $palette['accent_ink'],
            'fontDisplay' => $theme['font_display'],
            'fontBody' => $theme['font_body'],
            'fontCss' => FontLoader::css([$theme['font_display'], $theme['font_body']]),
        ])->render();
    }

    /** Возвращает содержимое PDF, не касаясь диска приложения */
    public function pdf(Presentation $presentation): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'speech');
        $pdf = $temp.'.pdf';

        try {
            $shot = Browsershot::html($this->html($presentation))
                ->showBackground()
                // Сверху чуть больше: там шапка с названием
                ->margins(22, 20, 20, 20)
                ->paperSize(210, 297, 'mm')
                ->timeout(60);

            if (! app()->environment('local')) {
                $shot->noSandbox();
            }

            if ($binary = config('deck.chrome_path')) {
                $shot->setChromePath($binary);
            }

            $shot->savePdf($pdf);

            return file_get_contents($pdf);
        } catch (Throwable $e) {
            throw new RuntimeException('Не удалось напечатать речь: '.$e->getMessage(), previous: $e);
        } finally {
            foreach ([$temp, $pdf] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /** Русские числительные: 1 слайд, 2 слайда, 5 слайдов */
    private function plural(int $n, string $one, string $few, string $many): string
    {
        $mod100 = $n % 100;
        $mod10 = $n % 10;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return $many;
        }

        if ($mod10 === 1) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return $few;
        }

        return $many;
    }
}
