<?php

namespace App\Console\Commands;

use App\Models\Presentation;
use Illuminate\Console\Command;

/**
 * Что реально лежит в базе у презентации:
 *   php artisan deck:show 16
 */
class ShowOutline extends Command
{
    protected $signature = 'deck:show {id? : ID презентации, по умолчанию последняя}';

    protected $description = 'Показывает сохранённую структуру слайдов';

    public function handle(): int
    {
        $presentation = $this->argument('id')
            ? Presentation::find($this->argument('id'))
            : Presentation::whereNotNull('outline')->latest('id')->first();

        if (! $presentation) {
            $this->error('Презентация не найдена.');

            return self::FAILURE;
        }

        $outline = $presentation->outline ?? [];

        $this->newLine();
        $this->components->twoColumnDetail('ID', (string) $presentation->id);
        $this->components->twoColumnDetail('Колонка title', $presentation->title ?? '—');
        $this->components->twoColumnDetail('Название в структуре', $outline['title'] ?? '—');
        $this->components->twoColumnDetail('Тема оформления', $presentation->theme ?? '—');
        $this->components->twoColumnDetail(
            'Обновлено',
            $presentation->updated_at?->diffForHumans() ?? '—',
        );
        $this->components->twoColumnDetail(
            'Файл напечатан',
            $presentation->generated_at?->diffForHumans() ?? '—',
        );

        $this->newLine();

        foreach ($outline['slides'] ?? [] as $i => $slide) {
            $this->line(sprintf(
                '  <fg=yellow>%02d</> <options=bold>%s</> <fg=gray>[%s]</>',
                $i + 1,
                $slide['heading'] ?? '—',
                $slide['layout'] ?? '?',
            ));
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
