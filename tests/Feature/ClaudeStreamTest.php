<?php

use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeException;
use App\Services\Claude\StreamReader;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
   Ответ модели приходит потоком (SSE): иначе шлюз рвёт соединение
   после минуты тишины. Поток собирается в то же тело, что и без него.
*/

/** SSE-поток с вызовом инструмента, JSON порезан на куски как в жизни */
function sseToolCall(string $tool, array $input): string
{
    $json = json_encode($input, JSON_UNESCAPED_UNICODE);
    // По символам, а не байтам: в JSON-событии кусок должен быть цельным UTF-8
    $parts = mb_str_split($json, 7);

    $events = [
        ['message_start', ['type' => 'message_start', 'message' => ['id' => 'm1', 'model' => 'claude-x', 'usage' => ['input_tokens' => 120, 'output_tokens' => 1]]]],
        ['content_block_start', ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 't1', 'name' => $tool, 'input' => []]]],
        ['ping', ['type' => 'ping']],
    ];

    foreach ($parts as $part) {
        $events[] = ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => $part]]];
    }

    $events[] = ['content_block_stop', ['type' => 'content_block_stop', 'index' => 0]];
    $events[] = ['message_delta', ['type' => 'message_delta', 'delta' => ['stop_reason' => 'tool_use'], 'usage' => ['output_tokens' => 345]]];
    $events[] = ['message_stop', ['type' => 'message_stop']];

    return collect($events)
        ->map(fn ($e) => "event: {$e[0]}\r\ndata: ".json_encode($e[1], JSON_UNESCAPED_UNICODE)."\r\n\r\n")
        ->implode('');
}

test('поток собирается в обычное тело ответа', function () {
    $body = StreamReader::read(Utils::streamFor(sseToolCall('build_outline', ['title' => 'Пётр I', 'slides' => [['heading' => 'Флот']]])));

    expect($body['model'])->toBe('claude-x')
        ->and($body['stop_reason'])->toBe('tool_use')
        ->and($body['usage'])->toBe(['input_tokens' => 120, 'output_tokens' => 345])
        ->and($body['content'][0]['name'])->toBe('build_outline')
        ->and($body['content'][0]['input'])->toBe(['title' => 'Пётр I', 'slides' => [['heading' => 'Флот']]]);
});

test('текстовый ответ тоже собирается', function () {
    $sse = "data: {\"type\":\"message_start\",\"message\":{\"model\":\"m\",\"usage\":{\"input_tokens\":1}}}\n\n"
        ."data: {\"type\":\"content_block_start\",\"index\":0,\"content_block\":{\"type\":\"text\",\"text\":\"\"}}\n\n"
        ."data: {\"type\":\"content_block_delta\",\"index\":0,\"delta\":{\"type\":\"text_delta\",\"text\":\"При\"}}\n\n"
        ."data: {\"type\":\"content_block_delta\",\"index\":0,\"delta\":{\"type\":\"text_delta\",\"text\":\"вет\"}}\n\n";

    expect(StreamReader::read(Utils::streamFor($sse))['content'][0]['text'])->toBe('Привет');
});

test('ошибка внутри потока превращается в повторяемое исключение', function () {
    $sse = "event: error\ndata: {\"type\":\"error\",\"error\":{\"type\":\"overloaded_error\",\"message\":\"Overloaded\"}}\n\n";

    try {
        StreamReader::read(Utils::streamFor($sse));
        $this->fail('Ожидалось исключение');
    } catch (ClaudeException $e) {
        expect($e->isRetryable())->toBeTrue();
    }
});

test('клиент просит поток и разбирает его', function () {
    Http::fake(['gw.test/*' => Http::response(
        sseToolCall('build_outline', ['title' => 'T']),
        200,
        ['Content-Type' => 'text/event-stream; charset=utf-8'],
    )]);

    $client = new ClaudeClient('k', 'https://gw.test', 'claude-x', 30000, 150000);
    $result = $client->structured('sys', 'prompt', ['type' => 'object'], 'build_outline', 'd');

    expect($result->data)->toBe(['title' => 'T'])
        ->and($result->inputTokens)->toBe(120)
        ->and($result->outputTokens)->toBe(345);

    Http::assertSent(fn (Request $r) => $r['stream'] === true);
});

test('шлюз без поддержки потока отвечает обычным JSON — тоже работает', function () {
    Http::fake(['gw.test/*' => Http::response([
        'model' => 'claude-x',
        'stop_reason' => 'tool_use',
        'usage' => ['input_tokens' => 5, 'output_tokens' => 7],
        'content' => [['type' => 'tool_use', 'name' => 'build_outline', 'input' => ['title' => 'T']]],
    ])]);

    $client = new ClaudeClient('k', 'https://gw.test', 'claude-x', 30000, 150000);

    expect($client->structured('sys', 'p', ['type' => 'object'], 'build_outline', 'd')->data)->toBe(['title' => 'T']);
});
