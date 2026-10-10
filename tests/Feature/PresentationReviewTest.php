<?php

use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Оценка готовой презентации: звёзды 1–5 и необязательный отзыв.
*/

function readyDeck(?User $owner = null): Presentation
{
    $deck = Presentation::create([
        'user_id' => ($owner ?? User::factory()->create())->id,
        'topic' => 'Тема',
    ]);
    $deck->forceFill(['status' => PresentationStatus::Ready, 'file_path' => 'x.pdf'])->save();

    return $deck;
}

test('владелец ставит оценку и пишет отзыв', function () {
    $deck = readyDeck();

    $this->actingAs($deck->user)
        ->post(route('presentations.review', $deck), [
            'rating' => 4,
            'review' => '  Хорошо, но мало цифр  ',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $deck->refresh();

    expect($deck->rating)->toBe(4)
        ->and($deck->review)->toBe('Хорошо, но мало цифр')
        ->and($deck->reviewed_at)->not->toBeNull();
});

test('отзыв необязателен, а повторная оценка заменяет прежнюю', function () {
    $deck = readyDeck();

    $this->actingAs($deck->user)->post(route('presentations.review', $deck), ['rating' => 2, 'review' => 'Плохо']);
    $this->actingAs($deck->user)->post(route('presentations.review', $deck), ['rating' => 5]);

    $deck->refresh();

    expect($deck->rating)->toBe(5)->and($deck->review)->toBeNull();
});

test('оценка не трогает updated_at — метку версии превью', function () {
    $deck = readyDeck();
    $before = $deck->updated_at;

    $this->travel(5)->minutes();
    $this->actingAs($deck->user)->post(route('presentations.review', $deck), ['rating' => 3]);

    expect($deck->refresh()->updated_at->equalTo($before))->toBeTrue();
});

test('кривая оценка отклоняется', function (array $body, string $field) {
    $deck = readyDeck();

    $this->actingAs($deck->user)
        ->post(route('presentations.review', $deck), $body)
        ->assertSessionHasErrors($field);

    expect($deck->refresh()->rating)->toBeNull();
})->with([
    'без звёзд' => [['review' => 'текст'], 'rating'],
    'ноль' => [['rating' => 0], 'rating'],
    'шесть' => [['rating' => 6], 'rating'],
    'не число' => [['rating' => 'пять'], 'rating'],
    'длинный отзыв' => [['rating' => 5, 'review' => str_repeat('а', 1001)], 'review'],
]);

test('чужую презентацию оценить нельзя', function () {
    $deck = readyDeck();

    $this->actingAs(User::factory()->create())
        ->post(route('presentations.review', $deck), ['rating' => 1])
        ->assertForbidden();

    expect($deck->refresh()->rating)->toBeNull();
});

test('неготовую презентацию оценить нельзя', function () {
    $deck = readyDeck();
    $deck->forceFill(['status' => PresentationStatus::Failed])->save();

    $this->actingAs($deck->user)
        ->post(route('presentations.review', $deck), ['rating' => 5])
        ->assertNotFound();
});

test('в списке у готовой презентации есть оценка и адрес для неё, у неготовой — нет', function () {
    $this->withoutVite();

    $deck = readyDeck();
    $deck->forceFill(['rating' => 4, 'review' => 'Норм'])->save();

    $this->travel(1)->minutes(); // список идёт от новых к старым
    $draft = Presentation::create(['user_id' => $deck->user_id, 'topic' => 'Черновик']);

    $this->actingAs($deck->user)
        ->get(route('presentations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('presentations.data.0.id', $draft->id)
            ->where('presentations.data.0.reviewUrl', null)
            ->where('presentations.data.1.rating', 4)
            ->where('presentations.data.1.review', 'Норм')
            ->where('presentations.data.1.reviewUrl', route('presentations.review', $deck)));
});
