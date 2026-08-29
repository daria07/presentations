<?php

namespace App\Console\Commands;

use App\Models\Presentation;
use Illuminate\Console\Command;
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
                '       слайдов: %d · кредитов у автора: %d · пробная: %s',
                count($p->outline['slides'] ?? []),
                $p->user?->credits ?? 0,
                $p->user?->trial_used ? 'использована' : 'доступна',
            ));
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
