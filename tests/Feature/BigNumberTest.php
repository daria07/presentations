<?php

use App\Models\Presentation;
use App\Models\User;
use App\Services\Deck\DeckRenderer;

/*
   Кегль большого числа зависит от длины: длинное значение в кегле
   короткого переносилось, и строки наезжали друг на друга.
*/

function bigNumberHtml(string $value): string
{
    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'Гражданская война',
        'outline' => ['title' => 'Гражданская война', 'slides' => [
            ['layout' => 'bignumber', 'heading' => 'Колчак', 'stats' => [['value' => $value, 'label' => 'Расстрел']]],
        ]],
    ]);

    return app(DeckRenderer::class)->html($presentation);
}

test('короткое число — во весь слайд', function () {
    expect(bigNumberHtml('72%'))->toContain('class="bignum-value"');
});

test('длинное значение — ступенью мельче', function () {
    expect(bigNumberHtml('13 млн'))->toContain('bignum-value--md')
        ->and(bigNumberHtml('Февраль 1920'))->toContain('bignum-value--sm')
        ->and(bigNumberHtml('Октябрь–ноябрь 1917'))->toContain('bignum-value--xs');
});
