<?php

namespace App\Http\Requests\Presentations;

use App\Services\Deck\Icons;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Правка структуры приходит целиком: порядок слайдов, удаление и
 * добавление — это просто другой массив. Поэтому проверяем его
 * строго, иначе в вёрстку попадёт что угодно.
 */
class UpdateOutlineRequest extends FormRequest
{
    private const LAYOUTS = [
        'title', 'bullets', 'stats', 'timeline', 'quote',
        'comparison', 'process', 'matrix', 'bignumber', 'closing',
    ];

    public function rules(): array
    {
        return self::outlineRules();
    }

    /**
     * Правила структуры — общие для сохранения и для превью.
     *
     * Черновик из редактора ($draft) проверяется так же строго по
     * форме и размеру, но допускает пустые заголовки: человек как раз
     * стёр старый и печатает новый, и превью не должно на этом замирать.
     *
     * @return array<string, array<int, string>>
     */
    public static function outlineRules(bool $draft = false): array
    {
        // Пустой — можно, отсутствующий — нет: шаблон обращается
        // к заголовку напрямую
        $required = $draft ? ['present', 'nullable'] : ['required'];

        return [
            'title' => [...$required, 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:300'],

            'slides' => ['required', 'array', 'min:1', 'max:30'],
            // Каждый слайд, пункт и показатель — именно массив: строка
            // вместо него прошла бы мимо вложенных правил и уронила бы
            // шаблон на $b['icon']
            'slides.*' => ['required', 'array'],
            'slides.*.layout' => ['required', 'string', 'in:'.implode(',', self::LAYOUTS)],
            'slides.*.heading' => [...$required, 'string', 'max:120'],
            'slides.*.subheading' => ['nullable', 'string', 'max:300'],
            'slides.*.notes' => ['nullable', 'string', 'max:600'],

            'slides.*.bullets' => ['nullable', 'array', 'max:6'],
            'slides.*.bullets.*' => ['array'],
            'slides.*.bullets.*.title' => ['nullable', 'string', 'max:100'],
            'slides.*.bullets.*.text' => ['nullable', 'string', 'max:400'],
            'slides.*.bullets.*.icon' => ['nullable', 'string', 'in:'.implode(',', Icons::names())],

            'slides.*.stats' => ['nullable', 'array', 'max:4'],
            'slides.*.stats.*' => ['array'],
            'slides.*.stats.*.value' => ['nullable', 'string', 'max:40'],
            'slides.*.stats.*.label' => ['nullable', 'string', 'max:150'],

            'slides.*.quote' => ['nullable', 'array'],
            'slides.*.quote.text' => ['nullable', 'string', 'max:400'],
            'slides.*.quote.author' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'У презентации должно быть название.',
            'slides.required' => 'Должен остаться хотя бы один слайд.',
            'slides.min' => 'Должен остаться хотя бы один слайд.',
            'slides.max' => 'Больше тридцати слайдов не поместится.',
            'slides.*.heading.required' => 'У каждого слайда должен быть заголовок.',
            'slides.*.heading.max' => 'Заголовок длиннее 120 знаков не поместится на слайд.',
        ];
    }
}
