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
    | Оформление
    |--------------------------------------------------------------------------
    | Титульный и финальный слайды рисуются на тёмном фоне, содержательные —
    | на светлом. Это задаёт ритм и не даёт презентации выглядеть однообразно.
    */

    // Совпадает с фирменным цветом сайта — файл и интерфейс
    // выглядят частями одного продукта.
    'default_theme' => 'clay',

    'themes' => [

        'graphite' => [
            'name' => 'Графит',
            'paper' => '#FFFFFF',
            'ink' => '#12151C',
            'muted' => '#69707E',
            'rule' => '#E4E7EE',
            'accent' => '#1F5FD6',
            'accent_ink' => '#164AA8',
            'accent_soft' => '#EDF2FD',
            'cover_bg' => '#12151C',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#7AA5FF',
            'font_display' => 'Manrope',
            'font_body' => 'Golos Text',
        ],

        'forest' => [
            'name' => 'Хвойный',
            'paper' => '#FFFFFF',
            'ink' => '#12201A',
            'muted' => '#5E6F66',
            'rule' => '#E0E8E3',
            'accent' => '#1C7A55',
            'accent_ink' => '#145C40',
            'accent_soft' => '#E9F4EF',
            'cover_bg' => '#12201A',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#6FD3A6',
            'font_display' => 'Manrope',
            'font_body' => 'Golos Text',
        ],

        'clay' => [
            'name' => 'Терракота',
            'paper' => '#FFFDFB',
            'ink' => '#231A16',
            'muted' => '#7A6B63',
            'rule' => '#EDE4DE',
            'accent' => '#B4502A',
            'accent_ink' => '#8C3D1F',
            'accent_soft' => '#FAEFEA',
            'cover_bg' => '#231A16',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#E8916B',
            'font_display' => 'Manrope',
            'font_body' => 'Golos Text',
        ],

        'ink' => [
            'name' => 'Тушь',
            'paper' => '#FFFFFF',
            'ink' => '#111111',
            'muted' => '#6B6B6B',
            'rule' => '#E4E4E4',
            'accent' => '#111111',
            'accent_ink' => '#000000',
            'accent_soft' => '#F2F2F2',
            'cover_bg' => '#111111',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#FFFFFF',
            'font_display' => 'Manrope',
            'font_body' => 'Golos Text',
        ],

        'plum' => [
            'name' => 'Слива',
            'paper' => '#FCFAFD',
            'ink' => '#1E1424',
            'muted' => '#6B5F73',
            'rule' => '#E9E2ED',
            'accent' => '#6D3E8E',
            'accent_ink' => '#54306E',
            'accent_soft' => '#F3ECF8',
            'cover_bg' => '#1E1424',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#B98FD6',
            'font_display' => 'Manrope',
            'font_body' => 'Golos Text',
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
