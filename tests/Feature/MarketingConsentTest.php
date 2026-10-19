<?php

use App\Models\User;
use App\Support\MarketingConsent;
use Illuminate\Support\Facades\URL;

/*
   Согласие на рекламные письма: отдельное, добровольное, с доказательствами
   и отзывом в любой момент.
*/

function registerWith(array $extra = []): User
{
    test()->post(route('register.store'), [
        'name' => 'Тест',
        'email' => 'consent@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        ...$extra,
    ])->assertSessionHasNoErrors();

    return User::where('email', 'consent@example.com')->firstOrFail();
}

test('без галочки регистрация проходит, согласия нет', function () {
    $user = registerWith();

    expect(MarketingConsent::has($user))->toBeFalse()
        ->and($user->marketing_consent_at)->toBeNull();
});

test('с галочкой — согласие с датой, IP, источником и версией текста', function () {
    $user = registerWith(['marketing' => 'on']);

    expect(MarketingConsent::has($user))->toBeTrue()
        ->and($user->marketing_consent_source)->toBe('register')
        ->and($user->marketing_consent_version)->toBe(MarketingConsent::VERSION)
        ->and($user->marketing_consent_ip)->not->toBeNull();
});

test('на странице регистрации галочка есть и текст согласия с сервера', function () {
    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page->where('marketingText', MarketingConsent::TEXT));
});

test('в настройках согласие можно дать и отозвать, история сохраняется', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/marketing', ['marketing' => true])->assertRedirect();
    $user->refresh();

    expect(MarketingConsent::has($user))->toBeTrue()
        ->and($user->marketing_consent_source)->toBe('settings');

    $this->actingAs($user)->patch('/settings/marketing', ['marketing' => false])->assertRedirect();
    $user->refresh();

    expect(MarketingConsent::has($user))->toBeFalse()
        // Когда давал — помним: это история, а не текущее состояние
        ->and($user->marketing_consent_at)->not->toBeNull()
        ->and($user->marketing_revoked_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('marketing', false));
});

test('«Не получать такие письма» отзывает и согласие на рекламу', function () {
    $user = User::factory()->create();
    $user->forceFill(['marketing_consent_at' => now()])->save();

    $this->post(URL::signedRoute('news.unsubscribe', ['user' => $user->id]))->assertOk();

    expect(MarketingConsent::has($user->refresh()))->toBeFalse()
        ->and($user->news_unsubscribed_at)->not->toBeNull();
});
