<?php

namespace App\Services\Claude;

use RuntimeException;

class ClaudeException extends RuntimeException
{
    /** Сообщение, которое не стыдно показать пользователю */
    public function forUser(): string
    {
        return 'Не получилось связаться с моделью. Попробуйте ещё раз через минуту.';
    }
}
