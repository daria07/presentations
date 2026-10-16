<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Метрики в админке: метки регистрации, что из людей вышло,
   фильтры, выгрузка.
*/

beforeEach(function () {
    $this->withoutVite();
    config(['admin.emails' => ['boss@example.com']]);
    $this->admin = User::factory()->create(['email' => 'boss@example.com']);
});

/** Пользователь с метками, презентациями и оплатами */
function visitor(array $utm, int $presentations = 0, array $payments = [], ?string $registeredAt = null): User
{
    $user = User::factory()->create();
    $user->forceFill([
        ...$utm,
        'created_at' => $registeredAt ? Carbon::parse($registeredAt, 'Europe/Moscow')->utc() : now(),
    ])->save();

    for ($i = 1; $i <= $presentations; $i++) {
        Presentation::create(['user_id' => $user->id, 'topic' => "Тема {$i}"]);
    }

    foreach ($payments as $amount) {
        $payment = Payment::create([
            'user_id' => $user->id,
            'provider' => 'fake',
            'provider_payment_id' => 'p-'.Str::random(8),
            'amount' => $amount,
            'credits_granted' => 1,
        ]);
        $payment->forceFill(['status' => PaymentStatus::Paid])->save();
    }

    return $user;
}

test('сводка по источникам: регистрации, презентации, оплаты и выручка', function () {
    visitor(['utm_source' => 'yandex', 'utm_medium' => 'cpc', 'utm_campaign' => 'school'], 2, [14900, 24900]);
    visitor(['utm_source' => 'yandex', 'utm_medium' => 'cpc', 'utm_campaign' => 'school'], 1);
    visitor(['utm_source' => 'yandex', 'utm_medium' => 'cpc', 'utm_campaign' => 'school']);
    visitor([], 1, [14900]);

    $this->actingAs($this->admin)
        ->get('/admin/metrics')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Metrics')
            // Администратор тоже пользователь без меток
            ->where('totals.users', 5)
            ->where('totals.made', 3)
            ->where('totals.paying', 2)
            ->where('totals.revenue', 14900 + 24900 + 14900)
            ->where('groups.0.source', 'yandex')
            ->where('groups.0.users', 3)
            ->where('groups.0.made', 2)
            ->where('groups.0.paying', 1)
            ->where('groups.0.revenue', 39800)
            ->where('options.source', ['yandex']));
});

test('фильтр по метке, «без меток», оплате и поиску', function () {
    $paid = visitor(['utm_source' => 'yandex', 'utm_campaign' => 'school'], 1, [14900]);
    visitor(['utm_source' => 'yandex', 'utm_campaign' => 'students']);
    visitor(['utm_source' => 'vk']);

    $count = fn (string $query) => $this->actingAs($this->admin)
        ->get('/admin/metrics?'.$query)
        ->assertOk();

    $count('source=yandex')->assertInertia(fn (Assert $p) => $p->where('users.total', 2));
    $count('source=yandex&campaign=school')->assertInertia(fn (Assert $p) => $p
        ->where('users.total', 1)
        ->where('users.items.0.email', $paid->email)
        ->where('users.items.0.paid', 14900)
        ->where('users.items.0.payments', 1));
    // Без меток — только администратор
    $count('source=__none')->assertInertia(fn (Assert $p) => $p->where('users.total', 1));
    $count('paid=yes')->assertInertia(fn (Assert $p) => $p->where('users.total', 1));
    $count('paid=no')->assertInertia(fn (Assert $p) => $p->where('users.total', 3));
    $count('search='.urlencode(strtoupper($paid->email)))->assertInertia(fn (Assert $p) => $p->where('users.total', 1));
});

test('фильтр по датам — сутки по Москве, «по» включительно', function () {
    visitor(['utm_source' => 'a'], registeredAt: '2026-10-10 00:30');
    visitor(['utm_source' => 'b'], registeredAt: '2026-10-09 23:30');
    visitor(['utm_source' => 'c'], registeredAt: '2026-10-11 23:59');

    $this->actingAs($this->admin)
        ->get('/admin/metrics?from=2026-10-10&to=2026-10-11')
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.users', 2)
            ->where('filters.from', '2026-10-10'));
});

test('сортировка по выручке', function () {
    visitor(['utm_source' => 'a'], 0, [14900]);
    $rich = visitor(['utm_source' => 'b'], 0, [59000]);

    $this->actingAs($this->admin)
        ->get('/admin/metrics?sort=revenue')
        ->assertInertia(fn (Assert $page) => $page->where('users.items.0.id', $rich->id));
});

test('выгрузка CSV с теми же фильтрами', function () {
    $user = visitor(['utm_source' => 'yandex', 'utm_campaign' => 'school'], 1, [14900]);
    visitor(['utm_source' => 'other-network']);

    $csv = $this->actingAs($this->admin)
        ->get('/admin/metrics/export?source=yandex')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->toContain($user->email)
        ->toContain('school')
        ->toContain(';149;')
        ->not->toContain('other-network');
});

test('посторонний метрики не видит', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/metrics')->assertNotFound();
    $this->actingAs($user)->get('/admin/metrics/export')->assertNotFound();
});

test('пробная, сколько презентаций, остаток и кнопка скидки — у каждого и в фильтрах', function () {
    $user = visitor(['utm_source' => 'yandex']);
    $user->forceFill([
        'trial_used' => true,
        'credits' => 2,
        'promo_variant' => 'b',
        'promo_shown_at' => now()->subDay(),
        'promo_clicked_at' => now()->subHours(20),
        'discount_used_at' => now()->subHours(19),
    ])->save();
    Presentation::create(['user_id' => $user->id, 'topic' => 'проба', 'paid_with' => 'trial']);
    Presentation::create(['user_id' => $user->id, 'topic' => 'купленная', 'paid_with' => 'credit']);
    Presentation::create(['user_id' => $user->id, 'topic' => 'открытая', 'paid_with' => 'unlocked']);

    $shown = visitor(['utm_source' => 'yandex']);
    $shown->forceFill(['promo_variant' => 'a', 'promo_shown_at' => now()])->save();

    $this->actingAs($this->admin)
        ->get('/admin/metrics?source=yandex&discount=used')
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.items.0.trialUsed', true)
            ->where('users.items.0.presentations', 3)
            ->where('users.items.0.trialPresentations', 1)
            ->where('users.items.0.paidPresentations', 2)
            ->where('users.items.0.credits', 2)
            ->where('users.items.0.discount.variant', 'b')
            ->where('users.items.0.discount.usedAt', fn ($v) => $v !== null));

    $count = fn (string $query) => $this->actingAs($this->admin)->get('/admin/metrics?source=yandex&'.$query);

    $count('discount=shown')->assertInertia(fn (Assert $p) => $p->where('users.total', 2));
    $count('discount=clicked')->assertInertia(fn (Assert $p) => $p->where('users.total', 1));
    $count('discount=unseen')->assertInertia(fn (Assert $p) => $p->where('users.total', 0));
    $count('trial=yes')->assertInertia(fn (Assert $p) => $p->where('users.total', 1));
    $count('trial=no')->assertInertia(fn (Assert $p) => $p->where('users.total', 1));
});

test('сохранение в редакторе считается правкой, и метрики это показывают', function () {
    Queue::fake();

    $user = visitor(['utm_source' => 'yandex']);
    $deck = Presentation::create([
        'user_id' => $user->id,
        'topic' => 'Тема',
        'outline' => ['title' => 'Было', 'slides' => [['layout' => 'title', 'heading' => 'Было']]],
    ]);
    Presentation::create(['user_id' => $user->id, 'topic' => 'Не трогали']);
    visitor(['utm_source' => 'yandex'], 1);

    foreach (['Стало', 'Стало ещё лучше'] as $title) {
        $this->actingAs($user)
            ->put(route('presentations.outline', $deck), [
                'title' => $title,
                'slides' => [['layout' => 'title', 'heading' => $title]],
            ])
            ->assertSessionHasNoErrors();
    }

    expect($deck->refresh()->edits_count)->toBe(2)
        ->and($deck->edited_at)->not->toBeNull();

    $this->actingAs($this->admin)
        ->get('/admin/metrics?source=yandex&edited=yes')
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.items.0.editedPresentations', 1)
            ->where('users.items.0.edits', 2));

    $this->actingAs($this->admin)
        ->get('/admin/metrics?source=yandex&edited=no')
        ->assertInertia(fn (Assert $page) => $page->where('users.total', 1));
});
