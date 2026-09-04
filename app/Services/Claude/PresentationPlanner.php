<?php

namespace App\Services\Claude;

use App\Models\ApiCall;
use App\Models\Presentation;
use Illuminate\Support\Facades\Log;

/**
 * Два шага общения с моделью:
 *   1) уточняющие вопросы по теме,
 *   2) готовая структура слайдов с учётом ответов.
 */
class PresentationPlanner
{
    private const LAYOUTS = [
        'title', 'bullets', 'stats', 'timeline', 'quote',
        'comparison', 'process', 'matrix', 'bignumber', 'closing',
    ];

    /** Сырой ответ последнего вызова — для отладки */
    public ?array $lastRaw = null;

    public function __construct(private readonly ClaudeClient $claude) {}

    public static function make(): self
    {
        return new self(ClaudeClient::make());
    }

    /**
     * Шаг 1. Возвращает список уточняющих вопросов с вариантами ответов.
     *
     * @return array<int, array{key: string, question: string, options: array<int, string>}>
     */
    public function askClarifyingQuestions(Presentation $presentation): array
    {
        $result = $this->claude->structured(
            system: $this->clarifySystemPrompt($presentation->hasSourceText()),
            prompt: $this->clarifyPrompt($presentation),
            schema: Schemas::clarifyingQuestions(),
            toolName: 'ask_questions',
            toolDescription: 'Задать уточняющие вопросы по теме презентации.',
            maxTokens: 1500,
        );

        $this->record($presentation, 'clarify', $result);

        return $result->data['questions'] ?? [];
    }

    /**
     * Шаг 2. Собирает структуру слайдов.
     */
    public function buildOutline(Presentation $presentation): array
    {
        $result = $this->claude->structured(
            system: $this->outlineSystemPrompt($presentation->slide_count, $presentation->hasSourceText()),
            prompt: $this->outlinePrompt($presentation),
            schema: Schemas::outline(),
            toolName: 'build_outline',
            toolDescription: 'Собрать структуру презентации по слайдам.',
            maxTokens: 16000,
        );

        $this->record($presentation, 'outline', $result);

        $this->lastRaw = $result->raw;

        $outline = $this->normalizeOutline($result->data, $presentation);

        // Пустая структура — это всегда неожиданность, и разбираться
        // в ней без сырого ответа невозможно. Сохраняем его целиком.
        if ($outline['slides'] === []) {
            Log::error('Модель вернула структуру без слайдов', [
                'presentation' => $presentation->id,
                'stop_reason' => $result->raw['stop_reason'] ?? null,
                'usage' => $result->raw['usage'] ?? null,
                'data' => $result->data,
            ]);

            throw new ClaudeException(
                'Модель вернула пустую структуру. Подробности в логе.'
            );
        }

        return $outline;
    }

    /**
     * Модель иногда опускает необязательные поля. Приводим ответ
     * к предсказуемому виду, чтобы дальше по коду не было проверок на null.
     */
    private function normalizeOutline(array $data, Presentation $presentation): array
    {
        $data = Json::unwrap($data);

        $slides = [];

        foreach (Json::toArray($data['slides'] ?? []) as $raw) {
            $slide = $this->normalizeSlide(Json::toArray($raw));

            if ($slide !== null) {
                $slides[] = $slide;
            }
        }

        $title = $data['title']
            ?? $slides[0]['heading']
            ?? $presentation->topic;

        $subtitle = $data['subtitle'] ?? $data['subheading'] ?? null;

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'slides' => $this->ensureTitleSlide($slides, $title, $subtitle),
        ];
    }

    /**
     * Титульный слайд обязателен, а его содержание нам и так известно.
     * Модель это правило периодически теряет — особенно когда работает
     * по длинному тексту, — поэтому не полагаемся на неё.
     *
     * @param  array<int, array>  $slides
     * @return array<int, array>
     */
    private function ensureTitleSlide(array $slides, string $title, ?string $subtitle): array
    {
        if (($slides[0]['layout'] ?? null) === 'title') {
            return $slides;
        }

        array_unshift($slides, [
            'layout' => 'title',
            'heading' => $title,
            'subheading' => $subtitle,
            'bullets' => [],
            'stats' => [],
            'quote' => null,
            'notes' => null,
        ]);

        return $slides;
    }

    /**
     * Приводит один слайд к предсказуемому виду. Модель иногда
     * называет поля по-своему — subtitle вместо subheading, items
     * вместо bullets, — поэтому принимаем обе формы.
     */
    private function normalizeSlide(array $slide): ?array
    {
        $heading = $slide['heading'] ?? $slide['title'] ?? null;

        if (blank($heading)) {
            return null;
        }

        $layout = $slide['layout'] ?? 'bullets';

        if (! in_array($layout, self::LAYOUTS, true)) {
            $layout = 'bullets';
        }

        $bullets = [];

        foreach (Json::toArray($slide['bullets'] ?? $slide['items'] ?? []) as $bullet) {
            $bullet = Json::toArray($bullet);
            $title = $bullet['title'] ?? $bullet['label'] ?? null;
            $text = $bullet['text'] ?? $bullet['description'] ?? null;

            if (filled($title) || filled($text)) {
                $bullets[] = [
                    'title' => (string) ($title ?? ''),
                    'text' => (string) ($text ?? ''),
                ];
            }
        }

        $stats = [];

        foreach (Json::toArray($slide['stats'] ?? []) as $stat) {
            $stat = Json::toArray($stat);

            if (filled($stat['value'] ?? null)) {
                $stats[] = [
                    'value' => (string) $stat['value'],
                    'label' => (string) ($stat['label'] ?? ''),
                ];
            }
        }

        $quote = Json::toArray($slide['quote'] ?? []);

        return [
            'layout' => $layout,
            'heading' => (string) $heading,
            'subheading' => $slide['subheading'] ?? $slide['subtitle'] ?? null,
            'bullets' => $bullets,
            'stats' => $stats,
            'quote' => filled($quote['text'] ?? null) ? [
                'text' => (string) $quote['text'],
                'author' => (string) ($quote['author'] ?? ''),
            ] : null,
            'notes' => $slide['notes'] ?? null,
        ];
    }

    // ---------------------------------------------------------------
    // Промпты
    // ---------------------------------------------------------------

    private function clarifySystemPrompt(bool $fromText): string
    {
        if ($fromText) {
            // По готовому тексту содержание уже известно — спрашивать
            // про него бессмысленно. Уточнять надо подачу.
            return <<<'TXT'
            Человек принёс готовый текст, из которого нужно собрать презентацию.
            Содержание уже есть — твоя задача уточнить, как его подать.

            Правила:
            - Не больше трёх вопросов, обычно хватает двух.
            - Не спрашивай о том, что и так видно из текста.
            - Каждый вопрос — с готовыми вариантами ответа.
            - Не спрашивай про цвета и шрифты.

            Хорошие направления: кто аудитория, что вынести на первый план,
            насколько подробно раскрывать, нужны ли выводы в конце.
            TXT;
        }

        return <<<'TXT'
        Ты помогаешь собрать презентацию. Твоя задача на этом шаге —
        задать минимум вопросов, которые сильнее всего повлияют на содержание.

        Правила:
        - Не больше четырёх вопросов, обычно хватает двух-трёх.
        - Спрашивай только то, что нельзя угадать из темы.
        - Каждый вопрос — с готовыми вариантами ответа, чтобы человеку
          хватило одного клика.
        - Не спрашивай про оформление, цвета и шрифты: это не твоя часть.
        - Формулируй по-русски, коротко, без вводных слов.

        Хорошие направления: кто аудитория, какой угол зрения важнее,
        насколько глубоко погружаться, сколько времени на доклад.
        TXT;
    }

    /** Сколько знаков текста показать на шаге уточнений */
    private const CLARIFY_EXCERPT = 3000;

    private function clarifyPrompt(Presentation $presentation): string
    {
        $lines = [];

        if ($presentation->hasSourceText()) {
            $text = $presentation->source_text;
            $full = mb_strlen($text);

            // Вопросы касаются подачи, а не содержания — начала текста
            // достаточно, чтобы понять, о чём он. Гонять сорок страниц
            // ради трёх вопросов незачем.
            $excerpt = mb_substr($text, 0, self::CLARIFY_EXCERPT);

            $lines[] = 'Начало текста, по которому собираем презентацию:';
            $lines[] = '';
            $lines[] = $excerpt;

            if ($full > self::CLARIFY_EXCERPT) {
                $lines[] = '';
                $lines[] = "[…всего в тексте {$full} знаков]";
            }

            $lines[] = '';
        }

        $lines[] = "Тема: {$presentation->topic}";
        $lines[] = "Планируемое количество слайдов: {$presentation->slide_count}";

        return implode("\n", $lines);
    }

    private function outlineSystemPrompt(int $slideCount, bool $fromText): string
    {
        $source = $fromText
            ? <<<'TXT'

            Работай строго по присланному тексту. Не добавляй фактов, дат,
            цифр и имён, которых в нём нет — даже если уверен, что они верные.
            Твоя работа здесь — отобрать главное и разложить по слайдам,
            а не дополнить материал.

            Если текста хватает не на все слайды, лучше сделай меньше:
            плотные слайды честнее, чем растянутые.
            TXT
            : '';

        return <<<TXT
        Ты собираешь структуру презентации на {$slideCount} слайдов.
        {$source}

        Первый слайд всегда layout title, последний — closing.
        Это не обсуждается.

        Вёрстку остальных выбирай по форме содержания, а не для разнообразия.
        Если материал — перечисление, это bullets, даже если bullets уже был.

        - bullets — перечисление равноправных пунктов, от трёх до пяти.
        - stats — от двух до четырёх чисел, которые стоит поставить рядом.
        - bignumber — одно число, ради которого существует весь слайд.
          Ровно один элемент в stats, в subheading — что оно означает.
        - timeline — события, привязанные к годам, в хронологии.
        - process — этапы, идущие один за другим, где важен порядок.
          От трёх до пяти пунктов в bullets.
        - comparison — ровно два подхода или периода рядом. Два bullets.
        - matrix — четыре категории, которые делятся по двум признакам.
          Ровно четыре bullets, в subheading назови оси деления.
        - quote — только подлинная цитата с настоящим автором.

        Подряд три одинаковые вёрстки — повод пересобрать содержание.

        Как писать текст:
        - Заголовок слайда — до 50 знаков, без точки в конце.
        - В bullets поле title — два-четыре слова, text — одно предложение.
        - Никакой воды и канцелярита. Конкретные факты, числа, имена.
        - Не выдумывай цитаты и статистику. Если точных данных нет,
          обойдись без них.
        - notes — одна фраза для того, кто выступает: что сказать вслух,
          чего нет на слайде. Не пересказывай слайд своими словами.

        Отвечай на русском языке.
        TXT;
    }

    private function outlinePrompt(Presentation $presentation): string
    {
        $lines = [];

        if ($presentation->hasSourceText()) {
            $lines[] = 'Исходный текст:';
            $lines[] = '';
            $lines[] = $presentation->source_text;
            $lines[] = '';
            $lines[] = '---';
            $lines[] = '';
        }

        $lines[] = "Тема: {$presentation->topic}";

        foreach ($presentation->clarifications ?? [] as $item) {
            if (filled($item['question'] ?? null) && filled($item['answer'] ?? null)) {
                $lines[] = "{$item['question']} — {$item['answer']}";
            }
        }

        return implode("\n", $lines);
    }

    // ---------------------------------------------------------------

    private function record(Presentation $presentation, string $purpose, ClaudeResult $result): void
    {
        ApiCall::create([
            'user_id' => $presentation->user_id,
            'presentation_id' => $presentation->id,
            'purpose' => $purpose,
            'model' => $result->model,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'cached_tokens' => $result->cachedTokens,
            'cost' => $result->cost,
        ]);
    }
}
