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
            'note' => 'Спокойная нейтраль, подходит почти всему',
            'paper' => '#FCFBF8',
            'ink' => '#1E1C1A',
            'muted' => '#746F68',
            'rule' => '#E7E1DA',
            'accent' => '#7C8FA6',
            'accent_ink' => '#5F738C',
            'accent_soft' => '#EAEFF4',
            'cover_bg' => '#D8D2CB',
            'cover_ink' => '#1F1B18',
            'cover_accent' => '#8EA1B7',
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
            'note' => 'Тёплая и человечная',
            'paper' => '#FFF8F3',
            'ink' => '#241A16',
            'muted' => '#7A675E',
            'rule' => '#EEDFD4',
            'accent' => '#D88963',
            'accent_ink' => '#B56A47',
            'accent_soft' => '#FBE9E0',
            'cover_bg' => '#E7D2C3',
            'cover_ink' => '#2B1C16',
            'cover_accent' => '#E39B73',
        ],

        'sage' => [
            'name' => 'Шалфей',
            'note' => 'Мягкая зелень: образование, экология, здоровье',
            'paper' => '#FBFCF8',
            'ink' => '#172019',
            'muted' => '#667267',
            'rule' => '#DFE7DE',
            'accent' => '#7FA08B',
            'accent_ink' => '#5F8370',
            'accent_soft' => '#E7F0EA',
            'cover_bg' => '#C9D5CB',
            'cover_ink' => '#162019',
            'cover_accent' => '#9DB5A5',
        ],

        'wine' => [
            'name' => 'Вино',
            'note' => 'Editorial: умно и дорого',
            'paper' => '#FEFAFA',
            'ink' => '#221518',
            'muted' => '#7A666B',
            'rule' => '#EADCE0',
            'accent' => '#A66A78',
            'accent_ink' => '#864F5D',
            'accent_soft' => '#F5E8EC',
            'cover_bg' => '#4A2732',
            'cover_ink' => '#FFF7F8',
            'cover_accent' => '#D8A1AF',
        ],

        'honey' => [
            'name' => 'Мёд',
            'note' => 'Светлая и тёплая: маркетинг, lifestyle, малый бизнес',
            'paper' => '#FFFBF4',
            'ink' => '#241C12',
            'muted' => '#7C705F',
            'rule' => '#ECE2D2',
            'accent' => '#D8AE63',
            'accent_ink' => '#AF8741',
            'accent_soft' => '#F9EFD9',
            'cover_bg' => '#E7D4A9',
            'cover_ink' => '#2A2115',
            'cover_accent' => '#F0C97A',
        ],

        'lagoon' => [
            'name' => 'Лагуна',
            'note' => 'Чистая и современная: digital, аналитика, SaaS',
            'paper' => '#F9FCFD',
            'ink' => '#132029',
            'muted' => '#60737E',
            'rule' => '#DCE8EC',
            'accent' => '#6FA7BA',
            'accent_ink' => '#4E8599',
            'accent_soft' => '#E2F0F4',
            'cover_bg' => '#23465A',
            'cover_ink' => '#F3FAFC',
            'cover_accent' => '#8AC6D8',
        ],

        'iris' => [
            'name' => 'Ирис',
            'note' => 'Креативная, но не кричащая',
            'paper' => '#FDFBFE',
            'ink' => '#1D1624',
            'muted' => '#736A7A',
            'rule' => '#E8E0EC',
            'accent' => '#9A86C8',
            'accent_ink' => '#7866A8',
            'accent_soft' => '#F0EAF7',
            'cover_bg' => '#584A72',
            'cover_ink' => '#FBF8FE',
            'cover_accent' => '#B8A6DE',
        ],

        'neon' => [
            'name' => 'Неон-поп',
            'note' => 'Яркий акцент, который всё ещё управляем',
            'paper' => '#FFFBF8',
            'ink' => '#241327',
            'muted' => '#7A657D',
            'rule' => '#F0E1EC',
            'accent' => '#F05C9D',
            'accent_ink' => '#C73B79',
            'accent_soft' => '#FFE3EF',
            'cover_bg' => '#3B1A52',
            'cover_ink' => '#FFF8FC',
            'cover_accent' => '#FF8DBB',
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
