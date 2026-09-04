<?php

namespace App\Jobs;

use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Notifications\PresentationReady;
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
        // Между постановкой в очередь и выполнением могло пройти время,
        // поэтому работаем с актуальным состоянием, а не с тем,
        // что было сериализовано вместе с задачей.
        $presentation = $this->presentation->refresh();

        $presentation->update([
            'status' => PresentationStatus::Generating,
            'theme' => $this->theme ?? $presentation->theme ?? config('deck.default_theme'),
        ]);

        // Структура могла быть куплена на прошлой попытке — если тогда
        // не напечатался файл, платить за неё второй раз незачем.
        if (! $this->hasSlides($presentation)) {
            try {
                $outline = $planner->buildOutline($presentation);
            } catch (ClaudeException $e) {
                $this->stopUnlessRetryable($e);

                return;
            }

            $presentation->update([
                'outline' => $outline,
                'title' => $outline['title'] ?? null,
            ]);

            $presentation->refresh();
        }

        $path = $renderer->pdf($presentation, $presentation->theme);

        $presentation->update([
            'file_path' => $path,
            'file_format' => 'pdf',
            'status' => PresentationStatus::Ready,
            'generated_at' => now(),
            'error_message' => null,
        ]);

        // Письмо уходит отдельной задачей: сбой почты не должен
        // отменять успешную генерацию.
        $presentation->user->notify(new PresentationReady($presentation->refresh()));
    }

    /** Есть ли в структуре хотя бы один пригодный слайд */
    private function hasSlides(Presentation $presentation): bool
    {
        $slides = $presentation->outline['slides'] ?? null;

        return is_array($slides) && $slides !== [];
    }

    /**
     * Повторять есть смысл только временные сбои. Кривой запрос или
     * пустой баланс от повтора не починятся, а расходы удвоят.
     */
    protected function stopUnlessRetryable(ClaudeException $e): void
    {
        if ($e->isRetryable()) {
            throw $e;
        }

        $this->fail($e);
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
