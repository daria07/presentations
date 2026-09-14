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
    | Любая гамма сочетается с любой темой, поэтому вариантов 21,
    | и каждый собран осознанно, а не случайной комбинацией.
    */

    'default_palette' => 'graphite',
    'default_theme' => 'precise',

    'palettes' => [

        'graphite' => [
            'name' => 'Графит',
            'note' => 'Синий на белом, деловой',
            'paper' => '#FFFFFF',
            'ink' => '#14161A',
            'muted' => '#6B7280',
            'rule' => '#E3E6EA',
            'accent' => '#2F6BFF',
            'accent_ink' => '#1E4FD1',
            'accent_soft' => '#EAF0FF',
            'cover_bg' => '#101319',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#6E93FF',
        ],

        'coal' => [
            'name' => 'Уголь',
            'note' => 'Чёрный с оранжевым акцентом',
            'paper' => '#FFFFFF',
            'ink' => '#111111',
            'muted' => '#6E6E6E',
            'rule' => '#E4E4E4',
            'accent' => '#F2542D',
            'accent_ink' => '#C43A18',
            'accent_soft' => '#FDEAE3',
            'cover_bg' => '#161616',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#FF8F6B',
        ],

        'pine' => [
            'name' => 'Хвоя',
            'note' => 'Спокойная зелень',
            'paper' => '#FCFDFB',
            'ink' => '#12211A',
            'muted' => '#63756C',
            'rule' => '#DDE6E0',
            'accent' => '#1E8F63',
            'accent_ink' => '#14684A',
            'accent_soft' => '#E3F3EB',
            'cover_bg' => '#0E2019',
            'cover_ink' => '#F4FBF7',
            'cover_accent' => '#57C596',
        ],

        'bordeaux' => [
            'name' => 'Бордо',
            'note' => 'Глубокий красный',
            'paper' => '#FFFCFC',
            'ink' => '#1E1214',
            'muted' => '#77666A',
            'rule' => '#E9DFE1',
            'accent' => '#9E2A3B',
            'accent_ink' => '#761D2B',
            'accent_soft' => '#F7E7EA',
            'cover_bg' => '#1C0F12',
            'cover_ink' => '#FFF6F7',
            'cover_accent' => '#D7697A',
        ],

        'sand' => [
            'name' => 'Песок',
            'note' => 'Тёплая охра',
            'paper' => '#FDFBF6',
            'ink' => '#241F16',
            'muted' => '#7A7264',
            'rule' => '#E8E1D3',
            'accent' => '#B9791F',
            'accent_ink' => '#8C5A11',
            'accent_soft' => '#F7EEDC',
            'cover_bg' => '#20190F',
            'cover_ink' => '#FDF8EF',
            'cover_accent' => '#E2A94E',
        ],

        'ocean' => [
            'name' => 'Океан',
            'note' => 'Холодная бирюза',
            'paper' => '#FAFCFD',
            'ink' => '#0F1D26',
            'muted' => '#5F7480',
            'rule' => '#DCE5EA',
            'accent' => '#0E7490',
            'accent_ink' => '#0A5A70',
            'accent_soft' => '#DFF0F5',
            'cover_bg' => '#0B1A22',
            'cover_ink' => '#F2FAFC',
            'cover_accent' => '#4FB6D1',
        ],

        'plum' => [
            'name' => 'Слива',
            'note' => 'Фиолетовый',
            'paper' => '#FDFBFE',
            'ink' => '#1B1423',
            'muted' => '#6E6478',
            'rule' => '#E5DEEA',
            'accent' => '#6D3FD1',
            'accent_ink' => '#5228A8',
            'accent_soft' => '#EDE6FA',
            'cover_bg' => '#170F1E',
            'cover_ink' => '#FAF6FE',
            'cover_accent' => '#A585F2',
        ],
        'dopamine' => [
            'name' => 'Дофамин',
            'note' => 'Фуксия и фиолетовый, жёлтый акцент на обложке',
            'paper' => '#FFFCF8',
            'ink' => '#1A0B2E',
            'muted' => '#6E5A7E',
            'rule' => '#EFE6F2',
            'accent' => '#FF2E88',
            'accent_ink' => '#C2005F',
            'accent_soft' => '#FFE1EF',
            'cover_bg' => '#3B0A6B',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#FFD166',
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
