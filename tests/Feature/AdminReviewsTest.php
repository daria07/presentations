<?php

use App\Models\Presentation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Отзывы в сводке админки: все, постранично, свежие сверху.
*/

beforeEach(function () {
    $this->withoutVite();
    config(['admin.emails' => ['boss@example.com']]);
    $this->admin = User::factory()->create(['email' => 'boss@example.com']);
});

function rated(int $count): void
{
    $user = User::factory()->create();

    foreach (range(1, $count) as $i) {
        Presentation::create([
            'user_id' => $user->id,
            'topic' => "Тема {$i}",
            'rating' => 1 + $i % 5,
            'review' => "Отзыв {$i}",
            'reviewed_at' => now()->subMinutes($count - $i),
        ]);
    }
}

test('отзывы листаются по страницам, свежие сверху', function () {
    rated(23);

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviews.total', 23)
            ->where('reviews.lastPage', 3)
            ->has('reviews.items', 10)
            ->where('reviews.items.0.review', 'Отзыв 23'));

    $this->actingAs($this->admin)
        ->get('/admin?reviews_page=3')
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviews.currentPage', 3)
            ->has('reviews.items', 3)
            ->where('reviews.items.2.review', 'Отзыв 1')
            ->where('reviews.nextUrl', null));
});

test('листание отзывов отдаёт только отзывы', function () {
    rated(12);

    // Повторный запрос с only=reviews, как делает кнопка «Вперёд»:
    // в ответе только отзывы, остальная сводка не считается
    $this->actingAs($this->admin)
        ->get('/admin?reviews_page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('reviews', fn (Assert $reload) => $reload
                ->where('reviews.currentPage', 2)
                ->has('reviews.items', 2)
                ->missing('cards')
                ->missing('promo')));
});
