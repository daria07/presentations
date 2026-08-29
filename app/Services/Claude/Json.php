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

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Разворачивает случай, когда весь объект завёрнут в одну строку:
     * ['{"title": ...}'] превращается в ['title' => ...].
     */
    public static function unwrap(mixed $value): array
    {
        $array = self::toArray($value);

        if (count($array) === 1 && array_key_first($array) === 0 && is_string($array[0])) {
            $inner = self::toArray($array[0]);

            if ($inner !== []) {
                return $inner;
            }
        }

        return $array;
    }
}
