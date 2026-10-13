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
        // Пиратские книги и студенческие файлообменники: ссылка на них
        // в источниках подставляет человека, а текст там сомнительный
        'obuchalka.org', 'studfile.net', 'studfiles.net', 'studopedia.ru', 'studopedia.org',
        'studwood.net', 'studbooks.net', 'twirpx.com', 'twirpx.org', 'libgen.is', 'litmir.me',
        'bookfi.net', 'kniga.lib', 'vuzlit.com', 'bibliofond.ru', 'allbest.ru',
    ];

    /**
     * Сайты, которые поднимаем выше под уровень аудитории: школьные —
     * по программе, вузовские — научные статьи, ЭБС и клинические
     * рекомендации.
     */
    private const PREFERRED = [
        'school' => [
            'resh.edu.ru', 'prosv.ru', 'interneturok.ru', 'uchi.ru', 'foxford.ru',
            'skysmart.ru', 'yaklass.ru', 'infourok.ru', '100points.ru',
        ],
        'university' => [
            'cyberleninka.ru', 'studentlibrary.ru', 'rosmedlib.ru', 'elibrary.ru',
            'cr.minzdrav.gov.ru', 'rmj.ru', 'urait.ru', 'znanium.ru', 'msdmanuals.com',
            'ru.wikipedia.org',
        ],
    ];

    /**
     * Где искать страницу учебника — по убыванию: электронные библиотеки
     * и издательства (точные выходные данные, студенту туда и нужно),
     * потом книжные магазины (данные тоже точные, но это витрина).
     */
    private const BOOK_SITES = [
        'studentlibrary.ru', 'rosmedlib.ru', 'urait.ru', 'znanium.ru', 'e.lanbook.com',
        'iprbookshop.ru', 'book.ru', 'rusneb.ru', 'elibrary.ru', 'geotar.ru', 'medbook.ru',
        'prosv.ru', 'labirint.ru', 'chitai-gorod.ru', 'bookvoed.ru', 'my-shop.ru',
    ];

    /** Клиники и сайты «о болезнях для всех», которые не узнать по слову в адресе */
    private const CLINIC_BLOGS = [
        'mc21.ru', 'polyclinika.ru', 'probolezny.ru', 'fdoctor.ru', 'vitaclinica.ru',
        'clinicnacpp.ru', 'klinikabudzdorov.ru', 'smclinic.ru', 'medsi.ru', 'invitro.ru',
        'gemotest.ru', 'sberhealth.ru', 'docdoc.ru', 'prodoctorov.ru', 'napopravku.ru',
    ];

    /** Файлы, а не страницы: их текст отсюда не достать */
    private const FILES = '/\.(pdf|docx?|pptx?|xlsx?|rtf|zip|rar)(\?|#|$)/i';

    public function __construct(
        private readonly PresentationPlanner $planner,
        private readonly YandexSearch $search,
        private readonly PageReader $reader,
    ) {}

    /**
     * @return list<array{id: int, url: string, title: string, domain: string, text: string, kind?: string}>
     */
    public function collect(Presentation $presentation): array
    {
        try {
            $plan = $this->planner->searchQueries($presentation);
        } catch (Throwable $e) {
            Log::warning('Источники: не составили запросы', ['presentation' => $presentation->id, 'error' => $e->getMessage()]);
            $plan = ['queries' => [mb_substr($presentation->topic, 0, 200)], 'level' => 'general', 'textbook' => ''];
        }

        $queries = $plan['queries'];

        // Учебник ищем первым: если Яндекс начнёт отказывать по лимиту,
        // пусть лучше не хватит одного из общих запросов
        $textbook = $plan['textbook'] !== '' ? $this->textbook($presentation, $plan['textbook']) : null;

        // Для студентов — ещё запрос по научным статьям: обычная выдача
        // по медицине и праву забита блогами клиник и юрфирм
        if ($plan['level'] === 'university') {
            $queries[] = $queries[0].' site:cyberleninka.ru';
        }

        $lists = [];

        foreach ($queries as $query) {
            $found = $this->find($presentation, $query, 10);

            if ($found !== null) {
                $lists[] = $found;
            }
        }

        $candidates = array_slice(
            self::prefer($this->merge($lists, $textbook['domain'] ?? null), $plan['level']),
            0,
            self::CANDIDATES,
        );

        $texts = [];

        if ($candidates !== []) {
            try {
                $texts = $this->reader->readMany(array_column($candidates, 'url'), $queries);
            } catch (Throwable $e) {
                Log::warning('Источники: не скачали страницы', ['presentation' => $presentation->id, 'error' => $e->getMessage()]);
            }
        }

        // Учебник, который назвал человек, — всегда первым: на него
        // ссылаются в первую очередь, даже если текста книги у нас нет
        $sources = $textbook !== null ? [['id' => 1, ...$textbook]] : [];

        foreach ($candidates as $candidate) {
            if (count($sources) >= self::MAX_SOURCES + ($textbook !== null ? 1 : 0)) {
                break;
            }

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
        }

        Log::info('Источники собраны', [
            'presentation' => $presentation->id,
            'запросы' => $queries,
            'уровень' => $plan['level'],
            'учебник' => $textbook['url'] ?? null,
            'кандидатов' => count($candidates),
            'источников' => count($sources),
        ]);

        return $sources;
    }

    /**
     * Один поисковый запрос. Сбой — не ошибка генерации: вернём null,
     * и презентация соберётся из того, что нашлось по остальным.
     *
     * @return list<array{url: string, domain: string, title: string, passages: list<string>}>|null
     */
    private function find(Presentation $presentation, string $query, int $limit): ?array
    {
        try {
            $results = $this->search->search($query, $limit);
        } catch (Throwable $e) {
            Log::warning('Источники: поиск не удался', ['presentation' => $presentation->id, 'query' => $query, 'error' => $e->getMessage()]);

            return null;
        }

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

        return $results;
    }

    /**
     * Страница учебника, который назвал человек.
     *
     * Сами учебники почти все за платным доступом, поэтому текста книги
     * у нас не будет — только описание со страницы издательства или
     * библиотеки. Модель это знает (см. PresentationPlanner) и не
     * выдумывает цитат и страниц. Предпочитаем издательства и ЭБС:
     * там точное название, авторы и год.
     *
     * @return array{url: string, title: string, domain: string, text: string, kind: string}|null
     */
    private function textbook(Presentation $presentation, string $query): ?array
    {
        $results = $this->find($presentation, $query, 10) ?? [];
        $usable = array_values(array_filter(
            $results,
            fn (array $r) => ! self::blocked(self::domain($r['url'])),
        ));

        if ($usable === []) {
            return null;
        }

        usort($usable, fn (array $a, array $b) => self::bookRank(self::domain($a['url'])) <=> self::bookRank(self::domain($b['url'])));
        $page = $usable[0];

        return [
            'url' => $page['url'],
            'title' => self::bookTitle($page['title']) ?: self::domain($page['url']),
            'domain' => self::domain($page['url']),
            'text' => implode(' ', $page['passages']),
            'kind' => 'textbook',
        ];
    }

    /**
     * Сайты под уровень аудитории — выше в списке. Остальные остаются,
     * просто идут после: хорошая статья с другого сайта лучше, чем
     * ничего.
     *
     * @param  list<array{url: string, domain: string, title: string}>  $candidates
     * @return list<array{url: string, domain: string, title: string}>
     */
    private static function prefer(array $candidates, string $level): array
    {
        $preferred = self::PREFERRED[$level] ?? [];

        if ($preferred === []) {
            return $candidates;
        }

        // 0 — сайты под уровень, 1 — обычные, 2 — для студентов слабые:
        // блоги клиник пишут для пациентов, а не для лекции
        $rank = function (string $domain) use ($preferred, $level): int {
            if (self::matches($domain, $preferred)) {
                return 0;
            }

            return $level === 'university' && self::isClinicBlog($domain) ? 2 : 1;
        };

        // usort устойчив: внутри групп порядок выдачи сохраняется
        usort($candidates, fn (array $a, array $b) => $rank($a['domain']) <=> $rank($b['domain']));

        return $candidates;
    }

    /**
     * Сайт клиники или статья «для пациентов»: mc21.ru, polyclinika.ru,
     * probolezny.ru. Узнаём по словам в адресе — списком всех клиник
     * страны их не перечислить.
     */
    private static function isClinicBlog(string $domain): bool
    {
        return self::matches($domain, self::CLINIC_BLOGS)
            || preg_match('/(clinic|klinik|poliklin|polyclin|medcent|medcenter|zdorov|bolezn|doctor|vrach|lechen|^mc\d)/i', $domain) === 1;
    }

    /** Место сайта в BOOK_SITES; чужие сайты — в конце */
    private static function bookRank(string $domain): int
    {
        foreach (self::BOOK_SITES as $rank => $site) {
            if (self::matches($domain, [$site])) {
                return $rank;
            }
        }

        return count(self::BOOK_SITES);
    }

    /**
     * Заголовок страницы магазина — это витрина: «… купить»,
     * «| Читай-город». В списке источников нужно название книги.
     */
    private static function bookTitle(string $title): string
    {
        $title = (string) preg_replace('/\s*(\.\.\.|…)\s*$/u', '', $title);
        $title = (string) preg_replace('/\s*[|—–-]\s*(купить|читать|скачать|интернет-магазин|читай-город|лабиринт|буквоед).*$/iu', '', $title);
        $title = (string) preg_replace('/\s+(купить|читать онлайн|скачать)(\s.*)?$/iu', '', $title);

        return trim($title);
    }

    /** @param  list<string>  $list */
    private static function matches(string $domain, array $list): bool
    {
        foreach ($list as $item) {
            if ($domain === $item || str_ends_with($domain, '.'.$item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Сводит выдачу нескольких запросов в один список: по очереди
     * берём первый результат каждого запроса, потом второй и так
     * далее. Один сайт — один источник.
     *
     * @param  list<list<array{url: string, domain: string, title: string, passages: list<string>}>>  $lists
     * @return list<array{url: string, domain: string, title: string}>
     */
    private function merge(array $lists, ?string $skip = null): array
    {
        $merged = [];
        // Сайт учебника уже в источниках — второй раз его не берём
        $seen = $skip !== null ? [$skip => true] : [];
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
        return self::matches($domain, self::BLOCKED);
    }
}
