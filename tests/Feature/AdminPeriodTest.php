<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Разбивка сводки по периодам. Сутки считаются по Москве, а база
   в UTC — поэтому главное здесь проверить границы около полуночи.
*/

function adminUser(): User
{
    $admin = User::factory()->create(['email' => 'boss@example.com']);
    config(['admin.emails' => ['boss@example.com'], 'admin.timezone' => 'Europe/Moscow']);

    return $admin;
}

/** Пользователь, «зарегистрированный» в указанный момент по Москве */
function registeredAt(string $moscow): User
{
    $user = User::factory()->create();
    $user->forceFill(['created_at' => Carbon::parse($moscow, 'Europe/Moscow')->utc()])->save();

    return $user;
}

beforeEach(function () {
    $this->withoutVite();
    // 10 октября, 01:30 по Москве = 9 октября, 22:30 UTC
    $this->travelTo(Carbon::parse('2026-10-10 01:30', 'Europe/Moscow'));
});

test('сегодня — это сегодня по Москве, а не по UTC', function () {
    $admin = adminUser();               // создан «сейчас» — сегодня
    registeredAt('2026-10-10 00:10');   // сегодня по Москве, вчера по UTC
    registeredAt('2026-10-09 23:50');   // вчера
    registeredAt('2026-10-05 12:00');   // в пределах 7 дней
    registeredAt('2026-09-01 12:00');   // давно

    $users = fn (string $period) => $this->actingAs($admin)
        ->get('/admin?period='.$period)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('period', $period))
        ->viewData('page')['props']['cards']['users'];

    expect($users('today'))->toBe(2)
        ->and($users('yesterday'))->toBe(1)
        ->and($users('week'))->toBe(4)
        ->and($users('all'))->toBe(5);
});

test('сравнение идёт с таким же отрезком перед выбранным', function () {
    $admin = adminUser();
    registeredAt('2026-10-09 10:00');   // вчера
    registeredAt('2026-10-08 10:00');   // позавчера
    registeredAt('2026-10-08 11:00');   // позавчера

    $this->actingAs($admin)
        ->get('/admin?period=yesterday')
        ->assertInertia(fn (Assert $page) => $page
            ->where('cards.users', 1)
            ->where('previous.users', 2));
});

test('выручка и статусы тоже режутся периодом', function () {
    $admin = adminUser();
    $user = registeredAt('2026-09-01 12:00');

    foreach (['2026-10-10 00:30' => 19900, '2026-10-09 12:00' => 49900] as $at => $amount) {
        $payment = Payment::create([
            'user_id' => $user->id,
            'provider' => 'fake',
            'provider_payment_id' => 'p-'.$amount,
            'amount' => $amount,
            'credits_granted' => 1,
        ]);
        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'created_at' => Carbon::parse($at, 'Europe/Moscow')->utc(),
        ])->save();
    }

    $deck = Presentation::create(['user_id' => $user->id, 'topic' => 'x']);
    $deck->forceFill(['created_at' => Carbon::parse('2026-10-09 12:00', 'Europe/Moscow')->utc()])->save();

    $this->actingAs($admin)
        ->get('/admin?period=today')
        ->assertInertia(fn (Assert $page) => $page
            ->where('cards.revenue', 19900)
            ->where('cards.presentations', 0)
            ->where('previous.revenue', 49900)
            ->where('previous.presentations', 1));
});

test('неизвестный период — всё время, без сравнения', function () {
    $this->actingAs(adminUser())
        ->get('/admin?period=../../etc')
        ->assertInertia(fn (Assert $page) => $page
            ->where('period', 'all')
            ->where('previous', null));
});

test('посторонний сводку не видит', function () {
    adminUser();

    $this->actingAs(User::factory()->create())
        ->get('/admin?period=today')
        ->assertNotFound();
});
