<?php

namespace App\Support;

/**
 * Русские числительные: 1 слайд, 2 слайда, 5 слайдов.
 *
 * Отдельным классом, а не методом по месту: правило одно на всё
 * приложение, а исключение в нём неочевидное — 11, 12, 13 и 14 ведут
 * себя не как 1, 2, 3 и 4. Про них стабильно забывают, и в письме
 * появляется «1 слайдов». Тот же подход, что и во фронтенде,
 * resources/js/lib/plural.ts.
 */
class Plural
{
    public static function word(int $n, string $one, string $few, string $many): string
    {
        $abs = abs($n);
        $hundred = $abs % 100;
        $ten = $abs % 10;

        if ($hundred >= 11 && $hundred <= 14) {
            return $many;
        }

        if ($ten === 1) {
            return $one;
        }

        if ($ten >= 2 && $ten <= 4) {
            return $few;
        }

        return $many;
    }

    /** «6 слайдов» — число вместе со склонённым словом */
    public static function slides(int $n): string
    {
        return $n.' '.self::word($n, 'слайд', 'слайда', 'слайдов');
    }
}
