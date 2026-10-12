<?php

namespace App\Jobs;

use App\Jobs\Concerns\FinishesGeneration;
use App\Models\Presentation;
use App\Services\Deck\DeckRenderer;
use App\Services\Images\Illustrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Рисует картинки к готовой структуре и печатает PDF.
 *
 * Отдельная задача, а не продолжение GeneratePresentation: конспект
 * и структура вместе идут 2–3 минуты, и с картинками генерация
 * упиралась бы в лимит воркера в 300 секунд.
 *
 * Повторов нет: картинки, которые не получились, Illustrator
 * отмечает сам и дальше не трогает, а повтор целиком заплатил бы
 * за те, что уже нарисованы.
 */
class IllustratePresentation implements ShouldQueue
{
    use FinishesGeneration, Queueable;

    public int $tries = 1;

    public int $timeout = 280;

    public function __construct(
        public Presentation $presentation,
        public ?string $spent = null,
    ) {}

    public function handle(Illustrator $illustrator, DeckRenderer $renderer): void
    {
        $presentation = $this->presentation->refresh();

        $illustrator->illustrate($presentation);

        $this->finish($presentation->refresh(), $renderer);
    }

    public function failed(Throwable $e): void
    {
        $this->failGeneration($this->presentation, $this->spent, $e);
    }
}
