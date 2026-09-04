<?php

namespace App\Services\Deck;

/**
 * Фоновые мотивы для титульного и финального слайдов.
 *
 * Рисуются линиями в цвете акцента с низкой непрозрачностью — это
 * фон, а не иллюстрация. Задача мотива не рассказать о теме, а задать
 * настроение и убрать ощущение пустоты на обложке.
 *
 * Набор закрытый, как и у иконок: модель выбирает из словаря.
 */
class Motifs
{
    /** Что означает каждый мотив — эти же подсказки уходят в промпт */
    public const MEANINGS = [
        'waves' => 'вода, природа, циклы, спокойное течение',
        'rings' => 'охват, повторение, круг вопросов, влияние',
        'grid' => 'структура, порядок, данные, система',
        'bars' => 'рост, статистика, экономика, измерения',
        'dots' => 'множество, распределение, люди, разнообразие',
        'diagonals' => 'движение, скорость, динамика, перемены',
        'network' => 'связи, взаимодействие, отношения, сети',
        'layers' => 'уровни, история, слои, накопление',
    ];

    public static function names(): array
    {
        return array_keys(self::MEANINGS);
    }

    public static function has(?string $name): bool
    {
        return $name !== null && isset(self::MEANINGS[$name]);
    }

    /** Готовая разметка мотива на сетке 400×300 */
    public static function svg(?string $name): string
    {
        if (! self::has($name)) {
            return '';
        }

        $shapes = match ($name) {
            'waves' => self::waves(),
            'rings' => self::rings(),
            'grid' => self::grid(),
            'bars' => self::bars(),
            'dots' => self::dots(),
            'diagonals' => self::diagonals(),
            'network' => self::network(),
            'layers' => self::layers(),
        };

        return '<svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" '
            .'fill="none" stroke="currentColor" stroke-width="1.6" '
            .'stroke-linecap="round">'.$shapes.'</svg>';
    }

    // ---------------------------------------------------------------

    private static function waves(): string
    {
        $out = '';

        for ($i = 0; $i < 7; $i++) {
            $y = 70 + $i * 34;
            $out .= sprintf(
                '<path d="M-20 %1$d Q 30 %2$d 80 %1$d T 180 %1$d T 280 %1$d T 380 %1$d T 480 %1$d"/>',
                $y,
                $y - 22,
            );
        }

        return $out;
    }

    private static function rings(): string
    {
        $out = '';

        for ($i = 1; $i <= 7; $i++) {
            $out .= sprintf('<circle cx="330" cy="240" r="%d"/>', $i * 34);
        }

        return $out;
    }

    private static function grid(): string
    {
        $out = '';

        for ($x = 20; $x <= 400; $x += 38) {
            $out .= sprintf('<path d="M%d 0v300"/>', $x);
        }

        for ($y = 20; $y <= 300; $y += 38) {
            $out .= sprintf('<path d="M0 %1$dh400"/>', $y);
        }

        return $out;
    }

    private static function bars(): string
    {
        $out = '';
        $heights = [60, 95, 78, 130, 110, 165, 145, 200, 185, 240];

        foreach ($heights as $i => $h) {
            $out .= sprintf(
                '<rect x="%d" y="%d" width="22" height="%d" rx="3"/>',
                18 + $i * 38,
                290 - $h,
                $h,
            );
        }

        return $out;
    }

    private static function dots(): string
    {
        $out = '';

        for ($x = 24; $x <= 400; $x += 32) {
            for ($y = 24; $y <= 300; $y += 32) {
                $out .= sprintf(
                    '<circle cx="%d" cy="%d" r="2.6" fill="currentColor" stroke="none"/>',
                    $x,
                    $y,
                );
            }
        }

        return $out;
    }

    private static function diagonals(): string
    {
        $out = '';

        for ($i = -6; $i < 14; $i++) {
            $x = $i * 40;
            $out .= sprintf('<path d="M%d 320L%d -20"/>', $x, $x + 170);
        }

        return $out;
    }

    private static function network(): string
    {
        // Узлы расставлены руками: случайные точки складываются
        // в кашу, а так рисунок читается как связная схема
        $nodes = [
            [60, 70], [150, 40], [250, 90], [340, 55],
            [40, 190], [130, 160], [230, 205], [330, 165],
            [90, 265], [200, 285], [300, 255], [380, 235],
        ];

        $links = [
            [0, 1], [1, 2], [2, 3], [0, 4], [1, 5], [2, 6], [3, 7],
            [4, 5], [5, 6], [6, 7], [4, 8], [5, 8], [6, 9], [7, 10],
            [8, 9], [9, 10], [10, 11], [3, 11],
        ];

        $out = '';

        foreach ($links as [$a, $b]) {
            $out .= sprintf(
                '<path d="M%d %dL%d %d"/>',
                $nodes[$a][0], $nodes[$a][1],
                $nodes[$b][0], $nodes[$b][1],
            );
        }

        foreach ($nodes as [$x, $y]) {
            $out .= sprintf('<circle cx="%d" cy="%d" r="5"/>', $x, $y);
        }

        return $out;
    }

    private static function layers(): string
    {
        $out = '';

        for ($i = 0; $i < 6; $i++) {
            $y = 40 + $i * 42;
            $inset = $i * 14;
            $out .= sprintf(
                '<path d="M%d %dh%d"/>',
                $inset,
                $y,
                400 - $inset * 2,
            );
            $out .= sprintf(
                '<path d="M%d %dh%d"/>',
                $inset + 20,
                $y + 14,
                340 - $inset * 2,
            );
        }

        return $out;
    }
}
