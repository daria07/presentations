<?php

namespace App\Services\Images;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Рисует картинки через OpenAI-совместимый шлюз (/v1/images/generations)
 * или через асинхронный API задач (IMAGE_API=async, см. attemptAsync).
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
     * Ключи, чьи задачи async-шлюз принял, но не дорисовал к концу
     * отведённого времени. Платится только успех, и такая задача ещё
     * может закончиться и списать деньги, поэтому на запасной шлюз её
     * не отправляем: иначе одна картинка оплачивается дважды.
     *
     * @var array<int, string>
     */
    public array $unfinished = [];

    /**
     * prompt — полный запрос, compact — короткий, для моделей, которые
     * рисуют текст запроса буквами (grok). Какой отправить, решает
     * настройка шлюза: у основного и запасного она своя.
     *
     * @param  array<string, array{prompt: string, size: string, compact?: string}>  $requests
     * @return array<string, array{bytes: string, input: int, output: int, cost?: int}|null>
     *                                                                                       null — картинку получить не удалось;
     *                                                                                       cost — цена, если шлюз назвал её сам
     */
    public function generateMany(array $requests): array
    {
        $this->refused = [];
        $this->unfinished = [];
        $started = microtime(true);
        $fallback = self::fallbackConfig();
        $primary = config('services.images');

        /*
           С запасным шлюзом основному оставляем меньше времени: задача
           рисования живёт 280 секунд, и запасному нужен свой заход.
           Async-шлюзу (sozdai) — больше: картинка у него рисуется
           до двух минут, а запасной нужен только на случай, когда он
           лежит, и тогда это выясняется за первые секунды
        */
        $reserve = ($primary['api'] ?? 'openai') === 'async'
            ? self::ASYNC_FALLBACK_RESERVE
            : self::FALLBACK_RESERVE;

        $results = $this->attempt(
            $requests,
            $primary,
            $started + ($fallback ? self::BUDGET - $reserve : self::BUDGET),
        );

        // Не вышли — пробуем запасной шлюз. Кроме отказов по содержанию
        // (их Illustrator переспросит упрощённой сценой) и задач, которые
        // основной ещё рисует: за них он может успеть взять деньги
        $missing = array_filter(
            $requests,
            fn (string $key) => $results[$key] === null
                && ! in_array($key, $this->refused, true)
                && ! in_array($key, $this->unfinished, true),
            ARRAY_FILTER_USE_KEY,
        );

        if ($fallback !== null && $missing !== []) {
            Log::info('Картинка: пробуем запасной шлюз', ['keys' => array_keys($missing), 'model' => $fallback['model']]);

            foreach ($this->attempt($missing, $fallback, $started + self::BUDGET) as $key => $result) {
                $results[$key] = $result;
            }
        }

        return $results;
    }

    /**
     * Нужен ли шлюзу короткий запрос: явная настройка compact_prompt
     * или модель grok, которая рисует служебные фразы буквами.
     *
     * @param  array<string, mixed>  $config
     */
    private static function wantsCompact(array $config): bool
    {
        $flag = $config['compact_prompt'] ?? null;

        if ($flag !== null && $flag !== '') {
            return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
        }

        return str_starts_with((string) ($config['model'] ?? ''), 'grok');
    }

    /** Сколько всего можно рисовать в рамках одной задачи, секунд */
    private const BUDGET = 230;

    /** Сколько из них оставить запасному шлюзу */
    private const FALLBACK_RESERVE = 110;

    /** То же при async-шлюзе: ему 190 секунд, запасному 40 */
    private const ASYNC_FALLBACK_RESERVE = 40;

    /**
     * Запасной шлюз картинок (IMAGE_FALLBACK_*), если задан.
     * Незаданные поля берутся у основного.
     *
     * @return array<string, mixed>|null
     */
    private static function fallbackConfig(): ?array
    {
        $fallback = config('services.images_fallback');

        if (! is_array($fallback) || blank($fallback['model'] ?? null)) {
            return null;
        }

        $merged = [...config('services.images'), ...array_filter($fallback, fn ($v) => $v !== null && $v !== '')];

        // Свой адрес без явного API — это обычный OpenAI-совместимый
        // шлюз, а не тот же sozdai, что у основного
        if (blank($fallback['api'] ?? null) && filled($fallback['base_url'] ?? null)) {
            $merged['api'] = 'openai';
        }

        return $merged;
    }

    /**
     * Заходы к одному шлюзу: обрывы и временные ошибки повторяем,
     * пока есть время и заходы.
     *
     * @param  array<string, array{prompt: string, size: string, compact?: string}>  $requests
     * @param  array<string, mixed>  $config
     * @return array<string, array{bytes: string, input: int, output: int, cost?: int}|null>
     *
     * @phpstan-impure заполняет refused и unfinished
     */
    private function attempt(array $requests, array $config, float $deadline): array
    {
        if (($config['api'] ?? 'openai') === 'async') {
            return $this->attemptAsync($requests, $config, $deadline);
        }

        $results = array_fill_keys(array_keys($requests), null);
        $pending = $requests;
        $streams = filter_var($config['stream'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $compact = self::wantsCompact($config);

        for ($round = 1; $round <= self::ROUNDS && $pending !== []; $round++) {
            // Ждать дольше, чем осталось до конца задачи, бессмысленно
            $timeout = (int) min(150, $deadline - microtime(true));

            if ($timeout < 30) {
                break;
            }

            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $key) => $pool->as($key)
                    ->withToken((string) $config['key'])
                    ->timeout($timeout)
                    ->connectTimeout(20)
                    ->post($config['base_url'].'/v1/images/generations', [
                        'model' => $config['model'],
                        'prompt' => $compact && isset($pending[$key]['compact'])
                            ? $pending[$key]['compact']
                            : $pending[$key]['prompt'],
                        'size' => $pending[$key]['size'],
                        'quality' => $config['quality'],
                        'n' => 1,
                        // Поток держит соединение живым на долгой картинке,
                        // но не все шлюзы его умеют: codex-api отвечает
                        // «Streaming is not available». Там IMAGE_STREAM=false
                        ...($streams ? ['stream' => true, 'partial_images' => 3] : []),
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
                    } elseif ($response->serverError()) {
                        // 502/503 у шлюза — «временно недоступно», а не отказ
                        // по существу: следующий заход часто проходит
                        $retry[$key] = $request;
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
                $bytes = is_string($b64)
                    ? base64_decode($b64, true)
                    : ($result['url'] !== null ? self::download($result['url']) : false);

                if ($bytes === false || $bytes === '') {
                    $body = $response->body();

                    // Длина, число строк data и хвост — чтобы по логу было
                    // видно, оборван поток или пришёл в неожиданном виде
                    Log::warning('Картинка: в ответе нет изображения', [
                        'key' => $key,
                        'round' => $round,
                        'length' => strlen($body),
                        'data_lines' => substr_count($body, "\ndata:") + (str_starts_with($body, 'data:') ? 1 : 0),
                        'body' => mb_substr($body, 0, 300),
                        'tail' => mb_substr($body, -300),
                    ]);

                    // Пустой или оборванный ответ с кодом 200 — тот же
                    // обрыв, только шлюз закрыл поток вежливо. Повторяем
                    $retry[$key] = $request;

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

    /** Как часто спрашивать о готовности задачи, секунд */
    private const POLL_EVERY = 3;

    /**
     * Асинхронный API (sozdai.ai): POST /v1/media/generations ставит
     * задачу и сразу отвечает id, GET /v1/media/generations/{id}
     * сообщает статус: queued, submitted, running, затем succeeded,
     * failed или timeout. Готовая картинка — ссылкой в result.urls.
     *
     * Все задачи ставим разом и опрашиваем пачкой, пока не кончится
     * время. Платится только успех, поэтому упавшую задачу ставим
     * заново — до ROUNDS раз. Отказ по контент-политике не повторяем:
     * его Illustrator переспросит упрощённой сценой.
     *
     * @param  array<string, array{prompt: string, size: string, compact?: string}>  $requests
     * @param  array<string, mixed>  $config
     * @return array<string, array{bytes: string, input: int, output: int, cost?: int}|null>
     */
    private function attemptAsync(array $requests, array $config, float $deadline): array
    {
        $results = array_fill_keys(array_keys($requests), null);
        $endpoint = $config['base_url'].'/v1/media/generations';
        $compact = self::wantsCompact($config);

        /** @var array<string, array{prompt: string, size: string, compact?: string}> $queue ещё не поставлены */
        $queue = $requests;
        /** @var array<string, string> $tasks ключ => id поставленной задачи */
        $tasks = [];
        /** @var array<string, int> $submits сколько раз ставили задачу */
        $submits = array_fill_keys(array_keys($requests), 0);

        while (($queue !== [] || $tasks !== []) && microtime(true) < $deadline) {
            if ($queue !== []) {
                $responses = Http::pool(fn (Pool $pool) => array_map(
                    fn (string $key) => $pool->as($key)
                        ->withToken((string) $config['key'])
                        ->timeout(30)
                        ->connectTimeout(15)
                        ->post($endpoint, [
                            'model' => $config['model'],
                            'prompt' => $compact && isset($queue[$key]['compact'])
                                ? $queue[$key]['compact']
                                : $queue[$key]['prompt'],
                            'aspect_ratio' => self::aspectRatio($queue[$key]['size']),
                            'resolution' => (string) ($config['resolution'] ?? '1K'),
                        ]),
                    array_map('strval', array_keys($queue)),
                ));

                $submitted = $queue;
                $queue = [];

                foreach ($submitted as $key => $request) {
                    $key = (string) $key;
                    $submits[$key]++;
                    $response = $responses[$key] ?? null;
                    $id = $response instanceof Response && $response->successful() ? $response->json('id') : null;

                    if (is_string($id) && $id !== '') {
                        $tasks[$key] = $id;

                        continue;
                    }

                    Log::warning('Картинка: задача не поставилась', [
                        'key' => $key,
                        'attempt' => $submits[$key],
                        'status' => $response instanceof Response ? $response->status() : null,
                        'body' => $response instanceof Response ? mb_substr($response->body(), 0, 500) : null,
                        'error' => $response instanceof Throwable ? $response->getMessage() : null,
                        'prompt' => mb_substr($request['prompt'], 0, 300),
                    ]);

                    if ($response instanceof Response && self::isPolicyRefusal($response)) {
                        $this->refused[] = $key;
                    } elseif (! $response instanceof Response || $response->serverError() || $response->status() === 429) {
                        // Обрыв, 5xx и «слишком часто» — временное
                        // Поставить ещё раз, если заходы не кончились
                        if ($submits[$key] < self::ROUNDS) {
                            $queue[$key] = $requests[$key];
                        }
                    }
                }
            }

            if ($tasks === []) {
                // Нечего опрашивать: либо всё кончилось, либо ждём
                // повторной постановки после временной ошибки
                if ($queue !== []) {
                    Sleep::for(self::POLL_EVERY)->seconds();
                }

                continue;
            }

            Sleep::for(self::POLL_EVERY)->seconds();

            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $key) => $pool->as($key)
                    ->withToken((string) $config['key'])
                    ->timeout(20)
                    ->connectTimeout(10)
                    ->get($endpoint.'/'.$tasks[$key]),
                array_map('strval', array_keys($tasks)),
            ));

            foreach ($tasks as $key => $id) {
                $key = (string) $key;
                $response = $responses[$key] ?? null;

                // Обрыв или 5xx при опросе — задача от этого не страдает,
                // спросим на следующем круге
                if (! $response instanceof Response || $response->serverError()) {
                    continue;
                }

                $status = $response->successful() ? (string) $response->json('status') : 'failed';

                if (! in_array($status, ['succeeded', 'failed', 'timeout'], true)) {
                    continue; // queued, submitted, running
                }

                unset($tasks[$key]);

                if ($status === 'succeeded') {
                    $url = $response->json('result.urls.0');
                    $bytes = is_string($url) ? self::download($url) : false;

                    if ($bytes === false || $bytes === '') {
                        Log::warning('Картинка: задача готова, но картинки нет', [
                            'key' => $key,
                            'id' => $id,
                            'body' => mb_substr($response->body(), 0, 500),
                        ]);
                        // Поставить ещё раз, если заходы не кончились
                        if ($submits[$key] < self::ROUNDS) {
                            $queue[$key] = $requests[$key];
                        }

                        continue;
                    }

                    $results[$key] = [
                        'bytes' => $bytes,
                        'input' => 0,
                        'output' => 0,
                        'cost' => self::usdToUnits($response->json('cost')),
                    ];

                    continue;
                }

                // failed / timeout, или задачу не нашли (404)
                $error = $response->json('error');
                $error = is_array($error) ? (string) json_encode($error, JSON_UNESCAPED_UNICODE) : (string) $error;

                Log::warning('Картинка: задача не удалась', [
                    'key' => $key,
                    'id' => $id,
                    'status' => $status,
                    'http' => $response->status(),
                    'error' => mb_substr($error, 0, 500),
                    'prompt' => mb_substr($requests[$key]['prompt'], 0, 300),
                ]);

                if (self::isPolicyText($error)) {
                    $this->refused[] = $key;
                } else {
                    // Поставить ещё раз, если заходы не кончились
                    if ($submits[$key] < self::ROUNDS) {
                        $queue[$key] = $requests[$key];
                    }
                }
            }
        }

        // Принятые, но не дорисованные — ещё могут списать деньги
        $this->unfinished = array_map('strval', array_keys($tasks));

        if ($tasks !== [] || $queue !== []) {
            Log::warning('Картинка: не дождались задач до конца отведённого времени', [
                'keys' => array_keys($tasks + $queue),
                'tasks' => array_values($tasks),
            ]);
        }

        return $results;
    }

    /**
     * Размер в пикселях (как его задаёт Illustrator) — в пропорцию
     * для async API: 1536x1024 → 3:2, 1024x1024 → 1:1.
     */
    public static function aspectRatio(string $size): string
    {
        if (preg_match('/^(\d+)x(\d+)$/', $size, $m) !== 1 || (int) $m[1] === 0 || (int) $m[2] === 0) {
            return 'auto';
        }

        [$w, $h] = [(int) $m[1], (int) $m[2]];
        $gcd = function (int $a, int $b) use (&$gcd): int {
            return $b === 0 ? $a : $gcd($b, $a % $b);
        };
        $d = $gcd($w, $h);

        return ($w / $d).':'.($h / $d);
    }

    /**
     * Цена задачи из ответа шлюза («0.0500», доллары) — в сотых долях
     * цента, как у остальных записей расхода.
     */
    private static function usdToUnits(mixed $cost): int
    {
        return is_numeric($cost) ? (int) round((float) $cost * 10_000) : 0;
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
     * @return array{b64: string|null, url: string|null, usage: array<string, mixed>, error: string|null}
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
                'url' => is_string($response->json('data.0.url')) ? $response->json('data.0.url') : null,
                'usage' => is_array($usage) ? $usage : [],
                'error' => null,
            ];
        }

        $result = ['b64' => null, 'url' => null, 'usage' => [], 'error' => null];

        foreach (self::events($body) as $event) {
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

            // Иногда шлюз отдаёт не саму картинку, а ссылку на неё
            $url = $event['data'][0]['url'] ?? $event['url'] ?? null;

            if (is_string($url) && ! $partial) {
                $result['url'] = $url;
            }

            if (is_array($event['usage'] ?? null)) {
                $result['usage'] = $event['usage'];
            }
        }

        // Ошибка в начале потока, а изображение всё же пришло — это не отказ
        if ($result['b64'] !== null || $result['url'] !== null) {
            $result['error'] = null;
        }

        return $result;
    }

    /**
     * События потока (SSE) как массивы.
     *
     * По стандарту одно событие может занимать несколько строк «data:»
     * подряд, до пустой строки. Шлюз так и делает с готовой картинкой:
     * мегабайты base64 режет на строки, и каждая строка по отдельности —
     * не JSON. Раньше строки разбирались поодиночке, и готовая картинка
     * терялась: в логе «в ответе нет изображения», а слайд без картинки.
     *
     * Поэтому строки события склеиваем. Если склеенное не разбирается
     * (несколько JSON подряд без пустой строки между ними), разбираем
     * каждую строку отдельно, как раньше.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function events(string $body): array
    {
        $groups = [];
        $lines = [];

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            // Пустая строка или начало нового события закрывают текущее
            if ($line === '' || str_starts_with($line, 'event:')) {
                if ($lines !== []) {
                    $groups[] = $lines;
                    $lines = [];
                }

                continue;
            }

            // Остальное — комментарии («: PING») и служебные поля
            if (str_starts_with($line, 'data:')) {
                $data = substr($line, 5);
                $lines[] = str_starts_with($data, ' ') ? substr($data, 1) : $data;
            }
        }

        if ($lines !== []) {
            $groups[] = $lines;
        }

        $events = [];

        foreach ($groups as $group) {
            $whole = json_decode(trim(implode('', $group)), true);

            if (is_array($whole)) {
                $events[] = $whole;

                continue;
            }

            foreach ($group as $line) {
                $event = json_decode(trim($line), true);

                if (is_array($event)) {
                    $events[] = $event; // [DONE] и обрывки пропускаем
                }
            }
        }

        return $events;
    }

    /**
     * Картинка по ссылке, которую прислал шлюз вместо самих байтов.
     * Ссылка временная, качаем сразу. Берём только https и только
     * то, что похоже на картинку, — и не больше 15 МБ.
     */
    private static function download(string $url): string|false
    {
        if (! str_starts_with($url, 'https://')) {
            return false;
        }

        try {
            $response = Http::timeout(60)->connectTimeout(15)->retry(2, 1000, throw: false)->get($url);
        } catch (Throwable $e) {
            Log::warning('Картинка: не скачали по ссылке', ['url' => $url, 'error' => $e->getMessage()]);

            return false;
        }

        $bytes = $response->successful() ? $response->body() : '';
        $isImage = str_starts_with($bytes, "\x89PNG") || str_starts_with($bytes, "\xFF\xD8") || str_starts_with($bytes, 'RIFF');

        if (! $isImage || strlen($bytes) > 15_000_000) {
            Log::warning('Картинка: по ссылке не картинка', ['url' => $url, 'status' => $response->status()]);

            return false;
        }

        return $bytes;
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
