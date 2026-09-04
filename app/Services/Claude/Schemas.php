<?php

namespace App\Services\Claude;

use App\Services\Deck\Icons;
use App\Services\Deck\Motifs;

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
                'motif' => [
                    'type' => 'string',
                    'enum' => [...Motifs::names(), 'none'],
                    'description' => 'Фоновый узор обложки по духу темы, '
                        .'или none, если тема не просит узора. '
                        .collect(Motifs::MEANINGS)
                            ->map(fn ($m, $k) => "{$k} — {$m}")
                            ->implode('; '),
                ],
                'slides' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'layout' => [
                                'type' => 'string',
                                'enum' => [
                                    'title', 'bullets', 'stats', 'timeline',
                                    'quote', 'comparison', 'process', 'matrix',
                                    'bignumber', 'closing',
                                ],
                                'description' => 'Тип вёрстки. bullets — перечисление; '
                                    .'stats — несколько чисел; bignumber — одно число, '
                                    .'которое стоит выделить; timeline — события по годам; '
                                    .'process — этапы, идущие один за другим; '
                                    .'comparison — два подхода рядом; matrix — четыре '
                                    .'категории по двум осям; quote — цитата.',
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
                                'description' => 'Для bullets, comparison, process и matrix. '
                                    .'В comparison ровно два, в matrix ровно четыре.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'text' => ['type' => 'string'],
                                        'icon' => [
                                            'type' => 'string',
                                            // none в словаре нужен, чтобы поле можно было
                                            // сделать обязательным: необязательные поля
                                            // модель систематически пропускает
                                            'enum' => [...Icons::names(), 'none'],
                                            'description' => 'Пиктограмма по смыслу пункта. '
                                                .'Если ничего не подходит — none.',
                                        ],
                                    ],
                                    'required' => ['title', 'text', 'icon'],
                                ],
                            ],
                            'stats' => [
                                'type' => 'array',
                                'maxItems' => 4,
                                'description' => 'Для stats, timeline и bignumber: число или год '
                                    .'плюс подпись. В bignumber ровно один.',
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
