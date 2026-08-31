<?php

namespace App\Jobs;

use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Services\Claude\ClaudeException;
use App\Services\Claude\PresentationPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Спрашивает у модели уточняющие вопросы по теме.
 * Кредит здесь не списывается — человек ещё не получил результат,
 * но возможность заплатить проверена в контроллере до постановки.
 */
class PrepareQuestions implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public Presentation $presentation) {}

    public function handle(PresentationPlanner $planner): void
    {
        try {
            $questions = $planner->askClarifyingQuestions($this->presentation);
        } catch (ClaudeException $e) {
            $this->stopUnlessRetryable($e);

            return;
        }

        $this->presentation->update([
            'clarifications' => array_map(fn ($q) => [
                'key' => $q['key'] ?? null,
                'question' => $q['question'] ?? '',
                'options' => $q['options'] ?? [],
                'answer' => null,
            ], $questions),
            'status' => PresentationStatus::Asking,
        ]);
    }

    /**
     * Повторять есть смысл только временные сбои. Кривой запрос или
     * пустой баланс от повтора не починятся, а расходы удвоят.
     */
    protected function stopUnlessRetryable(ClaudeException $e): void
    {
        if ($e->isRetryable()) {
            throw $e;
        }

        $this->fail($e);
    }

    public function failed(Throwable $e): void
    {
        $this->presentation->markFailed(match (true) {
            $e instanceof ClaudeException => $e->forUser(),
            config('app.debug') => class_basename($e).': '.$e->getMessage(),
            default => 'Не получилось подготовить вопросы. Попробуйте ещё раз.',
        });
    }
}
