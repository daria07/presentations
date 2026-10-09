<?php

use App\Models\User;

/*
   credits и trial_used намеренно вне fillable — их нельзя выставить
   массовым присваиванием, в том числе и фабрикой. Поэтому в тестах
   проставляем их отдельно.
*/
function userWith(int $credits, bool $trialUsed): User
{
    $user = User::factory()->create();

    $user->forceFill(['credits' => $credits, 'trial_used' => $trialUsed])->save();

    return $user;
}

test('первая генерация уходит с пробной, баланс не трогаем', function () {
    $user = userWith(0, false);

    expect($user->spendCredit())->toBe('trial')
        ->and($user->refresh()->trial_used)->toBeTrue()
        ->and($user->credits)->toBe(0);
});

test('дальше списывается кредит', function () {
    $user = userWith(2, true);

    expect($user->spendCredit())->toBe('credit')
        ->and($user->refresh()->credits)->toBe(1);
});

test('платить нечем — не списываем', function () {
    $user = userWith(0, true);

    expect($user->spendCredit())->toBeNull()
        ->and($user->refresh()->credits)->toBe(0);
});

/*
   Главное в этом файле: сбой на пробной генерации возвращает пробную,
   а не настоящий кредит. Раньше возвращался кредит, и несколько сбоев
   подряд давали человеку баланс без единой оплаты.
*/
test('возврат пробной не превращается в кредит', function () {
    $user = userWith(0, false);

    $spent = $user->spendCredit();
    $user->refundCredit($spent);

    expect($user->refresh()->credits)->toBe(0)
        ->and($user->trial_used)->toBeFalse();
});

test('возврат кредита кладёт его обратно на баланс', function () {
    $user = userWith(2, true);

    $spent = $user->spendCredit();
    $user->refundCredit($spent);

    expect($user->refresh()->credits)->toBe(2);
});

test('если не списывали, то и возвращать нечего', function () {
    $user = userWith(1, true);

    $user->refundCredit(null);

    expect($user->refresh()->credits)->toBe(1);
});
