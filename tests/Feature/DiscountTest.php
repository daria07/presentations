<?php

use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\Billing;
use App\Services\Billing\Discount;
use App\Services\Billing\Package;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Скидка для тех, у кого кончились генерации, и A/B-тест текста
   кнопки. Главное: кнопка видна только кому надо, скидка честно
   считается при оплате, действует 48 часов и один раз.
*/

beforeEach(function () {
    $this->withoutVite();
    config([
        'billing.provider' => 'fake',
        'billing.discount' => ['percent' => 30, 'hours' => 48],
        'admin.emails' => ['boss@example.com'],
    ]);
});

function outOfCredits(): User
{
    $user = User::factory()->create();
    $user->forceFill(['credits' => 0, 'trial_used' => true])->save();

    return $user;
}

function promoOnList(User $user): mixed
{
    return test()->actingAs($user)
        ->get(route('presentations.index'))
        ->assertOk()
        ->viewData('page')['props']['promo'];
}

test('кнопку видит только тот, у кого кончились и платные, и пробная', function () {
    $withCredits = User::factory()->create();
    $withCredits->forceFill(['credits' => 3, 'trial_used' => true])->save();

    $withTrial = User::factory()->create();
    $withTrial->forceFill(['credits' => 0, 'trial_used' => false])->save();

    expect(promoOnList($withCredits))->toBeNull()
        ->and(promoOnList($withTrial))->toBeNull()
        ->and(promoOnList(outOfCredits()))->not->toBeNull();
});

test('вариант закрепляется за человеком и делит поток пополам', function () {
    $users = collect(range(1, 6))->map(fn () => outOfCredits());

    $first = $users->map(fn (User $u) => promoOnList($u)['variant']);
    $again = $users->map(fn (User $u) => promoOnList($u->refresh())['variant']);

    expect($again->all())->toBe($first->all())
        ->and($first->countBy()->sortKeys()->all())->toBe(['a' => 3, 'b' => 3]);

    $user = $users->first()->refresh();
    expect(promoOnList($user)['text'])->toBe(Discount::VARIANTS[$user->promo_variant])
        ->and($user->promo_shown_at)->not->toBeNull()
        ->and($user->promo_clicked_at)->toBeNull();
});

test('клик запускает скидку на 48 часов и ведёт на тарифы со скидочными ценами', function () {
    $user = outOfCredits();

    $this->actingAs($user)
        ->post(route('billing.discount'))
        ->assertRedirect(route('billing.index'));

    $user->refresh();
    expect($user->promo_clicked_at)->not->toBeNull()
        ->and((int) round(now()->diffInHours($user->discount_until)))->toBe(48);

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('discount.percent', 30)
            // «Рабочий»: 599 ₽ → 419 ₽, старая цена зачёркнута
            ->where('packages.1.key', 'work')
            ->where('packages.1.amount', '419')
            ->where('packages.1.oldAmount', '599'));
});

test('повторный клик не продлевает скидку', function () {
    $user = outOfCredits();
    $this->actingAs($user)->post(route('billing.discount'));
    $until = $user->refresh()->discount_until;

    $this->travel(10)->hours();
    $this->actingAs($user)->post(route('billing.discount'));

    expect($user->refresh()->discount_until->equalTo($until))->toBeTrue();
});

test('тот, кому кнопка не положена, скидку кликом не получит', function () {
    $user = User::factory()->create();
    $user->forceFill(['credits' => 5, 'trial_used' => true])->save();

    $this->actingAs($user)->post(route('billing.discount'));

    expect($user->refresh()->discount_until)->toBeNull();
});

test('оплата со скидкой: цена считается на сервере, скидка одноразовая', function () {
    $user = outOfCredits();
    $this->actingAs($user)->post(route('billing.discount'));

    Billing::make()->start($user->refresh(), Package::find('work'), '/');
    $payment = Payment::latest('id')->first();

    expect($payment->amount)->toBe(41900)
        ->and($payment->discount_percent)->toBe(30);

    Billing::make()->settle($payment->provider_payment_id, true);

    $user->refresh();
    expect($user->credits)->toBe(20)
        ->and($user->discount_used_at)->not->toBeNull()
        ->and(Discount::active($user))->toBeFalse();

    // Следующая покупка — по обычной цене
    Billing::make()->start($user, Package::find('work'), '/');
    expect(Payment::latest('id')->first()->amount)->toBe(59900);
});

test('после 48 часов скидка гаснет, и кнопка больше не предлагается', function () {
    $user = outOfCredits();
    $this->actingAs($user)->post(route('billing.discount'));

    $this->travel(49)->hours();
    $user->refresh();

    Billing::make()->start($user, Package::find('work'), '/');

    expect(Payment::latest('id')->first()->amount)->toBe(59900)
        ->and(promoOnList($user))->toBeNull();
});

test('админка считает показы, клики и оплаты по вариантам', function () {
    $admin = User::factory()->create(['email' => 'boss@example.com']);

    $users = collect(range(1, 4))->map(fn () => outOfCredits());
    $users->each(fn (User $u) => promoOnList($u));

    // Кликнули двое из группы «a», один из них оплатил
    $groupA = $users->map->refresh()->where('promo_variant', 'a')->values();
    $groupA->each(fn (User $u) => $this->actingAs($u)->post(route('billing.discount')));

    Billing::make()->start($groupA[0]->refresh(), Package::find('start'), '/');
    Billing::make()->settle(Payment::latest('id')->first()->provider_payment_id, true);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('promo.variants.0.key', 'a')
            ->where('promo.variants.0.shown', 2)
            ->where('promo.variants.0.clicked', 2)
            ->where('promo.variants.0.buyers', 1)
            ->where('promo.variants.0.revenue', 13900)
            ->where('promo.variants.1.key', 'b')
            ->where('promo.variants.1.shown', 2)
            ->where('promo.variants.1.clicked', 0));
});

test('цена со скидкой округляется вниз до рубля', function () {
    expect(Discount::apply(59900))->toBe(41900)
        ->and(Discount::apply(19900))->toBe(13900)
        ->and(Discount::apply(149900))->toBe(104900);
});
