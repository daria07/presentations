<?php

namespace App\Services\Claude;

use App\Models\ApiCall;
use App\Models\Presentation;
use App\Services\Deck\Icons;
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
     * Шаг 2. Конспект темы.
     *
     * Отдельный вызов до разбивки по слайдам. Когда модель сразу пишет
     * готовые слайды, она пишет их в рамках: заголовок до 50 знаков,
     * пункт до 150, не больше четырёх. Думать о содержании и
     * одновременно умещаться в рамку — две разные работы, и вторая
     * побеждает: получается ровно, аккуратно и ни о чём.
     *
     * Здесь рамок нет. Сначала модель разбирается, что по теме вообще
     * есть содержательного, и только потом, следующим вызовом, это
     * укладывается в слайды.
     *
     * @return array<string, mixed>
     */
    public function buildBrief(Presentation $presentation): array
    {
        $result = $this->claude->structured(
            system: $this->briefSystemPrompt($presentation->hasSourceText()),
            prompt: $this->outlinePrompt($presentation),
            schema: Schemas::brief(),
            toolName: 'write_brief',
            toolDescription: 'Продумать содержание презентации до разбивки по слайдам.',
            maxTokens: 6000,
        );

        $this->record($presentation, 'brief', $result);

        return $result->data;
    }

    /**
     * Шаг 3. Собирает структуру слайдов.
     */
    public function buildOutline(Presentation $presentation): array
    {
        $result = $this->claude->structured(
            system: $this->outlineSystemPrompt($presentation->slide_count, $presentation->hasSourceText()),
            prompt: $this->outlinePrompt($presentation, $presentation->brief),
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

        /*
           Слайдов заметно меньше заказанного — молча отдавать такое
           нельзя: человек просил восемь, а получил один. Разбираться
           постфактум не в чем, если не сохранить сырой ответ, поэтому
           сохраняем — вместе с тем, сколько слайдов было до разбора.
           Расхождение этих двух чисел сразу говорит, кто виноват:
           модель недодала или мы потеряли при нормализации.
        */
        $asked = $presentation->slide_count;
        $got = count($outline['slides']);
        $beforeNormalize = count(
            Json::toArray(Json::unwrap($result->data)['slides'] ?? [])
        );

        if ($got < $asked - 1) {
            Log::warning('Слайдов меньше, чем просили', [
                'presentation' => $presentation->id,
                'просили' => $asked,
                'после разбора' => $got,
                'до разбора' => $beforeNormalize,
                'stop_reason' => $result->raw['stop_reason'] ?? null,
                'usage' => $result->raw['usage'] ?? null,
                'data' => $result->data,
            ]);
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
            'slides' => $this->thinIcons(
                $this->ensureTitleSlide($slides, $title, $subtitle)
            ),
        ];
    }

    /**
     * Модель ставит значок к каждому пункту каждого слайда, сколько бы
     * её об этом ни просили. Значки на всех слайдах подряд превращаются
     * в пестроту, поэтому решение принимаем сами, а не промптом.
     *
     * @param  array<int, array>  $slides
     * @return array<int, array>
     */
    private function thinIcons(array $slides): array
    {
        // Внутри слайда значки либо у всех пунктов, либо ни у кого:
        // половина строк с картинкой выглядит как незакрытая вёрстка.
        // Повтор одного значка в слайде — признак, что модель подбирала
        // приблизительно, и такой слайд честнее оставить без значков.
        $candidates = [];

        foreach ($slides as $index => $slide) {
            $icons = array_column($slide['bullets'] ?? [], 'icon');

            $full = $icons !== []
                && ! in_array(null, $icons, true)
                && count(array_unique($icons)) === count($icons);

            if ($full) {
                $candidates[] = $index;
            } else {
                $slides[$index] = $this->stripIcons($slide);
            }
        }

        // Значок держится там, где он часть фигуры — в шагах процесса,
        // в клетках матрицы, в точках таймлайна. В обычном списке это
        // просто столбик картинок слева, и он уходит первым.
        $weight = ['process' => 0, 'matrix' => 1, 'timeline' => 2];

        usort($candidates, fn (int $a, int $b) => [
            $weight[$slides[$a]['layout']] ?? 9, $a,
        ] <=> [
            $weight[$slides[$b]['layout']] ?? 9, $b,
        ]);

        // Не больше трети слайдов со значками — так они читаются как
        // акцент, а не как оформление по умолчанию.
        $limit = max(1, intdiv(count($slides), 3));

        foreach (array_slice($candidates, $limit) as $index) {
            $slides[$index] = $this->stripIcons($slides[$index]);
        }

        return $slides;
    }

    /**
     * @param  array<string, mixed>  $slide
     * @return array<string, mixed>
     */
    private function stripIcons(array $slide): array
    {
        foreach ($slide['bullets'] ?? [] as $i => $bullet) {
            $slide['bullets'][$i]['icon'] = null;
        }

        return $slide;
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
                $icon = $bullet['icon'] ?? null;

                $bullets[] = [
                    'title' => (string) ($title ?? ''),
                    'text' => (string) ($text ?? ''),
                    // Модель иногда придумывает названия — берём только те,
                    // что действительно есть в наборе
                    'icon' => Icons::has($icon) ? $icon : null,
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

    private function briefSystemPrompt(bool $fromText): string
    {
        $source = $fromText
            ? <<<'TXT'
            Работай строго по присланному тексту. Твоя задача — вынуть из
            него суть: что в нём главное, какие мысли связаны между собой,
            какие цифры и примеры стоит сохранить, а что второстепенно.
            Не добавляй фактов, которых в тексте нет.
            TXT
            : <<<'TXT'
            Опирайся на то, что знаешь по теме. Конкретику — числа, даты,
            имена, названия — приводи только ту, в которой уверен.
            В чём не уверен, выноси в uncertain, а не подавай как факт.
            TXT;

        return <<<TXT
        Ты готовишь содержание выступления. Слайды будут потом, сейчас
        важно только одно: чтобы человеку было что сказать.

        {$source}

        Что делает конспект хорошим:
        - Каждый блок отвечает на вопрос «и что?». Не «виды X бывают
          такие-то», а почему это важно, к чему приводит, что с этим
          делать.
        - Механизм важнее перечисления. Не «факторы: А, Б, В», а как
          именно А влияет на результат.
        - Конкретика вместо категорий. Не «крупные компании»,
          а «Сбер и Тинькофф». Не «значительно вырос» — насколько.
        - Неочевидное ценнее общеизвестного. Если блок можно написать,
          зная о теме только её название, он лишний.

        В avoid перечисли банальности, которые на этой теме напрашиваются
        сами: именно их потом не должно оказаться на слайдах.

        Блоков делай больше, чем будет слайдов: на следующем шаге из них
        отберут лучшие, и выбор должен быть.

        Пиши по-русски.
        TXT;
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

        Содержание уже продумано и прислано конспектом. Твоя работа —
        уложить его в слайды, ничего не растеряв по дороге. Главное
        правило: слайд несёт мысль из конспекта, а не её название.
        Если в блоке был механизм, пример или число — они должны
        оказаться на слайде. Пересказ заголовками — провал работы.

        Блоков в конспекте больше, чем слайдов: отбери те, что сильнее,
        остальные выброси целиком. Склеивать два блока в один слайд
        можно только если они про одно и то же — иначе оба станут
        поверхностными.

        Чего в презентации быть не должно: всё перечисленное в avoid,
        и утверждения из uncertain в виде факта — их либо смягчить,
        либо не брать.

        Первый слайд всегда layout title, последний — closing.
        Это не обсуждается.

        Вёрстку остальных выбирай по форме содержания.

        Жёсткое ограничение: не больше двух одинаковых вёрсток подряд.
        Если третий слайд снова просится в тот же тип — значит содержание
        не проработано, а не оформление. Перестрой сам материал:
        - три причины и три следствия рядом — это comparison, а не два
          списка подряд;
        - четыре разновидности чего-либо — это matrix;
        - последовательность действий — process, даже если её можно
          записать списком;
        - слайд, где главное это одна цифра, — bignumber.

        В презентации из восьми и более слайдов должно встретиться
        минимум четыре разных типа вёрстки.

        - bullets — перечисление равноправных пунктов, от трёх до четырёх.
        - stats — от двух до четырёх чисел, которые стоит поставить рядом.
        - bignumber — одно число, ради которого существует весь слайд.
          Ровно один элемент в stats, в subheading — что оно означает.
        - timeline — события, привязанные к годам, в хронологии.
        - process — этапы, идущие один за другим, где важен порядок.
          От трёх до четырёх пунктов в bullets.
        - comparison — ровно два подхода или периода рядом. Два bullets.
        - matrix — четыре категории, которые делятся по двум признакам.
          Ровно четыре bullets, в subheading назови оси деления.
        - quote — только подлинная цитата с настоящим автором.



        Как писать текст:
        - Заголовок слайда — до 50 знаков, без точки в конце. Заголовок
          утверждает, а не называет: «Сроки растут из-за согласований»,
          а не «Сроки».
        - В bullets поле title — два-четыре слова, text — одно предложение
          до 150 знаков. Высота слайда фиксирована, и лишнее обрежется,
          поэтому решает плотность, а не длина: в одном предложении
          должен быть факт, а не подводка к нему. «Согласование занимает
          три недели из восьми» — хорошо; «Этот этап требует внимания» —
          выброшенная строка.
        - Если материала на слайд больше, чем влезает, разнеси на два
          слайда, а не ужимай до общих слов.
        - Никакой воды и канцелярита.
        - Не выдумывай цитаты и статистику. Если точных данных нет,
          обойдись без них: «выручка выросла» вместо «выручка выросла
          на 34%», «сроки сдвинулись» вместо «сроки сдвинулись на месяц».
        - Отсутствие данных — не повод отказываться от работы. Даже по
          теме, где у тебя нет ни одной конкретной цифры, презентация
          собирается: структура, формулировки, вопросы к обсуждению,
          места, которые докладчик заполнит сам. Собери такую.
        - notes — два-четыре предложения для того, кто выступает. Это
          не подпись к слайду, а то, что он скажет вслух сверх него:
          объяснение механизма, пример, оговорка, переход к следующему
          слайду. На слайде — опора, в notes — сам рассказ. Здесь
          ограничения по высоте нет, и то, что не поместилось на слайд,
          место находит именно тут. Не пересказывай слайд своими словами.
        - icon — редкий акцент, а не оформление каждого пункта.
          На большей части слайдов у всех пунктов стоит none, и это
          нормально: презентация совсем без значков выглядит строго,
          а презентация со значками на каждой строке — пёстро.
          Значки ставь только там, где они несут смысл и где в слайде
          все значки разные: про сроки clock, про рост growth, про риск
          alert, про людей people, про деньги money, про исследование
          search. Если в слайде хотя бы один пункт остался без точного
          значка — ставь none у всех пунктов этого слайда.

        Отвечай на русском языке.
        TXT;
    }

    /**
     * Исходные данные для шагов «конспект» и «структура».
     *
     * Один и тот же текст на оба шага: на первом из него рождается
     * конспект, на втором к нему же добавляется сам конспект.
     *
     * @param  array<string, mixed>|null  $brief
     */
    private function outlinePrompt(Presentation $presentation, ?array $brief = null): string
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

        if ($brief !== null) {
            $lines[] = '';
            $lines[] = '--- Конспект ---';
            $lines[] = '';
            $lines[] = (string) json_encode(
                $brief,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );
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
