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

        /*
         | Шаблон — это цельная визуальная система: палитра, шрифты
         | и характер вёрстки. Ключ style указывает на файл стилей
         | в resources/views/deck/styles, разметка слайдов у всех общая.
         */

        'graphite' => [
            'name' => 'Строгий',
            'note' => 'Волосяные линии, острые углы, деловой тон',
            'style' => 'strict',
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
            'name' => 'Мягкий',
            'note' => 'Крупные скругления, воздух, спокойная зелень',
            'style' => 'soft',
            'paper' => '#F7FAF8',
            'ink' => '#12201A',
            'muted' => '#5E6F66',
            'rule' => '#DDE9E3',
            'accent' => '#1C7A55',
            'accent_ink' => '#145C40',
            'accent_soft' => '#E4F1EB',
            'cover_bg' => '#12201A',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#6FD3A6',
            'font_display' => 'Manrope',
            'font_body' => 'Golos Text',
        ],

        'clay' => [
            'name' => 'Журнальный',
            'note' => 'Засечки в заголовках, тёплая бумага',
            'style' => 'magazine',
            'paper' => '#FFFCF8',
            'ink' => '#231A16',
            'muted' => '#7A6B63',
            'rule' => '#EDE2DA',
            'accent' => '#B4502A',
            'accent_ink' => '#8C3D1F',
            'accent_soft' => '#FAEFEA',
            'cover_bg' => '#231A16',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#E8916B',
            'font_display' => 'Lora',
            'font_body' => 'Golos Text',
        ],

        'ink' => [
            'name' => 'Контрастный',
            'note' => 'Чёрное и белое, крупный шрифт, жирные линии',
            'style' => 'contrast',
            'paper' => '#FFFFFF',
            'ink' => '#0A0A0A',
            'muted' => '#666666',
            'rule' => '#0A0A0A',
            'accent' => '#0A0A0A',
            'accent_ink' => '#000000',
            'accent_soft' => '#F0F0F0',
            'cover_bg' => '#0A0A0A',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#FFFFFF',
            'font_display' => 'Manrope',
            'font_body' => 'Golos Text',
        ],

        'plum' => [
            'name' => 'Академический',
            'note' => 'Нумерация разделов, сдержанный тон',
            'style' => 'academic',
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
            'font_display' => 'Lora',
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
