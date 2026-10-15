<?php

use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
   Последние генерации в сводке админки: метка оплаты, фильтры
   по оплате и статусу, постранично.
*/

beforeEach(function () {
    $this->withoutVite();
    config(['admin.emails' => ['boss@example.com']]);
    $this->admin = User::factory()->create(['email' => 'boss@example.com']);
});

function generation(?string $paidWith, PresentationStatus $status = PresentationStatus::Ready): Presentation
{
    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'Тема '.($paidWith ?? 'без оплаты'),
        'paid_with' => $paidWith,
    ]);
    $presentation->forceFill(['status' => $status])->save();

    return $presentation;
}

test('у каждой генерации видно, оплаченная она или пробная', function () {
    generation('trial');
    generation('credit');
    generation('unlocked');

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('recent.total', 3)
            ->where('recent.access', 'all')
            ->where('recent.items.0.access', 'unlocked')
            ->where('recent.items.1.access', 'paid')
            ->where('recent.items.2.access', 'trial'));
});

test('фильтр по оплате: оплаченные — купленные и открытые, пробные — только пробные', function () {
    generation('trial');
    generation('credit');
    generation('unlocked');
    generation(null, PresentationStatus::Asking);

    $this->actingAs($this->admin)
        ->get('/admin?recent_access=paid')
        ->assertInertia(fn (Assert $page) => $page
            ->where('recent.access', 'paid')
            ->where('recent.total', 2));

    $this->actingAs($this->admin)
        ->get('/admin?recent_access=trial')
        ->assertInertia(fn (Assert $page) => $page
            ->where('recent.total', 1)
            ->where('recent.items.0.access', 'trial'));
});

test('фильтр по статусу и неизвестный фильтр', function () {
    generation('credit');
    generation('credit', PresentationStatus::Failed);
    generation('trial', PresentationStatus::Generating);

    $this->actingAs($this->admin)
        ->get('/admin?recent_status=failed')
        ->assertInertia(fn (Assert $page) => $page
            ->where('recent.status', 'failed')
            ->where('recent.total', 1));

    $this->actingAs($this->admin)
        ->get('/admin?recent_status=working&recent_access=trial')
        ->assertInertia(fn (Assert $page) => $page->where('recent.total', 1));

    // Чужое значение — как «все», а не ошибка
    $this->actingAs($this->admin)
        ->get('/admin?recent_status=nope&recent_access=nope')
        ->assertInertia(fn (Assert $page) => $page
            ->where('recent.status', 'all')
            ->where('recent.access', 'all')
            ->where('recent.total', 3));
});

test('генерации листаются, фильтр сохраняется в ссылках', function () {
    foreach (range(1, 14) as $i) {
        generation('credit');
    }
    generation('trial');

    $this->actingAs($this->admin)
        ->get('/admin?recent_access=paid')
        ->assertInertia(fn (Assert $page) => $page
            ->where('recent.lastPage', 2)
            ->has('recent.items', 12)
            ->where('recent.nextUrl', fn (string $url) => str_contains($url, 'recent_access=paid')
                && str_contains($url, 'recent_page=2')));

    // Листание отдаёт только этот блок
    $this->actingAs($this->admin)
        ->get('/admin?recent_access=paid&recent_page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('recent', fn (Assert $reload) => $reload
                ->where('recent.currentPage', 2)
                ->has('recent.items', 2)
                ->missing('cards')));
});
