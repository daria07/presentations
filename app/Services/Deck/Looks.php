<?php

namespace App\Services\Deck;

/**
 * Единый список оформлений для интерфейса.
 *
 * Лендинг, страница презентации и редактор показывают одни и те же
 * темы и гаммы. Держать три копии этого списка — гарантия, что через
 * месяц на лендинге останутся семь гамм, а в продукте будет десять.
 */
class Looks
{
    /**
     * Темы: характер оформления — шрифты, скругления, ритм.
     *
     * @return array<int, array<string, string>>
     */
    public static function themes(): array
    {
        return collect(config('deck.themes'))
            ->map(fn (array $theme, string $key) => [
                'key' => $key,
                'name' => $theme['name'],
                'note' => $theme['note'] ?? '',
                'style' => $theme['style'],
                'font' => $theme['font_display'],
                'fontBody' => $theme['font_body'],
            ])
            ->values()
            ->all();
    }

    /**
     * Гаммы: только цвет, любая сочетается с любой темой.
     *
     * Отдаём все десять ролей, а не три: лендинг рисует из них живые
     * мини-слайды, и урезанный набор пришлось бы добирать вручную.
     *
     * @return array<int, array<string, string>>
     */
    public static function palettes(): array
    {
        return collect(config('deck.palettes'))
            ->map(fn (array $palette, string $key) => [
                'key' => $key,
                'name' => $palette['name'],
                'note' => $palette['note'] ?? '',
                'paper' => $palette['paper'],
                'ink' => $palette['ink'],
                'muted' => $palette['muted'],
                'rule' => $palette['rule'],
                'accent' => $palette['accent'],
                'accentInk' => $palette['accent_ink'],
                'accentSoft' => $palette['accent_soft'],
                'cover' => $palette['cover_bg'],
                'coverInk' => $palette['cover_ink'],
                'coverAccent' => $palette['cover_accent'],
            ])
            ->values()
            ->all();
    }
}
