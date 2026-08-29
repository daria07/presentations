<?php

namespace App\Services\Claude;

use Illuminate\Http\Client\PendingRequest;
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
        $payload = [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'tools' => [[
                'name' => $toolName,
                'description' => $toolDescription,
                'input_schema' => $schema,
            ]],
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

            throw new ClaudeException("API вернул {$response->status()}: {$error}");
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

        $data = $this->extractToolInput($body, $toolName);
        $usage = $body['usage'] ?? [];

        $inputTokens = (int) ($usage['input_tokens'] ?? 0);
        $outputTokens = (int) ($usage['output_tokens'] ?? 0);
        $cachedTokens = (int) ($usage['cache_read_input_tokens'] ?? 0);

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
     * Достаёт из ответа блок tool_use с нужным именем.
     */
    private function extractToolInput(array $body, string $toolName): array
    {
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $toolName) {
                return Json::unwrap($block['input'] ?? []);
            }
        }

        Log::error('Claude: в ответе нет tool_use', ['body' => $body]);

        throw new ClaudeException('Модель вернула ответ в неожиданном формате.');
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
            ->timeout(180)
            ->connectTimeout(15)
            ->retry(2, 2000, throw: false);
    }
}
