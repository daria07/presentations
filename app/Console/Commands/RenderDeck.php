<?php

namespace App\Console\Commands;

use App\Models\Presentation;
use App\Services\Deck\DeckRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Печатает PDF по уже собранной структуре:
 *   php artisan deck:render 12
 *   php artisan deck:render 12 --theme=bold --palette=pine --html
 */
class RenderDeck extends Command
{
    protected $signature = 'deck:render
                            {id? : ID презентации, по умолчанию последняя}
                            {--theme= : precise | bold | soft}
                            {--palette= : graphite | coal | pine | bordeaux | sand | ocean | plum}
                            {--html : Сохранить ещё и HTML, чтобы посмотреть вёрстку в браузере}
                            {--open : Открыть готовый файл}';

    protected $description = 'Собирает PDF из структуры слайдов';

    public function handle(DeckRenderer $renderer): int
    {
        $presentation = $this->argument('id')
            ? Presentation::find($this->argument('id'))
            : Presentation::whereNotNull('outline')->latest('id')->first();

        if (! $presentation) {
            $this->error('Презентация не найдена. Сначала: php artisan claude:test "тема" --keep');

            return self::FAILURE;
        }

        if (blank($presentation->outline)) {
            $this->error("У презентации #{$presentation->id} нет структуры.");

            return self::FAILURE;
        }

        $theme = $this->option('theme');
        $palette = $this->option('palette');

        try {
            if ($this->option('html')) {
                $htmlPath = storage_path("app/deck-{$presentation->id}.html");
                file_put_contents($htmlPath, $renderer->html($presentation, $theme, palette: $palette));
                $this->components->twoColumnDetail('HTML', $htmlPath);
            }

            $path = null;

            $this->components->task('Печатаем PDF', function () use ($renderer, $presentation, $theme, $palette, &$path) {
                $path = $renderer->pdf($presentation, $theme, $palette);

                return true;
            });
        } catch (Throwable $e) {
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $presentation->update([
            'file_path' => $path,
            'file_format' => 'pdf',
            'status' => \App\Enums\PresentationStatus::Ready,
            'generated_at' => now(),
        ]);

        $full = Storage::disk(config('deck.disk'))->path($path);

        $this->newLine();
        $this->components->twoColumnDetail('Презентация', $presentation->outline['title'] ?? '—');
        $this->components->twoColumnDetail('Слайдов', (string) count($presentation->outline['slides']));
        $this->components->twoColumnDetail('Тема', $theme ?: ($presentation->theme ?? config('deck.default_theme')));
        $this->components->twoColumnDetail('Гамма', $palette ?: ($presentation->palette ?? config('deck.default_palette')));
        $this->components->twoColumnDetail('Файл', $full);
        $this->components->twoColumnDetail('Размер', number_format(filesize($full) / 1024, 0).' КБ');

        if ($this->option('open')) {
            exec('open '.escapeshellarg($full));
        }

        return self::SUCCESS;
    }
}
