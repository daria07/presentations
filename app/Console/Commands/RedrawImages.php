<?php

namespace App\Console\Commands;

use App\Enums\PresentationStatus;
use App\Jobs\RenderPresentation;
use App\Models\Presentation;
use App\Services\Images\Illustrator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
 */
class RedrawImages extends Command
{
    protected $signature = 'images:redraw
                            {--id=* : Только эти презентации}
                            {--since= : Созданные не раньше этой даты}
                            {--all : И пробные тоже, не только оплаченные}
                            {--dry-run : Только показать список}
                            {--force : Не спрашивать подтверждение}';

    protected $description = 'Дорисовывает несостоявшиеся картинки оплаченных презентаций и перепечатывает PDF';

    public function handle(Illustrator $illustrator): int
    {
        if (! Illustrator::enabled()) {
            $this->error('Картинки выключены: не задана модель в services.images.model.');

            return self::FAILURE;
        }

        $targets = $this->targets();

        if ($targets->isEmpty()) {
            $this->components->info('Дорисовывать нечего.');

            return self::SUCCESS;
        }

        $images = $targets->sum(fn (Presentation $p) => self::missing($p));

        $this->table(
            ['id', 'почта', 'оплата', 'создана', 'картинок'],
            $targets->map(fn (Presentation $p) => [
                $p->id,
                $p->user?->email ?? '—',
                $p->paid_with ?? '—',
                $p->created_at?->format('d.m H:i'),
                self::missing($p),
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
            ->filter(fn (Presentation $p) => self::missing($p) > 0)
            ->values();
    }

    /** Сколько картинок в структуре помечены «не вышла» */
    private static function missing(Presentation $presentation): int
    {
        $count = 0;

        foreach ($presentation->outline['slides'] ?? [] as $slide) {
            if (is_array($slide) && ! empty($slide['image']['failed']) && blank($slide['image']['path'] ?? null)
                && filled($slide['image']['scene'] ?? null)) {
                $count++;
            }
        }

        return $count;
    }

    private function redraw(Presentation $presentation, Illustrator $illustrator): void
    {
        $this->line("№{$presentation->id}: рисуем…");

        // Снимаем отметку «не вышла» — тогда Illustrator возьмётся заново
        $outline = $presentation->outline;

        foreach ($outline['slides'] as $i => $slide) {
            if (is_array($slide) && ! empty($slide['image']['failed']) && blank($slide['image']['path'] ?? null)) {
                unset($outline['slides'][$i]['image']['failed']);
            }
        }

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

        $left = self::missing($presentation->refresh());
        $this->components->info("№{$presentation->id}: готово".($left ? ", не вышло ещё {$left}" : ''));
    }
}
