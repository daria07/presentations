<?php

namespace App\Console\Commands;

use App\Enums\PresentationStatus;
use App\Jobs\RenderPresentation;
use App\Models\Presentation;
use App\Services\Deck\Illustrations;
use App\Services\Images\Illustrator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Дорисовывает картинки, которые не вышли, и перепечатывает PDF.
 *
 * Шлюз время от времени обрывает ответ с готовой картинкой, и до
 * исправления такие картинки сразу помечались «не вышла». Здесь
 * отметку снимаем, рисуем заново и перепечатываем файл.
 *
 * По умолчанию — только оплаченные презентации (за купленную
 * генерацию или открытые потом): каждая картинка — платный запрос.
 *
 *   php artisan images:redraw --dry-run       что будет сделано
 *   php artisan images:redraw                 сделать (спросит)
 *   php artisan images:redraw --id=351        одну презентацию
 *   php artisan images:redraw --since=2026-10-01
 *   php artisan images:redraw --id=351 --fresh   перерисовать ВСЕ картинки,
 *                                                 и удачные тоже (например,
 *                                                 с буквами на картинке)
 */
class RedrawImages extends Command
{
    protected $signature = 'images:redraw
                            {--id=* : Только эти презентации}
                            {--since= : Созданные не раньше этой даты}
                            {--all : И пробные тоже, не только оплаченные}
                            {--fresh : Перерисовать все картинки заново, а не только несостоявшиеся (только с --id)}
                            {--dry-run : Только показать список}
                            {--force : Не спрашивать подтверждение}';

    protected $description = 'Дорисовывает несостоявшиеся картинки оплаченных презентаций и перепечатывает PDF';

    public function handle(Illustrator $illustrator): int
    {
        if (! Illustrator::enabled()) {
            $this->error('Картинки выключены: не задана модель в services.images.model.');

            return self::FAILURE;
        }

        // Перерисовка всего платная и необратимая — только поимённо
        if ($this->option('fresh') && ! $this->option('id')) {
            $this->error('--fresh работает только вместе с --id: перерисовать все картинки у всех — слишком дорого.');

            return self::FAILURE;
        }

        $targets = $this->targets();

        if ($targets->isEmpty()) {
            $this->components->info('Дорисовывать нечего.');

            return self::SUCCESS;
        }

        $images = $targets->sum(fn (Presentation $p) => $this->missing($p));

        $this->table(
            ['id', 'почта', 'оплата', 'создана', 'картинок'],
            $targets->map(fn (Presentation $p) => [
                $p->id,
                $p->user->email ?? '—',
                $p->paid_with ?? '—',
                $p->created_at?->format('d.m H:i'),
                $this->missing($p),
            ])->all(),
        );

        $this->line("Презентаций: {$targets->count()}, картинок: {$images}.");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Рисуем и перепечатываем?')) {
            $this->components->warn('Отменено.');

            return self::SUCCESS;
        }

        foreach ($targets as $presentation) {
            $this->redraw($presentation, $illustrator);
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, Presentation> */
    private function targets(): Collection
    {
        return Presentation::query()
            ->with('user:id,email')
            ->where('status', PresentationStatus::Ready)
            ->when($this->option('id'), fn (Builder $q, array $ids) => $q->whereIn('id', $ids))
            ->when($this->option('since'), fn (Builder $q, string $since) => $q->where('created_at', '>=', $since))
            ->when(! $this->option('all'), fn (Builder $q) => $q->whereIn('paid_with', ['credit', 'unlocked']))
            ->orderBy('id')
            ->get()
            ->filter(fn (Presentation $p) => $this->missing($p) > 0)
            ->values();
    }

    /**
     * Сколько картинок дорисовать: помеченные «не вышла» плюс те,
     * которых не хватает до нормы (Illustrations::topUp)
     */
    private function missing(Presentation $presentation): int
    {
        // Заново — все назначенные картинки, удачные тоже
        if ($this->option('fresh')) {
            return count(array_filter(
                $presentation->outline['slides'] ?? [],
                fn ($s) => is_array($s) && filled($s['image']['scene'] ?? null),
            ));
        }

        $slides = $presentation->outline['slides'] ?? [];
        $count = self::planned(self::withTopUp($presentation, $slides)) - self::planned($slides);

        foreach ($presentation->outline['slides'] ?? [] as $slide) {
            if (is_array($slide) && ! empty($slide['image']['failed']) && blank($slide['image']['path'] ?? null)
                && filled($slide['image']['scene'] ?? null)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Добор до нормы — только у презентаций, сделанных уже с картинками.
     * Старые, без единой картинки, не трогаем: это была бы не починка,
     * а новые платные картинки к презентациям, где их не обещали.
     *
     * @param  array<int, mixed>  $slides
     * @return array<int, mixed>
     */
    private static function withTopUp(Presentation $presentation, array $slides): array
    {
        return self::planned($slides) > 0
            ? Illustrations::topUp($slides, $presentation->slide_count)
            : $slides;
    }

    /**
     * Сколько картинок вообще назначено в структуре
     *
     * @param  array<int, mixed>  $slides
     */
    private static function planned(array $slides): int
    {
        return count(array_filter($slides, fn ($s) => is_array($s) && isset($s['image'])));
    }

    private function redraw(Presentation $presentation, Illustrator $illustrator): void
    {
        $this->line("№{$presentation->id}: рисуем…");

        // Снимаем отметку «не вышла» — тогда Illustrator возьмётся заново
        $outline = $presentation->outline;

        $disk = Storage::disk(config('deck.disk'));

        foreach ($outline['slides'] as $i => $slide) {
            if (! is_array($slide) || ! isset($slide['image'])) {
                continue;
            }

            if ($this->option('fresh')) {
                // Старый файл больше не нужен: новый ляжет под другим именем
                $old = $slide['image']['path'] ?? null;

                if (is_string($old) && $old !== '') {
                    $disk->delete($old);
                }

                unset($outline['slides'][$i]['image']['path'], $outline['slides'][$i]['image']['failed']);

                continue;
            }

            if (! empty($slide['image']['failed']) && blank($slide['image']['path'] ?? null)) {
                unset($outline['slides'][$i]['image']['failed']);
            }
        }

        $outline['slides'] = self::withTopUp($presentation, $outline['slides']);
        $presentation->update(['outline' => $outline]);

        try {
            $illustrator->illustrate($presentation->refresh());
            RenderPresentation::dispatchSync($presentation->refresh());
        } catch (Throwable $e) {
            $this->components->error("№{$presentation->id}: {$e->getMessage()}");

            // Файл остался прежним — возвращаем статус, чтобы презентация
            // не застряла в «генерируется»
            $presentation->update(['status' => PresentationStatus::Ready]);

            return;
        }

        // Не вышли и в этот раз — помечены failed без файла
        $left = count(array_filter(
            $presentation->refresh()->outline['slides'] ?? [],
            fn ($s) => is_array($s) && ! empty($s['image']['failed']) && blank($s['image']['path'] ?? null),
        ));
        $this->components->info("№{$presentation->id}: готово".($left ? ", не вышло ещё {$left}" : ''));
    }
}
