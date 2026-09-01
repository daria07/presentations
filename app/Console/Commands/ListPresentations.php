<?php

namespace App\Console\Commands;

use App\Models\Presentation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Что сейчас в базе — для быстрой диагностики:
 *   php artisan deck:list
 */
class ListPresentations extends Command
{
    protected $signature = 'deck:list {--limit=10}';

    protected $description = 'Показывает последние презентации и их состояние';

    public function handle(): int
    {
        $items = Presentation::with('user')
            ->latest('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($items->isEmpty()) {
            $this->components->warn('Презентаций нет.');

            return self::SUCCESS;
        }

        $disk = Storage::disk(config('deck.disk'));

        $this->newLine();

        // Задачи в очереди без работающего воркера — самая частая
        // причина «презентация висит и ничего не происходит»
        $waiting = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();

        if ($waiting > 0) {
            $this->components->warn(
                "В очереди ждёт задач: {$waiting}. Запущен ли php artisan queue:work?"
            );
        }

        if ($failed > 0) {
            $this->components->error(
                "Упавших задач: {$failed}. Подробности: php artisan queue:failed"
            );
        }

        foreach ($items as $p) {
            $fileState = match (true) {
                blank($p->file_path) => '<fg=gray>файла нет</>',
                $disk->exists($p->file_path) => '<fg=green>файл на месте</>',
                default => '<fg=red>файл потерян</>',
            };

            $this->line(sprintf(
                '  <fg=yellow>#%d</>  %-11s %-30s %s',
                $p->id,
                $p->status->value,
                str($p->title ?: $p->topic)->limit(28),
                $fileState,
            ));

            if (filled($p->error_message)) {
                $this->line("       <fg=red>{$p->error_message}</>");
            }

            $this->line(sprintf(
                '       <fg=gray>%s · %d слайдов · %s</>',
                $p->user?->email ?? 'автор удалён',
                count($p->outline['slides'] ?? []),
                $p->created_at?->diffForHumans() ?? '',
            ));
        }

        $this->newLine();

        // Баланс — свойство пользователя, а не презентации,
        // поэтому он один раз внизу, а не в каждой строке
        foreach ($items->pluck('user')->filter()->unique('id') as $user) {
            $this->components->twoColumnDetail(
                $user->email,
                $user->credits.' генераций'
                    .($user->trial_used ? '' : ' + пробная'),
            );
        }

        return self::SUCCESS;
    }
}
