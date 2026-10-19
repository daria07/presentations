<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Акция «Мы обновились»: скидка на сайте тем, кто зарегистрировался
 * до обновления (обложки, картинки на слайдах, источники).
 *
 * Включается при первом показе — плашкой над списком презентаций —
 * и действует несколько дней (config/billing.php → update_offer).
 * Срок у каждого свой: человек, зашедший через неделю после старта,
 * получает те же дни, что и пришедший в первый день.
 *
 * Скидка — на одну покупку любого пакета. При оплате побеждает
 * большая из действующих скидок (Discount::current), а какая
 * сработала, записывается в платёж.
 */
final class UpdateOffer
{
    public static function percent(): int
    {
        return max(0, min(90, (int) config('billing.update_offer.percent', 0)));
    }

    /** Граница регистрации в UTC; null — акция выключена */
    public static function cutoff(): ?Carbon
    {
        $value = config('billing.update_offer.registered_before');

        return filled($value) ? Carbon::parse((string) $value, config('admin.timezone'))->utc() : null;
    }

    /** Положена ли скидка: зарегистрировался до обновления, ещё не пользовался */
    public static function eligible(User $user): bool
    {
        $cutoff = self::cutoff();

        return self::percent() > 0
            && $cutoff !== null
            && $user->created_at !== null
            && $user->created_at->lt($cutoff)
            && $user->update_offer_used_at === null;
    }

    /** Скидка показана, срок не вышел, не использована */
    public static function active(User $user): bool
    {
        return $user->update_offer_until !== null
            && $user->update_offer_until->isFuture()
            && $user->update_offer_used_at === null;
    }

    /**
     * Для плашки: включает скидку при первом показе и возвращает её.
     * null — показывать нечего: не положена, истекла или использована.
     *
     * @return array{percent: int, until: string}|null
     */
    public static function forUser(User $user): ?array
    {
        if ($user->update_offer_at === null && self::eligible($user) && ! self::ended()) {
            $user->forceFill([
                'update_offer_at' => now(),
                'update_offer_until' => now()->addDays(max(1, (int) config('billing.update_offer.days', 7))),
            ])->save();
        }

        if (! self::active($user)) {
            return null;
        }

        return [
            'percent' => self::percent(),
            'until' => $user->update_offer_until->toIso8601String(),
        ];
    }

    /** Акция закончилась для новых показов */
    private static function ended(): bool
    {
        $ends = config('billing.update_offer.ends_at');

        return filled($ends) && Carbon::parse((string) $ends, config('admin.timezone'))->isPast();
    }
}
