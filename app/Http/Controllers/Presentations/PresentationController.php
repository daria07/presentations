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
use App\Services\Deck\DeckRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresentationController extends Controller
{
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
        ]);
    }

    /** Форма новой презентации */
    public function create(Request $request): Response
    {
        return Inertia::render('presentations/Create', [
            'maxSource' => \App\Http\Requests\Presentations\StorePresentationRequest::MAX_SOURCE,
            'credits' => $request->user()->credits,
            'trialAvailable' => ! $request->user()->trial_used,
            'themes' => $this->themes(),
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
            'themes' => $this->themes(),
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
        $outcome = DB::transaction(function () use ($presentation, $user, $answers): string {
            $locked = Presentation::query()
                ->whereKey($presentation->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked->status !== PresentationStatus::Asking) {
                return 'busy';
            }

            if (! $user->spendCredit()) {
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

        $outcome = DB::transaction(function () use ($presentation, $user): string {
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

            if ($failed && ! $user->spendCredit()) {
                return 'no-credits';
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
                fn () => GeneratePresentation::dispatch($presentation->refresh()),
            ),
        };
    }

    /**
     * Смена оформления у готовой презентации.
     *
     * Структура уже куплена и лежит в базе, поэтому перепечатка
     * не стоит ни обращения к модели, ни генерации у человека.
     */
    public function theme(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->authorize('update', $presentation);

        $request->validate([
            'theme' => ['required', 'string', 'in:'.implode(',', array_keys(config('deck.themes')))],
        ]);

        if (! $presentation->isReady()) {
            return back()->with('toast', [
                'type' => 'info',
                'message' => 'Сменить оформление можно у готовой презентации.',
            ]);
        }

        $theme = $request->string('theme')->value();

        if ($theme === $presentation->theme) {
            return back();
        }

        $presentation->update([
            'theme' => $theme,
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
            ],
            'layouts' => $this->layouts(),
            'icons' => \App\Services\Deck\Icons::names(),
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

        $title = $request->string('title')->trim()->value();

        $presentation->update([
            'outline' => [
                'title' => $title,
                'subtitle' => $request->input('subtitle'),
                // Мотив в форме редактора не участвует — сохраняем прежний,
                // иначе первая же правка текста стёрла бы фон обложки
                'motif' => $presentation->outline['motif'] ?? null,
                'slides' => array_values($request->input('slides')),
            ],
            'title' => $title,
            // Пока файл не перепечатан, презентация считается
            // незавершённой: иначе на странице покажется старый PDF,
            // и человек решит, что правки не сохранились.
            'status' => PresentationStatus::Generating,
        ]);

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
    public function preview(Request $request, Presentation $presentation, DeckRenderer $renderer): \Illuminate\Http\Response
    {
        $this->authorize('view', $presentation);

        $draft = $request->input('slides');

        if (is_array($draft) && $draft !== []) {
            // Копия в памяти: до базы эти данные не доедут
            $presentation = clone $presentation;
            $presentation->outline = [
                'title' => $request->input('title') ?: $presentation->topic,
                'subtitle' => $request->input('subtitle'),
                'motif' => $presentation->outline['motif'] ?? null,
                'slides' => array_values($draft),
            ];
        }

        abort_unless(filled($presentation->outline['slides'] ?? null), 404);

        return response($renderer->html($presentation, $presentation->theme, forScreen: true))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function download(Presentation $presentation): StreamedResponse
    {
        $this->authorize('view', $presentation);

        abort_unless($presentation->isReady(), 404);

        $name = str($presentation->title ?: $presentation->topic)
            ->limit(60, '')
            ->slug()
            ->append('.pdf')
            ->value();

        return Storage::disk(config('deck.disk'))->download($presentation->file_path, $name);
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
            'slideCount' => count($presentation->outline['slides'] ?? []) ?: $presentation->slide_count,
            'createdAt' => $presentation->created_at?->toIso8601String(),
            'url' => route('presentations.show', $presentation),
        ];
    }

    /** Полные данные одной презентации */
    private function detail(Presentation $presentation): array
    {
        return [
            ...$this->summary($presentation),
            'topic' => $presentation->topic,
            'fromText' => $presentation->hasSourceText(),
            'theme' => $presentation->theme ?? config('deck.default_theme'),
            'questions' => $presentation->status === PresentationStatus::Asking
                ? $presentation->clarifications
                : null,
            'outline' => $presentation->outline,
            'isPending' => $presentation->status->isPending()
                || $presentation->status === PresentationStatus::Draft,
            'isReady' => $presentation->isReady(),
            'error' => $presentation->error_message,
            'shareUrl' => $presentation->isReady() ? $presentation->shareUrl() : null,
            'previewUrl' => $presentation->isReady()
                ? $presentation->shareUrl().'?v='.$presentation->generated_at?->timestamp
                : null,
            'downloadUrl' => $presentation->isReady()
                ? route('presentations.download', $presentation)
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
        ];
    }

    private function themes(): array
    {
        return collect(config('deck.themes'))
            ->map(fn ($theme, $key) => [
                'key' => $key,
                'name' => $theme['name'],
                'note' => $theme['note'] ?? '',
                'accent' => $theme['accent'],
                'cover' => $theme['cover_bg'],
            ])
            ->values()
            ->all();
    }
}
