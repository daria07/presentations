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

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        // Базовый URL. Прямо в Anthropic — https://api.anthropic.com,
        // через посредника — адрес шлюза. Путь /v1/messages добавляется сам.
        'base_url' => rtrim(env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'), '/'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        // Цены за миллион токенов, в сотых доли цента: 10000 = $1.
        // Задаются по тарифу шлюза, а не по прайсу Anthropic.
        'price_input' => env('ANTHROPIC_PRICE_INPUT', 2700),
        'price_output' => env('ANTHROPIC_PRICE_OUTPUT', 13300),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
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
