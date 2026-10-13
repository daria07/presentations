<?php

namespace App\Jobs;

use App\Enums\PresentationStatus;
use App\Jobs\Concerns\FinishesGeneration;
use App\Models\Presentation;
use App\Services\Claude\ClaudeException;
use App\Services\Claude\PresentationPlanner;
use App\Services\Deck\DeckRenderer;
use App\Services\Images\Illustrator;
use App\Services\Search\SourceCollector;
use App\Services\Search\YandexSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    use FinishesGeneration, Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @param  string|null  $spent  чем заплатили за эту генерацию:
     *                              'trial', 'credit' или null, если
     *                              не списывали (перезапуск застрявшей)
     */
    public function __construct(
        public Presentation $presentation,
        public ?string $theme = null,
        public ?string $palette = null,
        public ?string $spent = null,
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
            'palette' => $this->palette ?? $presentation->palette ?? config('deck.default_palette'),
        ]);

        // Структура могла быть куплена на прошлой попытке — если тогда
        // не напечатался файл, платить за неё второй раз незачем.
        if (! $this->hasSlides($presentation)) {
            try {
                /*
                   Сначала конспект, потом слайды. Конспект, купленный
                   на прошлой попытке, переиспользуем: он не зависит ни
                   от темы оформления, ни от того, почему сорвалась
                   печать.
                */
                /*
                   Для презентации по теме сначала ищем источники: по ним
                   пишется конспект, и они же встают последним слайдом.
                   Пустой список тоже сохраняем — значит, искали и не
                   нашли, и на повторной попытке искать заново незачем.
                */
                if ($presentation->sources === null
                    && blank($presentation->brief)
                    && ! $presentation->hasSourceText()
                    && YandexSearch::enabled()) {
                    // Сборщик берём только здесь: без поиска он не нужен
                    $presentation->update(['sources' => app(SourceCollector::class)->collect($presentation)]);
                    $presentation->refresh();
                }

                if (blank($presentation->brief)) {
                    $presentation->update([
                        'brief' => $planner->buildBrief($presentation),
                    ]);

                    /*
                       Структуру собирает следующая задача. Поиск, конспект
                       и структура вместе идут до пяти минут — ровно лимит
                       воркера, и подробная презентация в него не влезала.
                       Новая задача начинает отсчёт заново, а конспект уже
                       в базе: повторно за него не платим.
                    */
                    // Обложку рисуем сразу, параллельно со структурой: ей
                    // хватает темы из конспекта. Ставим первой — если воркер
                    // один, она хотя бы не будет ждать конца структуры
                    $presentation->refresh();

                    if (Illustrator::enabled() && filled($presentation->brief['cover_scene'] ?? null)) {
                        $presentation->update(['cover_image' => ['pending' => true]]);
                        IllustrateCover::dispatch($presentation);
                    }

                    self::dispatch($presentation, $this->theme, $this->palette, $this->spent);

                    return;
                }

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

        // Картинки рисует отдельная задача — она же и допечатает PDF.
        // Здесь на них уже не хватило бы времени воркера.
        if (Illustrator::enabled() && Illustrator::pending($presentation->outline)) {
            IllustratePresentation::dispatch($presentation, $this->spent);

            return;
        }

        $this->finish($presentation, $renderer);
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
        $this->failGeneration($this->presentation, $this->spent, $e);
    }
}
