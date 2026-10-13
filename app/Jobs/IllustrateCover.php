<?php

namespace App\Jobs;

use App\Models\Presentation;
use App\Services\Images\Illustrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Рисует обложку, пока собирается структура слайдов.
 *
 * Обложка — самая долгая картинка (горизонталь 1536×1024), а нужна
 * ей только тема из конспекта. Поэтому она стартует сразу после
 * конспекта, параллельно со структурой. Результат кладём в
 * cover_image, а IllustratePresentation подставит его в обложку.
 *
 * Ничего не роняет: не вышло — {failed: true}, и обложку нарисуют
 * вместе с остальными картинками или оставят однотонной.
 */
class IllustrateCover implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 280;

    public function __construct(public Presentation $presentation) {}

    public function handle(Illustrator $illustrator): void
    {
        $presentation = $this->presentation->refresh();

        try {
            $path = $illustrator->drawCover($presentation);
        } catch (Throwable $e) {
            Log::warning('Обложка: не нарисовали', ['presentation' => $presentation->id, 'error' => $e->getMessage()]);
            $path = null;
        }

        // Пишем только своё поле: структуру в это время пишет другая задача
        $presentation->update(['cover_image' => $path !== null ? ['path' => $path] : ['failed' => true]]);
    }

    public function failed(Throwable $e): void
    {
        $this->presentation->update(['cover_image' => ['failed' => true]]);
    }
}
