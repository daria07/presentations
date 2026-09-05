<?php

namespace App\Services\Deck;

/**
 * Готовит @font-face со шрифтами, вшитыми прямо в CSS.
 *
 * Печать PDF идёт в headless-браузере, у которого нет доступа к
 * маршрутам приложения. Если ссылаться на шрифты по адресу, браузер
 * пойдёт в сеть — это лишние секунды на каждую генерацию и полный
 * отказ, когда сеть недоступна. Поэтому файлы кодируются в base64
 * и уезжают внутри самой страницы.
 */
class FontLoader
{
    private const MANIFEST = 'build/fonts-manifest.json';

    /**
     * Память на время запроса. Раньше здесь стоял rememberForever, но
     * шрифты весят сотни килобайт, кэш у нас в базе, а ключ меняется
     * при каждой пересборке фронта — старые записи копились в таблице
     * и никогда не удалялись. Само построение занимает миллисекунды:
     * прочитать три файла и закодировать. Держать это вечно незачем.
     *
     * @var array<string, string>
     */
    private static array $memo = [];

    /**
     * @param  array<int, string>  $families  например ['Golos Text', 'Manrope']
     */
    public static function css(array $families): string
    {
        $manifest = public_path(self::MANIFEST);

        if (! file_exists($manifest)) {
            return '';
        }

        $key = implode('|', $families).':'.filemtime($manifest);

        return self::$memo[$key] ??= self::build($families);
    }

    private static function build(array $families): string
    {
        $manifest = public_path(self::MANIFEST);

        if (! file_exists($manifest)) {
            return '';
        }

        $data = json_decode(file_get_contents($manifest), true) ?: [];
        $rules = [];

        foreach ($data['preloads'] ?? [] as $variant) {
            if (! in_array($variant['family'] ?? '', $families, true)) {
                continue;
            }

            $file = public_path('build/'.$variant['file']);

            if (! file_exists($file)) {
                continue;
            }

            $encoded = base64_encode(file_get_contents($file));

            $rules[] = sprintf(
                "@font-face{font-family:\"%s\";font-style:%s;font-weight:%d;font-display:block;".
                "src:url(data:font/woff2;charset=utf-8;base64,%s) format(\"woff2\");}",
                $variant['family'],
                $variant['style'] ?? 'normal',
                $variant['weight'] ?? 400,
                $encoded,
            );
        }

        return implode("\n", $rules);
    }
}
