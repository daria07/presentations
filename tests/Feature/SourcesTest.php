<?php

use App\Models\Presentation;
use App\Models\User;
use App\Services\Claude\PresentationPlanner;
use App\Services\Deck\DeckRenderer;
use App\Services\Search\PageReader;
use App\Services\Search\SourceCollector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
   Источники: запросы → Яндекс → тексты страниц → конспект →
   последний слайд со ссылками.
*/

beforeEach(function () {
    config([
        'services.anthropic' => [
            'key' => 'k', 'base_url' => 'https://gw.test', 'model' => 'claude-x',
            'price_input' => 1, 'price_output' => 1,
        ],
        'services.yandex_search' => ['key' => 'ya', 'folder' => 'b1g'],
    ]);

    // У фейковых хостов нет DNS — считаем их публичными, кроме
    // внутреннего, на котором проверяем защиту
    PageReader::$resolveUsing = fn (string $host) => $host === 'intranet.test' ? ['10.0.0.5'] : ['93.184.216.34'];
});

afterEach(fn () => PageReader::$resolveUsing = null);

/**
 * Ответ модели: вызов того инструмента, который у неё попросили.
 * Клиент сверяет имя, и чужое имя считает отказом модели.
 */
function toolReply(array $input): Closure
{
    return fn (Request $request) => Http::response([
        'model' => 'claude-x',
        'stop_reason' => 'tool_use',
        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        'content' => [['type' => 'tool_use', 'name' => $request['tools'][0]['name'], 'input' => $input]],
    ]);
}

/** Выдача Яндекса в том виде, в каком она приходит: XML в base64 */
function yandexReply(array $urls): array
{
    $docs = collect($urls)->map(fn ($url) => '<group><doc><url>'.htmlspecialchars($url).'</url>'
        .'<domain>'.parse_url($url, PHP_URL_HOST).'</domain>'
        .'<title>Про <hlword>Петра</hlword> '.parse_url($url, PHP_URL_HOST).'</title>'
        .'<passages><passage>Отрывок</passage></passages></doc></group>')->implode('');

    return ['rawData' => base64_encode('<?xml version="1.0" encoding="utf-8"?><yandexsearch><response><results><grouping>'.$docs.'</grouping></results></response></yandexsearch>')];
}

function articlePage(string $fact): string
{
    $paragraph = str_repeat("{$fact} — важный факт о реформах, который стоит знать. ", 12);

    return "<html><body><nav>Меню сайта Главная Контакты О нас и всякое прочее меню</nav>
        <article><h1>Реформы Петра</h1><p>{$paragraph}</p><p>{$paragraph}</p></article>
        <footer>Подвал сайта, все права защищены, копирование запрещено</footer></body></html>";
}

test('собирает тексты страниц, пропуская видео, повторы сайтов и внутренние адреса', function () {
    Http::fake([
        'gw.test/*' => toolReply(['queries' => ['реформы Петра I', 'Табель о рангах']]),
        'searchapi.api.cloud.yandex.net/*' => Http::sequence()
            ->push(yandexReply(['https://ru.wikipedia.org/wiki/A', 'https://www.youtube.com/watch?v=1', 'https://intranet.test/secret']))
            ->push(yandexReply(['https://ru.wikipedia.org/wiki/B', 'https://foxford.ru/x', 'https://site.test/file.pdf'])),
        'ru.wikipedia.org/*' => Http::response(articlePage('Сенат учреждён в 1711 году'), 200, ['Content-Type' => 'text/html; charset=utf-8']),
        'foxford.ru/*' => Http::response(articlePage('Коллегии заменили приказы'), 200, ['Content-Type' => 'text/html']),
        '*' => Http::response('nope', 500),
    ]);

    $presentation = Presentation::create(['user_id' => User::factory()->create()->id, 'topic' => 'Пётр I']);

    $sources = app(SourceCollector::class)->collect($presentation);

    expect(array_column($sources, 'url'))->toBe(['https://ru.wikipedia.org/wiki/A', 'https://foxford.ru/x'])
        ->and($sources[0]['id'])->toBe(1)
        ->and($sources[0]['title'])->toBe('Про Петра ru.wikipedia.org')
        ->and($sources[0]['text'])->toContain('Сенат учреждён в 1711 году')
        ->and($sources[0]['text'])->not->toContain('Меню сайта')
        ->and($sources[0]['text'])->not->toContain('Подвал');

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'intranet.test'));
});

test('закрытые адреса не скачиваются', function (string $url) {
    expect(PageReader::isPublic($url))->toBeFalse();
})->with([
    'http://127.0.0.1/',
    'http://10.1.2.3/admin',
    'http://intranet.test/',
    'https://example.com:8080/',
    'ftp://example.com/',
    'javascript:alert(1)',
    'http://user:pass@example.com/',
]);

test('последний слайд — источники, на которые опирался конспект', function () {
    Http::fake(['gw.test/*' => toolReply([
        'title' => 'Пётр I',
        'slides' => [
            ['layout' => 'title', 'heading' => 'Пётр I'],
            ['layout' => 'bullets', 'heading' => 'Сенат', 'bullets' => [['title' => 'a', 'text' => 'b']]],
            ['layout' => 'closing', 'heading' => 'Итоги'],
        ],
    ])]);

    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'Пётр I',
        'slide_count' => 3,
        'brief' => ['thesis' => 'x', 'points' => [['title' => 'Сенат', 'substance' => '…', 'sources' => [2]]]],
        'sources' => [
            ['id' => 1, 'url' => 'https://a.test/1', 'title' => 'Первый', 'domain' => 'a.test', 'text' => '…'],
            ['id' => 2, 'url' => 'https://b.test/2', 'title' => 'Второй', 'domain' => 'b.test', 'text' => '…'],
        ],
    ]);

    $slides = PresentationPlanner::make()->buildOutline($presentation)['slides'];
    $last = end($slides);

    expect($last['layout'])->toBe('sources')
        ->and($last['sources'])->toBe([['title' => 'Второй', 'url' => 'https://b.test/2']])
        ->and($slides[count($slides) - 2]['layout'])->toBe('closing');
});

test('конспект получает тексты источников', function () {
    Http::fake(['gw.test/*' => toolReply(['thesis' => 'x', 'arc' => 'y', 'points' => []])]);

    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'Пётр I',
        'sources' => [['id' => 1, 'url' => 'https://a.test/1', 'title' => 'Первый', 'domain' => 'a.test', 'text' => 'Сенат учреждён в 1711 году']],
    ]);

    PresentationPlanner::make()->buildBrief($presentation);

    Http::assertSent(fn (Request $r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), '[1] Первый (a.test)')
        && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'Сенат учреждён в 1711 году'));
});

test('ссылки на слайде источников кликабельны, и только http(s)', function () {
    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'Пётр I',
        'outline' => ['title' => 'Пётр I', 'slides' => [
            ['layout' => 'title', 'heading' => 'Пётр I'],
            ['layout' => 'sources', 'heading' => 'Источники', 'sources' => [
                ['title' => 'Википедия', 'url' => 'https://ru.wikipedia.org/wiki/Пётр_I'],
                ['title' => 'Плохая', 'url' => 'javascript:alert(1)'],
            ]],
        ]],
    ]);

    $html = app(DeckRenderer::class)->html($presentation, forScreen: true);

    expect($html)->toContain('href="https://ru.wikipedia.org/wiki/Пётр_I"')
        ->and($html)->toContain('target="_blank"')
        ->and($html)->not->toContain('javascript:alert');
});

test('из длинной статьи берутся абзацы по запросу, а не только начало', function () {
    $filler = str_repeat('<p>'.str_repeat('Пётр провёл реформу армии и флота, создал рекрутские наборы. ', 6).'</p>', 30);
    $html = '<html><body><article><p>'.str_repeat('Реформы Петра I — преобразования начала XVIII века. ', 3).'</p>'
        .$filler
        .'<h2>Экономика</h2><p>Число мануфактур выросло с 15 до 200, торговля через Петербург стала главной. Экономические меры шли за войной.</p>'
        .'</article></body></html>';

    $text = (new PageReader)->extract($html, ['экономические реформы Петра I мануфактуры торговля']);

    expect(mb_strlen($text))->toBeLessThanOrEqual(PageReader::MAX_CHARS)
        ->and($text)->toStartWith('Реформы Петра I — преобразования')
        ->and($text)->toContain("## Экономика\nЧисло мануфактур выросло с 15 до 200");
});
