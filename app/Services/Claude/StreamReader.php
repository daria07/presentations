<?php

namespace App\Services\Claude;

use Psr\Http\Message\StreamInterface;

/**
 * Собирает потоковый ответ Messages API (SSE) в то же тело, что
 * приходит без потока: model, content, stop_reason, usage.
 *
 * Зачем поток. Шлюзы рвут соединение, по которому долго не идут
 * данные: cheapai и claudehub — ровно через минуту. Без потока модель
 * пишет структуру презентации две-три минуты молча, и ответ не
 * доживает до конца. В потоке байты идут всё время, и соединение
 * считается живым.
 */
final class StreamReader
{
    /**
     * @return array<string, mixed>
     */
    public static function read(StreamInterface $stream): array
    {
        $body = ['content' => [], 'usage' => []];
        $partialJson = [];
        $buffer = '';

        while (! $stream->eof()) {
            $chunk = $stream->read(8192);

            if ($chunk === '') {
                continue;
            }

            $buffer .= str_replace("\r\n", "\n", $chunk);

            // События разделены пустой строкой
            while (($end = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + 2);

                self::apply($event, $body, $partialJson);
            }
        }

        if (trim($buffer) !== '') {
            self::apply($buffer, $body, $partialJson);
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<int, string>  $partialJson
     */
    private static function apply(string $event, array &$body, array &$partialJson): void
    {
        $data = '';

        foreach (explode("\n", $event) as $line) {
            if (str_starts_with($line, 'data:')) {
                $data .= ltrim(substr($line, 5));
            }
        }

        $payload = json_decode($data, true);

        if (! is_array($payload)) {
            return;
        }

        $index = (int) ($payload['index'] ?? 0);

        switch ($payload['type'] ?? null) {
            case 'message_start':
                $message = $payload['message'] ?? [];
                $body['id'] = $message['id'] ?? null;
                $body['model'] = $message['model'] ?? null;
                $body['usage'] = $message['usage'] ?? [];
                break;

            case 'content_block_start':
                $body['content'][$index] = $payload['content_block'] ?? [];
                $partialJson[$index] = '';
                break;

            case 'content_block_delta':
                $delta = $payload['delta'] ?? [];

                if (($delta['type'] ?? null) === 'text_delta') {
                    $body['content'][$index]['text'] = ($body['content'][$index]['text'] ?? '').($delta['text'] ?? '');
                } elseif (($delta['type'] ?? null) === 'input_json_delta') {
                    $partialJson[$index] = ($partialJson[$index] ?? '').($delta['partial_json'] ?? '');
                }
                break;

            case 'content_block_stop':
                if (($body['content'][$index]['type'] ?? null) === 'tool_use' && ($partialJson[$index] ?? '') !== '') {
                    $input = json_decode($partialJson[$index], true);
                    // Оборванный JSON оставляем строкой — findToolInput
                    // его не примет, и сработает обычная вторая попытка
                    $body['content'][$index]['input'] = is_array($input) ? $input : $partialJson[$index];
                }
                break;

            case 'message_delta':
                $body['stop_reason'] = $payload['delta']['stop_reason'] ?? ($body['stop_reason'] ?? null);
                $body['usage'] = array_merge($body['usage'], $payload['usage'] ?? []);
                break;

            case 'error':
                throw new ClaudeException(
                    'Шлюз прервал ответ: '.($payload['error']['message'] ?? 'неизвестная ошибка'),
                    code: 529,
                );
        }
    }
}
