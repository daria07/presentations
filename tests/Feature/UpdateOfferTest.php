<?php

use App\Mail\UpdateNews;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\User;
use App\Services\Billing\Billing;
use App\Services\Billing\Discount;
use App\Services\Billing\Package;
use App\Services\Billing\UpdateOffer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/*
   «Мы обновились»: скидка на сайте давним пользователям и служебное
   письмо о новостях с отпиской.
*/

beforeEach(function () {
    $this->withoutVite();
    config([
        'billing.provider' => 'fake',
        'billing.discount' => ['percent' => 30, 'hours' => 48],
        'billing.update_offer' => [
            'percent' => 50,
            'days' => 7,
            'registered_before' => '2026-10-13 01:47',
            'ends_at' => null,
        ],
    ]);
});

function registered(string $moscowTime, array $extra = []): User
{
    $user = User::factory()->create();
    $user->forceFill([
        'created_at' => Carbon::parse($moscowTime, 'Europe/Moscow')->utc(),
        ...$extra,
    ])->save();

    return $user->refresh();
}

function offerOnList(User $user): mixed
{
    return test()->actingAs($user)
        ->get(route('presentations.index'))
        ->assertOk()
        ->viewData('page')['props']['updateOffer'];
}

test('скидку видят только зарегистрированные до обновления, срок идёт с первого показа', function () {
    $old = registered('2026-10-13 01:46');
    $new = registered('2026-10-13 01:48');

    expect(offerOnList($new))->toBeNull();

    $offer = offerOnList($old);

    expect($offer['percent'])->toBe(50)
        ->and($old->refresh()->update_offer_at)->not->toBeNull()
        ->and(round($old->update_offer_at->diffInDays($old->update_offer_until)))->toEqual(7.0);

    // Повторный показ срок не продлевает
    $until = $old->update_offer_until;
    $this->travel(2)->days();
    offerOnList($old->refresh());

    expect($old->refresh()->update_offer_until->equalTo($until))->toBeTrue();
});

test('пока идёт акция, кнопки скидки за кончившиеся генерации нет', function () {
    $user = registered('2026-10-01 12:00', ['credits' => 0, 'trial_used' => true]);

    $page = $this->actingAs($user)->get(route('presentations.index'))->viewData('page')['props'];

    expect($page['updateOffer'])->not->toBeNull()
        ->and($page['promo'])->toBeNull();
});

test('цена на витрине и в платеже — со скидкой акции, после оплаты она погашена', function () {
    $user = registered('2026-10-01 12:00');
    offerOnList($user);
    $user->refresh();

    $package = Package::find('single');
    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertInertia(fn ($page) => $page->where('discount.percent', 50));

    Billing::make()->start($user, $package, '/');
    $payment = Payment::latest('id')->first();

    expect($payment->discount_percent)->toBe(50)
        ->and($payment->amount)->toBe(Discount::apply($package->amount, 50))
        ->and($payment->payload['discount'])->toBe('update');

    Billing::make()->settle($payment->provider_payment_id, true);
    $user->refresh();

    expect($user->update_offer_used_at)->not->toBeNull()
        // Скидка кнопки при этом не тронута — это другая акция
        ->and($user->discount_used_at)->toBeNull()
        ->and(offerOnList($user))->toBeNull();
});

test('из двух скидок при оплате берётся большая', function () {
    $user = registered('2026-10-01 12:00', ['credits' => 0, 'trial_used' => true]);
    $user->forceFill(['promo_clicked_at' => now(), 'discount_until' => now()->addDay()])->save();

    expect(Discount::current($user)['percent'])->toBe(30);

    offerOnList($user);

    expect(Discount::current($user->refresh()))->toMatchArray(['percent' => 50, 'source' => 'update']);
});

/** Презентация, сделанная в это время по Москве */
function madeAt(User $user, string $moscowTime): void
{
    $presentation = Presentation::create(['user_id' => $user->id, 'topic' => 'Тема']);
    $presentation->forceFill(['created_at' => Carbon::parse($moscowTime, 'Europe/Moscow')->utc()])->save();
}

test('письмо: тем, чьи презентации были без картинок, не отписавшимся и по одному разу', function () {
    Mail::fake();

    // Делал презентацию до обновления — почта не подтверждена, но
    // подтверждение на сайте выключено, и это не помеха
    $old = registered('2026-10-01 12:00', ['email_verified_at' => null]);
    madeAt($old, '2026-10-02 12:00');

    // Зарегистрировался до, но первую презентацию сделал уже с картинками
    madeAt(registered('2026-10-01 12:00'), '2026-10-15 12:00');
    // Не делал ни одной
    registered('2026-10-01 12:00');
    // После обновления
    madeAt(registered('2026-10-20 12:00'), '2026-10-20 13:00');
    // Отписался
    madeAt(registered('2026-10-01 12:00', ['news_unsubscribed_at' => now()]), '2026-10-02 12:00');

    $this->artisan('news:update')->assertSuccessful();
    Mail::assertNothingQueued();

    $this->artisan('news:update --send --force')->assertSuccessful();
    $this->artisan('news:update --send --force')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(UpdateNews::class, fn ($mail) => $mail->hasTo($old->email));
    expect(DB::table('mail_sends')->count())->toBe(1);
});

test('с письмом получатель снова получает пробную, остальные — нет', function () {
    Mail::fake();

    $old = registered('2026-10-01 12:00', ['trial_used' => true, 'credits' => 0]);
    madeAt($old, '2026-10-02 12:00');
    $unsubscribed = registered('2026-10-01 12:00', ['trial_used' => true, 'news_unsubscribed_at' => now()]);
    madeAt($unsubscribed, '2026-10-02 12:00');

    // Пробное письмо пробную не возвращает
    $this->artisan('news:update --test='.$old->email)->assertSuccessful();
    expect($old->refresh()->trial_used)->toBeTrue();

    $this->artisan('news:update --send --force')->assertSuccessful();

    expect($old->refresh()->trial_used)->toBeFalse()
        ->and($unsubscribed->refresh()->trial_used)->toBeTrue();

    // Скидка 50% при этом на месте
    expect(UpdateOffer::eligible($old))->toBeTrue();
});

test('в письме нет скидки, есть отписка и реквизиты', function () {
    $user = registered('2026-10-01 12:00');
    $html = (new UpdateNews($user))->render();

    expect($html)->toContain('Что нового')
        ->toContain('/unsubscribe/'.$user->id)
        ->toContain((string) config('legal.inn'))
        // Служебное письмо: ни скидки, ни цены
        ->not->toContain('скидк')
        ->not->toContain('−50')
        ->not->toContain('₽');
});

test('отписка по ссылке: страница с кнопкой, отписывает только POST', function () {
    $user = registered('2026-10-01 12:00');
    $url = URL::signedRoute('news.unsubscribe', ['user' => $user->id]);

    $this->get($url)->assertOk()->assertSee('Отписаться');
    expect($user->refresh()->news_unsubscribed_at)->toBeNull();

    $this->post($url)->assertOk()->assertSee('Вы отписались');
    expect($user->refresh()->news_unsubscribed_at)->not->toBeNull();

    // Без подписи — нельзя
    $this->post('/unsubscribe/'.$user->id)->assertForbidden();
});

test('подсказка «Открыть за …» показывает цену самого дешёвого пакета со скидкой', function () {
    $fresh = registered('2026-10-20 12:00');

    $this->actingAs($fresh)
        ->get(route('presentations.create'))
        ->assertInertia(fn ($page) => $page->where('entryPrice', '149'));

    // Давний пользователь со скидкой акции 50%: 149 → 74 (вниз до рубля)
    $old = registered('2026-10-01 12:00');
    offerOnList($old);

    $this->actingAs($old->refresh())
        ->get(route('presentations.create'))
        ->assertInertia(fn ($page) => $page->where('entryPrice', '74'));
});
