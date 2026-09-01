<?php

namespace App\Jobs;

use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Services\Deck\DeckRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Перепечатывает PDF из уже готовой структуры.
 *
 * К модели не обращается и генерацию не списывает: смена оформления
 * для человека бесплатна, потому что нам она ничего не стоит.
 */
class RenderPresentation implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public Presentation $presentation) {}

    public function handle(DeckRenderer $renderer): void
    {
        $this->presentation->update(['status' => PresentationStatus::Generating]);

        $path = $renderer->pdf($this->presentation, $this->presentation->theme);

        $this->presentation->update([
            'file_path' => $path,
            'file_format' => 'pdf',
            'status' => PresentationStatus::Ready,
            'generated_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Перепечатка упала', [
            'presentation' => $this->presentation->id,
            'error' => $e->getMessage(),
        ]);

        // Структура цела, поэтому возвращаем в готовое состояние
        // со старым файлом — терять презентацию из-за смены темы глупо.
        $this->presentation->update([
            'status' => PresentationStatus::Ready,
            'error_message' => null,
        ]);
    }
}
