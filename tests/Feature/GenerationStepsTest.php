<?php

use App\Enums\PresentationStatus;
use App\Jobs\RenderPresentation;
use App\Models\Presentation;
use App\Models\User;
use App\Services\Deck\DeckRenderer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
   Экран ожидания показывает шаги генерации, а редактор умеет
   убирать картинку со слайда.
*/

beforeEach(function () {
    Storage::fake('local');
    config(['deck.disk' => 'local', 'services.images.model' => 'gpt-image-test']);
});

/** @param  array<string, mixed>  $attributes */
function generating(array $attributes = []): Presentation
{
    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'Пётр I',
        ...$attributes,
    ]);
    $presentation->forceFill(['status' => PresentationStatus::Generating])->save();

    return $presentation;
}

/** @return array<string, string> */
function stepStates(Presentation $presentation): array
{
    $steps = test()->actingAs($presentation->user)
        ->getJson(route('presentations.status', $presentation))
        ->json('steps');

    return collect($steps)->pluck('state', 'label')->all();
}

test('пока картинки не нарисованы, текущий шаг — «Рисуем иллюстрации»', function () {
    $presentation = generating([
        'brief' => ['thesis' => 'x'],
        'outline' => ['title' => 'Пётр', 'slides' => [
            ['layout' => 'title', 'heading' => 'Пётр', 'image' => ['scene' => 'Neva', 'side' => 'cover']],
        ]],
    ]);

    expect(stepStates($presentation))->toBe([
        'Продумываем содержание' => 'done',
        'Собираем слайды' => 'done',
        'Рисуем иллюстрации' => 'current',
        'Печатаем файл' => 'todo',
    ]);
});

test('без конспекта идёт первый шаг, а без модели картинок шага с ними нет', function () {
    config(['services.images.model' => null]);

    expect(stepStates(generating()))->toBe([
        'Продумываем содержание' => 'current',
        'Собираем слайды' => 'todo',
        'Печатаем файл' => 'todo',
    ]);
});

test('перепечатка готовой презентации — один шаг печати', function () {
    $presentation = generating([
        'file_path' => 'presentations/1/deck.pdf',
        'outline' => ['title' => 'Пётр', 'slides' => [['layout' => 'title', 'heading' => 'Пётр']]],
    ]);

    expect(stepStates($presentation))->toBe(['Печатаем файл' => 'current']);
});

test('убранная в редакторе картинка удаляется с диска при сохранении', function () {
    Queue::fake();

    $presentation = generating();
    $dir = DeckRenderer::imageDir($presentation);
    Storage::disk('local')->put("$dir/0-keep.jpg", 'a');
    Storage::disk('local')->put("$dir/1-gone.jpg", 'b');

    $presentation->forceFill([
        'status' => PresentationStatus::Ready,
        'outline' => ['title' => 'Пётр', 'slides' => [
            ['layout' => 'title', 'heading' => 'Пётр', 'image' => ['path' => "$dir/0-keep.jpg", 'side' => 'cover']],
            ['layout' => 'bullets', 'heading' => 'Верфи', 'bullets' => [['title' => 'a', 'text' => 'b']], 'image' => ['path' => "$dir/1-gone.jpg", 'side' => 'right']],
        ]],
    ])->save();

    $slides = $presentation->outline['slides'];
    $slides[1]['image'] = null;

    $this->actingAs($presentation->user)
        ->put(route('presentations.outline', $presentation), [
            'title' => 'Пётр',
            'slides' => $slides,
        ])
        ->assertSessionHasNoErrors();

    expect(Storage::disk('local')->exists("$dir/0-keep.jpg"))->toBeTrue()
        ->and(Storage::disk('local')->exists("$dir/1-gone.jpg"))->toBeFalse()
        ->and($presentation->refresh()->outline['slides'][1]['image'])->toBeNull();

    Queue::assertPushed(RenderPresentation::class);
});

test('с поиском первый шаг — «Ищем источники»', function () {
    config(['services.yandex_search' => ['key' => 'ya', 'folder' => 'b1g']]);

    expect(stepStates(generating()))->toBe([
        'Ищем источники' => 'current',
        'Продумываем содержание' => 'todo',
        'Собираем слайды' => 'todo',
        'Рисуем иллюстрации' => 'todo',
        'Печатаем файл' => 'todo',
    ]);
});
