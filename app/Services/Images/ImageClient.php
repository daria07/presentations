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
 * Картинку просим потоком (stream): шлюз рвёт соединение, если
 * 60 секунд от модели не пришло ни байта, а горизонтальная обложка
 * рисуется как раз около минуты. В потоке шлюз шлёт отметки о ходе
 * работы каждые несколько секунд, и соединение не простаивает. Само
 * изображение приходит последним событием, целиком.
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
                        'stream' => true,
                        'partial_images' => 3,
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

                $result = self::result($response);

                // Ошибка может прийти и внутри потока, уже после кода 200
                if ($result['error'] !== null) {
                    Log::warning('Картинка: шлюз отказал', [
                        'key' => $key,
                        'status' => $response->status(),
                        'body' => mb_substr($result['error'], 0, 500),
                        'prompt' => mb_substr($request['prompt'], 0, 300),
                    ]);

                    if (self::isPolicyText($result['error'])) {
                        $this->refused[] = (string) $key;
                    }

                    continue;
                }

                $b64 = $result['b64'];
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
                    'input' => (int) ($result['usage']['input_tokens'] ?? 0),
                    'output' => (int) ($result['usage']['output_tokens'] ?? 0),
                ];
            }

            $pending = $retry;
        }

        return $results;
    }

    /**
     * Готовое изображение из ответа — потокового или обычного.
     *
     * Поток — строки «data: {...}»: сначала отметки о ходе работы, в конце
     * результат. Шлюз отдаёт его в двух видах: свой (image.generation.result,
     * картинка в data[0].b64_json) и как у OpenAI (image_generation.completed,
     * картинка в b64_json). Наброски (partial_image) пропускаем.
     * Если шлюз поток не включил, ответ — обычный JSON того же вида.
     *
     * @return array{b64: string|null, usage: array<string, mixed>, error: string|null}
     */
    public static function result(Response $response): array
    {
        $body = $response->body();

        // Поток начинается с «data:» или с «event:» — шлюз шлёт оба
        // формата: свой (image.generation.*) и OpenAI (image_generation.*)
        $streamed = preg_match('/^(data|event):/m', substr($body, 0, 2000)) === 1;

        if (! $streamed) {
            $usage = $response->json('usage');

            return [
                'b64' => is_string($response->json('data.0.b64_json')) ? $response->json('data.0.b64_json') : null,
                'usage' => is_array($usage) ? $usage : [],
                'error' => null,
            ];
        }

        $result = ['b64' => null, 'usage' => [], 'error' => null];

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $event = json_decode(trim(substr($line, 5)), true);

            if (! is_array($event)) {
                continue; // [DONE] и пустые строки
            }

            if (isset($event['error'])) {
                $error = $event['error'];
                $result['error'] = is_array($error) ? (string) json_encode($error, JSON_UNESCAPED_UNICODE) : (string) $error;
            }

            // Берём последнее изображение: промежуточные наброски, если
            // шлюз их пришлёт, идут раньше готового
            $b64 = $event['data'][0]['b64_json'] ?? $event['b64_json'] ?? null;

            $kind = (string) ($event['object'] ?? $event['type'] ?? '');
            $partial = in_array($kind, ['image.generation.chunk', 'image_generation.partial_image'], true);

            if (is_string($b64) && ! $partial) {
                $result['b64'] = $b64;
            }

            if (is_array($event['usage'] ?? null)) {
                $result['usage'] = $event['usage'];
            }
        }

        // Ошибка в начале потока, а изображение всё же пришло — это не отказ
        if ($result['b64'] !== null) {
            $result['error'] = null;
        }

        return $result;
    }

    private static function isPolicyText(string $text): bool
    {
        $text = mb_strtolower($text);

        return str_contains($text, 'policy')
            || str_contains($text, 'safety')
            || str_contains($text, 'moderation')
            || str_contains($text, '政策');
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
