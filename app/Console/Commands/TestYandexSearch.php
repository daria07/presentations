<?php

namespace App\Console\Commands;

use App\Services\Search\YandexSearch;
use Illuminate\Console\Command;
use Throwable;

/**
 * Проверка ключа Яндекс Search API и того, что он находит:
 *
 *   php artisan search:yandex
 *   php artisan search:yandex "Пётр I реформы государственного управления"
 */
class TestYandexSearch extends Command
{
    protected $signature = 'search:yandex
                            {query? : Что искать}
                            {--limit=10 : Сколько результатов}';

    protected $description = 'Пробный поиск источников через Яндекс Search API';

    public function handle(YandexSearch $search): int
    {
        if (! YandexSearch::enabled()) {
            $this->error('В .env не заданы YANDEX_SEARCH_API_KEY и YANDEX_FOLDER_ID.');

            return self::FAILURE;
        }

        $query = $this->argument('query') ?: 'Пётр I реформы государственного управления';
        $this->line("Ищем: {$query}");

        $started = microtime(true);

        try {
            $results = $search->search($query, (int) $this->option('limit'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('Нашли %d за %.1f с', count($results), microtime(true) - $started));
        $this->newLine();

        foreach ($results as $i => $r) {
            $this->line(sprintf('<info>[%d] %s</info>', $i + 1, $r['title']));
            $this->line('    '.$r['url']);

            foreach ($r['passages'] as $passage) {
                $this->line('    · '.$passage);
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
