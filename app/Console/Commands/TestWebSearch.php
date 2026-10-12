<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Проверяет, пропускает ли шлюз встроенный поиск Claude (web_search).
 *
 *   php artisan claude:search-test
 *   php artisan claude:search-test "история Диснейленда"
 *   php artisan claude:search-test --model=claude-opus-5-5
 *
 * Один запрос к модели с инструментом web_search: если шлюз его
 * поддерживает, в ответе будут блоки с результатами поиска и
 * ссылки-цитаты в тексте. Если нет — шлюз вернёт ошибку, либо модель
 * ответит по памяти без единого поиска. Команда различает все три
 * случая и пишет, какой вышел.
 *
 * Стоит денег: до трёх поисков ($10 за 1000 у Anthropic, у шлюза
 * может быть своя цена) плюс токены найденных страниц.
 */
class TestWebSearch extends Command
{
    protected $signature = 'claude:search-test
                            {topic? : Тема для поиска}
                            {--model= : Другая модель вместо LLM_MODEL}
                            {--tool=web_search_20250305 : Версия инструмента поиска}';

    protected $description = 'Проверяет, работает ли встроенный поиск Claude через шлюз';

    public function handle(): int
    {
        $config = config('services.anthropic');

        if (blank($config['key'])) {
            $this->error('В .env не задан LLM_API_KEY.');

            return self::FAILURE;
        }

        $topic = $this->argument('topic') ?: 'Диснейленды мира: где находятся и когда открылись';
        $model = $this->option('model') ?: $config['model'];

        $this->components->twoColumnDetail('Шлюз', $config['base_url']);
        $this->components->twoColumnDetail('Модель', $model);
        $this->components->twoColumnDetail('Инструмент', $this->option('tool'));
        $this->newLine();

        $payload = [
            'model' => $model,
            'max_tokens' => 2000,
            'tools' => [[
                'type' => $this->option('tool'),
                'name' => 'web_search',
                'max_uses' => 3,
            ]],
            'messages' => [[
                'role' => 'user',
                'content' => "Найди в интернете 3–5 надёжных источников на русском языке по теме «{$topic}» "
                    .'и кратко перечисли 3 ключевых факта, у каждого — ссылка на источник. '
                    .'Обязательно воспользуйся поиском, не отвечай по памяти.',
            ]],
        ];

        $started = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $config['key'],
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout(180)
                ->connectTimeout(20)
                ->post($config['base_url'].'/v1/messages', $payload);
        } catch (Throwable $e) {
            $this->error('Запрос не ушёл: '.$e->getMessage());

            return self::FAILURE;
        }

        $seconds = round(microtime(true) - $started, 1);

        if ($response->failed()) {
            $this->components->error("Шлюз вернул ошибку {$response->status()} за {$seconds} с");
            $this->line($response->body());
            $this->newLine();
            $this->components->warn('Скорее всего, шлюз не поддерживает web_search. Если в его документации указана другая версия инструмента, передайте её через --tool=');

            return self::FAILURE;
        }

        $body = $response->json() ?? [];
        $content = $response->collect('content');

        // Что искала модель
        $queries = $content
            ->where('type', 'server_tool_use')
            ->map(fn ($b) => $b['input']['query'] ?? '?');

        // Что нашёл поиск
        $results = $content
            ->where('type', 'web_search_tool_result')
            ->map(fn ($b) => is_array($b['content'] ?? null) && array_is_list($b['content']) ? $b['content'] : [])
            ->collapse()
            ->filter(fn ($r) => isset($r['url']));

        // Ошибки поиска приходят не HTTP-кодом, а блоком в ответе
        $searchErrors = $content
            ->where('type', 'web_search_tool_result')
            ->map(fn ($b) => $b['content']['error_code'] ?? null)
            ->filter();

        // Ссылки, которые модель поставила в текст
        $citations = $content
            ->where('type', 'text')
            ->map(fn ($b) => $b['citations'] ?? [])
            ->collapse()
            ->filter(fn ($c) => isset($c['url']))
            ->unique('url');

        $text = $content->where('type', 'text')->pluck('text')->implode('');

        $this->components->twoColumnDetail('Ответила модель', $body['model'] ?? '—');
        $this->components->twoColumnDetail('stop_reason', $body['stop_reason'] ?? '—');
        $this->components->twoColumnDetail('Время', "{$seconds} с");
        $this->components->twoColumnDetail('Поисков (по usage)', (string) ($body['usage']['server_tool_use']['web_search_requests'] ?? 0));
        $this->components->twoColumnDetail('Токенов на вход / выход', ($body['usage']['input_tokens'] ?? '?').' / '.($body['usage']['output_tokens'] ?? '?'));
        $this->newLine();

        if ($queries->isNotEmpty()) {
            $this->line('<options=bold>Запросы модели в поиск:</>');
            $queries->each(fn ($q) => $this->line("  · {$q}"));
            $this->newLine();
        }

        if ($results->isNotEmpty()) {
            $this->line('<options=bold>Найдено страниц: '.$results->count().'</>');
            $results->take(8)->each(fn ($r) => $this->line('  · '.($r['title'] ?? '').' — '.$r['url']));
            $this->newLine();
        }

        if ($citations->isNotEmpty()) {
            $this->line('<options=bold>Ссылки в ответе (citations):</>');
            $citations->each(fn ($c) => $this->line('  · '.($c['title'] ?? '').' — '.$c['url']));
            $this->newLine();
        }

        $this->line('<options=bold>Текст ответа:</>');
        $this->line($text ?: '—');
        $this->newLine();

        // Ссылки, которые модель написала прямо в тексте. Проверяем,
        // что они открываются: выдуманная ссылка обычно даёт 404
        $links = $this->checkLinks($text);

        // Вердикт
        if ($searchErrors->isNotEmpty()) {
            $this->components->error('Поиск вызван, но вернул ошибку: '.$searchErrors->implode(', '));

            return self::FAILURE;
        }

        if ($results->isNotEmpty() && $citations->isNotEmpty()) {
            $this->components->info('Поиск работает: модель искала, нашла страницы и поставила ссылки.');

            return self::SUCCESS;
        }

        if ($results->isNotEmpty()) {
            $this->components->warn('Поиск работает, но модель не поставила ссылки-цитаты в текст. Для презентаций это решается подсказкой.');

            return self::SUCCESS;
        }

        /*
           Блоков поиска нет, но вход раздут: наш запрос — пара сотен
           токенов, а пришло на порядки больше. Так бывает, когда шлюз
           ищет сам и подкладывает найденное в запрос текстом, а ответ
           отдаёт без служебных блоков. Тогда судим по ссылкам: если
           они открываются, поиск был.
        */
        $inflated = ($body['usage']['input_tokens'] ?? 0) > 2000;
        $alive = collect($links)->filter()->count();

        if ($inflated && $links !== [] && $alive === count($links)) {
            $this->components->warn(
                'Похоже, шлюз ищет сам и подкладывает результаты в запрос: служебных блоков поиска нет, '
                .'но вход раздут и все ссылки живые. Работает, но поиском управляет шлюз, а не мы.'
            );

            return self::SUCCESS;
        }

        if ($inflated && $alive > 0) {
            $this->components->warn(
                "Шлюз, похоже, ищет сам, но открылось только {$alive} из ".count($links).' ссылок — часть, видимо, выдумана.'
            );

            return self::FAILURE;
        }

        $this->components->error('Поиска не было: шлюз, похоже, молча выбросил инструмент, и модель ответила по памяти. Ссылкам в таком ответе верить нельзя.');

        return self::FAILURE;
    }

    /**
     * Открывает каждую ссылку из текста и печатает, что вернул сайт.
     *
     * @return array<string, bool> адрес => открылась ли
     */
    private function checkLinks(string $text): array
    {
        preg_match_all('~https?://[^\s)\]>"\'«»]+~u', $text, $matches);

        $urls = array_values(array_unique(array_map(
            fn (string $url) => rtrim($url, '.,;:'),
            $matches[0],
        )));

        if ($urls === []) {
            return [];
        }

        $this->line('<options=bold>Проверка ссылок из текста:</>');

        $checked = [];

        foreach ($urls as $url) {
            try {
                $status = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (link check)'])
                    ->timeout(15)
                    ->get($url)
                    ->status();
            } catch (Throwable) {
                $status = 0;
            }

            // 403 у части сайтов — защита от роботов, а не отсутствие
            // страницы. Считаем живой: человек в браузере её откроет
            $ok = ($status >= 200 && $status < 400) || $status === 403;
            $checked[$url] = $ok;

            $mark = $ok ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$mark} ".($status ?: 'нет ответа')." {$url}");
        }

        $this->newLine();

        return $checked;
    }
}
