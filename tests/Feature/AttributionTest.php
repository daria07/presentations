<?php

use App\Models\User;
use App\Support\Attribution;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\Features;

/*
   Кука уходит в запрос через withCookie, а он шифрует значение сам —
   подкладываем обычный JSON. А вот в ответе кука уже зашифрована
   EncryptCookies, с префиксом из имени куки, и читать её приходится
   тем же способом, каким она собрана.
*/
function attributionCookie(array $data): string
{
    return (string) json_encode($data, JSON_UNESCAPED_UNICODE);
}

function readAttribution($response): array
{
    $cookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === Attribution::COOKIE);

    expect($cookie)->not->toBeNull();

    $value = CookieValuePrefix::remove(
        Crypt::decrypt($cookie->getValue(), false)
    );

    return json_decode($value, true);
}

test('метки из адреса запоминаются в куке', function () {
    $response = $this->get('/?utm_source=yandex&utm_medium=cpc&utm_campaign=poisk&yclid=123');

    $data = readAttribution($response);

    expect($data['utm_source'])->toBe('yandex')
        ->and($data['utm_medium'])->toBe('cpc')
        ->and($data['utm_campaign'])->toBe('poisk')
        ->and($data['click_id'])->toBe('123')
        ->and($data['landing_url'])->toContain('utm_source=yandex');
});

test('без меток и внешнего перехода кука не ставится', function () {
    $response = $this->get('/');

    $names = collect($response->headers->getCookies())
        ->map(fn ($c) => $c->getName());

    expect($names)->not->toContain(Attribution::COOKIE);
});

test('первый источник не перетирается следующими заходами', function () {
    $response = $this
        ->withCookie(Attribution::COOKIE, attributionCookie([
            'utm_source' => 'yandex',
            'utm_campaign' => 'poisk',
        ]))
        ->get('/?utm_source=vk&utm_campaign=target');

    $names = collect($response->headers->getCookies())
        ->map(fn ($c) => $c->getName());

    expect($names)->not->toContain(Attribution::COOKIE);
});

test('метки переезжают в аккаунт при регистрации', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this
        ->withCookie(Attribution::COOKIE, attributionCookie([
            'utm_source' => 'yandex',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'poisk',
            'click_id' => '123',
        ]))
        ->post(route('register.store'), [
            'name' => 'Тест',
            'email' => 'utm@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $user = User::where('email', 'utm@example.com')->firstOrFail();

    expect($user->utm_source)->toBe('yandex')
        ->and($user->utm_campaign)->toBe('poisk')
        ->and($user->click_id)->toBe('123');
});

test('метки нельзя подставить формой регистрации', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Тест',
        'email' => 'fake@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'utm_source' => 'подделка',
    ]);

    expect(User::where('email', 'fake@example.com')->firstOrFail()->utm_source)
        ->toBeNull();
});
