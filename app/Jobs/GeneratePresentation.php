<?php

namespace App\Jobs;

use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Services\Claude\ClaudeException;
use App\Services\Claude\PresentationPlanner;
use App\Services\Deck\DeckRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Собирает структуру слайдов и печатает PDF.
 *
 * Кредит списывается в контроллере, до постановки в очередь: если
 * списывать здесь, человек успеет нажать кнопку несколько раз.
 * Здесь — только возврат, когда упали по своей вине.
 */
class GeneratePresentation implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public Presentation $presentation,
        public ?string $theme = null,
    ) {}

    public function handle(PresentationPlanner $planner, DeckRenderer $renderer): void
    {
        $this->presentation->update(['status' => PresentationStatus::Generating]);

        $outline = $planner->buildOutline($this->presentation);

        $this->presentation->update([
            'outline' => $outline,
            'title' => $outline['title'] ?? null,
        ]);

        $path = $renderer->pdf($this->presentation, $this->theme);

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
        Log::error('Генерация упала', [
            'presentation' => $this->presentation->id,
            'error' => $e->getMessage(),
        ]);

        // Человек не виноват, что у нас не получилось — возвращаем кредит
        $this->presentation->user->refundCredit();

        $this->presentation->markFailed(match (true) {
            $e instanceof ClaudeException => $e->forUser(),
            config('app.debug') => class_basename($e).': '.$e->getMessage(),
            default => 'Не получилось собрать презентацию. Кредит вернули на счёт.',
        });
    }
}
