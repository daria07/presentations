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
            'note' => 'Собранная деловая нейтраль',
            'paper' => '#FCFCFB',
            'ink' => '#16181C',
            'muted' => '#66707C',
            'rule' => '#DCE2E8',
            'accent' => '#5E7FA3',
            'accent_ink' => '#436587',
            'accent_soft' => '#EAF1F7',
            'cover_bg' => '#5E6E80',
            'cover_ink' => '#FFFFFF',
            'cover_accent' => '#AFC2D7',
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
            'note' => 'Тёплая терракота: уверенно и по-человечески',
            'paper' => '#FFFCF8',
            'ink' => '#241914',
            'muted' => '#75665D',
            'rule' => '#E7DDD4',
            'accent' => '#C9794F',
            'accent_ink' => '#9F5C39',
            'accent_soft' => '#F8E7DD',
            'cover_bg' => '#8F5A3C',
            'cover_ink' => '#FFF8F4',
            'cover_accent' => '#E6AA84',
        ],

        'sage' => [
            'name' => 'Шалфей',
            'note' => 'Чистая зелень: учёба, экология, дело',
            'paper' => '#FBFCFA',
            'ink' => '#142019',
            'muted' => '#617066',
            'rule' => '#DCE5DE',
            'accent' => '#5E9077',
            'accent_ink' => '#3F6F59',
            'accent_soft' => '#E5F0E8',
            'cover_bg' => '#355646',
            'cover_ink' => '#F7FBF8',
            'cover_accent' => '#8EB49E',
        ],

        'wine' => [
            'name' => 'Вино',
            'note' => 'Editorial: умно и серьёзно',
            'paper' => '#FFFBFB',
            'ink' => '#211417',
            'muted' => '#736268',
            'rule' => '#E6DADF',
            'accent' => '#9D4E63',
            'accent_ink' => '#7C364B',
            'accent_soft' => '#F4E6EB',
            'cover_bg' => '#4E2433',
            'cover_ink' => '#FFF7F9',
            'cover_accent' => '#D58EA5',
        ],

        'honey' => [
            'name' => 'Мёд',
            'note' => 'Тёплая деловая: образование, истории, мягкий маркетинг',
            'paper' => '#FFFDF8',
            'ink' => '#221A11',
            'muted' => '#786D5D',
            'rule' => '#E8DFD0',
            'accent' => '#C89A3D',
            'accent_ink' => '#9A741E',
            'accent_soft' => '#F7EDD7',
            'cover_bg' => '#7B5A1E',
            'cover_ink' => '#FFF9EF',
            'cover_accent' => '#E8C36D',
        ],

        'lagoon' => [
            'name' => 'Лагуна',
            'note' => 'Технологичная: digital, AI, SaaS, аналитика',
            'paper' => '#FAFCFD',
            'ink' => '#10202A',
            'muted' => '#5E7380',
            'rule' => '#D9E6EC',
            'accent' => '#3E8EAA',
            'accent_ink' => '#216B85',
            'accent_soft' => '#E2F1F5',
            'cover_bg' => '#183C4F',
            'cover_ink' => '#F4FBFD',
            'cover_accent' => '#79BDD2',
        ],

        'iris' => [
            'name' => 'Ирис',
            'note' => 'Креативная, но собранная',
            'paper' => '#FDFCFE',
            'ink' => '#1C1522',
            'muted' => '#6D6678',
            'rule' => '#E4DDEC',
            'accent' => '#7E68B8',
            'accent_ink' => '#614A96',
            'accent_soft' => '#EEE8F7',
            'cover_bg' => '#46385F',
            'cover_ink' => '#FBF8FE',
            'cover_accent' => '#B19DDB',
        ],

        'neon' => [
            'name' => 'Неон-поп',
            'note' => 'Яркая для маркетинга — без игрушечности',
            'paper' => '#FFFDFB',
            'ink' => '#1E1125',
            'muted' => '#735F79',
            'rule' => '#EEDFEB',
            'accent' => '#D94B8A',
            'accent_ink' => '#AF2D67',
            'accent_soft' => '#FFE3EF',
            'cover_bg' => '#3E184F',
            'cover_ink' => '#FFF8FC',
            'cover_accent' => '#FF89B5',
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
