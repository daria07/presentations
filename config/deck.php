<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Размер слайда
    |--------------------------------------------------------------------------
    | 16:9 в миллиметрах — те же пропорции, что у PowerPoint по умолчанию.
    */

    'width' => 338.667,
    'height' => 190.5,

    /*
    |--------------------------------------------------------------------------
    | Оформление: гамма и тема — две независимые оси
    |--------------------------------------------------------------------------
    | Гамма отвечает только за цвет: десять ролей, ничего больше.
    | Тема — за характер: шрифты, кегли, скругления, линии, тени, поля.
    | Любая гамма сочетается с любой темой, поэтому вариантов 24,
    | и каждый собран осознанно, а не случайной комбинацией.
    */

    'default_palette' => 'fog',
    'default_theme' => 'precise',

    'palettes' => [

        'fog' => [
            'name' => 'Туман',
            'note' => 'Собранный slate-blue: деловая нейтраль',
            'paper' => '#FCFCFD',
            'ink' => '#13161A',
            'muted' => '#5F6874',
            'rule' => '#D8DEE6',
            'accent' => '#4E6F97',
            'accent_ink' => '#35577E',
            'accent_soft' => '#E8EEF6',
            'cover_bg' => '#516277',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#B6C6DA',
        ],

        'graphite' => [
            'name' => 'Графит',
            'note' => 'Тёмная обложка, холодный синий: деловая и строгая',
            'paper' => '#FBFBFC',
            'ink' => '#14161A',
            'muted' => '#5F6672',
            'rule' => '#E3E6EA',
            'accent' => '#4A6FA5',
            'accent_ink' => '#3A5A8A',
            'accent_soft' => '#E9EEF6',
            'cover_bg' => '#14161A',
            'cover_ink' => '#F7F8FA',
            'cover_accent' => '#8FA9D0',
        ],

        'clay' => [
            'name' => 'Глина',
            'note' => 'Уверенная терракота',
            'paper' => '#FFFDFC',
            'ink' => '#211713',
            'muted' => '#6E6158',
            'rule' => '#E5D9D0',
            'accent' => '#B96A43',
            'accent_ink' => '#8E4E2E',
            'accent_soft' => '#F7E5DC',
            'cover_bg' => '#8C4F31',
            'cover_ink' => '#FFF8F4',
            'cover_accent' => '#E0A07B',
        ],

        'sage' => [
            'name' => 'Шалфей',
            'note' => 'Глубокий академический зелёный',
            'paper' => '#FBFCFB',
            'ink' => '#111A15',
            'muted' => '#5D6962',
            'rule' => '#D9E1DC',
            'accent' => '#4F7C67',
            'accent_ink' => '#355B49',
            'accent_soft' => '#E3EEE8',
            'cover_bg' => '#3F5F4D',
            'cover_ink' => '#F7FBF8',
            'cover_accent' => '#96B7A5',
        ],

        'wine' => [
            'name' => 'Вино',
            'note' => 'Editorial: глубоко и серьёзно',
            'paper' => '#FFFCFD',
            'ink' => '#1D1216',
            'muted' => '#6B5D63',
            'rule' => '#E4D8DD',
            'accent' => '#8C3F57',
            'accent_ink' => '#6A293F',
            'accent_soft' => '#F2E3E8',
            'cover_bg' => '#4A2230',
            'cover_ink' => '#FFF8FA',
            'cover_accent' => '#D18AA0',
        ],

        'honey' => [
            'name' => 'Мёд',
            'note' => 'Янтарь: тепло и статус',
            'paper' => '#FFFDF9',
            'ink' => '#21180E',
            'muted' => '#716455',
            'rule' => '#E6DCCB',
            'accent' => '#B8882F',
            'accent_ink' => '#8B6417',
            'accent_soft' => '#F5E9D1',
            'cover_bg' => '#7A5616',
            'cover_ink' => '#FFF9F0',
            'cover_accent' => '#E2BE67',
        ],

        'lagoon' => [
            'name' => 'Лагуна',
            'note' => 'Технологичная: digital, AI, SaaS, аналитика',
            'paper' => '#FAFCFD',
            'ink' => '#0E1C24',
            'muted' => '#586D79',
            'rule' => '#D6E3E9',
            'accent' => '#2F7E99',
            'accent_ink' => '#1B6078',
            'accent_soft' => '#DFEEF4',
            'cover_bg' => '#17394A',
            'cover_ink' => '#F4FBFD',
            'cover_accent' => '#73B8CE',
        ],

        'iris' => [
            'name' => 'Ирис',
            'note' => 'Взрослый креативный фиолетовый',
            'paper' => '#FDFCFE',
            'ink' => '#18131E',
            'muted' => '#675F72',
            'rule' => '#E1DBE8',
            'accent' => '#6F58A8',
            'accent_ink' => '#533D85',
            'accent_soft' => '#ECE5F4',
            'cover_bg' => '#43355E',
            'cover_ink' => '#FBF8FE',
            'cover_accent' => '#AF9AD8',
        ],

        'neon' => [
            'name' => 'Неон',
            'note' => 'Яркая, но под контролем',
            'paper' => '#FFFDFD',
            'ink' => '#1B1020',
            'muted' => '#6E5C73',
            'rule' => '#EADDE7',
            'accent' => '#C93C7E',
            'accent_ink' => '#9A245D',
            'accent_soft' => '#FEE1EC',
            'cover_bg' => '#3B164B',
            'cover_ink' => '#FFF8FC',
            'cover_accent' => '#FF84B3',
        ],

        'night' => [
            'name' => 'Ночь',
            'note' => 'Тёмные слайды целиком — для зала и проектора',
            'paper' => '#14151A',
            'ink' => '#F2F3F6',
            'muted' => '#9BA1AC',
            'rule' => '#2A2D36',
            'accent' => '#7FA3DC',
            'accent_ink' => '#A9C0EA',
            'accent_soft' => '#1E212A',
            'cover_bg' => '#0B0C10',
            'cover_ink' => '#F7F8FA',
            'cover_accent' => '#A9C0EA',
        ],

    ],

    'themes' => [

        'precise' => [
            'name' => 'Строгая',
            'note' => 'Прямые углы, волосяные линии, деловой тон',
            'style' => 'precise',
            'font_display' => 'IBM Plex Sans',
            'font_body' => 'IBM Plex Sans',
        ],

        'bold' => [
            'name' => 'Модная',
            'note' => 'Крупный гротеск, скругления, мягкие тени',
            'style' => 'bold',
            'font_display' => 'Unbounded',
            'font_body' => 'Manrope',
        ],

        'soft' => [
            'name' => 'Мягкая',
            'note' => 'Округлый шрифт, воздух, спокойный ритм',
            'style' => 'soft',
            'font_display' => 'Nunito',
            'font_body' => 'Onest',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Где хранить готовые файлы
    |--------------------------------------------------------------------------
    */

    'disk' => env('DECK_DISK', 'local'),

    // Путь к бинарнику Chrome. Пусто — Browsershot ищет сам.
    'chrome_path' => env('CHROME_PATH'),

    'path' => 'presentations',

];
