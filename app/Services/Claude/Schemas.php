<?php

namespace App\Services\Claude;

/**
 * JSON-схемы, по которым модель обязана вернуть ответ.
 */
class Schemas
{
    public static function clarifyingQuestions(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'maxItems' => 4,
                    'description' => 'Вопросы, ответы на которые сильнее всего повлияют на содержание.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => [
                                'type' => 'string',
                                'description' => 'Короткий латинский идентификатор, например audience или depth.',
                            ],
                            'question' => [
                                'type' => 'string',
                                'description' => 'Вопрос на русском, одно предложение.',
                            ],
                            'options' => [
                                'type' => 'array',
                                'minItems' => 2,
                                'maxItems' => 4,
                                'items' => ['type' => 'string'],
                                'description' => 'Готовые варианты ответа, коротко.',
                            ],
                        ],
                        'required' => ['key', 'question', 'options'],
                    ],
                ],
            ],
            'required' => ['questions'],
        ];
    }

    public static function outline(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Название презентации, до 60 знаков.',
                ],
                'subtitle' => [
                    'type' => 'string',
                    'description' => 'Подзаголовок для титульного слайда.',
                ],
                'slides' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'layout' => [
                                'type' => 'string',
                                'enum' => ['title', 'bullets', 'stats', 'timeline', 'quote', 'comparison', 'closing'],
                                'description' => 'Тип вёрстки слайда.',
                            ],
                            'heading' => [
                                'type' => 'string',
                                'description' => 'Заголовок слайда, до 50 знаков.',
                            ],
                            'subheading' => [
                                'type' => 'string',
                                'description' => 'Необязательный подзаголовок.',
                            ],
                            'bullets' => [
                                'type' => 'array',
                                'maxItems' => 5,
                                'description' => 'Для layout bullets и comparison.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'text' => ['type' => 'string'],
                                    ],
                                    'required' => ['title', 'text'],
                                ],
                            ],
                            'stats' => [
                                'type' => 'array',
                                'maxItems' => 4,
                                'description' => 'Для layout stats и timeline: число или год плюс подпись.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'value' => ['type' => 'string'],
                                        'label' => ['type' => 'string'],
                                    ],
                                    'required' => ['value', 'label'],
                                ],
                            ],
                            'quote' => [
                                'type' => 'object',
                                'description' => 'Для layout quote.',
                                'properties' => [
                                    'text' => ['type' => 'string'],
                                    'author' => ['type' => 'string'],
                                ],
                                'required' => ['text', 'author'],
                            ],
                            'notes' => [
                                'type' => 'string',
                                'description' => 'Заметки докладчика, два-три предложения.',
                            ],
                        ],
                        'required' => ['layout', 'heading', 'notes'],
                    ],
                ],
            ],
            'required' => ['title', 'slides'],
        ];
    }
}
