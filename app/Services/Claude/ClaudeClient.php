<?php

namespace App\Services\Claude;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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
            throw new ClaudeException('В .env не задан LLM_API_KEY.');
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

        /*
           cheapai в потоке сообщает вход как попало: то 9 388 из кэша,
           то 8 токенов вместо двадцати тысяч. Платим мы при этом за
           весь вход. Если шлюз назвал явно меньше, чем мы отправили,
           считаем сами: в русском тексте токен — примерно три знака.
        */
        $estimate = self::estimateTokens($system, $messages, $tools);

        if ($inputTokens + $cachedTokens < $estimate / 2) {
            $inputTokens = max(0, $estimate - $cachedTokens);
        }

        Log::info('Claude: ответ получен', [
            'tool' => $toolName,
            // Чем представилась модель на самом деле: у шлюза имя в
            // каталоге и то, что реально отвечает, совпадают не всегда.
            // stop_reason рядом: при нашем tool_choice он обязан быть
            // tool_use, и end_turn выдаёт переводную прослойку
            'ответила' => $body['model'] ?? null,
            // Куда ушёл запрос: по этой строке видно, если процесс взял
            // адрес шлюза не из .env, а из своего окружения
            'шлюз' => parse_url($this->baseUrl, PHP_URL_HOST),
            'stop_reason' => $body['stop_reason'] ?? null,
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
            cost: $this->cost($inputTokens + $cachedTokens, $outputTokens),
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
        bool $forceTool = true,
    ): array {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
            'tools' => $tools,
            /*
               Заставляем модель ответить именно этим инструментом.

               Не каждый шлюз это умеет: часть из них отвечает 400
               «does not support forced tool_choice». Тогда просим
               'auto' — модель сама решает, вызывать ли инструмент.
               Она почти всегда вызывает, а на случай «почти» у нас
               уже есть вторая попытка с напоминанием.
            */
            'tool_choice' => $forceTool
                ? ['type' => 'tool', 'name' => $toolName]
                : ['type' => 'auto'],
            // Поток: иначе шлюз рвёт соединение через минуту тишины,
            // а структура пишется две-три (подробности — StreamReader)
            'stream' => true,
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

            // Шлюз не умеет принудительный выбор инструмента — повторяем
            // мягко, вместо того чтобы падать на ровном месте
            if ($forceTool && $response->status() === 400 && str_contains($error, 'tool_choice')) {
                Log::warning('Claude: шлюз не принимает принудительный tool_choice, пробуем auto', [
                    'model' => $this->model,
                ]);

                return $this->send($system, $messages, $tools, $toolName, $maxTokens, forceTool: false);
            }

            throw new ClaudeException(
                "API вернул {$response->status()}: {$error}",
                code: $response->status(),
            );
        }

        $body = $this->readBody($response);

        // Если модель упёрлась в лимит, JSON инструмента приходит
        // оборванным на середине — молча пропускать это нельзя.
        if (($body['stop_reason'] ?? null) === 'max_tokens') {
            Log::error('Claude: ответ обрезан по max_tokens', [
                'max_tokens' => $maxTokens,
                'usage' => $body['usage'] ?? null,
            ]);

            /*
               content: повторять бессмысленно — ответ не помещается
               в лимит. Человеку про токены знать незачем, подробности
               ушли в журнал строкой выше.
            */
            throw ClaudeException::content(
                'Ответ получился слишком длинным и не поместился целиком. '
                .'Попробуйте уменьшить количество слайдов.'
            );
        }

        return $body;
    }

    /**
     * Тело ответа: собираем поток или, если шлюз поток не поддержал
     * и ответил обычным JSON, берём JSON как есть.
     *
     * @return array<string, mixed>
     */
    private function readBody(Response $response): array
    {
        $type = (string) $response->header('Content-Type');

        if (! str_contains($type, 'text/event-stream')) {
            return (array) $response->json();
        }

        try {
            return StreamReader::read($response->toPsrResponse()->getBody());
        } catch (ClaudeException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Поток оборвался посреди ответа — это тот же обрыв связи
            Log::error('Claude: поток оборвался', ['error' => $e->getMessage()]);

            throw new ClaudeException('Сеть недоступна: '.$e->getMessage(), code: 503, previous: $e);
        }
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
     * Грубая оценка входа в токенах по длине запроса.
     *
     * @param  array<int, mixed>  $messages
     * @param  array<int, mixed>  $tools
     */
    private static function estimateTokens(string $system, array $messages, array $tools): int
    {
        $chars = mb_strlen($system)
            + mb_strlen((string) json_encode($messages, JSON_UNESCAPED_UNICODE))
            + mb_strlen((string) json_encode($tools, JSON_UNESCAPED_UNICODE));

        return (int) ceil($chars / 3);
    }

    /**
     * Себестоимость вызова в сотых доли цента.
     * Цены в конфиге заданы за миллион токенов.
     *
     * Вход считаем вместе с прочитанным из кэша: cheapai почти весь
     * вход записывает в кэш (input 2, cache 9 388) и берёт за него
     * примерно по цене обычного входа. Без этого себестоимость в
     * админке выходила вдвое меньше списаний.
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
            // Поток читаем по мере прихода, а не ждём ответ целиком.
            // read_timeout — сколько ждать следующего куска: живой поток
            // шлёт данные каждые несколько секунд, а полторы минуты
            // тишины значат, что шлюз завис, и лучше повторить запрос,
            // чем ждать до общего таймаута
            ->withOptions(['stream' => true, 'read_timeout' => 90])
            ->timeout(280)
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
