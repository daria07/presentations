<?php

namespace App\Http\Requests\Presentations;

use Illuminate\Foundation\Http\FormRequest;

class StorePresentationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:3', 'max:500'],
            'slide_count' => ['required', 'integer', 'min:4', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'topic.required' => 'Напишите, о чём должна быть презентация.',
            'topic.min' => 'Слишком коротко — добавьте пару слов о теме.',
            'topic.max' => 'Тема длиннее 500 знаков, попробуйте короче.',
            'slide_count.min' => 'Меньше четырёх слайдов не собрать.',
            'slide_count.max' => 'Максимум двадцать слайдов за раз.',
        ];
    }
}
