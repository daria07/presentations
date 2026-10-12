<?php

namespace App\Jobs\Concerns;

use App\Enums\PresentationStatus;
use App\Models\Presentation;
use App\Notifications\PresentationReady;
use App\Services\Claude\ClaudeException;
use App\Services\Deck\DeckRenderer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Конец генерации: печать PDF и уведомление — или возврат списанного,
 * если упали. Общее для GeneratePresentation и IllustratePresentation:
 * генерация заканчивается в той из них, что работала последней.
 */
trait FinishesGeneration
{
    protected function finish(Presentation $presentation, DeckRenderer $renderer): void
    {
        $path = $renderer->pdf($presentation, $presentation->theme, $presentation->palette);

        $presentation->update([
            'file_path' => $path,
            'file_format' => 'pdf',
            'status' => PresentationStatus::Ready,
            'generated_at' => now(),
            'error_message' => null,
        ]);

        // Письмо уходит отдельной задачей: сбой почты не должен
        // отменять успешную генерацию.
        $presentation->user->notify(new PresentationReady($presentation->refresh()));
    }

    protected function failGeneration(Presentation $presentation, ?string $spent, Throwable $e): void
    {
        Log::error('Генерация упала', [
            'presentation' => $presentation->id,
            'job' => class_basename($this),
            'error' => $e->getMessage(),
        ]);

        // Человек не виноват, что у нас не получилось — возвращаем
        // ровно то, что списали
        $presentation->user->refundCredit($spent);

        $returned = $spent === null
            ? ''
            : ' '.($spent === 'trial'
                ? 'Пробную генерацию вернули.'
                : 'Кредит вернули на счёт.');

        $presentation->markFailed(match (true) {
            $e instanceof ClaudeException => $e->forUser(),
            config('app.debug') => class_basename($e).': '.$e->getMessage(),
            default => 'Не получилось собрать презентацию.'.$returned,
        });
    }
}
