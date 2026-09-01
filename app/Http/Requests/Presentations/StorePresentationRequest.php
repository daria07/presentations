<?php

namespace App\Http\Requests\Presentations;

use Illuminate\Foundation\Http\FormRequest;

class StorePresentationRequest extends FormRequest
{
    /** Столько знаков влезает примерно в сорок страниц текста */
    public const MAX_SOURCE = 60000;

    public function rules(): array
    {
        return [
            'topic' => ['nullable', 'required_without:source_text', 'string', 'min:3', 'max:500'],
            'source_text' => ['nullable', 'required_without:topic', 'string', 'min:200', 'max:'.self::MAX_SOURCE],
            'slide_count' => ['required', 'integer', 'min:4', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'topic.required_without' => 'Напишите тему или вставьте готовый текст.',
            'topic.min' => 'Слишком коротко — добавьте пару слов о теме.',
            'topic.max' => 'Тема длиннее 500 знаков, попробуйте короче.',
            'source_text.required_without' => 'Вставьте текст или укажите тему.',
            'source_text.min' => 'Для текста этого мало — нужно хотя бы 200 знаков.',
            'source_text.max' => 'Текст слишком большой. Максимум 60 000 знаков — это около сорока страниц.',
            'slide_count.min' => 'Меньше четырёх слайдов не собрать.',
            'slide_count.max' => 'Максимум двадцать слайдов за раз.',
        ];
    }
}
