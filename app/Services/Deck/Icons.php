<?php

namespace App\Services\Deck;

/**
 * Пиктограммы для слайдов.
 *
 * Набор намеренно закрытый: модель выбирает из этого словаря, а не
 * придумывает названия. Иначе она попросит иконку, которой у нас нет,
 * и на слайде окажется пустое место.
 *
 * Рисунки простые и линейные — одна толщина штриха, скруглённые
 * концы. Так они не спорят с текстом и одинаково смотрятся в любом
 * шаблоне, потому что наследуют цвет от родителя.
 */
class Icons
{
    /** Внутренности SVG на сетке 24×24 */
    private const SHAPES = [
        // Время и порядок
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'hourglass' => '<path d="M6 3h12M6 21h12"/><path d="M7 3v3.5c0 2 5 3.5 5 5.5s-5 3.5-5 5.5V21"/><path d="M17 3v3.5c0 2-5 3.5-5 5.5s5 3.5 5 5.5V21"/>',

        // Динамика
        'growth' => '<polyline points="3 17 9 11 13 15 21 7"/><polyline points="15 7 21 7 21 13"/>',
        'decline' => '<polyline points="3 7 9 13 13 9 21 17"/><polyline points="15 17 21 17 21 11"/>',
        'chart' => '<path d="M3 21h18"/><path d="M6 21v-7M12 21V5M18 21v-11"/>',
        'arrow' => '<path d="M4 12h14"/><polyline points="13 6 19 12 13 18"/>',

        // Мышление
        'idea' => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-4 10.4c.6.6 1 1.4 1 2.2V17h6v-1.4c0-.8.4-1.6 1-2.2A6 6 0 0 0 12 3z"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M16.2 16.2L21 21"/>',
        'book' => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 0 4 22z"/><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20"/>',
        'layers' => '<polygon points="12 3 21 8 12 13 3 8"/><polyline points="3 13 12 18 21 13"/>',

        // Люди
        'person' => '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>',
        'people' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.6a3.5 3.5 0 0 1 0 4.8"/><path d="M17.5 14.6A6.5 6.5 0 0 1 21.5 20"/>',
        'message' => '<path d="M4 5h16v11H9l-5 4z"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/>',
        'home' => '<path d="M4 11l8-7 8 7"/><path d="M6 10v10h12V10"/>',

        // Оценка
        'check' => '<circle cx="12" cy="12" r="9"/><polyline points="8 12.4 11 15.4 16.4 9"/>',
        'alert' => '<path d="M12 4L2.6 20h18.8z"/><path d="M12 10v4"/><circle cx="12" cy="17.2" r=".9" fill="currentColor" stroke="none"/>',
        'plus' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>',
        'minus' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
        'star' => '<polygon points="12 3 14.6 9.2 21 9.8 16.2 14.2 17.6 20.6 12 17.3 6.4 20.6 7.8 14.2 3 9.8 9.4 9.2"/>',
        'scale' => '<path d="M12 4v16M6 20h12M4 8h16"/><path d="M4 8l-2 5a3 3 0 0 0 4 0z"/><path d="M20 8l2 5a3 3 0 0 1-4 0z"/>',

        // Механика
        'gear' => '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="7.5"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="M10.9 12.1L20 3"/><path d="M16.8 6.2l2.5 2.5"/>',
        'link' => '<path d="M10 13a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.5 1.5"/><path d="M14 11a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5"/>',
        'bolt' => '<polygon points="13 2 4 14 11 14 10 22 20 10 13 10"/>',

        // Природа и наука
        'flask' => '<path d="M9 3h6"/><path d="M10 3v6L4.6 19a1.5 1.5 0 0 0 1.3 2.2h12.2A1.5 1.5 0 0 0 19.4 19L14 9V3"/><path d="M7.6 15h8.8"/>',
        'drop' => '<path d="M12 3c4 5 6 8 6 11a6 6 0 0 1-12 0c0-3 2-6 6-11z"/>',
        'flame' => '<path d="M12 3c3 4 6 6 6 10a6 6 0 0 1-12 0c0-2 1-3.6 2-5 .5 1.5 1.5 2 2.5 2C11.5 8 11 5 12 3z"/>',
        'leaf' => '<path d="M20 4C10 4 4 9 4 17c0 1.4.3 2.4.3 2.4C10 20 20 15 20 4z"/><path d="M4.5 19.5c3.5-6 8-9.5 13-11.5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18z"/>',
        'heart' => '<path d="M12 20s-8-4.6-8-10a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.4-8 10-8 10z"/>',

        // Прочее
        'money' => '<ellipse cx="12" cy="6.5" rx="7" ry="3"/><path d="M5 6.5v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5"/><path d="M5 11.5v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5"/>',
        'place' => '<path d="M12 21s7-6 7-11a7 7 0 0 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    ];

    /** @return array<int, string> Названия для словаря модели */
    public static function names(): array
    {
        return array_keys(self::SHAPES);
    }

    public static function has(?string $name): bool
    {
        return $name !== null && isset(self::SHAPES[$name]);
    }

    /**
     * Готовая разметка иконки. Цвет и размер наследуются от родителя,
     * поэтому одна иконка одинаково работает во всех шаблонах.
     */
    public static function svg(?string $name, string $size = '100%'): string
    {
        if (! self::has($name)) {
            return '';
        }

        return sprintf(
            '<svg viewBox="0 0 24 24" width="%s" height="%s" fill="none" '
            .'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" '
            .'stroke-linejoin="round">%s</svg>',
            $size,
            $size,
            self::SHAPES[$name],
        );
    }
}
