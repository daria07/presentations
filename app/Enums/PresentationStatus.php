<?php

namespace App\Enums;

enum PresentationStatus: string
{
    /** Пользователь только ввёл тему */
    case Draft = 'draft';
    /** Claude задал уточняющие вопросы, ждём ответов */
    case Asking = 'asking';
    /** Задача поставлена в очередь */
    case Queued = 'queued';
    /** Воркер генерирует файл */
    case Generating = 'generating';
    /** Файл готов */
    case Ready = 'ready';
    /** Что-то пошло не так */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Asking => 'Уточняем детали',
            self::Queued => 'В очереди',
            self::Generating => 'Генерируем',
            self::Ready => 'Готово',
            self::Failed => 'Ошибка',
        };
    }

    /** Статусы, при которых работа ещё идёт — фронт продолжает опрашивать */
    public function isPending(): bool
    {
        return in_array($this, [self::Queued, self::Generating], true);
    }
}
