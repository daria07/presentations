<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Цели Метрики, которые знает только сервер.
 *
 * Регистрацию раньше считали отправкой формы — и получали дубли:
 * форма уходит и с ошибкой («пароль слишком короткий»), и дважды при
 * двойном щелчке, а вход через VK и Google формы не отправляет вовсе.
 * Настоящий факт регистрации знает только сервер: он кладёт цель во
 * флеш сессии, следующая страница отдаёт её в props, браузер сообщает
 * Метрике один раз (resources/js/layouts/AppLayout.vue).
 *
 * У каждой цели свой id: обновит человек страницу или вернётся
 * назад — повторно она не уйдёт.
 */
final class MetrikaGoal
{
    public const SESSION = 'metrika_goals';

    /** @param  array<string, scalar>  $params */
    public static function flash(string $name, array $params = []): void
    {
        $goals = (array) session()->get(self::SESSION, []);
        $goals[] = ['id' => (string) Str::uuid(), 'name' => $name, 'params' => $params];

        session()->flash(self::SESSION, $goals);
    }
}
