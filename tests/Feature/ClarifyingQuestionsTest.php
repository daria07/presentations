<?php

use App\Enums\PresentationStatus;
use App\Jobs\PrepareQuestions;
use App\Models\Presentation;
use App\Models\User;
use App\Services\Claude\PresentationPlanner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
   Уточняющие вопросы: шлюз присылает их по-разному поломанными,
   а форма на сайте должна получить нормальные вопросы с вариантами.
*/

beforeEach(function () {
    config([
        'services.anthropic' => [
            'key' => 'k', 'base_url' => 'https://gw.test', 'model' => 'claude-x',
            'price_input' => 1, 'price_output' => 1,
        ],
    ]);
});

function questionsReply(array $input): Closure
{
    return fn (Request $request) => Http::response([
        'model' => 'claude-x',
        'stop_reason' => 'tool_use',
        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        'content' => [['type' => 'tool_use', 'name' => $request['tools'][0]['name'], 'input' => $input]],
    ]);
}

function askingPresentation(): Presentation
{
    $presentation = Presentation::create([
        'user_id' => User::factory()->create()->id,
        'topic' => 'кошки против собак',
        'slide_count' => 8,
    ]);
    $presentation->forceFill(['status' => PresentationStatus::Draft])->save();

    return $presentation;
}

$good = [
    ['key' => 'audience', 'question' => 'Для кого презентация?', 'options' => ['Школьники', 'Студенты']],
    ['key' => 'depth', 'question' => 'Насколько подробно?', 'options' => ['Кратко', 'Подробно']],
];

test('вопросы приходят нормально — берутся как есть', function () use ($good) {
    expect(PresentationPlanner::cleanQuestions(['questions' => $good]))->toBe($good);
});

test('весь список строкой с JSON', function () use ($good) {
    $data = ['questions' => json_encode($good, JSON_UNESCAPED_UNICODE)];

    expect(PresentationPlanner::cleanQuestions($data))->toBe($good);
});

test('список из одной строки с JSON — тот случай, что дал пустую форму', function () use ($good) {
    $data = ['questions' => [json_encode($good, JSON_UNESCAPED_UNICODE)]];

    expect(PresentationPlanner::cleanQuestions($data))->toBe($good);
});

test('каждый вопрос отдельной строкой с JSON', function () use ($good) {
    $data = ['questions' => array_map(fn ($q) => json_encode($q, JSON_UNESCAPED_UNICODE), $good)];

    expect(PresentationPlanner::cleanQuestions($data))->toBe($good);
});

test('варианты ответа строкой с JSON', function () {
    $data = ['questions' => [['key' => 'a', 'question' => 'Кому?', 'options' => '["Школьникам", "Студентам"]']]];

    expect(PresentationPlanner::cleanQuestions($data)[0]['options'])->toBe(['Школьникам', 'Студентам']);
});

test('вопрос без текста или с одним вариантом отбрасывается', function () {
    $data = ['questions' => [
        ['key' => 'a', 'question' => '', 'options' => ['x', 'y']],
        ['key' => 'b', 'question' => 'Один вариант?', 'options' => ['x']],
        ['key' => null, 'question' => 'Годный?', 'options' => ['да', 'нет', 'да']],
    ]];

    expect(PresentationPlanner::cleanQuestions($data))
        ->toBe([['key' => null, 'question' => 'Годный?', 'options' => ['да', 'нет']]]);
});

test('задача сохраняет починенные вопросы и ждёт ответов', function () use ($good) {
    Http::fake(['gw.test/*' => questionsReply(['questions' => [json_encode($good, JSON_UNESCAPED_UNICODE)]])]);

    $presentation = askingPresentation();
    (new PrepareQuestions($presentation))->handle(app(PresentationPlanner::class));
    $presentation->refresh();

    expect($presentation->status)->toBe(PresentationStatus::Asking)
        ->and(array_column($presentation->clarifications, 'question'))->toBe(['Для кого презентация?', 'Насколько подробно?'])
        ->and($presentation->clarifications[0]['answer'])->toBeNull();
});

test('без годных вопросов — стандартные, а не пустая форма', function () {
    Http::fake(['gw.test/*' => questionsReply(['questions' => ['что-то непонятное']])]);

    $presentation = askingPresentation();
    (new PrepareQuestions($presentation))->handle(app(PresentationPlanner::class));
    $presentation->refresh();

    expect($presentation->status)->toBe(PresentationStatus::Asking)
        ->and(array_column($presentation->clarifications, 'key'))->toBe(['audience', 'depth'])
        ->and(collect($presentation->clarifications)->every(fn ($q) => $q['question'] !== '' && count($q['options']) >= 2))->toBeTrue();
});
