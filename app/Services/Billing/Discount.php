<?php

namespace App\Services\Billing;

use App\Models\User;

/**
 * Скидка для тех, у кого закончились генерации.
 *
 * Над списком презентаций появляется кнопка. Её текст — один из двух
 * вариантов A/B-теста, каждому человеку навсегда достаётся один.
 * Клик включает скидку на одну покупку на ограниченное время
 * (config/billing.php → discount). Отсчёт идёт с клика, а не с показа:
 * иначе человек, зашедший через неделю, увидел бы обещание, которое
 * уже истекло.
 *
 * Показ и клик записываются в пользователя — по ним админка считает,
 * какой текст кликают чаще.
 */
final class Discount
{
    /** Тексты кнопки по вариантам A/B-теста */
    public const VARIANTS = [
        'a' => 'Сгенерировать презентации со скидкой',
        'b' => 'Получить скидку на генерацию презентаций',
    ];

    public static function percent(): int
    {
        return (int) config('billing.discount.percent', 30);
    }

    /**
     * Показывать ли кнопку: генерации кончились (и платные, и пробная),
     * скидкой ещё не пользовались, и она либо не начата, либо идёт.
     * Истёкшую неиспользованную второй раз не предлагаем.
     */
    public static function offerable(User $user): bool
    {
        return $user->credits < 1
            && $user->trial_used
            && $user->discount_used_at === null
            && ($user->promo_clicked_at === null || self::active($user));
    }

    /** Скидка начата кликом, не истекла и не использована */
    public static function active(User $user): bool
    {
        return $user->discount_until !== null
            && $user->discount_until->isFuture()
            && $user->discount_used_at === null;
    }

    /**
     * Кнопка для показа. Первый показ закрепляет вариант за человеком
     * и записывает время — это знаменатель в статистике кликов.
     *
     * Вариант — по чётности id: стабилен без хранения случайного
     * числа и делит поток почти ровно пополам.
     *
     * @return array{variant: string, text: string, percent: int, until: string|null}
     */
    public static function offer(User $user): array
    {
        if ($user->promo_variant === null || $user->promo_shown_at === null) {
            $user->forceFill([
                'promo_variant' => $user->promo_variant ?? ($user->id % 2 === 0 ? 'a' : 'b'),
                'promo_shown_at' => $user->promo_shown_at ?? now(),
            ])->save();
        }

        return [
            'variant' => $user->promo_variant,
            'text' => self::VARIANTS[$user->promo_variant] ?? self::VARIANTS['a'],
            'percent' => self::percent(),
            'until' => self::active($user) ? $user->discount_until->toIso8601String() : null,
        ];
    }

    /**
     * Клик по кнопке: записываем его один раз и запускаем отсчёт.
     * Повторный клик ничего не продлевает.
     */
    public static function claim(User $user): void
    {
        if (! self::offerable($user) || $user->promo_clicked_at !== null) {
            return;
        }

        $user->forceFill([
            'promo_variant' => $user->promo_variant ?? ($user->id % 2 === 0 ? 'a' : 'b'),
            'promo_shown_at' => $user->promo_shown_at ?? now(),
            'promo_clicked_at' => now(),
            'discount_until' => now()->addHours((int) config('billing.discount.hours', 48)),
        ])->save();
    }

    /**
     * Цена со скидкой в копейках, округлённая вниз до целого рубля:
     * 599 ₽ → 419 ₽, а не 419,30.
     */
    public static function apply(int $amount): int
    {
        $discounted = intdiv($amount * (100 - self::percent()), 100);

        return intdiv($discounted, 100) * 100;
    }
}
