<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\MetrikaGoal;
use Illuminate\Auth\Events\Registered;

/**
 * Регистрация — цель Метрики «registration», ровно одна на аккаунт.
 *
 * Событие Registered бросают и форма (Fortify), и вход через VK ID
 * и Google при создании аккаунта, поэтому все способы попадают в
 * одну цель. Способ уходит параметром: в Метрике видно, сколько
 * пришло через форму, VK, Mail.ru и Google.
 *
 * Регистрировать вручную не нужно: Laravel сам находит слушателей
 * в app/Listeners по типу аргумента handle(). Добавить Event::listen
 * ещё и руками — значит получить каждую цель дважды.
 */
class TrackRegistration
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $method = match (true) {
            $user->vk_id !== null => session()->get('vkid_provider') === 'mail_ru' ? 'mail_ru' : 'vk',
            $user->google_id !== null => 'google',
            default => 'email',
        };

        MetrikaGoal::flash('registration', ['method' => $method]);
    }
}
