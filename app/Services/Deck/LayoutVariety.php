<?php

namespace App\Services\Deck;

/**
 * Два слайда одной вёрстки подряд выглядят как один слайд, разрезанный
 * пополам: две хронологии, два списка — глаз перестаёт отличать, где
 * кончился один и начался другой.
 *
 * Модель это правило знает, но держит не всегда, поэтому досматриваем
 * сами: второй слайд пары переводим в соседнюю по смыслу вёрстку,
 * не теряя содержания. Работает только при генерации — в редакторе
 * человек ставит вёрстки как хочет.
 */
final class LayoutVariety
{
    /** Обложки и служебные слайды не трогаем */
    private const FIXED = ['title', 'closing', 'sources'];

    /**
     * @param  array<int, array<string, mixed>>  $slides
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $slides): array
    {
        foreach ($slides as $i => $slide) {
            $previous = $slides[$i - 1]['layout'] ?? null;
            $layout = $slide['layout'] ?? null;

            if ($i === 0 || $layout !== $previous || in_array($layout, self::FIXED, true)) {
                continue;
            }

            $next = $slides[$i + 1]['layout'] ?? null;

            foreach (self::alternatives($slide) as $candidate) {
                if ($candidate['layout'] !== $previous && $candidate['layout'] !== $next) {
                    $slides[$i] = $candidate;

                    break;
                }
            }
        }

        return $slides;
    }

    /**
     * Во что можно превратить слайд, не потеряв содержания, — по порядку
     * предпочтения.
     *
     * @param  array<string, mixed>  $slide
     * @return list<array<string, mixed>>
     */
    private static function alternatives(array $slide): array
    {
        $bullets = $slide['bullets'] ?? [];
        $stats = $slide['stats'] ?? [];
        $count = count($bullets);

        return match ($slide['layout']) {
            'bullets' => array_values(array_filter([
                $count === 2 ? self::as($slide, 'comparison') : null,
                $count === 4 ? self::as($slide, 'matrix') : null,
                $count >= 3 && $count <= 4 ? self::as($slide, 'process') : null,
            ])),
            'process' => [self::as($slide, 'bullets')],
            'comparison' => [self::as($slide, 'bullets')],
            'matrix' => [self::as($slide, 'bullets'), self::as($slide, 'process')],
            // Даты с подписями — это те же этапы, только с годом в заголовке
            'timeline' => array_values(array_filter([
                count($stats) >= 2 && count($stats) <= 4 ? self::as($slide, 'stats') : null,
                self::statsToBullets($slide, 'process'),
            ])),
            'stats' => array_values(array_filter([
                count($stats) === 1 ? self::as($slide, 'bignumber') : null,
                self::statsToBullets($slide, 'bullets'),
            ])),
            // Цитату и одно число переложить не во что без потерь
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $slide
     * @return array<string, mixed>
     */
    private static function as(array $slide, string $layout): array
    {
        return [...$slide, 'layout' => $layout];
    }

    /**
     * Числа и даты становятся пунктами: значение — заголовок пункта,
     * подпись — текст.
     *
     * @param  array<string, mixed>  $slide
     * @return array<string, mixed>|null
     */
    private static function statsToBullets(array $slide, string $layout): ?array
    {
        $stats = $slide['stats'] ?? [];

        if (count($stats) < 2 || count($stats) > 4) {
            return null;
        }

        return [
            ...$slide,
            'layout' => $layout,
            'bullets' => array_map(fn (array $stat) => [
                'title' => (string) ($stat['value'] ?? ''),
                'text' => (string) ($stat['label'] ?? ''),
                'icon' => null,
            ], array_values($stats)),
            'stats' => [],
        ];
    }
}
