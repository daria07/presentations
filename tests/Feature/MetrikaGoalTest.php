<?php

use App\Models\User;
use App\Support\MetrikaGoal;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Цель «registration» ставит сервер — ровно одну на созданный аккаунт,
   с параметром способа. Ошибка в форме цели не даёт.
*/

beforeEach(function () {
    $this->withoutVite();
});

function registrationGoals(): array
{
    return array_values(array_filter(
        (array) session(MetrikaGoal::SESSION, []),
        fn (array $g) => $g['name'] === 'registration',
    ));
}

test('регистрация формой — одна цель, способ email, и она приходит в props', function () {
    $this->post(route('register.store'), [
        'name' => 'Анна',
        'email' => 'anna@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ])->assertRedirect(config('fortify.home'));

    expect(registrationGoals())->toHaveCount(1)
        ->and(registrationGoals()[0]['params'])->toBe(['method' => 'email']);

    $this->get(config('fortify.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('goals.0.name', 'registration')
            ->where('goals.0.params.method', 'email'));

    // Следующая страница — уже без цели: флеш живёт один запрос
    $this->get(config('fortify.home'))
        ->assertInertia(fn (Assert $page) => $page->where('goals', []));
});

test('ошибка в форме — аккаунта нет, и цели нет', function () {
    $this->post(route('register.store'), [
        'name' => 'Анна',
        'email' => 'anna@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(registrationGoals())->toBe([]);
});

test('вход через VK и Mail.ru — способ виден в цели', function (string $provider, string $method) {
    config(['services.vkid.client_id' => '12345']);
    Http::fake([
        'id.vk.com/oauth2/auth' => Http::response(['access_token' => 'token']),
        'id.vk.com/oauth2/user_info' => Http::response(['user' => ['user_id' => 7, 'first_name' => 'Анна', 'email' => 'anna@mail.ru']]),
    ]);

    $this->get(route('auth.vk.redirect', ['provider' => $provider]));
    $this->get(route('auth.vk.callback', ['code' => 'c', 'device_id' => 'd', 'state' => session('vkid.state')]));

    expect(registrationGoals())->toHaveCount(1)
        ->and(registrationGoals()[0]['params'])->toBe(['method' => $method]);
})->with([
    'VK' => ['vkid', 'vk'],
    'Mail.ru' => ['mail_ru', 'mail_ru'],
]);

test('повторный вход через VK — не регистрация, цели нет', function () {
    config(['services.vkid.client_id' => '12345']);
    $user = User::factory()->create();
    $user->forceFill(['vk_id' => '7'])->save();
    Http::fake([
        'id.vk.com/oauth2/auth' => Http::response(['access_token' => 'token']),
        'id.vk.com/oauth2/user_info' => Http::response(['user' => ['user_id' => 7, 'email' => 'x@mail.ru']]),
    ]);

    $this->get(route('auth.vk.redirect'));
    $this->get(route('auth.vk.callback', ['code' => 'c', 'device_id' => 'd', 'state' => session('vkid.state')]));

    $this->assertAuthenticatedAs($user);
    expect(registrationGoals())->toBe([]);
});

test('регистрация через Google — способ google', function () {
    config(['services.google.client_id' => 'gid', 'services.google.client_secret' => 'gs']);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'g1', 'email' => 'a@gmail.com', 'email_verified' => true]),
    ]);

    $this->get(route('auth.google.redirect'));
    $this->get(route('auth.google.callback', ['code' => 'c', 'state' => session('google_auth.state')]));

    expect(registrationGoals())->toHaveCount(1)
        ->and(registrationGoals()[0]['params'])->toBe(['method' => 'google']);
});
