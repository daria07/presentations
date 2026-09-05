<?php

namespace App\Services\Claude;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Тонкая обёртка над Messages API.
 *
 * Работает только в режиме структурированного ответа: модель обязана
 * вернуть JSON по заданной схеме через механизм инструментов. Так не
 * приходится парсить свободный текст и ловить лишние markdown-обёртки.
 */
class ClaudeClient
{
    private const VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $priceInput,
        private readonly int $priceOutput,
    ) {}

    public static function make(): self
    {
        $config = config('services.anthropic');

        if (blank($config['key'])) {
            throw new ClaudeException('В .env не задан ANTHROPIC_API_KEY.');
        }

        return new self(
            apiKey: $config['key'],
            baseUrl: $config['base_url'],
            model: $config['model'],
            priceInput: (int) $config['price_input'],
            priceOutput: (int) $config['price_output'],
        );
    }

    /**
     * Просит модель заполнить структуру по JSON-схеме.
     *
     * @param  array  $schema  JSON Schema ожидаемого объекта
     */
    public function structured(
        string $system,
        string $prompt,
        array $schema,
        string $toolName,
        string $toolDescription,
        int $maxTokens = 8000,
    ): ClaudeResult {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $tools = [[
            'name' => $toolName,
            'description' => $toolDescription,
            'input_schema' => $schema,
        ]];

        $startedAt = microtime(true);

        $body = $this->send($system, $messages, $tools, $toolName, $maxTokens);
        $data = $this->findToolInput($body, $toolName);

        $inputTokens = (int) ($body['usage']['input_tokens'] ?? 0);
        $outputTokens = (int) ($body['usage']['output_tokens'] ?? 0);
        $cachedTokens = (int) ($body['usage']['cache_read_input_tokens'] ?? 0);

        // Модель иногда отвечает обычным текстом вместо вызова инструмента:
        // рассуждает вслух или отказывается («точных данных у меня нет»).
        // Это поправимо — показываем ей её же ответ и просим ещё раз.
        if ($data === null) {
            $said = $this->extractText($body);

            Log::warning('Claude: вместо инструмента пришёл текст, пробуем ещё раз', [
                'tool' => $toolName,
                'said' => mb_substr($said, 0, 500),
            ]);

            $messages[] = ['role' => 'assistant', 'content' => $said !== '' ? $said : '(пустой ответ)'];
            $messages[] = ['role' => 'user', 'content' => $this->nudge($toolName)];

            $body = $this->send($system, $messages, $tools, $toolName, $maxTokens);
            $data = $this->findToolInput($body, $toolName);

            // Считаем обе попытки: вторая тоже оплачена
            $inputTokens += (int) ($body['usage']['input_tokens'] ?? 0);
            $outputTokens += (int) ($body['usage']['output_tokens'] ?? 0);
            $cachedTokens += (int) ($body['usage']['cache_read_input_tokens'] ?? 0);

            if ($data === null) {
                Log::error('Claude: инструмент не вызван и со второй попытки', ['body' => $body]);

                throw ClaudeException::content(
                    'Модель не смогла собрать структуру по этой теме. '
                    .'Попробуйте сформулировать её конкретнее или добавить исходный текст.'
                );
            }
        }

        $seconds = round(microtime(true) - $startedAt, 1);

        Log::info('Claude: ответ получен', [
            'tool' => $toolName,
            'seconds' => $seconds,
            'input' => $inputTokens,
            'output' => $outputTokens,
            // Главный показатель скорости: сколько токенов в секунду
            // выдаёт модель. Вход на время почти не влияет.
            'tokens_per_second' => $seconds > 0 ? round($outputTokens / $seconds) : null,
        ]);

        return new ClaudeResult(
            data: $data,
            raw: $body,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cachedTokens: $cachedTokens,
            cost: $this->cost($inputTokens, $outputTokens),
            model: $this->model,
        );
    }

    /**
     * Одна отправка запроса. Возвращает разобранное тело ответа.
     *
     * @param  array<int, array>  $messages
     * @param  array<int, array>  $tools
     */
    private function send(
        string $system,
        array $messages,
        array $tools,
        string $toolName,
        int $maxTokens,
    ): array {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
            'tools' => $tools,
            // Заставляем модель ответить именно этим инструментом
            'tool_choice' => ['type' => 'tool', 'name' => $toolName],
        ];

        try {
            $response = $this->request()->post($this->baseUrl.'/v1/messages', $payload);
        } catch (Throwable $e) {
            Log::error('Claude: запрос не ушёл', ['error' => $e->getMessage()]);

            throw new ClaudeException('Сеть недоступна: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error('Claude: ошибка API', [
                'status' => $response->status(),
                'error' => $error,
            ]);

            throw new ClaudeException(
                "API вернул {$response->status()}: {$error}",
                code: $response->status(),
            );
        }

        $body = $response->json();

        // Если модель упёрлась в лимит, JSON инструмента приходит
        // оборванным на середине — молча пропускать это нельзя.
        if (($body['stop_reason'] ?? null) === 'max_tokens') {
            Log::error('Claude: ответ обрезан по max_tokens', [
                'max_tokens' => $maxTokens,
                'usage' => $body['usage'] ?? null,
            ]);

            throw new ClaudeException(
                "Ответ не поместился в {$maxTokens} токенов. Уменьши количество слайдов или подними max_tokens."
            );
        }

        return $body;
    }

    /**
     * Что сказать модели, когда она ответила текстом вместо инструмента.
     */
    private function nudge(string $toolName): string
    {
        return <<<TXT
        Ответ не принят: нужен вызов инструмента {$toolName}, а не текст.

        Отказываться нельзя. Если точных данных по теме нет — это нормально
        и не мешает работе: строй слайды на структуре и формулировках без
        конкретных чисел, дат и цитат. Пиши «выручка выросла», а не
        «выручка выросла на 34%». Пустые места оставляй пользователю.

        Вызови инструмент {$toolName} прямо сейчас, ничего не поясняя.
        TXT;
    }

    /**
     * Достаёт из ответа блок tool_use с нужным именем.
     * Возвращает null, если модель ответила чем-то другим.
     */
    private function findToolInput(array $body, string $toolName): ?array
    {
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $toolName) {
                return Json::unwrap($block['input'] ?? []);
            }
        }

        return null;
    }

    /**
     * Собирает текстовые блоки ответа — то, что модель сказала вместо дела.
     */
    private function extractText(array $body): string
    {
        $parts = [];

        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text' && filled($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * Себестоимость вызова в сотых доли цента.
     * Цены в конфиге заданы за миллион токенов.
     */
    private function cost(int $input, int $output): int
    {
        return (int) round(
            $input / 1_000_000 * $this->priceInput
            + $output / 1_000_000 * $this->priceOutput
        );
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::VERSION,
            'content-type' => 'application/json',
        ])
            ->timeout(240)
            ->connectTimeout(20)
            // Шлюз регулярно рвёт соединение на середине. Обрыв — вещь
            // случайная, поэтому попыток три и пауза растёт: 1с, 3с.
            ->retry([1000, 3000], when: $this->shouldRetry(...), throw: false);
    }

    /**
     * Повторять есть смысл только то, что может пройти со второй
     * попытки: обрыв связи, лимит провайдера, сбой на их стороне.
     * Кривой запрос и пустой баланс от повтора не починятся, а вот
     * денег и времени сожгут вдвое больше.
     */
    private function shouldRetry(Throwable $e): bool
    {
        // Обрыв соединения, недоступность, таймаут — всё это
        // с хорошими шансами пройдёт со второй попытки
        if ($e instanceof ConnectionException) {
            Log::warning('Claude: соединение оборвалось, пробуем снова', [
                'error' => $e->getMessage(),
            ]);

            return true;
        }

        if (! $e instanceof RequestException) {
            return false;
        }

        $status = $e->response->status();

        return $status === 429 || $status >= 500;
    }
}
