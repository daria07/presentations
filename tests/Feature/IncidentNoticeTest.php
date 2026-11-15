<?php

use App\Mail\IncidentNotice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
   Письмо о сбое у хостинга: всем, кроме отписавшихся, по одному разу.
*/

test('письмо о сбое уходит всем, кроме отписавшихся, и только один раз', function () {
    Mail::fake();

    $first = User::factory()->create();
    $second = User::factory()->create(['trial_used' => true]);
    User::factory()->create(['news_unsubscribed_at' => now()]);

    $this->artisan('news:incident')->assertSuccessful();
    Mail::assertNothingQueued();

    $this->artisan('news:incident --send --force')->assertSuccessful();
    $this->artisan('news:incident --send --force')->assertSuccessful();

    Mail::assertQueuedCount(2);
    Mail::assertQueued(IncidentNotice::class, fn ($mail) => $mail->hasTo($first->email));
    Mail::assertQueued(IncidentNotice::class, fn ($mail) => $mail->hasTo($second->email));
    expect(DB::table('mail_sends')->where('campaign', 'incident-2026-11')->count())->toBe(2)
        // Пробную это письмо не трогает
        ->and($second->refresh()->trial_used)->toBeTrue();
});

test('в письме о сбое — извинения, что делать, отписка и реквизиты', function () {
    $user = User::factory()->create();
    $html = (new IncidentNotice($user))->render();

    expect($html)->toContain('Beget')
        ->toContain('Приносим свои извинения')
        ->toContain('в&nbsp;прежнем режиме')
        ->toContain('начислим генерации')
        ->toContain('/unsubscribe/'.$user->id)
        ->toContain((string) config('legal.inn'))
        ->not->toContain('₽');
});
