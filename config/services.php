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
        'quality' => env('IMAGE_QUALITY', 'medium'),
        // Короткие запросы без служебных фраз: для моделей, которые рисуют
        // текст запроса буквами (grok). Не задано — включается для grok сам
        'compact_prompt' => env('IMAGE_COMPACT_PROMPT'),
        'price_input' => env('IMAGE_PRICE_INPUT', 15000),
        'price_output' => env('IMAGE_PRICE_OUTPUT', 90000),
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
