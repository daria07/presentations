<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
     * Языковая модель. Переменные называются LLM_*, а не ANTHROPIC_*:
     * ANTHROPIC_BASE_URL, ANTHROPIC_MODEL и ANTHROPIC_API_KEY читает
     * Claude Code и передаёт всем процессам, которые из него запущены.
     * Запущенный оттуда воркер очереди брал адрес и модель из окружения
     * Claude Code, а не из .env, — и генерация уходила в чужой шлюз.
     * Старые имена читаем как запасные, чтобы сервер не сломался до
     * того, как в его .env переименуют строки.
     */
    'anthropic' => [
        'key' => env('LLM_API_KEY', env('ANTHROPIC_API_KEY')),
        // Базовый URL. Прямо в Anthropic — https://api.anthropic.com,
        // через посредника — адрес шлюза. Путь /v1/messages добавляется сам.
        'base_url' => rtrim((string) env('LLM_BASE_URL', env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com')), '/'),
        'model' => env('LLM_MODEL', env('ANTHROPIC_MODEL', 'claude-sonnet-5-5')),
        // Цены за миллион токенов, в сотых доли цента: 10000 = $1.
        // Задаются по тарифу шлюза, а не по прайсу Anthropic.
        'price_input' => env('LLM_PRICE_INPUT', env('ANTHROPIC_PRICE_INPUT', 2700)),
        'price_output' => env('LLM_PRICE_OUTPUT', env('ANTHROPIC_PRICE_OUTPUT', 13300)),
    ],

    /*
     * Картинки для слайдов (App\Services\Images). Пустая IMAGE_MODEL —
     * картинок нет вовсе: так их можно выключить одной строкой, если
     * шлюз начнёт сбоить. Адрес и ключ по умолчанию те же, что у текста.
     * Цены — в тех же единицах: сотые доли цента за 1M токенов.
     */
    'images' => [
        'model' => env('IMAGE_MODEL'),
        'base_url' => rtrim((string) env('IMAGE_BASE_URL', env('LLM_BASE_URL', env('ANTHROPIC_BASE_URL', ''))), '/'),
        'key' => env('IMAGE_API_KEY', env('LLM_API_KEY', env('ANTHROPIC_API_KEY'))),
        /*
         * Как устроен API картинок:
         * openai — /v1/images/generations, картинка в ответе на запрос;
         * async  — /v1/media/generations (sozdai.ai): запрос ставит
         *          задачу, результат забираем опросом по id. Цену шлюз
         *          называет сам, IMAGE_PRICE_* для него не нужны
         */
        'api' => env('IMAGE_API', 'openai'),
        // Разрешение для async: 1K, 2K или 4K, каждый шаг вдвое дороже
        'resolution' => env('IMAGE_RESOLUTION', '1K'),
        'quality' => env('IMAGE_QUALITY', 'medium'),
        // Короткие запросы без служебных фраз: для моделей, которые рисуют
        // текст запроса буквами (grok). Не задано — включается для grok сам
        'compact_prompt' => env('IMAGE_COMPACT_PROMPT'),
        // Потоковый ответ. Выключить для шлюзов, которые его не умеют
        'stream' => env('IMAGE_STREAM', true),
        'price_input' => env('IMAGE_PRICE_INPUT', 15000),
        'price_output' => env('IMAGE_PRICE_OUTPUT', 90000),
    ],

    /*
     * Запасной шлюз картинок: к нему идут картинки, которые основной
     * не отдал (обрывы, 5xx, пустые ответы). Без модели запасного нет.
     * Незаданные поля берутся у основного шлюза.
     */
    'images_fallback' => [
        'model' => env('IMAGE_FALLBACK_MODEL'),
        'base_url' => env('IMAGE_FALLBACK_BASE_URL') ? rtrim((string) env('IMAGE_FALLBACK_BASE_URL'), '/') : null,
        'key' => env('IMAGE_FALLBACK_API_KEY'),
        // Не задано: запасной со своим адресом — openai, без своего
        // адреса — такой же, как основной
        'api' => env('IMAGE_FALLBACK_API'),
        'stream' => env('IMAGE_FALLBACK_STREAM'),
        'compact_prompt' => env('IMAGE_FALLBACK_COMPACT_PROMPT'),
    ],

    /*
     * Яндекс Search API — поиск источников для презентации по теме.
     * Без ключа или каталога поиск выключен, и генерация идёт как
     * раньше: по знаниям модели, без слайда с источниками.
     */
    'yandex_search' => [
        'key' => env('YANDEX_SEARCH_API_KEY'),
        'folder' => env('YANDEX_FOLDER_ID'),
    ],

    /*
     * Вход через VK ID (id.vk.com). Без номера приложения кнопки «Войти
     * через VK» нет. Номер — в кабинете id.vk.com → приложение → «ID
     * приложения». Секретный ключ не нужен: вход идёт по PKCE.
     */
    'vkid' => [
        'client_id' => env('VKID_CLIENT_ID'),
    ],

    /*
     * Вход через Google. Без номера и секрета кнопки «Войти через
     * Google» нет. Оба — в Google Cloud Console → APIs & Services →
     * Credentials → OAuth client ID (тип «Web application»).
     */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
     * Яндекс Метрика. Пусто — счётчик не подключается вовсе:
     * так локальная разработка не попадает в статистику.
     */
    'metrika' => [
        'id' => env('YANDEX_METRIKA_ID'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
