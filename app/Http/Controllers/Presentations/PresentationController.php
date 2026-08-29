<?php

namespace App\Http\Controllers\Presentations;

use App\Enums\PresentationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Presentations\AnswerQuestionsRequest;
use App\Http\Requests\Presentations\StorePresentationRequest;
use App\Jobs\GeneratePresentation;
use App\Jobs\PrepareQuestions;
use App\Models\Presentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresentationController extends Controller
{
    /** Список презентаций в кабинете */
    public function index(Request $request): Response
    {
        return Inertia::render('presentations/Index', [
            'presentations' => $request->user()
                ->presentations()
                ->paginate(12)
                ->through(fn (Presentation $p) => $this->summary($p)),
            'credits' => $request->user()->credits,
            'trialAvailable' => ! $request->user()->trial_used,
        ]);
    }

    /** Форма новой презентации */
    public function create(Request $request): Response
    {
        return Inertia::render('presentations/Create', [
            'credits' => $request->user()->credits,
            'trialAvailable' => ! $request->user()->trial_used,
            'themes' => $this->themes(),
        ]);
    }

    /** Создаём черновик и уходим спрашивать уточнения */
    public function store(StorePresentationRequest $request): RedirectResponse
    {
        $presentation = $request->user()->presentations()->create([
            'topic' => $request->string('topic')->trim(),
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

        if ($presentation->status !== PresentationStatus::Asking) {
            return back()->with('toast', [
                'type' => 'info',
                'message' => 'Эта презентация уже в работе.',
            ]);
        }

        $user = $request->user();

        if (! $user->spendCredit()) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Генерации закончились — пополните счёт, чтобы продолжить.',
            ]);
        }

        $answers = $request->collect('answers');

        $clarifications = collect($presentation->clarifications ?? [])
            ->map(fn (array $item, int $i) => [
                ...$item,
                'answer' => $answers->get($item['key'] ?? $i) ?? $answers->get((string) $i),
            ])
            ->all();

        $presentation->update([
            'clarifications' => $clarifications,
            'status' => PresentationStatus::Queued,
            'error_message' => null,
        ]);

        GeneratePresentation::dispatch($presentation, $request->input('theme'));

        return to_route('presentations.show', $presentation);
    }

    /** Повторная попытка после сбоя */
    public function retry(Request $request, Presentation $presentation): RedirectResponse
    {
        $this->authorize('update', $presentation);

        if ($presentation->status !== PresentationStatus::Failed) {
            return back()->with('toast', [
                'type' => 'info',
                'message' => 'Повторять нечего — презентация в состоянии «'
                    .$presentation->status->label().'».',
            ]);
        }

        if (! $request->user()->spendCredit()) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Генерации закончились — пополните счёт, чтобы продолжить.',
            ]);
        }

        $presentation->update([
            'status' => PresentationStatus::Queued,
            'error_message' => null,
        ]);

        GeneratePresentation::dispatch($presentation);

        return to_route('presentations.show', $presentation);
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

        if ($presentation->file_path) {
            Storage::disk(config('deck.disk'))->delete($presentation->file_path);
        }

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
            'questions' => $presentation->status === PresentationStatus::Asking
                ? $presentation->clarifications
                : null,
            'outline' => $presentation->outline,
            'isPending' => $presentation->status->isPending()
                || $presentation->status === PresentationStatus::Draft,
            'isReady' => $presentation->isReady(),
            'error' => $presentation->error_message,
            'shareUrl' => $presentation->isReady() ? $presentation->shareUrl() : null,
            'downloadUrl' => $presentation->isReady()
                ? route('presentations.download', $presentation)
                : null,
        ];
    }

    private function themes(): array
    {
        return collect(config('deck.themes'))
            ->map(fn ($theme, $key) => [
                'key' => $key,
                'name' => $theme['name'],
                'accent' => $theme['accent'],
                'cover' => $theme['cover_bg'],
            ])
            ->values()
            ->all();
    }
}
