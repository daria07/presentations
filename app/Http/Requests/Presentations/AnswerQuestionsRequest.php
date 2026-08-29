<?php

namespace App\Http\Requests\Presentations;

use Illuminate\Foundation\Http\FormRequest;

class AnswerQuestionsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:200'],
            'theme' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('deck.themes')))],
        ];
    }

    public function messages(): array
    {
        return [
            'answers.required' => 'Ответьте хотя бы на один вопрос.',
        ];
    }
}
