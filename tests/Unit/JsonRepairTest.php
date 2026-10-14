<?php

use App\Services\Claude\Json;

/*
   Модель иногда присылает массив слайдов строкой и не экранирует
   кавычку внутри текста. Раньше от этого пропадала вся структура.
*/

test('неэкранированная кавычка в тексте не ломает разбор', function () {
    $broken = '[{"heading": "Признание", "notes": "Он говорил: в „Ёжике в тумане" произошёл случай, а потом — \"чудо\"."}, {"heading": "Итоги"}]';

    $slides = Json::toArray($broken);

    expect($slides)->toHaveCount(2)
        ->and($slides[0]['notes'])->toBe('Он говорил: в „Ёжике в тумане" произошёл случай, а потом — "чудо".');
});

test('кавычка перед запятой в тексте тоже остаётся текстом', function () {
    $broken = '[{"notes": "Фильм „Ёжик", а не сказка, стал классикой", "heading": "А"}]';

    expect(Json::toArray($broken)[0]['notes'])->toBe('Фильм „Ёжик", а не сказка, стал классикой');
});

test('правильный JSON разбирается как раньше', function () {
    expect(Json::toArray('[{"a": "b, \"c\""}]'))->toBe([['a' => 'b, "c"']]);
});

test('поля, проглоченные соседним в разметке вызова, разрезаются обратно', function () {
    $data = Json::splitLeakedParameters([
        'arc' => "Начинаем с загадки.</arc>\n<parameter name=\"points\">[{\"title\": \"Легенда\", \"substance\": \"Слуга выбрал лимон\"}]</parameter>\n<parameter name=\"thesis\">Другой тезис</parameter>",
        'thesis' => 'Лимон показывает химию',
    ]);

    expect($data['arc'])->toBe('Начинаем с загадки.')
        ->and($data['points'][0]['title'])->toBe('Легенда')
        // Поле, пришедшее нормально, не перетираем
        ->and($data['thesis'])->toBe('Лимон показывает химию');
});
