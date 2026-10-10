<?php

namespace App\Services\Deck;

use Spatie\Browsershot\Browsershot;

/**
 * Общие настройки Chrome для печати PDF.
 *
 * На сервере Chrome работает без песочницы (noSandbox), поэтому ему
 * не даём ничего лишнего: страница собирается целиком из наших
 * шаблонов, шрифты встроены в base64, скриптов в вёрстке нет. Значит,
 * в сеть ходить незачем — и если когда-нибудь в HTML попадёт адрес
 * из ответа модели или от пользователя, Chrome не пойдёт по нему ни
 * во внутреннюю сеть, ни к метаданным облака.
 */
final class Chrome
{
    /**
     * Блокировка по вхождению подстроки в адрес запроса. Сама страница
     * открывается через file://, data: (шрифты) тоже не сетевой запрос.
     */
    public const BLOCKED_SCHEMES = ['http://', 'https://', 'ftp://', 'ws://', 'wss://'];

    public static function harden(Browsershot $shot): Browsershot
    {
        $shot->disableJavascript()
            ->blockUrls(self::BLOCKED_SCHEMES);

        // На сервере Chrome обычно запускается от пользователя без прав
        if (! app()->environment('local')) {
            $shot->noSandbox();
        }

        if ($binary = config('deck.chrome_path')) {
            $shot->setChromePath($binary);
        }

        return $shot;
    }
}
