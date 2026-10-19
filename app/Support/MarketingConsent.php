<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Согласие на рекламные письма: новости, акции, скидки.
 *
 * Отдельное и добровольное: галочка при регистрации снята по
 * умолчанию, без неё регистрироваться можно. Не путать с согласием
 * на обработку данных — его даёт сама регистрация, и объединять их
 * нельзя (ст. 18 закона «О рекламе»). Служебные письма — о готовой
 * презентации, оплате, ответ в чате — от этого согласия не зависят.
 *
 * Отозвать можно в настройках профиля и ссылкой «Не получать такие
 * письма» в любом письме.
 */
final class MarketingConsent
{
    /**
     * Текст согласия. Меняется текст — меняется версия: у каждого
     * человека записано, под какой редакцией он поставил галочку.
     */
    public const VERSION = '2026-10-20';

    public const TEXT = 'Хочу получать письма о скидках и новостях Слайдуши. '
        .'Отписаться можно в любой момент — в настройках профиля или по ссылке в письме.';

    public static function has(User $user): bool
    {
        return $user->marketing_consent_at !== null && $user->marketing_revoked_at === null;
    }

    /** $source — где поставили галочку: register или settings */
    public static function grant(User $user, Request $request, string $source): void
    {
        if (self::has($user)) {
            return;
        }

        $user->forceFill([
            'marketing_consent_at' => now(),
            'marketing_consent_ip' => $request->ip(),
            'marketing_consent_source' => $source,
            'marketing_consent_version' => self::VERSION,
            'marketing_revoked_at' => null,
        ])->save();
    }

    /** Дату и источник согласия не стираем: это история, а не текущее состояние */
    public static function revoke(User $user): void
    {
        if (! self::has($user)) {
            return;
        }

        $user->forceFill(['marketing_revoked_at' => now()])->save();
    }
}
