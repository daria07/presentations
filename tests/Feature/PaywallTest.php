<?php

use App\Enums\PresentationStatus;
use App\Jobs\PrepareQuestions;
use App\Jobs\RenderPresentation;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\User;
use App\Services\Billing\Billing;
use App\Services\Billing\Package;
use App\Services\Deck\DeckRenderer;
use Illuminate\Support\Facades\Queue;

/*
   Пробная версия: водяной знак, без скачивания, ссылки, правки и речи,
   не больше 8 слайдов. Открывается одной купленной генерацией.
*/

function trialDeck(int $credits = 0): Presentation
{
    $user = User::factory()->create();
    $user->forceFill(['credits' => $credits, 'trial_used' => true])->save();

    $presentation = Presentation::create([
        'user_id' => $user->id,
        'topic' => 'Пётр I',
        'outline' => ['title' => 'Пётр I', 'slides' => [
            ['layout' => 'title', 'heading' => 'Пётр I'],
            ['layout' => 'closing', 'heading' => 'Итоги'],
        ]],
    ]);
    $presentation->forceFill(['status' => PresentationStatus::Ready, 'file_path' => 'presentations/1.pdf'])->save();

    return $presentation;
}

test('купленная генерация тратится раньше пробной', function () {
    $user = User::factory()->create();
    $user->forceFill(['credits' => 1, 'trial_used' => false])->save();

    expect($user->spendCredit())->toBe('credit')
        ->and($user->refresh()->credits)->toBe(0)
        ->and($user->trial_used)->toBeFalse();
});

test('пробная версия закрыта: скачивание, речь и публичная ссылка', function () {
    $presentation = trialDeck();

    expect($presentation->isLocked())->toBeTrue();

    $this->actingAs($presentation->user);
    $this->get(route('presentations.download', $presentation))->assertForbidden();
    // Правка открыта и в пробной версии
    $this->get(route('presentations.edit', $presentation))->assertOk();
    $this->get(route('presentations.speech', $presentation))->assertForbidden();
    $this->get('/p/'.$presentation->share_token)->assertNotFound();
});

test('на слайдах пробной версии водяной знак, у полной — нет', function () {
    $presentation = trialDeck();
    $renderer = app(DeckRenderer::class);

    expect(substr_count($renderer->html($presentation), 'class="watermark"'))->toBe(2);

    $presentation->update(['unlocked_at' => now()]);

    expect($renderer->html($presentation))->not->toContain('class="watermark"');
});

test('полная версия открывается за генерацию и перепечатывается', function () {
    Queue::fake();
    $presentation = trialDeck(credits: 2);

    $this->actingAs($presentation->user)
        ->post(route('presentations.unlock', $presentation))
        ->assertRedirect();

    expect($presentation->refresh()->isLocked())->toBeFalse()
        ->and($presentation->status)->toBe(PresentationStatus::Generating)
        ->and($presentation->user->refresh()->credits)->toBe(1);

    Queue::assertPushed(RenderPresentation::class);
});

test('без генераций открыть нельзя — ведём к тарифам', function () {
    Queue::fake();
    $presentation = trialDeck();

    $this->actingAs($presentation->user)
        ->post(route('presentations.unlock', $presentation))
        ->assertRedirect(route('billing.index', ['for' => $presentation->id]));

    expect($presentation->refresh()->isLocked())->toBeTrue();
    Queue::assertNothingPushed();
});

test('в пробной версии не больше 8 слайдов', function () {
    Queue::fake();
    $user = User::factory()->create();
    $user->forceFill(['credits' => 0, 'trial_used' => false])->save();

    $this->actingAs($user)
        ->post(route('presentations.store'), ['topic' => 'Пётр I', 'slide_count' => 12])
        ->assertSessionHasErrors('slide_count');

    $this->post(route('presentations.store'), ['topic' => 'Пётр I', 'slide_count' => 8])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(PrepareQuestions::class);
});

test('оплата с закрытой презентации сразу открывает её полную версию', function () {
    Queue::fake();
    config(['billing.provider' => 'fake']);
    $presentation = trialDeck();
    $user = $presentation->user;

    Billing::make()->start($user, Package::find('three'), '/', $presentation);
    Billing::make()->settle(Payment::latest('id')->first()->provider_payment_id, true);

    // Три купленных, одна ушла на открытие
    expect($presentation->refresh()->isLocked())->toBeFalse()
        ->and($user->refresh()->credits)->toBe(2);

    Queue::assertPushed(RenderPresentation::class);
});

test('обычная покупка пробную презентацию не трогает', function () {
    config(['billing.provider' => 'fake']);
    $presentation = trialDeck();
    $user = $presentation->user;

    Billing::make()->start($user, Package::find('three'), '/');
    Billing::make()->settle(Payment::latest('id')->first()->provider_payment_id, true);

    expect($presentation->refresh()->isLocked())->toBeTrue()
        ->and($user->refresh()->credits)->toBe(3);
});
