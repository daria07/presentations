<?php

namespace App\Services\Claude;

use App\Services\Deck\Icons;

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

    /**
     * Конспект темы: то, что модель продумывает до разбивки по слайдам.
     *
     * Разделы заданы отдельными полями, а не одним куском текста:
     * так модель обязана пройти по каждому, а не ограничиться тем,
     * что первым пришло в голову.
     *
     * @return array<string, mixed>
     */
    public static function brief(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'thesis' => [
                    'type' => 'string',
                    'description' => 'Главная мысль всей презентации, одно предложение. Не тема, а утверждение, ради которого она существует.',
                ],
                'audience' => [
                    'type' => 'string',
                    'description' => 'Кто слушает и что уже знает. Одно предложение.',
                ],
                'arc' => [
                    'type' => 'string',
                    'description' => 'Как разворачивается рассказ: с чего начинаем, через что ведём, чем заканчиваем. Два-три предложения.',
                ],
                'points' => [
                    'type' => 'array',
                    'minItems' => 5,
                    'maxItems' => 14,
                    'description' => 'Содержательные блоки будущих слайдов. Больше, чем слайдов: лишнее потом отсеется.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'О чём блок, коротко.',
                            ],
                            'substance' => [
                                'type' => 'string',
                                'description' => 'Что конкретно тут говорится: механизм, причина, пример, следствие. Три-пять предложений, по существу, без общих слов.',
                            ],
                            'specifics' => [
                                'type' => 'array',
                                'maxItems' => 6,
                                'items' => ['type' => 'string'],
                                'description' => 'Конкретика: числа, даты, имена, названия, примеры. Только то, в чём уверен. Пустой массив, если уверенного нет.',
                            ],
                        ],
                        'required' => ['title', 'substance'],
                    ],
                ],
                'avoid' => [
                    'type' => 'array',
                    'maxItems' => 5,
                    'items' => ['type' => 'string'],
                    'description' => 'Банальности и общие места, которые на этой теме напрашиваются и которых надо избежать.',
                ],
                'uncertain' => [
                    'type' => 'array',
                    'maxItems' => 6,
                    'items' => ['type' => 'string'],
                    'description' => 'Утверждения, в которых не уверен. Их нельзя подавать как факт.',
                ],
            ],
            'required' => ['thesis', 'arc', 'points'],
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
                                // Четыре, а не пять: пятый пункт с длинным
                                // описанием не влезает в высоту слайда
                                'maxItems' => 4,
                                'description' => 'Для bullets, comparison, process и matrix. '
                                    .'В comparison ровно два, в matrix ровно четыре.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'text' => [
                                            'type' => 'string',
                                            'description' => 'Одно предложение до 120 знаков: '
                                                .'высота слайда фиксирована, лишнее обрежется.',
                                        ],
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
