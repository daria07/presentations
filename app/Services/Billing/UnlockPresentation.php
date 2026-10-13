<?php

namespace App\Services\Billing;

use App\Enums\PresentationStatus;
use App\Jobs\RenderPresentation;
use App\Models\Presentation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Открывает полную версию пробной презентации за одну купленную
 * генерацию: без водяного знака, со скачиванием, ссылкой и речью.
 *
 * Вызывается двумя путями: кнопкой на странице презентации и сразу
 * после оплаты, если человек пришёл к тарифам с закрытой презентации, —
 * чтобы после оплаты не нажимать ещё одну кнопку.
 */
class UnlockPresentation
{
    /**
     * Возвращает true, если презентация открыта сейчас. Уже открытая,
     * чужая или при нулевом балансе — false, и ничего не списывается.
     */
    public static function handle(Presentation $presentation): bool
    {
        return DB::transaction(function () use ($presentation): bool {
            $user = User::query()->whereKey($presentation->user_id)->lockForUpdate()->first();
            $fresh = Presentation::query()->whereKey($presentation->getKey())->lockForUpdate()->first();

            if ($user === null || $fresh === null || ! $fresh->isLocked() || $user->credits < 1) {
                return false;
            }

            $user->decrement('credits');
            $fresh->update(['unlocked_at' => now()]);

            // Файл перепечатываем без водяного знака. После коммита:
            // иначе воркер мог бы успеть взять задачу раньше, чем
            // отметка об открытии станет видна, и напечатать знак снова
            if ($fresh->isReady()) {
                $fresh->update(['status' => PresentationStatus::Generating]);
                RenderPresentation::dispatch($fresh)->afterCommit();
            }

            $presentation->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }
}
