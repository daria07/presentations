<?php

namespace App\Console\Commands;

use App\Services\Images\ImageClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Пробная генерация картинки через шлюз (cheapai):
 *
 *   php artisan images:test
 *   php artisan images:test "Замок Диснейленда на закате"
 *   php artisan images:test --quality=high --size=1536x1024
 *   php artisan images:test --endpoint=chat
 *
 * Картинки делаются через /v1/images/generations: чат cheapai для
 * gpt-image не принимает («not supported on the Chat Completions
 * endpoint»). Обрыв соединения повторяем до двух раз — шлюз их даёт.
 * Картинку сохраняем в storage/app/image-tests и печатаем путь,
 * токены и прикидку цены по тарифу из --price-in / --price-out.
 */
class TestImage extends Command
{
    protected $signature = 'images:test
                            {subject? : Что нарисовать}
                            {--model=gpt-image-2.5-flare : Модель}
                            {--size=1536x1024 : Размер; 1536x1024 — горизонталь под слайд}
                            {--quality=medium : low, medium или high}
                            {--endpoint=images : images или chat}
                            {--price-in=1.5 : Цена ввода, $ за 1M токенов}
                            {--price-out=9 : Цена вывода, $ за 1M токенов}';

    protected $description = 'Пробная генерация картинки для слайда через шлюз';

    /*
       Как будем просить картинки в генерации: без надписей (кириллицу
       модели рисуют плохо, а текст на слайде уже есть), в одном стиле
       на всю презентацию, с местом под текст.
    */
    private const STYLE = 'Editorial illustration for a presentation slide. Clean, modern, soft natural light, '
        .'muted harmonious colors, generous empty space on one side for slide text. '
        .'Absolutely no text, letters, numbers, logos, captions or watermarks in the image.';

    public function handle(): int
    {
        $config = config('services.anthropic');

        if (blank($config['key'])) {
            $this->error('В .env не задан LLM_API_KEY (тот же ключ cheapai).');

            return self::FAILURE;
        }

        $subject = $this->argument('subject') ?: 'Сказочный замок в парке развлечений на закате, вид издалека, гуляющие люди';
        $prompt = $subject.'. '.self::STYLE;
        $base = $config['base_url'];

        $this->components->twoColumnDetail('Шлюз', $base);
        $this->components->twoColumnDetail('Модель', (string) $this->option('model'));
        $this->components->twoColumnDetail('Размер / качество', $this->option('size').' / '.$this->option('quality'));
        $this->newLine();

        $kind = (string) $this->option('endpoint');

        foreach ([1, 2, 3] as $attempt) {
            $this->line("<options=bold>Попытка {$attempt}: {$kind}…</>");
            $started = microtime(true);

            try {
                $response = $kind === 'images'
                    ? $this->viaImages($base, $config['key'], $prompt)
                    : $this->viaChat($base, $config['key'], $prompt);
            } catch (Throwable $e) {
                $seconds = round(microtime(true) - $started, 1);
                $this->components->error("Соединение оборвалось через {$seconds} с: ".Str::limit($e->getMessage(), 120));

                continue;
            }

            $seconds = round(microtime(true) - $started, 1);

            if ($response->failed()) {
                // Ответ с ошибкой (отказ модели, неверный запрос) от
                // повтора не изменится — показываем и выходим
                $this->components->error("{$kind}: ошибка {$response->status()} за {$seconds} с");
                $this->line(Str::limit($response->body(), 800));

                return self::FAILURE;
            }

            // Потоковый ответ разбирает тот же код, что и генерация
            $streamed = $kind === 'images' ? ImageClient::result($response) : null;

            if ($streamed !== null && $streamed['error'] !== null) {
                $this->components->error("{$kind}: ошибка в потоке за {$seconds} с");
                $this->line(Str::limit($streamed['error'], 800));

                return self::FAILURE;
            }

            $image = $streamed !== null && $streamed['b64'] !== null
                ? ['b64' => $streamed['b64']]
                : $this->extractImage($response->json() ?? []);

            if ($image === null) {
                $this->components->warn("{$kind}: ответ пришёл за {$seconds} с, но картинки в нём не нашлось. Начало ответа:");
                $this->line(Str::limit($response->body(), 800));

                return self::FAILURE;
            }

            $path = $this->save($image);

            $this->components->twoColumnDetail('Время', "{$seconds} с");
            $this->usage($streamed['usage'] ?? ($response->json('usage') ?? []));
            $this->components->info("Готово: {$path}");

            return self::SUCCESS;
        }

        $this->components->error('Три обрыва подряд — шлюз не отдаёт картинку.');

        return self::FAILURE;
    }

    private function viaImages(string $base, string $key, string $prompt): Response
    {
        return Http::withToken($key)
            ->timeout(180)
            ->post($base.'/v1/images/generations', [
                'model' => $this->option('model'),
                'prompt' => $prompt,
                'size' => $this->option('size'),
                'quality' => $this->option('quality'),
                'n' => 1,
                // Поток держит соединение живым: без него шлюз рвёт
                // запрос на 60-й секунде тишины
                'stream' => true,
                'partial_images' => 3,
            ]);
    }

    private function viaChat(string $base, string $key, string $prompt): Response
    {
        return Http::withToken($key)
            ->timeout(180)
            ->post($base.'/v1/chat/completions', [
                'model' => $this->option('model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
    }

    /**
     * Ищет картинку в ответе: base64 или ссылку. Форматы у шлюзов
     * разные, поэтому смотрим во все известные места.
     *
     * @param  array<mixed>  $body
     * @return array{b64?: string, url?: string}|null
     */
    private function extractImage(array $body): ?array
    {
        // /v1/images/generations
        if ($b64 = data_get($body, 'data.0.b64_json')) {
            return ['b64' => (string) $b64];
        }

        if ($url = data_get($body, 'data.0.url')) {
            return ['url' => (string) $url];
        }

        // /v1/chat/completions: картинка в message.images или в тексте
        if ($url = data_get($body, 'choices.0.message.images.0.image_url.url')) {
            return $this->fromDataUrl((string) $url);
        }

        $content = data_get($body, 'choices.0.message.content');

        if (is_array($content)) {
            foreach ($content as $part) {
                if ($url = data_get($part, 'image_url.url')) {
                    return $this->fromDataUrl((string) $url);
                }
            }

            $content = '';
        }

        $text = (string) $content;

        if (preg_match('~data:image/[a-z]+;base64,[A-Za-z0-9+/=]+~', $text, $m)) {
            return $this->fromDataUrl($m[0]);
        }

        if (preg_match('~https?://\S+?\.(?:png|jpe?g|webp)(?:\?\S*)?~i', $text, $m)) {
            return ['url' => rtrim($m[0], ')')];
        }

        return null;
    }

    /** @return array{b64?: string, url?: string} */
    private function fromDataUrl(string $url): array
    {
        return str_starts_with($url, 'data:')
            ? ['b64' => Str::after($url, 'base64,')]
            : ['url' => $url];
    }

    /** @param  array{b64?: string, url?: string}  $image */
    private function save(array $image): string
    {
        $bytes = isset($image['b64'])
            ? (string) base64_decode($image['b64'])
            : Http::timeout(60)->get((string) ($image['url'] ?? ''))->body();

        $dir = storage_path('app/image-tests');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.'/'.now()->format('Ymd-His').'-'.$this->option('quality').'.png';
        file_put_contents($path, $bytes);

        return $path;
    }

    /** @param  array<string, mixed>  $usage */
    private function usage(array $usage): void
    {
        $in = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $out = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);

        if ($in === 0 && $out === 0) {
            $this->components->twoColumnDetail('Токены', 'шлюз не сообщил');

            return;
        }

        $cost = $in / 1_000_000 * (float) $this->option('price-in')
            + $out / 1_000_000 * (float) $this->option('price-out');

        $this->components->twoColumnDetail('Токенов на вход / выход', "{$in} / {$out}");
        $this->components->twoColumnDetail('Стоимость', '$'.number_format($cost, 4));
    }
}
