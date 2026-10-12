<?php

namespace App\Services\Images;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Рисует картинки через OpenAI-совместимый шлюз (/v1/images/generations).
 *
 * Все картинки презентации запрашиваются одновременно: одна рисуется
 * около 30 секунд, и по очереди четыре заняли бы две минуты.
 *
 * Шлюз время от времени рвёт соединение посреди генерации. Такие
 * запросы повторяем — до трёх заходов. А вот ответ с ошибкой (отказ
 * модели по теме, неверный запрос) от повтора не изменится: его
 * записываем в лог и больше не трогаем.
 */
class ImageClient
{
    private const ROUNDS = 3;

    /**
     * Ключи, по которым модель отказала по контент-политике в последнем
     * вызове generateMany. Такие отказы бывают и ложными (на безобидное
     * растение в интерьере), поэтому Illustrator пробует их ещё раз
     * с упрощённой сценой.
     *
     * @var array<int, string>
     */
    public array $refused = [];

    /**
     * @param  array<string, array{prompt: string, size: string}>  $requests
     * @return array<string, array{bytes: string, input: int, output: int}|null>
     *                                                                           null — картинку получить не удалось
     */
    public function generateMany(array $requests): array
    {
        $config = config('services.images');
        $results = array_fill_keys(array_keys($requests), null);
        $pending = $requests;
        $this->refused = [];

        for ($round = 1; $round <= self::ROUNDS && $pending !== []; $round++) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $key) => $pool->as($key)
                    ->withToken((string) $config['key'])
                    ->timeout(150)
                    ->connectTimeout(20)
                    ->post($config['base_url'].'/v1/images/generations', [
                        'model' => $config['model'],
                        'prompt' => $pending[$key]['prompt'],
                        'size' => $pending[$key]['size'],
                        'quality' => $config['quality'],
                        'n' => 1,
                    ]),
                array_keys($pending),
            ));

            $retry = [];

            foreach ($pending as $key => $request) {
                $response = $responses[$key] ?? null;

                if (! $response instanceof Response) {
                    // Обрыв соединения или таймаут — пробуем ещё раз
                    Log::warning('Картинка: соединение оборвалось', [
                        'key' => $key,
                        'round' => $round,
                        'error' => $response instanceof Throwable ? $response->getMessage() : null,
                    ]);
                    $retry[$key] = $request;

                    continue;
                }

                if ($response->failed()) {
                    Log::warning('Картинка: шлюз отказал', [
                        'key' => $key,
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 500),
                        'prompt' => mb_substr($request['prompt'], 0, 300),
                    ]);

                    if (self::isPolicyRefusal($response)) {
                        $this->refused[] = (string) $key;
                    }

                    continue;
                }

                $b64 = $response->json('data.0.b64_json');
                $bytes = is_string($b64) ? base64_decode($b64, true) : false;

                if ($bytes === false || $bytes === '') {
                    Log::warning('Картинка: в ответе нет изображения', [
                        'key' => $key,
                        'body' => mb_substr($response->body(), 0, 300),
                    ]);

                    continue;
                }

                $results[$key] = [
                    'bytes' => $bytes,
                    'input' => (int) ($response->json('usage.input_tokens') ?? 0),
                    'output' => (int) ($response->json('usage.output_tokens') ?? 0),
                ];
            }

            $pending = $retry;
        }

        return $results;
    }

    /**
     * Отказ по контент-политике. Шлюз пишет его по-разному (бывает и
     * по-китайски: «内容政策»), поэтому смотрим на несколько признаков.
     */
    private static function isPolicyRefusal(Response $response): bool
    {
        // Текст ошибки — и из JSON (там иероглифы уже раскодированы),
        // и сырым телом на случай другого формата
        $message = $response->json('error.message');
        $body = mb_strtolower((is_string($message) ? $message : '').' '.$response->body());

        return $response->status() === 400 && (
            str_contains($body, 'policy')
            || str_contains($body, 'safety')
            || str_contains($body, 'moderation')
            || str_contains($body, '政策')
        );
    }
}
