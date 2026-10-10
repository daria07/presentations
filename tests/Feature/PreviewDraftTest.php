<?php

use App\Models\Presentation;
use App\Models\User;

/*
   Превью черновика из редактора: структура приходит в теле запроса и
   идёт прямиком в Blade. Проверяем, что туда попадает только то, что
   прошло бы и при сохранении.
*/

function previewable(): Presentation
{
    $user = User::factory()->create();

    return Presentation::create([
        'user_id' => $user->id,
        'topic' => 'Тема',
        'outline' => [
            'title' => 'Сохранённое название',
            'slides' => [['layout' => 'title', 'heading' => 'Сохранённый слайд']],
        ],
    ]);
}

function previewDraft(Presentation $presentation, array $body)
{
    return test()->actingAs($presentation->user)
        ->post(route('presentations.preview', $presentation), $body);
}

test('нормальный черновик рисуется', function () {
    $response = previewDraft(previewable(), [
        'title' => 'Новое',
        'slides' => [
            ['layout' => 'title', 'heading' => 'Черновой заголовок'],
            ['layout' => 'bullets', 'heading' => 'Пункты', 'bullets' => [
                ['title' => 'Первый', 'text' => 'Текст'],
            ]],
        ],
    ]);

    $response->assertOk()->assertSee('Черновой заголовок');
});

test('пустой заголовок в черновике не ломает превью', function () {
    previewDraft(previewable(), [
        'title' => '',
        'slides' => [['layout' => 'bullets', 'heading' => '']],
    ])->assertOk();
});

test('кривой черновик — 422, а не 500 и не редирект', function (array $body) {
    previewDraft(previewable(), $body)->assertStatus(422);
})->with([
    'слайд строкой' => [['title' => 'x', 'slides' => ['просто строка']]],
    'неизвестный макет' => [['title' => 'x', 'slides' => [['layout' => '../welcome', 'heading' => 'x']]]],
    'пункт строкой' => [['title' => 'x', 'slides' => [['layout' => 'bullets', 'heading' => 'x', 'bullets' => ['строка']]]]],
    'чужая иконка' => [['title' => 'x', 'slides' => [['layout' => 'bullets', 'heading' => 'x', 'bullets' => [['icon' => 'nope']]]]]],
    'без заголовка вовсе' => [['title' => 'x', 'slides' => [['layout' => 'bullets']]]],
    'слишком много слайдов' => [['title' => 'x', 'slides' => array_fill(0, 31, ['layout' => 'bullets', 'heading' => 'x'])]],
    'слайды не массивом' => [['title' => 'x', 'slides' => 'строка']],
]);

test('без черновика показывается сохранённая версия', function () {
    $presentation = previewable();

    $this->actingAs($presentation->user)
        ->get(route('presentations.preview', $presentation))
        ->assertOk()
        ->assertSee('Сохранённый слайд');
});

test('чужое превью не открыть', function () {
    $presentation = previewable();

    $this->actingAs(User::factory()->create())
        ->post(route('presentations.preview', $presentation), [
            'title' => 'x',
            'slides' => [['layout' => 'title', 'heading' => 'x']],
        ])
        ->assertForbidden();
});

test('сохранение структуры тоже отбивает слайд-строку', function () {
    $presentation = previewable();

    $this->actingAs($presentation->user)
        ->put(route('presentations.outline', $presentation), [
            'title' => 'x',
            'slides' => [['layout' => 'bullets', 'heading' => 'x', 'stats' => ['строка']]],
        ])
        ->assertSessionHasErrors('slides.0.stats.0');
});
