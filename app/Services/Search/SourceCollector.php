<?php

namespace App\Services\Search;

use App\Models\ApiCall;
use App\Models\Presentation;
use App\Services\Claude\PresentationPlanner;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Источники для презентации по теме: запросы → Яндекс → тексты
 * лучших страниц.
 *
 * Ничего не роняет: если поиск не удался, возвращается пустой
 * список, и презентация собирается как раньше — по знаниям модели.
 */
class SourceCollector
{
    /** Сколько источников отдаём модели */
    public const MAX_SOURCES = 5;

    /** Сколько страниц пробуем скачать: часть не откроется или окажется пустой */
    private const CANDIDATES = 9;

    /**
     * Сайты, где нет статьи, которую можно прочитать: видео, соцсети,
     * магазины, ответы и ленты, которые отдают текст только скриптом.
     */
    private const BLOCKED = [
        'youtube.com', 'youtu.be', 'rutube.ru', 'vk.com', 'vkvideo.ru', 'ok.ru',
        't.me', 'instagram.com', 'facebook.com', 'tiktok.com', 'pinterest.com',
        'pinterest.ru', 'ozon.ru', 'wildberries.ru', 'avito.ru', 'market.yandex.ru',
        'aliexpress.ru', 'dzen.ru', 'zen.yandex.ru', 'otvet.mail.ru',
        'pikabu.ru', 'twitter.com', 'x.com',
    ];

    /** Файлы, а не страницы: их текст отсюда не достать */
    private const FILES = '/\.(pdf|docx?|pptx?|xlsx?|rtf|zip|rar)(\?|#|$)/i';

    public function __construct(
        private readonly PresentationPlanner $planner,
        private readonly YandexSearch $search,
        private readonly PageReader $reader,
    ) {}

    /**
     * @return list<array{id: int, url: string, title: string, domain: string, text: string}>
     */
    public function collect(Presentation $presentation): array
    {
        try {
            $queries = $this->planner->searchQueries($presentation);
        } catch (Throwable $e) {
            Log::warning('Источники: не составили запросы', ['presentation' => $presentation->id, 'error' => $e->getMessage()]);
            $queries = [mb_substr($presentation->topic, 0, 200)];
        }

        $lists = [];

        foreach ($queries as $query) {
            try {
                $lists[] = $this->search->search($query, 10);

                ApiCall::create([
                    'user_id' => $presentation->user_id,
                    'presentation_id' => $presentation->id,
                    'purpose' => 'search',
                    'model' => 'yandex-search',
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'cached_tokens' => 0,
                    'cost' => 0,
                ]);
            } catch (Throwable $e) {
                Log::warning('Источники: поиск не удался', ['presentation' => $presentation->id, 'query' => $query, 'error' => $e->getMessage()]);
            }
        }

        $candidates = array_slice($this->merge($lists), 0, self::CANDIDATES);

        if ($candidates === []) {
            return [];
        }

        try {
            $texts = $this->reader->readMany(array_column($candidates, 'url'), $queries);
        } catch (Throwable $e) {
            Log::warning('Источники: не скачали страницы', ['presentation' => $presentation->id, 'error' => $e->getMessage()]);

            return [];
        }

        $sources = [];

        foreach ($candidates as $candidate) {
            if (! isset($texts[$candidate['url']])) {
                continue;
            }

            $sources[] = [
                'id' => count($sources) + 1,
                'url' => $candidate['url'],
                'title' => $candidate['title'] !== '' ? $candidate['title'] : $candidate['domain'],
                'domain' => $candidate['domain'],
                'text' => $texts[$candidate['url']],
            ];

            if (count($sources) >= self::MAX_SOURCES) {
                break;
            }
        }

        Log::info('Источники собраны', [
            'presentation' => $presentation->id,
            'запросы' => $queries,
            'кандидатов' => count($candidates),
            'источников' => count($sources),
        ]);

        return $sources;
    }

    /**
     * Сводит выдачу нескольких запросов в один список: по очереди
     * берём первый результат каждого запроса, потом второй и так
     * далее. Один сайт — один источник.
     *
     * @param  list<list<array{url: string, domain: string, title: string, passages: list<string>}>>  $lists
     * @return list<array{url: string, domain: string, title: string}>
     */
    private function merge(array $lists): array
    {
        $merged = [];
        $seen = [];
        $depth = $lists === [] ? 0 : max(array_map('count', $lists));

        for ($i = 0; $i < $depth; $i++) {
            foreach ($lists as $list) {
                $item = $list[$i] ?? null;

                if ($item === null) {
                    continue;
                }

                $domain = self::domain($item['url']);

                if ($domain === '' || isset($seen[$domain]) || self::blocked($domain) || preg_match(self::FILES, $item['url'])) {
                    continue;
                }

                $seen[$domain] = true;
                $merged[] = [
                    'url' => $item['url'],
                    'domain' => $domain,
                    // Длинные заголовки Яндекс обрезает многоточием —
                    // на слайде оно выглядит как опечатка
                    'title' => trim((string) preg_replace('/\s*(\.\.\.|…)\s*$/u', '', $item['title'])),
                ];
            }
        }

        return $merged;
    }

    public static function domain(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return (string) preg_replace('/^www\./', '', $host);
    }

    private static function blocked(string $domain): bool
    {
        foreach (self::BLOCKED as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }
}
