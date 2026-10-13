<?php

namespace App\Http\Controllers\Presentations;

use App\Enums\PresentationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\AnswerQuestionsRequest;
use App\Http\Requests\Presentations\StorePresentationRequest;
use App\Http\Requests\Presentations\UpdateOutlineRequest;
use App\Jobs\GeneratePresentation;
use App\Jobs\PrepareQuestions;
use App\Jobs\RenderPresentation;
use App\Models\Presentation;
use App\Services\Billing\Discount;
use App\Services\Billing\UnlockPresentation;
use App\Services\Deck\DeckRenderer;
use App\Services\Deck\Icons;
use App\Services\Deck\Looks;
use App\Services\Deck\SpeechRenderer;
use App\Services\Images\Illustrator;
use App\Services\Search\YandexSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresentationController extends Controller
{
    /** Сколько слайдов можно в бесплатной версии */
    public const FREE_MAX_SLIDES = 8;

    /** Список презентаций в кабинете */
    public function index(Request $request): Response
    {
        $presentations = $request->user()
            ->presentations()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Presentation $p) => $this->summary($p));

        return Inertia::render('presentations/Index', [
            'presentations' => [
                'data' => $presentations->items(),
                'currentPage' => $presentations->currentPage(),
                'lastPage' => $presentations->lastPage(),
                'total' => $presentations->total(),
                'prevUrl' => $presentations->previousPageUrl(),
                'nextUrl' => $presentations->nextPageUrl(),
            ],
            'credits' => $request->user()->credits,
            'trialAvailable' => ! $request->user()->trial_used,
            // Кнопка скидки, когда генерации кончились. Первый показ
            // записывается — это знаменатель A/B-теста в админке
            'promo' => Discount::offerable($request->user())
                ? Discount::offer($request->user())
                : null,
        ]);
    }

    /** Форма новой презентации */
    public function create(Request $request): Response
    {
        return Inertia::render('presentations/Create', [
            'maxSource' => StorePresentationRequest::MAX_SOURCE,
            'credits' => $request->user()->credits,
            'trialAvailable' => ! $request->user()->trial_used,
            'themes' => Looks::themes(),
            'palettes' => Looks::palettes(),
        ]);
    }

    /** Создаём черновик и уходим спрашивать уточнения */
    public function store(StorePresentationRequest $request): RedirectResponse
    {
        // Уточняющие вопросы — платный вызов. Проверяем возможность
        // заплатить до него, а не после, иначе деньги уходят впустую.
        if (! $request->user()->hasCredits()) {
            return to_route('billing.index')->with('toast', [
                'type' => 'warning',
                'message' => 'Генерации закончились — выберите пакет, чтобы продолжить.',
            ]);
        }

        // Платить будет пробная — в бесплатной версии до 8 слайдов
        if ($request->user()->credits < 1 && $request->integer('slide_count') > self::FREE_MAX_SLIDES) {
            return back()->withErrors([
                'slide_count' => 'В бесплатной версии — до '.self::FREE_MAX_SLIDES.' слайдов. Больше — в платных тарифах.',
            ])->withInput();
        }

        $source = $request->string('source_text')->trim();
        $topic = $request->string('topic')->trim();

        $presentation = $request->user()->presentations()->create([
            // Если человек дал только текст, темой становится его начало —
            // она нужна для заголовка в списке, пока не готова структура.
            'topic' => $topic->isNotEmpty()
                ? $topic->value()
                : $source->limit(120)->value(),
            'source_text' => $source->isNotEmpty() ? $source->value() : null,
            'slide_count' => $request->integer('slide_count'),
            'status' => PresentationStatus::Draft,
        ]);

        PrepareQuestions::dispatch($presentation);

        return to_route('presentations.show', $presentation);
    }

    /** Одна презентация: вопросы, ожидание или готовый файл */
    public function show(Request $request, Presentation $presentation): Response
    {
        $this->authorize('view', $presentation);

        return Inertia::render('presentations/Show', [
            'presentation' => $this->detail($presentation),
            'themes' => Looks::themes(),
            'palettes' => Looks::palettes(),
            'credits' => $request->user()->credits,
            'trialAvailable' => ! $request->user()->trial_used,
        ]);
    }

    /** Лёгкая ручка для опроса статуса с фронта */
    public function status(Request $request, Presentation $presentation): JsonResponse
    {
        $this->authorize('view', $presentation);

        return response()->json($this->detail($presentation));
    }

    /** Ответы на уточнения — здесь же списываем кредит и ставим в очередь */
    public function answers(AnswerQuestionsRequest $request, Presentation $presentation): RedirectResponse
    {
        $this->authorize('update', $presentation);

        $answers = $request->collect('answers');
        $user = $request->user();

        // Всё в одной транзакции с блокировкой строки: иначе двойной
        // клик успевает списать два кредита и запустить две генерации.
        // Чем заплатили, нужно знать задаче: при сбое она возвращает
        // ровно это, а не всегда кредит
        $spent = null;

        $outcome = DB::transaction(function () use ($presentation, $user, $answers, &$spent): string {
            $locked = Presentation::query()
                ->whereKey($presentation->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked->status !== PresentationStatus::Asking) {
                return 'busy';
            }

            $spent = $user->spendCredit();

            if ($spent === null) {
                return 'no-credits';
            }

            $clarifications = collect($locked->clarifications ?? [])
                ->map(fn (array $item, int $i) => [
                    ...$item,
                    'answer' => $answers->get($item['key'] ?? $i) ?? $answers->get((string) $i),
                ])
                ->all();

            $locked->update([
                'clarifications' => $clarifications,
                'status' => PresentationStatus::Queued,
                'error_message' => null,
                // За купленную генерацию — сразу полная версия. По пробной —
                // бесплатная, и в ней не больше 8 слайдов
                'unlocked_at' => $spent === 'credit' ? now() : null,
                'slide_count' => $spent === 'trial'
                    ? min($locked->slide_count, self::FREE_MAX_SLIDES)
                    : $locked->slide_count,
            ]);

            return 'queued';
        });

        return match ($outcome) {
            'busy' => back()->with('toast', [
                'type' => 'info',
                'message' => 'Эта презентация уже в работе.',
            ]),
            'no-credits' => to_route('billing.index')->with('toast', [
                'type' => 'warning',
                'message' => 'Генерации закончились — выберите пакет, чтобы продолжить.',
            ]),
            default => tap(
                to_route('presentations.show', $presentation),
                fn () => GeneratePresentation::dispatch(
                    $presentation->refresh(),
                    $request->input('theme'),
                    $request->input('palette'),
                    $spent,
                ),
            ),
        };
    }

    /** Повторная попытка после сбоя */
    /**
     * Повторный запуск.
     *
     * Работает в двух случаях. После сбоя — тогда генерация списывается
     * заново, потому что при провале её вернули на счёт. И для задачи,
     * которая зависла: если воркер умер посреди работы, статус остаётся
     * «генерируем» навсегда, а перезагрузка страницы тут бессильна.
     * За такую задачу уже заплачено, второй раз списывать нельзя.
     */
    public function retry(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->authorize('update', $presentation);

        $user = $request->user();

        // Перезапуск застрявшей задачи ничего не списывает — тогда
        // при сбое и возвращать нечего
        $spent = null;

        $outcome = DB::transaction(function () use ($presentation, $user, &$spent): string {
            $locked = Presentation::query()
                ->whereKey($presentation->getKey())
                ->lockForUpdate()
                ->first();

            $failed = $locked->status === PresentationStatus::Failed;

            // Три минуты с запасом: обычная генерация занимает около минуты
            $stuck = $locked->status->isPending()
                && $locked->updated_at?->lt(now()->subMinutes(3));

            if (! $failed && ! $stuck) {
                return 'nothing-to-do';
            }

            if ($failed) {
                $spent = $user->spendCredit();

                if ($spent === null) {
                    return 'no-credits';
                }
            }

            $locked->update([
                'status' => PresentationStatus::Queued,
                'error_message' => null,
            ]);

            return $failed ? 'restarted' : 'unstuck';
        });

        return match ($outcome) {
            'nothing-to-do' => back()->with('toast', [
                'type' => 'info',
                'message' => 'Пока нечего перезапускать — подождите немного.',
            ]),
            'no-credits' => to_route('billing.index')->with('toast', [
                'type' => 'warning',
                'message' => 'Генерации закончились — выберите пакет, чтобы продолжить.',
            ]),
            default => tap(
                to_route('presentations.show', $presentation)->with('toast', [
                    'type' => 'info',
                    'message' => $outcome === 'unstuck'
                        ? 'Задача потерялась, запускаем заново. Генерация не списывается.'
                        : 'Запустили заново.',
                ]),
                fn () => GeneratePresentation::dispatch(
                    $presentation->refresh(),
                    spent: $spent,
                ),
            ),
        };
    }

    /**
     * Смена оформления у готовой презентации.
     *
     * Структура уже куплена и лежит в базе, поэтому перепечатка
     * не стоит ни обращения к модели, ни генерации у человека.
     */
    /**
     * Оценка: звёзды от 1 до 5 и необязательный отзыв. Повторная
     * отправка заменяет прежнюю — человек мог передумать.
     */
    public function review(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->authorize('update', $presentation);

        // Оценивать можно только то, что человек видел целиком
        abort_unless($presentation->isReady(), 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'review' => ['nullable', 'string', 'max:1000'],
        ], [
            'rating.required' => 'Выберите количество звёзд.',
            'rating.between' => 'Оценка — от одной до пяти звёзд.',
            'review.max' => 'Отзыв длиннее 1000 знаков не поместится.',
        ]);

        // Без отметки времени: updated_at — метка версии превью,
        // и от оценки слайды в окне просмотра перегружались бы зря
        $presentation->timestamps = false;
        $presentation->update([
            'rating' => (int) $data['rating'],
            'review' => filled($data['review'] ?? null) ? trim($data['review']) : null,
            'reviewed_at' => now(),
        ]);

        return back();
    }

    public function theme(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->authorize('update', $presentation);

        $request->validate([
            'theme' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('deck.themes')))],
            'palette' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('deck.palettes')))],
        ]);

        if (! $presentation->isReady()) {
            return back()->with('toast', [
                'type' => 'info',
                'message' => 'Сменить оформление можно у готовой презентации.',
            ]);
        }

        $theme = $request->input('theme', $presentation->theme);
        $palette = $request->input('palette', $presentation->palette);

        // Ничего не изменилось — и печатать заново нечего
        if ($theme === $presentation->theme && $palette === $presentation->palette) {
            return back();
        }

        $presentation->update([
            'theme' => $theme,
            'palette' => $palette,
            'status' => PresentationStatus::Generating,
        ]);

        RenderPresentation::dispatch($presentation);

        return back();
    }

    /**
     * Открыть полную версию бесплатной презентации за одну генерацию.
     *
     * Генераций нет — ведём к тарифам. Есть — списываем, снимаем
     * ограничения и перепечатываем PDF уже без водяного знака.
     */
    public function unlock(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->authorize('update', $presentation);

        if (! $presentation->isLocked()) {
            return back();
        }

        $unlocked = UnlockPresentation::handle($presentation);

        if (! $unlocked) {
            return to_route('billing.index', ['for' => $presentation->id])->with('toast', [
                'type' => 'info',
                'message' => 'Выберите тариф — после оплаты полная версия откроется сама.',
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Полная версия открыта. Обновляем файл без водяного знака.',
        ]);
    }

    /**
     * Скрыть или вернуть слайд с источниками.
     *
     * Слайд не удаляется, а помечается: передумали — источники вернутся
     * одним щелчком, без новой генерации. Скрытый слайд не попадает
     * ни в просмотр, ни в PDF, ни в речь докладчика.
     */
    public function sources(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->authorize('update', $presentation);

        $visible = $request->validate(['visible' => ['required', 'boolean']])['visible'];

        if (! $presentation->isReady()) {
            return back()->with('toast', [
                'type' => 'info',
                'message' => 'Скрыть источники можно у готовой презентации.',
            ]);
        }

        $outline = $presentation->outline ?? [];
        $index = null;

        foreach ($outline['slides'] ?? [] as $i => $slide) {
            if (($slide['layout'] ?? null) === 'sources') {
                $index = $i;
            }
        }

        abort_if($index === null, 404);

        // Уже так — перепечатывать нечего
        if (empty($outline['slides'][$index]['hidden']) === (bool) $visible) {
            return back();
        }

        $outline['slides'][$index]['hidden'] = ! $visible;

        $presentation->update([
            'outline' => $outline,
            'status' => PresentationStatus::Generating,
        ]);

        RenderPresentation::dispatch($presentation);

        return back();
    }

    /** Редактор структуры */
    public function edit(Presentation $presentation): Response
    {
        $this->authorize('update', $presentation);

        abort_unless(filled($presentation->outline['slides'] ?? null), 404);

        return Inertia::render('presentations/Edit', [
            'presentation' => [
                'id' => $presentation->id,
                'title' => $presentation->outline['title'] ?? $presentation->topic,
                'subtitle' => $presentation->outline['subtitle'] ?? null,
                'slides' => $presentation->outline['slides'],
                'previewUrl' => route('presentations.preview', $presentation),
                'showUrl' => route('presentations.show', $presentation),
                // Пробная версия правится, но остаётся с водяным знаком
                'locked' => $presentation->isLocked(),
            ],
            'layouts' => $this->layouts(),
            'icons' => Icons::names(),
        ]);
    }

    /**
     * Сохранение правок.
     *
     * HTML-превью обновляется сразу — оно рисуется тем же Blade без
     * запуска браузера. PDF перепечатывается в фоне: он нужен только
     * при скачивании, и заставлять ждать его ради опечатки незачем.
     */
    public function updateOutline(UpdateOutlineRequest $request, Presentation $presentation): RedirectResponse
    {
        $this->authorize('update', $presentation);

        // Пробная версия правится, но не разрастается: добавить слайдов
        // сверх бесплатных 8 нельзя (служебный слайд с источниками,
        // если он уже был, не отбираем)
        $limit = max(self::FREE_MAX_SLIDES, count($presentation->outline['slides'] ?? []));

        if ($presentation->isLocked() && count((array) $request->input('slides', [])) > $limit) {
            return back()->withErrors([
                'slides' => 'В бесплатной версии — до '.self::FREE_MAX_SLIDES.' слайдов. Больше — в полной версии.',
            ]);
        }

        $title = $request->string('title')->trim()->value();

        $presentation->update([
            'outline' => [
                'title' => $title,
                'subtitle' => $request->input('subtitle'),
                'slides' => array_values($request->input('slides')),
            ],
            'title' => $title,
            // Пока файл не перепечатан, презентация считается
            // незавершённой: иначе на странице покажется старый PDF,
            // и человек решит, что правки не сохранились.
            'status' => PresentationStatus::Generating,
        ]);

        // Картинки, убранные со слайдов, больше не нужны
        $presentation->pruneImages();

        RenderPresentation::dispatch($presentation);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Сохранили. Печатаем файл заново.',
        ]);
    }

    /**
     * HTML-превью для редактора: те же шаблоны, что идут в печать.
     *
     * Если в запросе пришла структура — рисуем её, ничего не сохраняя.
     * Это позволяет показывать правки сразу, пока человек печатает,
     * и при этом не трогать то, что лежит в базе.
     */
    public function preview(Request $request, Presentation $presentation, DeckRenderer $renderer): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $presentation);

        $draft = $request->input('slides');

        if ($draft !== null) {
            // Черновик проверяется теми же правилами, что и сохранение:
            // иначе в шаблон и в Chrome уходит что угодно — кривые данные
            // роняют рендер, огромный массив грузит сервер. Ответ — 422,
            // а не редирект назад: превью запрашивается через fetch, и
            // редирект вернул бы вместо слайдов HTML страницы редактора.
            $validator = Validator::make(
                $request->only(['title', 'subtitle', 'slides']),
                UpdateOutlineRequest::outlineRules(draft: true),
            );

            // Ошибки отдаём списком: без них редактор не может сказать,
            // какое поле мешает, и превью просто молча не обновляется
            if ($validator->fails()) {
                Log::warning('Превью: черновик не прошёл проверку', [
                    'presentation' => $presentation->id,
                    'errors' => $validator->errors()->toArray(),
                ]);

                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            // Копия в памяти: до базы эти данные не доедут
            $presentation = clone $presentation;
            $presentation->outline = [
                'title' => $request->input('title') ?: $presentation->topic,
                'subtitle' => $request->input('subtitle'),
                'slides' => array_values($draft),
            ];
        }

        abort_unless(filled($presentation->outline['slides'] ?? null), 404);

        return response($renderer->html(
            $presentation,
            $presentation->theme,
            forScreen: true,
            palette: $presentation->palette,
        ))
            ->header('Content-Type', 'text/html; charset=utf-8')
            // Превью собирается на лету из шаблонов и настроек. Кэш здесь
            // вреден: после правки вёрстки браузер продолжает показывать
            // старое, а адрес не меняется — метка версии завязана на
            // презентацию, а не на код.
            ->header('Cache-Control', 'no-store, must-revalidate');
    }

    public function download(Presentation $presentation): StreamedResponse
    {
        $this->authorize('view', $presentation);

        // Бесплатная версия: только просмотр на сайте
        abort_if($presentation->isLocked(), 403);

        abort_unless($presentation->isReady(), 404);

        $name = str($presentation->title ?: $presentation->topic)
            ->limit(60, '')
            ->slug()
            ->append('.pdf')
            ->value();

        return Storage::disk(config('deck.disk'))->download($presentation->file_path, $name);
    }

    /**
     * Речь докладчика — подарок к презентации: заметки, которые модель
     * уже написала при сборке структуры, разложены по листу A4.
     */
    public function speech(Presentation $presentation, SpeechRenderer $renderer): \Illuminate\Http\Response
    {
        $this->authorize('view', $presentation);

        // Бесплатная версия: только просмотр на сайте
        abort_if($presentation->isLocked(), 403);

        abort_unless(filled($presentation->outline['slides'] ?? null), 404);

        $name = str($presentation->title ?: $presentation->topic)
            ->limit(50, '')
            ->slug()
            ->append('-rech.pdf')
            ->value();

        // inline, а не attachment: речь чаще читают с экрана, чем хранят,
        // и скачать её из просмотрщика браузера всё равно можно
        return response($renderer->pdf($presentation))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$name.'"');
    }

    public function destroy(Presentation $presentation): RedirectResponse
    {
        $this->authorize('delete', $presentation);

        // Файл уберёт сама модель в событии deleting
        $presentation->delete();

        return to_route('presentations.index')->with('toast', [
            'type' => 'success',
            'message' => 'Презентация удалена.',
        ]);
    }

    // -----------------------------------------------------------------

    /** Короткая карточка для списка */
    private function summary(Presentation $presentation): array
    {
        return [
            'id' => $presentation->id,
            'title' => $presentation->title ?: $presentation->topic,
            'status' => $presentation->status->value,
            'statusLabel' => $presentation->status->label(),
            // Скрытые слайды (источники) в число не входят: человек видит
            // и скачивает презентацию без них
            'slideCount' => count(array_filter(
                $presentation->outline['slides'] ?? [],
                fn ($slide) => empty($slide['hidden']),
            )) ?: $presentation->slide_count,
            'createdAt' => $presentation->created_at?->toIso8601String(),
            'url' => route('presentations.show', $presentation),
            // Оценка ставится прямо из списка, в модальном окне
            'reviewUrl' => $presentation->isReady()
                ? route('presentations.review', $presentation)
                : null,
            'rating' => $presentation->rating,
            'review' => $presentation->review,
        ];
    }

    /**
     * Шаги генерации для экрана ожидания.
     *
     * Статус один — «Генерируем», а внутри него несколько минут разной
     * работы. Шаг выводим из того, что уже лежит в базе: конспект,
     * структура, ненарисованные картинки. Отдельное поле под шаг
     * пришлось бы держать в согласии с задачами очереди.
     *
     * @return list<array{label: string, state: string}>|null
     */
    private function steps(Presentation $presentation): ?array
    {
        if (! $presentation->status->isPending()) {
            return null;
        }

        // Перепечатка после правок или смены оформления: структура
        // и файл уже есть, осталась только печать
        if (filled($presentation->file_path)) {
            return [['label' => 'Печатаем файл', 'state' => 'current']];
        }

        $illustrated = Illustrator::enabled();
        $searching = YandexSearch::enabled() && ! $presentation->hasSourceText();

        $steps = array_filter([
            'search' => $searching ? 'Ищем источники' : null,
            'brief' => 'Продумываем содержание',
            'outline' => 'Собираем слайды',
            'images' => $illustrated ? 'Рисуем иллюстрации' : null,
            'print' => 'Печатаем файл',
        ]);

        $outline = $presentation->outline;

        $current = match (true) {
            // Задача ещё ждёт воркера — ни один шаг не начат
            $presentation->status === PresentationStatus::Queued => null,
            $searching && $presentation->sources === null && blank($presentation->brief) => 'search',
            blank($presentation->brief) => 'brief',
            blank($outline['slides'] ?? null) => 'outline',
            $illustrated && Illustrator::pending($outline) => 'images',
            default => 'print',
        };

        $position = $current === null ? -1 : (int) array_search($current, array_keys($steps), true);
        $result = [];

        foreach (array_values($steps) as $i => $label) {
            $result[] = [
                'label' => $label,
                'state' => match (true) {
                    $i < $position => 'done',
                    $i === $position => 'current',
                    default => 'todo',
                },
            ];
        }

        return $result;
    }

    /** null — слайда с источниками нет, иначе — показан ли он */
    private function sourcesState(Presentation $presentation): ?bool
    {
        foreach ($presentation->outline['slides'] ?? [] as $slide) {
            if (($slide['layout'] ?? null) === 'sources') {
                return empty($slide['hidden']);
            }
        }

        return null;
    }

    /** Полные данные одной презентации */
    private function detail(Presentation $presentation): array
    {
        return [
            ...$this->summary($presentation),
            'topic' => $presentation->topic,
            'fromText' => $presentation->hasSourceText(),
            'theme' => $presentation->theme ?? config('deck.default_theme'),
            'palette' => $presentation->palette ?? config('deck.default_palette'),
            'questions' => $presentation->status === PresentationStatus::Asking
                ? $presentation->clarifications
                : null,
            'outline' => $presentation->outline,
            'isPending' => $presentation->status->isPending()
                || $presentation->status === PresentationStatus::Draft,
            'isReady' => $presentation->isReady(),
            // Бесплатная версия: просмотр с водяным знаком, без
            // скачивания, ссылки, речи и редактора
            'locked' => $presentation->isLocked(),
            // Есть ли слайд с источниками и показан ли он: от этого
            // зависит переключатель на странице презентации
            'sourcesSlide' => $this->sourcesState($presentation),
            'steps' => $this->steps($presentation),
            'error' => $presentation->error_message,
            'shareUrl' => $presentation->isReady() && ! $presentation->isLocked() ? $presentation->shareUrl() : null,
            // Живая вёрстка, а не PDF: в HTML лежат все палитры,
            // поэтому шаблон переключается прямо в превью, без запроса.
            // Метка версии — чтобы браузер не показал слайды до правок.
            'previewUrl' => $presentation->isReady()
                ? route('presentations.preview', $presentation)
                    .'?v='.($presentation->updated_at?->timestamp ?? 0)
                : null,
            'downloadUrl' => $presentation->isReady() && ! $presentation->isLocked()
                ? route('presentations.download', $presentation)
                : null,
            // Речь собирается из заметок: если модель их не заполнила,
            // кнопку показывать нечем
            'speechUrl' => $presentation->isReady() && ! $presentation->isLocked() && $presentation->hasSpeakerNotes()
                ? route('presentations.speech', $presentation)
                : null,
            'editUrl' => filled($presentation->outline['slides'] ?? null)
                ? route('presentations.edit', $presentation)
                : null,
        ];
    }

    /** Типы вёрстки с человеческими названиями — для выбора в редакторе */
    private function layouts(): array
    {
        return [
            ['key' => 'title', 'name' => 'Титульный'],
            ['key' => 'bullets', 'name' => 'Пункты'],
            ['key' => 'stats', 'name' => 'Числа'],
            ['key' => 'bignumber', 'name' => 'Крупная цифра'],
            ['key' => 'timeline', 'name' => 'Хронология'],
            ['key' => 'process', 'name' => 'Этапы'],
            ['key' => 'comparison', 'name' => 'Сравнение'],
            ['key' => 'matrix', 'name' => 'Матрица'],
            ['key' => 'quote', 'name' => 'Цитата'],
            ['key' => 'closing', 'name' => 'Финальный'],
            ['key' => 'sources', 'name' => 'Источники'],
        ];
    }
}
