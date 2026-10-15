<?php

use App\Services\Deck\Illustrations;

/*
   Где будут картинки: обложка всегда, в теле 2 (до 8 слайдов) или
   3 (от 10). Отметки модели — приоритет, недостающее добираем сами.
*/

function slide(string $layout, bool $illustrate = false, int $bullets = 3, string $scene = 'a scene'): array
{
    return [
        'layout' => $layout,
        'heading' => "Заголовок {$layout}",
        'subheading' => null,
        'bullets' => array_fill(0, $layout === 'bullets' ? $bullets : 0, ['title' => 't', 'text' => 'x', 'icon' => null]),
        'stats' => [],
        'quote' => null,
        'notes' => null,
        'illustrate' => $illustrate,
        'scene' => $scene,
    ];
}

/** @return array<int, int> индексы слайдов с картинкой в теле */
function illustrated(array $slides): array
{
    return array_keys(array_filter(
        $slides,
        fn ($s, $i) => $i > 0 && isset($s['image']),
        ARRAY_FILTER_USE_BOTH,
    ));
}

test('обложка получает фон из cover_scene', function () {
    $slides = Illustrations::plan([slide('title'), slide('bullets'), slide('closing')], 6, 'castle at sunset');

    expect($slides[0]['image'])->toBe(['scene' => 'castle at sunset', 'side' => 'cover']);
});

test('до 8 слайдов — две картинки в теле, от 10 — три', function (int $count, int $expected) {
    $deck = [slide('title'), ...array_fill(0, $count - 2, slide('bullets')), slide('closing')];

    expect(illustrated(Illustrations::plan($deck, $count, 'x')))->toHaveCount($expected);
})->with([
    [6, 2], [8, 2], [10, 3], [12, 3], [15, 3],
]);

test('отмеченные моделью слайды идут первыми, остальное добирается', function () {
    $deck = [
        slide('title'),
        slide('bullets'),
        slide('stats'),
        slide('quote', illustrate: true),
        slide('timeline'),
        slide('bullets'),
        slide('closing'),
    ];

    expect(illustrated(Illustrations::plan($deck, 8, 'x')))->toBe([1, 3]);
});

test('картинки не ставятся на вёрстки без места и на списки из 4 пунктов, если есть выбор', function () {
    $deck = [
        slide('title'),
        slide('stats', illustrate: true),
        slide('bullets', illustrate: true, bullets: 4),
        slide('timeline'),
        slide('bignumber'),
        slide('comparison'),
        slide('quote'),
        slide('closing'),
    ];

    expect(illustrated(Illustrations::plan($deck, 8, 'x')))->toBe([4, 6]);
});

test('минимум добирается даже из списков на 4 пункта, если больше не из чего', function () {
    $deck = [slide('title'), slide('bullets', bullets: 4), slide('stats'), slide('bullets', bullets: 4), slide('closing')];

    expect(illustrated(Illustrations::plan($deck, 6, 'x')))->toBe([1, 3]);
});

test('соседние слайды берутся только если иначе не добрать', function () {
    $deck = [slide('title'), slide('bullets'), slide('bullets'), slide('bullets'), slide('bullets'), slide('closing')];

    expect(illustrated(Illustrations::plan($deck, 6, 'x')))->toBe([1, 3]);
});

test('сторона чередуется, а без сцены рисуем по заголовку', function () {
    $deck = [slide('title'), slide('bullets', scene: ''), slide('stats'), slide('quote'), slide('closing')];

    $slides = Illustrations::plan($deck, 6, null);

    expect($slides[1]['image'])->toBe(['scene' => 'Заголовок bullets', 'side' => 'right', 'format' => 'square'])
        ->and($slides[3]['image']['side'])->toBe('left')
        ->and($slides[0]['image']['scene'])->toBe('Заголовок title');
});

test('служебные поля scene и illustrate из структуры убираются', function () {
    $slides = Illustrations::plan([slide('title'), slide('bullets'), slide('closing')], 6, 'x');

    foreach ($slides as $s) {
        expect($s)->not->toHaveKeys(['scene', 'illustrate', 'image_format']);
    }
});

test('вертикаль — только если модель отметила высокий объект, у обложки формата нет', function () {
    $tall = slide('bullets');
    $tall['image_format'] = 'portrait';
    $odd = slide('quote');
    $odd['image_format'] = 'landscape';

    $slides = Illustrations::plan([slide('title'), $tall, slide('stats'), $odd, slide('closing')], 6, 'x');

    expect($slides[1]['image']['format'])->toBe('portrait')
        // Горизонтальный на половине слайда не допускаем
        ->and($slides[3]['image']['format'])->toBe('square')
        ->and($slides[0]['image'])->not->toHaveKey('format');
});

test('в коротком запросе у людей без заданного происхождения — славянская внешность', function () {
    $prompt = fn (string $scene, string $subject = '') => App\Services\Images\Illustrator::compactPrompt($scene, '', 'square', $subject);

    expect($prompt('Doctors discussing a patient in a hospital ward'))->toContain('Slavic appearance')
        // Происхождение задано сценой — не трогаем
        ->and($prompt('Japanese villagers at a temple gate'))->not->toContain('Slavic')
        // Людей нет — не трогаем
        ->and($prompt('Lighthouse on a cliff at sunset'))->not->toContain('Slavic')
        // Герой презентации задан — он и определяет, кто на картинке
        ->and($prompt('A man reading by a window', 'Albert Einstein, old man with white hair'))->not->toContain('Slavic');
});
