<?php

use App\Models\User;
use App\Services\Auth\Google;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Вход и регистрация через Google. Google подменён: проверяем PKCE,
   state и кого впускаем по его ответу.
*/

beforeEach(function () {
    $this->withoutVite();
    config(['services.google.client_id' => 'gid', 'services.google.client_secret' => 'gsecret']);
});

function fakeGoogle(array $user): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token', 'id_token' => 'jwt']),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response($user),
    ]);
}

function googleLogin(): TestResponse
{
    test()->get(route('auth.google.redirect'));

    return test()->get(route('auth.google.callback', ['code' => 'code', 'state' => session('google_auth.state')]));
}

test('кнопка ведёт на Google с PKCE, state и выбором аккаунта', function () {
    $url = $this->get(route('auth.google.redirect'))->headers->get('Location');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->and($query['client_id'])->toBe('gid')
        ->and($query['scope'])->toBe('openid email profile')
        ->and($query['code_challenge'])->toBe(Google::challenge(session('google_auth.verifier')))
        ->and($query['state'])->toBe(session('google_auth.state'))
        ->and($query['prompt'])->toBe('select_account')
        ->and($query['redirect_uri'])->toBe(route('auth.google.callback'));
});

test('новый человек — аккаунт создаётся, и он внутри', function () {
    fakeGoogle(['sub' => 'g-1', 'name' => 'Анна Петрова', 'email' => 'Anna@Gmail.com', 'email_verified' => true]);

    googleLogin()->assertRedirect(config('fortify.home'));

    $user = User::where('google_id', 'g-1')->firstOrFail();
    expect($user->email)->toBe('anna@gmail.com')->and($user->name)->toBe('Анна Петрова');
    $this->assertAuthenticatedAs($user);

    Http::assertSent(fn ($r) => $r->url() === 'https://oauth2.googleapis.com/token'
        && $r['client_secret'] === 'gsecret'
        && strlen((string) $r['code_verifier']) === 64);
});

test('второй вход — тот же аккаунт', function () {
    $user = User::factory()->create();
    $user->forceFill(['google_id' => 'g-1'])->save();
    fakeGoogle(['sub' => 'g-1', 'email' => 'other@gmail.com', 'email_verified' => true]);

    googleLogin();

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

test('почта уже зарегистрирована — Google привязывается к аккаунту', function () {
    $user = User::factory()->create(['email' => 'anna@gmail.com']);
    fakeGoogle(['sub' => 'g-1', 'email' => 'anna@gmail.com', 'email_verified' => true]);

    googleLogin();

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->google_id)->toBe('g-1')->and(User::count())->toBe(1);
});

test('неподтверждённую почту не принимаем и к чужому аккаунту не привязываем', function () {
    $owner = User::factory()->create(['email' => 'anna@gmail.com']);
    fakeGoogle(['sub' => 'g-1', 'email' => 'anna@gmail.com', 'email_verified' => false]);

    googleLogin()->assertRedirect(route('login'))->assertSessionHas('status');

    $this->assertGuest();
    expect($owner->refresh()->google_id)->toBeNull();
});

test('чужой state — не впускаем и к Google не ходим', function () {
    Http::fake();

    $this->get(route('auth.google.redirect'));
    $this->get(route('auth.google.callback', ['code' => 'code', 'state' => 'forged']))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    Http::assertNothingSent();
});

test('Google отказал в токене — возвращаем на вход', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

    googleLogin()->assertRedirect(route('login'))->assertSessionHas('status');

    $this->assertGuest();
});

test('без ключей кнопки нет и адрес не работает', function () {
    config(['services.google.client_secret' => null]);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('google', false));
    $this->get(route('auth.google.redirect'))->assertNotFound();
});

test('с ключами кнопка есть на входе и регистрации', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('google', true));
    $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('google', true));
});
