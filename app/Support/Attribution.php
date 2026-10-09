<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Источник, с которым человек пришёл на сайт.
 *
 * Метки стоят в адресе только на первой странице, а регистрация
 * случается через несколько переходов, иногда и через несколько дней.
 * Поэтому запоминаем их в куке и достаём уже при создании аккаунта.
 *
 * Запоминается первый источник, а не последний: нас интересует, что
 * привело человека, а не по какой ссылке он вернулся. Исключение —
 * заход без меток: если в куке лежит один реферер, а теперь человек
 * пришёл по рекламе, метки рекламы точнее.
 */
class Attribution
{
    public const COOKIE = 'attribution';

    /** Столько же живёт атрибуция в Яндекс.Метрике */
    public const DAYS = 90;

    /** @var list<string> */
    public const UTM = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
    ];

    /** Все поля, которые едут в куку и в карточку пользователя */
    public const FIELDS = [
        ...self::UTM, 'click_id', 'referrer', 'landing_url',
    ];

    /**
     * Что можно взять из текущего запроса. null — брать нечего:
     * ни меток, ни идентификатора клика, ни внешнего перехода.
     *
     * @return array<string, string>|null
     */
    public static function fromRequest(Request $request): ?array
    {
        $data = [];

        foreach (self::UTM as $key) {
            $value = self::clean($request->query($key));

            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        // Идентификатор клика Яндекса (yclid) и Google (gclid):
        // по нему реклама связывает визит со своим кликом
        $click = self::clean($request->query('yclid') ?? $request->query('gclid'));

        if ($click !== null) {
            $data['click_id'] = $click;
        }

        $referrer = self::externalReferrer($request);

        if ($referrer !== null) {
            $data['referrer'] = $referrer;
        }

        if ($data === []) {
            return null;
        }

        $data['landing_url'] = self::clean($request->fullUrl(), 1000) ?? '';

        return $data;
    }

    /**
     * Что уже лежит в куке.
     *
     * Кука приходит от браузера, то есть снаружи: что угодно внутри
     * может оказаться чем угодно. Поэтому читаем только известные
     * ключи и каждый подрезаем по длине.
     *
     * @return array<string, string>
     */
    public static function stored(Request $request): array
    {
        $raw = $request->cookie(self::COOKIE);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $data = [];

        foreach (self::FIELDS as $key) {
            $value = self::clean($decoded[$key] ?? null, $key === 'referrer' || $key === 'landing_url' ? 1000 : 255);

            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Поля для записи в пользователя: недостающие — null
     *
     * @return array<string, string|null>
     */
    public static function forUser(Request $request): array
    {
        $stored = self::stored($request);

        return collect(self::FIELDS)
            ->mapWithKeys(fn (string $key) => [$key => $stored[$key] ?? null])
            ->all();
    }

    /** Переход со стороны: свои же страницы источником не считаются */
    private static function externalReferrer(Request $request): ?string
    {
        $referrer = self::clean($request->headers->get('referer'), 1000);

        if ($referrer === null) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host)) {
            return null;
        }

        $own = parse_url(config('app.url'), PHP_URL_HOST);

        return $host === $request->getHost() || $host === $own ? null : $referrer;
    }

    /** Чужая строка: обрезаем, чистим от управляющих символов */
    private static function clean(mixed $value, int $limit = 255): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
