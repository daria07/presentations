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
    public function html(Presentation $presentation, ?string $theme = null): string
    {
        $outline = $presentation->outline ?? [];

        if (empty($outline['slides'])) {
            throw new RuntimeException('У презентации нет слайдов — сначала нужно собрать структуру.');
        }

        $theme = $this->theme($theme);

        return View::make('deck.deck', [
            'title' => $outline['title'] ?? $presentation->topic,
            'slides' => $outline['slides'],
            'theme' => $theme,
            'fontCss' => FontLoader::css(array_unique([
                $theme['font_display'],
                $theme['font_body'],
            ])),
            'width' => config('deck.width'),
            'height' => config('deck.height'),
        ])->render();
    }

    /**
     * Печатает PDF и возвращает путь на диске.
     */
    public function pdf(Presentation $presentation, ?string $theme = null): string
    {
        $disk = Storage::disk(config('deck.disk'));
        $relative = config('deck.path')."/{$presentation->id}-{$presentation->share_token}.pdf";

        // Browsershot умеет писать только в реальный путь, поэтому
        // печатаем во временный файл и уже его кладём на диск.
        $temp = tempnam(sys_get_temp_dir(), 'deck').'.pdf';

        try {
            $this->browser($presentation, $theme)->savePdf($temp);
            $disk->put($relative, file_get_contents($temp));
        } catch (Throwable $e) {
            throw new RuntimeException('Не удалось напечатать PDF: '.$e->getMessage(), previous: $e);
        } finally {
            if (file_exists($temp)) {
                unlink($temp);
            }
        }

        return $relative;
    }

    private function browser(Presentation $presentation, ?string $theme): Browsershot
    {
        $shot = Browsershot::html($this->html($presentation, $theme))
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

    private function theme(?string $name): array
    {
        $themes = config('deck.themes');
        $name ??= config('deck.default_theme');

        return $themes[$name] ?? $themes[config('deck.default_theme')];
    }
}
