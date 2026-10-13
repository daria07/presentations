<?php

namespace App\Jobs;

use App\Jobs\Concerns\FinishesGeneration;
use App\Models\Presentation;
use App\Services\Deck\DeckRenderer;
use App\Services\Images\Illustrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Sleep;
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

    /** Сколько ждать обложку после остальных картинок, секунд */
    private const COVER_WAIT = 90;

    public function handle(Illustrator $illustrator, DeckRenderer $renderer): void
    {
        $presentation = $this->presentation->refresh();

        /*
           Обложку могла уже нарисовать IllustrateCover. Готова — сразу
           подставляем. Ещё рисуется — рисуем остальные без неё и потом
           ждём. Не вышла — Illustrator нарисует её вместе со всеми.
        */
        $coverPending = ! empty($presentation->cover_image['pending']);

        if (! $coverPending) {
            $this->mergeCover($presentation);
        }

        $illustrator->illustrate($presentation->refresh(), skipCover: $coverPending);

        if ($coverPending) {
            $this->waitForCover($presentation);
            $this->mergeCover($presentation->refresh(), giveUp: true);
        }

        $this->finish($presentation->refresh(), $renderer);
    }

    private function waitForCover(Presentation $presentation): void
    {
        $until = microtime(true) + self::COVER_WAIT;

        while (! empty($presentation->refresh()->cover_image['pending']) && microtime(true) < $until) {
            Sleep::for(3)->seconds();
        }
    }

    /**
     * Подставляет готовую обложку в слайд-обложку.
     *
     * @param  bool  $giveUp  обложки так и нет — оставляем обложку
     *                        однотонной, а не рисуем её второй раз
     */
    private function mergeCover(Presentation $presentation, bool $giveUp = false): void
    {
        $path = $presentation->cover_image['path'] ?? null;

        if ($path === null && ! $giveUp) {
            return;
        }

        $outline = $presentation->outline ?? [];

        foreach ($outline['slides'] ?? [] as $i => $slide) {
            if (($slide['image']['side'] ?? null) !== 'cover' || filled($slide['image']['path'] ?? null)) {
                continue;
            }

            if ($path !== null) {
                $outline['slides'][$i]['image']['path'] = $path;
            } else {
                $outline['slides'][$i]['image']['failed'] = true;
            }
        }

        $presentation->update(['outline' => $outline]);
    }

    public function failed(Throwable $e): void
    {
        $this->failGeneration($this->presentation, $this->spent, $e);
    }
}
