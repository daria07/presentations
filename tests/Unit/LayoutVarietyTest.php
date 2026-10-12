<?php

use App\Services\Deck\LayoutVariety;

/*
   Две одинаковые вёрстки подряд при генерации разводятся,
   содержание при этом не теряется.
*/

function layoutSlide(string $layout, int $bullets = 0, int $stats = 0): array
{
    return [
        'layout' => $layout,
        'heading' => $layout,
        'bullets' => array_map(fn ($i) => ['title' => "п{$i}", 'text' => 'текст', 'icon' => null], range(1, max(1, $bullets))),
        'stats' => array_map(fn ($i) => ['value' => (string) (1700 + $i), 'label' => "событие {$i}"], range(1, max(1, $stats))),
    ];
}

test('две хронологии подряд: вторая становится числами', function () {
    $slides = LayoutVariety::apply([
        layoutSlide('title'),
        layoutSlide('timeline', stats: 3),
        layoutSlide('timeline', stats: 3),
        layoutSlide('closing'),
    ]);

    expect(array_column($slides, 'layout'))->toBe(['title', 'timeline', 'stats', 'closing'])
        ->and($slides[2]['stats'])->toHaveCount(3);
});

test('хронология перед числами превращается в этапы, даты становятся пунктами', function () {
    $slides = LayoutVariety::apply([
        layoutSlide('timeline', stats: 3),
        layoutSlide('timeline', stats: 3),
        layoutSlide('stats', stats: 2),
    ]);

    expect($slides[1]['layout'])->toBe('process')
        ->and($slides[1]['bullets'][0])->toBe(['title' => '1701', 'text' => 'событие 1', 'icon' => null]);
});

test('три списка подряд: средний меняется, крайние остаются', function () {
    $slides = LayoutVariety::apply([
        layoutSlide('bullets', bullets: 3),
        layoutSlide('bullets', bullets: 3),
        layoutSlide('bullets', bullets: 3),
    ]);

    expect(array_column($slides, 'layout'))->toBe(['bullets', 'process', 'bullets']);
});

test('цитаты и служебные слайды не трогаем', function () {
    $slides = LayoutVariety::apply([
        layoutSlide('quote'),
        layoutSlide('quote'),
        layoutSlide('closing'),
        layoutSlide('sources'),
    ]);

    expect(array_column($slides, 'layout'))->toBe(['quote', 'quote', 'closing', 'sources']);
});
