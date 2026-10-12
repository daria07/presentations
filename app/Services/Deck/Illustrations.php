<?php

namespace App\Services\Deck;

/**
 * Решает, на каких слайдах будут картинки.
 *
 * Модель при сборке структуры пишет для слайдов описание сцены
 * (scene) и отмечает, где картинка уместнее всего (illustrate). Но
 * сколько картинок будет и где именно — решаем мы, а не она:
 *
 * - обложка — всегда, фоном;
 * - в теле: 2 картинки в презентации до 8 слайдов, 3 — от 10;
 * - только на вёрстках, где есть место под половину слайда:
 *   bullets (до 3 пунктов), quote, bignumber;
 * - не на соседних слайдах, если можно иначе;
 * - сторона чередуется: справа, слева, справа;
 * - формат — квадрат; вертикальный, только если модель отметила
 *   высокий объект (image_format: portrait). Горизонтальный на
 *   половине слайда не бывает: вышла бы низкая полоска.
 *
 * Модель отмечает «свои» слайды неаккуратно: то ни одного, то все
 * подряд. Поэтому её отметки — только приоритет, а недостающие слайды
 * добираем сами. Если описания сцены нет, рисуем по заголовку:
 * картинка будет проще, но минимум соблюдён.
 */
final class Illustrations
{
    /** Вёрстки, в которых картинка занимает половину слайда */
    public const LAYOUTS = ['bullets', 'quote', 'bignumber'];

    /** Больше пунктов в половину слайда не помещается */
    public const MAX_BULLETS = 3;

    /** Сколько картинок в теле презентации (без обложки) */
    public static function target(int $slideCount): int
    {
        return $slideCount >= 10 ? 3 : 2;
    }

    /**
     * Проставляет слайдам поле image: ['scene' => …, 'side' => …].
     * Путь к файлу (path) появится позже, когда картинка нарисована.
     *
     * @param  array<int, array<string, mixed>>  $slides  слайды после нормализации,
     *                                                    с полями scene и illustrate
     * @return array<int, array<string, mixed>> те же слайды, с image и без scene/illustrate
     */
    public static function plan(array $slides, int $slideCount, ?string $coverScene): array
    {
        $chosen = self::choose($slides, self::target($slideCount));

        $side = 'right';

        foreach ($slides as $index => $slide) {
            $scene = trim((string) ($slide['scene'] ?? ''));

            if ($index === 0 && ($slide['layout'] ?? null) === 'title') {
                $slide['image'] = [
                    'scene' => filled($coverScene) ? trim($coverScene) : self::fallbackScene($slide),
                    'side' => 'cover',
                ];
            } elseif (in_array($index, $chosen, true)) {
                $slide['image'] = [
                    'scene' => $scene !== '' ? $scene : self::fallbackScene($slide),
                    'side' => $side,
                    'format' => ($slide['image_format'] ?? null) === 'portrait' ? 'portrait' : 'square',
                ];
                $side = $side === 'right' ? 'left' : 'right';
            }

            unset($slide['scene'], $slide['illustrate'], $slide['image_format']);
            $slides[$index] = $slide;
        }

        return $slides;
    }

    /**
     * Индексы слайдов под картинки, в порядке следования.
     *
     * @param  array<int, array<string, mixed>>  $slides
     * @return array<int, int>
     */
    private static function choose(array $slides, int $target): array
    {
        $fits = fn (array $s, bool $strict): bool => in_array($s['layout'] ?? null, self::LAYOUTS, true)
            && (! $strict || count($s['bullets'] ?? []) <= self::MAX_BULLETS);

        $chosen = [];

        /*
           Проходы от лучшего к запасному:
           1) отмечено моделью и помещается;
           2) не отмечено, но помещается;
           3) список из 4 пунктов — тесно, но лучше, чем не добрать минимум.
           Внутри прохода сначала берём несоседние слайды.
        */
        $passes = [
            fn (array $s) => ! empty($s['illustrate']) && $fits($s, true),
            fn (array $s) => $fits($s, true),
            fn (array $s) => $fits($s, false),
        ];

        foreach ($passes as $accepts) {
            foreach ([false, true] as $allowNeighbours) {
                foreach ($slides as $index => $slide) {
                    if (count($chosen) >= $target) {
                        break 3;
                    }

                    if ($index === 0 || in_array($index, $chosen, true) || ! $accepts($slide)) {
                        continue;
                    }

                    $nearby = in_array($index - 1, $chosen, true) || in_array($index + 1, $chosen, true);

                    if ($nearby && ! $allowNeighbours) {
                        continue;
                    }

                    $chosen[] = $index;
                }
            }
        }

        sort($chosen);

        return $chosen;
    }

    /**
     * Запасное описание: модели картинок понимают и русский.
     *
     * @param  array<string, mixed>  $slide
     */
    private static function fallbackScene(array $slide): string
    {
        return trim(($slide['heading'] ?? '').'. '.($slide['subheading'] ?? ''), '. ');
    }
}
