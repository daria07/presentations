<?php

namespace App\Services\Search;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Скачивает страницы из выдачи и вынимает из них основной текст.
 *
 * Адреса приходят из поисковика, то есть из чужих рук, поэтому
 * сервер ходит только на публичные адреса: не на localhost, не во
 * внутреннюю сеть и не туда же через перенаправление. Размер и
 * время ответа ограничены — страница не должна держать воркер.
 */
class PageReader
{
    /** Больше этого не качаем: статье хватает, а мегабайты — это не статья */
    public const MAX_BYTES = 2_000_000;

    /** Сколько текста страницы отдаём модели */
    public const MAX_CHARS = 6000;

    /** Сколько текста страницы просматриваем, чтобы выбрать из него нужное */
    public const SCAN_CHARS = 60000;

    /** Меньше — значит, текста на странице нет: каталог, заглушка, капча */
    public const MIN_CHARS = 400;

    /**
     * Как узнать адреса хоста. Подменяется в тестах: там у фейковых
     * хостов нет DNS.
     *
     * @var (Closure(string): list<string>)|null
     */
    public static ?Closure $resolveUsing = null;

    /**
     * @param  list<string>  $urls
     * @param  list<string>  $keywords  поисковые запросы: по ним из длинной
     *                                  статьи выбираются нужные абзацы
     * @return array<string, string> адрес => текст, только удачные
     */
    public function readMany(array $urls, array $keywords = []): array
    {
        $urls = array_values(array_unique(array_filter($urls, fn (string $url) => self::isPublic($url))));

        if ($urls === []) {
            return [];
        }

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $url) => $pool->as($url)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; SlaidushaBot/1.0; +https://slaidusha.ru)',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'ru,en;q=0.5',
                ])
                ->connectTimeout(4)
                ->timeout(10)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'protocols' => ['http', 'https'],
                        // Перенаправление проверяем так же, как исходный
                        // адрес: иначе публичная страница могла бы
                        // отправить нас во внутреннюю сеть
                        'on_redirect' => function ($request, $response, UriInterface $uri) {
                            if (! self::isPublic((string) $uri)) {
                                throw new RuntimeException('Перенаправление на закрытый адрес');
                            }
                        },
                    ],
                    'on_headers' => function (ResponseInterface $response) {
                        $type = strtolower($response->getHeaderLine('Content-Type'));

                        if ($type !== '' && ! str_contains($type, 'html')) {
                            throw new RuntimeException('Не HTML');
                        }

                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            throw new RuntimeException('Слишком большая страница');
                        }
                    },
                    // Content-Length бывает не указан — считаем по ходу
                    'progress' => function ($total, $downloaded) {
                        if ($downloaded > self::MAX_BYTES) {
                            throw new RuntimeException('Слишком большая страница');
                        }
                    },
                ])
                ->get($url),
            $urls,
        ));

        $texts = [];

        foreach ($urls as $url) {
            $response = $responses[$url] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            $text = $this->extract($this->utf8($response), $keywords);

            if (mb_strlen($text) >= self::MIN_CHARS) {
                $texts[$url] = $text;
            }
        }

        return $texts;
    }

    /**
     * Можно ли ходить по этому адресу: http(s), стандартный порт и
     * хост, который смотрит только в публичный интернет.
     */
    public static function isPublic(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        $port = $parts['port'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($port !== null && ! in_array($port, [80, 443], true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            $public = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );

            if ($public === false) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        if (self::$resolveUsing !== null) {
            return (self::$resolveUsing)($host);
        }

        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /** Тело страницы в UTF-8: русские сайты до сих пор бывают в cp1251 */
    private function utf8(Response $response): string
    {
        $body = $response->body();

        $charset = null;

        if (preg_match('/charset=["\']?([\w-]+)/i', $response->header('Content-Type'), $m)) {
            $charset = $m[1];
        } elseif (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', substr($body, 0, 4096), $m)) {
            $charset = $m[1];
        }

        if ($charset === null || strcasecmp($charset, 'utf-8') === 0 || strcasecmp($charset, 'utf8') === 0) {
            if (mb_check_encoding($body, 'UTF-8')) {
                return $body;
            }

            // Кодировка не указана, а текст не UTF-8 — почти наверняка cp1251
            return mb_convert_encoding($body, 'UTF-8', 'Windows-1251');
        }

        $known = array_map('strtolower', mb_list_encodings());

        if (! in_array(strtolower($charset), $known, true)) {
            return $body;
        }

        $converted = mb_convert_encoding($body, 'UTF-8', $charset);

        return is_string($converted) ? $converted : $body;
    }

    /**
     * Основной текст страницы: заголовки, абзацы и пункты списков
     * из статьи, без меню, подвалов, комментариев и рекламы.
     */
    /**
     * @param  list<string>  $keywords
     */
    public function extract(string $html, array $keywords = []): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);

        // Служебное убираем целиком, вместе с текстом внутри
        $junk = '//script|//style|//noscript|//nav|//header|//footer|//aside|//form'
            .'|//svg|//iframe|//button|//select|//template|//figure/figcaption|//sup';

        foreach (iterator_to_array($xpath->query($junk) ?: []) as $node) {
            if ($node instanceof DOMElement) {
                $node->parentNode?->removeChild($node);
            }
        }

        $noise = '/(^|[\s_-])(comments?|sidebar|menu|share|social|related|advert|ads|banner|cookies?|breadcrumbs?|popup|subscribe|navbox|reflist|references)([\s_-]|$)/i';

        foreach (iterator_to_array($xpath->query('//*[@class or @id]') ?: []) as $node) {
            if (! $node instanceof DOMElement || $node->parentNode === null) {
                continue;
            }

            // Классы вроде has-menu на body или article не должны
            // уносить с собой всю страницу
            if (in_array($node->nodeName, ['html', 'body', 'main', 'article'], true)) {
                continue;
            }

            if (preg_match($noise, $node->getAttribute('class').' '.$node->getAttribute('id'))) {
                $node->parentNode->removeChild($node);
            }
        }

        // Статья, если она размечена; иначе вся страница
        foreach (['//article', '//main', '//*[@role="main"]', '//body'] as $query) {
            $found = $xpath->query($query);
            $root = $found === false ? null : $found->item(0);

            if (! $root instanceof DOMElement) {
                continue;
            }

            $lines = $this->collect($xpath, $root);
            $length = array_sum(array_map(fn (string $line) => mb_strlen($line) + 1, $lines));

            if ($length >= self::MIN_CHARS || $query === '//body') {
                return $this->select($lines, $keywords);
            }
        }

        return '';
    }

    /**
     * Все текстовые блоки страницы по порядку: заголовки разделов
     * с пометкой «## » и абзацы.
     *
     * @return list<string>
     */
    private function collect(DOMXPath $xpath, DOMNode $root): array
    {
        $blocks = $xpath->query(
            './/h1|.//h2|.//h3|.//h4|.//p|.//blockquote|.//dd|.//li[not(.//p) and not(.//li)]',
            $root,
        ) ?: [];

        $lines = [];
        $length = 0;

        foreach ($blocks as $block) {
            if (! $block instanceof DOMElement) {
                continue;
            }

            $text = trim((string) preg_replace('/\s+/u', ' ', $block->textContent));
            // Сноски вида [12] и [источник не указан] модели не нужны
            $text = trim((string) preg_replace('/\[(\d+|[^\]]{0,30}указан[^\]]*)\]/u', '', $text));

            $heading = in_array($block->nodeName, ['h1', 'h2', 'h3', 'h4'], true);

            if ($text === '' || (! $heading && mb_strlen($text) < 40)) {
                continue;
            }

            $line = $heading ? '## '.$text : $text;

            if (end($lines) === $line) {
                continue;
            }

            $lines[] = $line;
            $length += mb_strlen($line) + 1;

            if ($length >= self::SCAN_CHARS) {
                break;
            }
        }

        return $lines;
    }

    /**
     * Что из статьи отдать модели.
     *
     * Обзорная статья длиннее лимита в разы, и её начало — это
     * введение. Если брать подряд с начала, про экономику в статье
     * «Реформы Петра I» до модели не доходит ничего. Поэтому берём
     * первый абзац (там определение) и дальше абзацы, где больше
     * всего слов из поисковых запросов, — в порядке статьи и с
     * заголовками их разделов.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $keywords
     */
    private function select(array $lines, array $keywords): string
    {
        $stems = self::stems($keywords);
        $length = array_sum(array_map(fn (string $line) => mb_strlen($line) + 1, $lines));

        if ($length <= self::MAX_CHARS || $stems === []) {
            return self::head($lines);
        }

        $scores = [];
        $sectionOf = [];
        $section = null;

        foreach ($lines as $i => $line) {
            if (str_starts_with($line, '## ')) {
                $section = $i;

                continue;
            }

            $sectionOf[$i] = $section;
            $scores[$i] = self::score($line, $stems);
        }

        if ($scores === [] || max($scores) === 0) {
            return self::head($lines);
        }

        $first = (int) array_key_first($scores);
        $keep = [$first => true];
        $used = mb_strlen($lines[$first]) + 1;

        // Сортировка устойчивая: при равном счёте раньше идёт то,
        // что раньше стоит в статье
        arsort($scores);

        foreach ($scores as $i => $score) {
            if ($score === 0) {
                break;
            }

            if (isset($keep[$i])) {
                continue;
            }

            $heading = $sectionOf[$i];
            $add = mb_strlen($lines[$i]) + 1
                + ($heading !== null && ! isset($keep[$heading]) ? mb_strlen($lines[$heading]) + 1 : 0);

            if ($used + $add > self::MAX_CHARS) {
                continue;
            }

            $keep[$i] = true;

            if ($heading !== null) {
                $keep[$heading] = true;
            }

            $used += $add;
        }

        ksort($keep);

        return implode("\n", array_map(fn (int $i) => $lines[$i], array_keys($keep)));
    }

    /**
     * Начало статьи в пределах лимита.
     *
     * @param  list<string>  $lines
     */
    private static function head(array $lines): string
    {
        $picked = [];
        $length = 0;

        foreach ($lines as $line) {
            $picked[] = $line;
            $length += mb_strlen($line) + 1;

            if ($length >= self::MAX_CHARS) {
                break;
            }
        }

        // Заголовки без текста под ними в конце ничего не дают
        while ($picked !== [] && str_starts_with((string) end($picked), '## ')) {
            array_pop($picked);
        }

        return mb_substr(implode("\n", $picked), 0, self::MAX_CHARS);
    }

    /**
     * Грубые основы слов запроса: «экономические» → «эконом»,
     * «мануфактуры» → «мануфа». Для русского этого хватает, чтобы
     * совпадали разные падежи и формы.
     *
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private static function stems(array $keywords): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(implode(' ', $keywords))) ?: [];
        $stems = [];

        foreach ($words as $word) {
            $length = mb_strlen($word);

            if ($length < 4) {
                continue;
            }

            $stems[] = match (true) {
                $length >= 7 => mb_substr($word, 0, 6),
                $length >= 5 => mb_substr($word, 0, $length - 1),
                default => $word,
            };
        }

        return array_values(array_unique($stems));
    }

    /**
     * Сколько слов запроса в абзаце: разные слова весят больше,
     * чем повтор одного и того же.
     *
     * @param  list<string>  $stems
     */
    private static function score(string $line, array $stems): int
    {
        $text = mb_strtolower($line);
        $distinct = 0;
        $hits = 0;

        foreach ($stems as $stem) {
            $count = mb_substr_count($text, $stem);

            if ($count > 0) {
                $distinct++;
                $hits += min($count, 3);
            }
        }

        return $distinct * 2 + min($hits, 6);
    }
}
