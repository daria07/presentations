<?php

namespace App\Services\Images;

/**
 * Пережимает картинку в JPEG.
 *
 * Модель отдаёт PNG по 2 МБ — четыре таких раздули бы PDF до десятка
 * мегабайт. В JPEG с качеством 82 та же картинка весит около 200 КБ,
 * и на слайде разницы не видно.
 */
final class Jpeg
{
    private const QUALITY = 82;

    /** Шире слайда в печати картинке быть незачем */
    private const MAX_SIDE = 1600;

    public static function compress(string $bytes): string
    {
        // Без GD отдаём как есть: браузер разберёт PNG и под видом JPEG.
        // На сервере GD стоит (DEPLOY.md), это запасной путь для разработки
        if (! function_exists('imagecreatefromstring')) {
            return $bytes;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return $bytes;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));

        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

            if ($resized !== false) {
                $image = $resized;
            }
        }

        ob_start();
        imagejpeg($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
