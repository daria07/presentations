<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Показывает, какие модели доступны под текущим ключом:
 *   php artisan claude:models
 *   php artisan claude:models --filter=sonnet
 */
class ListModels extends Command
{
    protected $signature = 'claude:models {--filter= : Показать только совпадающие по подстроке}';

    protected $description = 'Список моделей, доступных под ключом';

    public function handle(): int
    {
        $config = config('services.anthropic');

        if (blank($config['key'])) {
            $this->error('В .env не задан ANTHROPIC_API_KEY.');

            return self::FAILURE;
        }

        $response = Http::withToken($config['key'])
            ->timeout(30)
            ->get($config['base_url'].'/v1/models');

        if ($response->failed()) {
            $this->error("Шлюз вернул {$response->status()}: ".$response->body());

            return self::FAILURE;
        }

        $models = collect($response->json('data') ?? $response->json('models') ?? [])
            ->map(fn ($m) => is_array($m) ? ($m['id'] ?? $m['name'] ?? null) : $m)
            ->filter();

        if ($filter = $this->option('filter')) {
            $models = $models->filter(fn ($id) => str_contains(strtolower($id), strtolower($filter)));
        }

        if ($models->isEmpty()) {
            $this->components->warn('Ничего не нашлось.');

            return self::SUCCESS;
        }

        $this->newLine();
        $current = $config['model'];

        foreach ($models->sort() as $id) {
            $mark = $id === $current ? '<fg=green>●</>' : ' ';
            $this->line("  {$mark} {$id}");
        }

        $this->newLine();
        $this->components->twoColumnDetail('Всего', (string) $models->count());
        $this->components->twoColumnDetail('Сейчас в .env', $current);

        return self::SUCCESS;
    }
}
