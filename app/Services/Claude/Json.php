<?php

namespace App\Services\Claude;

/**
 * Шлюзы и модели по-разному сериализуют вложенные структуры:
 * иногда массив приходит уже разобранным, иногда — строкой с JSON.
 * Эти помощники приводят всё к массивам, чтобы дальше по коду
 * не приходилось гадать.
 */
class Json
{
    /** Массив как есть, JSON-строка — разобранной, всё прочее — пустым массивом. */
    public static function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            // Модель иногда сама сериализует вложенный массив в строку и
            // не экранирует кавычку внутри текста: «„Ёжике в тумане"».
            // Одна такая кавычка — и пропадает вся структура
            if (! is_array($decoded)) {
                $decoded = json_decode(self::escapeStrayQuotes($value), true);
            }

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Экранирует кавычки внутри строковых значений, которые модель
     * забыла экранировать.
     *
     * Идём по тексту и помним, внутри строки мы или нет. Кавычка внутри
     * строки закрывает её, только если за ней (после пробелов) идёт
     * то, что в JSON может стоять после строки: запятая, двоеточие,
     * скобка или конец текста. Иначе это кавычка из самого текста.
     */
    public static function escapeStrayQuotes(string $json): string
    {
        $out = '';
        $inString = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($inString && $char === '\\') {
                // Уже экранированный символ переносим как есть
                $out .= $char.($json[$i + 1] ?? '');
                $i++;

                continue;
            }

            if ($char !== '"') {
                $out .= $char;

                continue;
            }

            if (! $inString) {
                $inString = true;
                $out .= $char;

                continue;
            }

            $rest = ltrim(substr($json, $i + 1));
            $closes = match (true) {
                $rest === '' => true,
                in_array($rest[0], [':', '}', ']'], true) => true,
                // Запятая бывает и в тексте: «„Ёжике", а потом». После
                // настоящей запятой в JSON идёт новое значение
                $rest[0] === ',' => preg_match('/^,\s*(["{\[\-\d]|true|false|null)/', $rest) === 1,
                default => false,
            };

            if ($closes) {
                $inString = false;
                $out .= $char;
            } else {
                $out .= '\\"';
            }
        }

        return $out;
    }

    /**
     * Разворачивает случай, когда весь объект завёрнут в одну строку.
     *
     * Встречается в двух видах, и оба настоящие:
     *   ['{"title": ...}']            — ключ числовой
     *   ['slides' => '{"title": ...}'] — ключ именованный, назван по
     *   одному из полей схемы, а внутри лежит весь объект целиком
     *
     * Второй случай коварнее: снаружи структура выглядит правильной,
     * и без разворачивания мы принимаем ключи внутреннего объекта
     * за элементы списка. Так презентация на восемь слайдов
     * превращалась в один.
     *
     * Условие одно: в массиве ровно один элемент, и это строка
     * с разбираемым JSON. Настоящий ответ по нашим схемам так
     * выглядеть не может — в нём всегда несколько полей.
     */
    public static function unwrap(mixed $value): array
    {
        $array = self::toArray($value);

        if (count($array) !== 1) {
            return $array;
        }

        $only = reset($array);

        if (! is_string($only)) {
            return $array;
        }

        $inner = self::toArray($only);

        return $inner !== [] ? $inner : $array;
    }

    /**
     * Поля, которые модель «проглотила» внутрь соседнего.
     *
     * Иногда ответ инструмента приходит наполовину в разметке вызова:
     * в строке одного поля оказываются закрывающий тег и все следующие
     * поля целиком —
     *   "arc": "Начинаем с загадки…</arc>\n<parameter name=\"points\">[{…}]"
     * Так у конспекта пропали пункты (points) — главное его содержание —
     * а слайды писались по одной общей линии и вышли бедными.
     *
     * Разрезаем такую строку обратно на поля. Уже заполненные поля не
     * трогаем: если модель прислала их нормально, верим им.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function splitLeakedParameters(array $data): array
    {
        $marker = '<parameter name="';

        foreach ($data as $key => $value) {
            if (! is_string($value) || ! str_contains($value, $marker)) {
                continue;
            }

            $cut = (int) strpos($value, $marker);
            $data[$key] = trim((string) preg_replace('~</[a-z_]+>\s*$~u', '', rtrim(substr($value, 0, $cut))));

            preg_match_all(
                '~<parameter name="([a-z_]+)">(.*?)(?:</parameter>|(?=<parameter name=")|$)~su',
                substr($value, $cut),
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $name = $match[1];

                if (array_key_exists($name, $data) && ! in_array($data[$name], [null, '', []], true)) {
                    continue;
                }

                $raw = trim((string) preg_replace('~</[a-z_]+>\s*$~u', '', trim($match[2])));
                $decoded = json_decode($raw, true) ?? json_decode(self::escapeStrayQuotes($raw), true);

                $data[$name] = $decoded ?? $raw;
            }
        }

        return $data;
    }
}
