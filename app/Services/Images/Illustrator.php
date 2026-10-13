<?php

namespace App\Services\Images;

use App\Models\ApiCall;
use App\Models\Presentation;
use App\Services\Deck\DeckRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Рисует картинки для слайдов, которые выбрал Illustrations::plan.
 *
 * У такого слайда в структуре лежит image.scene — что нарисовать.
 * Здесь мы дописываем к сцене стиль темы оформления и композицию,
 * рисуем всё разом, пережимаем в JPEG и кладём путь в image.path.
 *
 * Ничто здесь не роняет генерацию: не нарисовалась картинка — слайд
 * остаётся без неё (image.failed), и презентация печатается как
 * обычно. Картинка — украшение, а не то, за что человек заплатил.
 */
class Illustrator
{
    /**
     * Обложка — во весь слайд. Картинка на половине слайда стоит
     * с полями и выходит почти квадратной (≈157×166 мм), поэтому
     * рисуем квадрат: вертикальную пришлось бы сильно резать сверху
     * и снизу, и у людей пропадали бы головы.
     */
    private const SIZE_COVER = '1536x1024';

    private const SIZE_HALF = '1024x1024';

    /** Для явно высоких объектов, если так решила модель */
    private const SIZE_PORTRAIT = '1024x1536';

    private const NO_TEXT = 'Absolutely no text, letters, numbers, captions, signs, logos or watermarks anywhere in the image.';

    /*
       Главное должно поместиться в кадр целиком: обрезанная крона
       или голова на слайде выглядят как ошибка вёрстки.
    */
    private const WHOLE_SUBJECT = 'Show the main subject entirely within the frame, from top to bottom, '
        .'with clear empty space above, below and around it; never crop the main subject at the edges.';

    public function __construct(private readonly ImageClient $client) {}

    /** Картинки выключены, если в .env не задана модель */
    public static function enabled(): bool
    {
        return filled(config('services.images.model'));
    }

    /**
     * Есть ли в структуре картинки, которые ещё не рисовали.
     *
     * @param  array<string, mixed>|null  $outline
     */
    public static function pending(?array $outline): bool
    {
        foreach ($outline['slides'] ?? [] as $slide) {
            if (self::needsDrawing($slide)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  bool  $skipCover  обложку рисует отдельная задача
     *                           (IllustrateCover), второй раз не платим
     */
    public function illustrate(Presentation $presentation, bool $skipCover = false): void
    {
        $outline = $presentation->outline ?? [];
        $style = $this->style($presentation);

        $subject = trim((string) ($outline['image_subject'] ?? ''));
        $requests = [];

        foreach ($outline['slides'] ?? [] as $index => $slide) {
            if (! self::needsDrawing($slide)) {
                continue;
            }

            if ($skipCover && ($slide['image']['side'] ?? null) === 'cover') {
                continue;
            }

            // Формат решает код: обложка — горизонталь, половина слайда —
            // квадрат, вертикаль — только если модель отметила высокий объект
            $format = match (true) {
                ($slide['image']['side'] ?? null) === 'cover' => 'cover',
                ($slide['image']['format'] ?? null) === 'portrait' => 'portrait',
                default => 'square',
            };

            $requests[(string) $index] = [
                'prompt' => $this->prompt((string) $slide['image']['scene'], $style, $format, $subject),
                'size' => match ($format) {
                    'cover' => self::SIZE_COVER,
                    'portrait' => self::SIZE_PORTRAIT,
                    default => self::SIZE_HALF,
                },
            ];
        }

        if ($requests === []) {
            return;
        }

        $started = microtime(true);
        $results = $this->client->generateMany($requests);

        /*
           Отказы по контент-политике бывают ложными: на безобидный
           фикус в гостиной модель вдруг отвечает «нарушает правила».
           Пробуем ещё раз с упрощённой сценой — по заголовку слайда,
           без деталей, которые могли её смутить.
        */
        $retry = [];

        foreach ($this->client->refused as $key) {
            $slide = $outline['slides'][$key] ?? [];
            $simple = trim(($slide['heading'] ?? '').'. '.($slide['subheading'] ?? ''), ' .');

            if ($simple !== '') {
                $cover = ($slide['image']['side'] ?? null) === 'cover';

                $retry[$key] = [
                    'prompt' => $this->prompt($simple, $style, $cover ? 'cover' : 'square', $subject),
                    'size' => $cover ? self::SIZE_COVER : self::SIZE_HALF,
                ];
            }
        }

        if ($retry !== []) {
            foreach ($this->client->generateMany($retry) as $key => $result) {
                $results[$key] = $result;
            }
        }
        foreach ($results as $index => $result) {
            if ($result === null) {
                // Второй раз за эту картинку не возьмёмся: повтор задачи
                // не должен снова платить за отказ модели
                $outline['slides'][$index]['image']['failed'] = true;

                continue;
            }

            $outline['slides'][$index]['image']['path'] = $this->store($presentation, (int) $index, $result);
        }

        $presentation->update(['outline' => $outline]);

        Log::info('Картинки нарисованы', [
            'presentation' => $presentation->id,
            'просили' => count($requests),
            'получили' => count(array_filter($results)),
            'seconds' => round(microtime(true) - $started, 1),
        ]);
    }

    /**
     * Обложка по конспекту, до того как готовы слайды.
     *
     * Обложке нужна только тема: сцена и герой лежат в конспекте.
     * Поэтому её рисуют параллельно со сборкой структуры, и к моменту
     * картинок для слайдов она обычно уже готова — а это самая долгая
     * картинка из всех.
     *
     * @return string|null путь к файлу или null, если не вышло
     */
    public function drawCover(Presentation $presentation): ?string
    {
        $brief = $presentation->brief ?? [];
        $scene = trim((string) ($brief['cover_scene'] ?? ''));

        if ($scene === '') {
            return null;
        }

        $style = $this->style($presentation);
        $subject = trim((string) ($brief['image_subject'] ?? ''));

        // Ключ строкой, а не «0»: PHP превратил бы «0» в число
        $results = $this->client->generateMany([
            'cover' => ['prompt' => $this->prompt($scene, $style, 'cover', $subject), 'size' => self::SIZE_COVER],
        ]);

        // Ложный отказ по контент-политике — пробуем по теме, без деталей
        if (($results['cover'] ?? null) === null && $this->client->refused !== []) {
            $results = $this->client->generateMany([
                'cover' => ['prompt' => $this->prompt($presentation->topic, $style, 'cover', $subject), 'size' => self::SIZE_COVER],
            ]);
        }

        $result = $results['cover'] ?? null;

        return $result === null ? null : $this->store($presentation, 0, $result);
    }

    /** Стиль картинок по теме оформления */
    private function style(Presentation $presentation): string
    {
        return (string) (config('deck.themes.'.$presentation->theme.'.image_style')
            ?? config('deck.themes.'.config('deck.default_theme').'.image_style', ''));
    }

    /**
     * Пережимает картинку в JPEG, кладёт в папку презентации и
     * записывает расход.
     *
     * @param  array{bytes: string, input: int, output: int}  $result
     */
    private function store(Presentation $presentation, int $index, array $result): string
    {
        $path = DeckRenderer::imageDir($presentation).'/'.$index.'-'.Str::lower(Str::random(8)).'.jpg';

        Storage::disk(config('deck.disk'))->put($path, Jpeg::compress($result['bytes']));

        $this->record($presentation, $result['input'], $result['output']);

        return $path;
    }

    /**
     * Сцену пишет модель, а стиль и композицию — мы: так все картинки
     * одной презентации выглядят одинаково, а картинка ложится в вёрстку.
     */
    private function prompt(string $scene, string $style, string $format, string $subject = ''): string
    {
        /*
           Картинки рисуются по одной, и художник не знает, о чём
           презентация: «пушистый питомец у щётки» в презентации про
           пуделя становится кошкой. Поэтому героя называем в каждом
           запросе — не требуя рисовать его, но запрещая подменять.
        */
        $hero = $subject === ''
            ? ''
            : ' The presentation is about '.trim($subject, ' .').': any animal, person or object '
                .'of this kind in the image must be exactly this, never a different breed, species or person.';

        $composition = match ($format) {
            'cover' => 'Wide landscape composition: the main subject on the right third, '
                .'the left half calmer and darker, with room for a title.',
            'portrait' => 'Vertical composition for a tall subject: the whole subject from base '
                .'to top centered in frame, with room above and below it.',
            default => 'Square composition: the main subject centered and fully in frame, '
                .'with some breathing room around it, nothing important near the edges.',
        };

        return trim($scene, ' .').'.'.$hero.' '.$composition.' '.self::WHOLE_SUBJECT.' '.$style.' '.self::NO_TEXT;
    }

    /** @param  mixed  $slide */
    private static function needsDrawing($slide): bool
    {
        return is_array($slide)
            && filled($slide['image']['scene'] ?? null)
            && blank($slide['image']['path'] ?? null)
            && empty($slide['image']['failed']);
    }

    private function record(Presentation $presentation, int $input, int $output): void
    {
        $config = config('services.images');

        ApiCall::create([
            'user_id' => $presentation->user_id,
            'presentation_id' => $presentation->id,
            'purpose' => 'image',
            'model' => (string) $config['model'],
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cached_tokens' => 0,
            // В тех же единицах, что и у текста: сотые доли цента
            'cost' => (int) round(
                $input / 1_000_000 * (int) $config['price_input']
                + $output / 1_000_000 * (int) $config['price_output']
            ),
        ]);
    }
}
