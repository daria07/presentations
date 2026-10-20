<?php

use App\Models\User;
use App\Services\Auth\VkId;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Вход и регистрация через VK ID. VK подменён: проверяем, что мы
   с ним честно проходим PKCE и state, и кого впускаем по его ответу.
*/

beforeEach(function () {
    $this->withoutVite();
    config(['services.vkid.client_id' => '12345']);
});

/** Ответ VK: токен и профиль */
function fakeVk(array $user): void
{
    Http::fake([
        'id.vk.com/oauth2/auth' => Http::response(['access_token' => 'token', 'user_id' => $user['user_id'] ?? 1]),
        'id.vk.com/oauth2/user_info' => Http::response(['user' => $user]),
    ]);
}

/** Кнопка → id.vk.com → callback с тем же state */
function vkLogin(array $extra = []): TestResponse
{
    test()->get(route('auth.vk.redirect'));
    $state = session('vkid.state');

    return test()->get(route('auth.vk.callback', [
        'code' => 'code', 'device_id' => 'device', 'state' => $state, ...$extra,
    ]));
}

test('кнопка ведёт на VK с PKCE и state', function () {
    $response = $this->get(route('auth.vk.redirect'));

    $url = $response->headers->get('Location');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://id.vk.com/authorize?')
        ->and($query['client_id'])->toBe('12345')
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['code_challenge'])->toBe(VkId::challenge(session('vkid.verifier')))
        ->and($query['state'])->toBe(session('vkid.state'))
        ->and($query['redirect_uri'])->toBe(route('auth.vk.callback'));
});

test('кнопка Mail ведёт на тот же VK ID, но через Mail.ru', function () {
    $mail = $this->get(route('auth.vk.redirect', ['provider' => 'mail_ru']))->headers->get('Location');
    $odd = $this->get(route('auth.vk.redirect', ['provider' => 'evil']))->headers->get('Location');

    parse_str((string) parse_url($mail, PHP_URL_QUERY), $m);
    parse_str((string) parse_url($odd, PHP_URL_QUERY), $o);

    expect($m['provider'])->toBe('mail_ru')
        ->and($o['provider'])->toBe('vkid');
});

test('новый человек с почтой — аккаунт создаётся, и он внутри', function () {
    fakeVk(['user_id' => 777, 'first_name' => 'Анна', 'last_name' => 'Петрова', 'email' => 'Anna@Mail.ru']);

    vkLogin()->assertRedirect(config('fortify.home'));

    $user = User::where('vk_id', '777')->firstOrFail();
    expect($user->email)->toBe('anna@mail.ru')
        ->and($user->name)->toBe('Анна Петрова');
    $this->assertAuthenticatedAs($user);

    // Обмен кода на токен — с секретом PKCE из сессии
    Http::assertSent(fn ($r) => $r->url() === 'https://id.vk.com/oauth2/auth'
        && $r['grant_type'] === 'authorization_code'
        && $r['device_id'] === 'device'
        && strlen((string) $r['code_verifier']) === 64);
});

test('второй вход — тот же аккаунт, а не новый', function () {
    $user = User::factory()->create();
    $user->forceFill(['vk_id' => '777'])->save();
    fakeVk(['user_id' => 777, 'first_name' => 'Анна', 'email' => 'other@mail.ru']);

    vkLogin()->assertRedirect(config('fortify.home'));

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

test('почта уже зарегистрирована — VK привязывается к этому аккаунту', function () {
    $user = User::factory()->create(['email' => 'anna@mail.ru']);
    fakeVk(['user_id' => 777, 'first_name' => 'Анна', 'email' => 'ANNA@mail.ru']);

    vkLogin();

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->vk_id)->toBe('777')
        ->and(User::count())->toBe(1);
});

test('VK не отдал почту — спрашиваем её, и аккаунт создаётся после', function () {
    fakeVk(['user_id' => 777, 'first_name' => 'Анна']);

    vkLogin()->assertRedirect(route('auth.vk.email'));
    $this->assertGuest();

    $this->get(route('auth.vk.email'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/VkEmail')->where('name', 'Анна'));

    $this->post(route('auth.vk.email.store'), ['email' => ' Anna@Mail.ru '])
        ->assertRedirect(config('fortify.home'));

    $user = User::where('vk_id', '777')->firstOrFail();
    expect($user->email)->toBe('anna@mail.ru');
    $this->assertAuthenticatedAs($user);
});

test('почту, введённую руками, к чужому аккаунту не привязываем', function () {
    $owner = User::factory()->create(['email' => 'anna@mail.ru']);
    fakeVk(['user_id' => 777, 'first_name' => 'Анна']);

    vkLogin();

    $this->post(route('auth.vk.email.store'), ['email' => 'anna@mail.ru'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($owner->refresh()->vk_id)->toBeNull();
});

test('чужой state — не впускаем и к VK не ходим', function () {
    Http::fake();

    $this->get(route('auth.vk.redirect'));
    $this->get(route('auth.vk.callback', ['code' => 'code', 'device_id' => 'device', 'state' => 'forged']))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    Http::assertNothingSent();
});

test('VK отказал в токене — возвращаем на вход с объяснением', function () {
    Http::fake(['id.vk.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

    vkLogin()->assertRedirect(route('login'))->assertSessionHas('status');

    $this->assertGuest();
});

test('без номера приложения кнопки нет, и адрес не работает', function () {
    config(['services.vkid.client_id' => null]);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('vkId', false));
    $this->get(route('auth.vk.redirect'))->assertNotFound();
});

test('с номером приложения кнопка есть на входе и регистрации', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('vkId', true));
    $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('vkId', true));
});
