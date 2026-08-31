<?php

namespace App\Services\Claude;

use RuntimeException;

class ClaudeException extends RuntimeException
{
    /**
     * Сообщение, которое не стыдно показать пользователю.
     * Код — это HTTP-статус ответа шлюза, если ошибка пришла оттуда.
     */
    public function forUser(): string
    {
        return match (true) {
            $this->getCode() === 401 => 'Сервис генерации временно недоступен. Мы уже разбираемся.',
            $this->getCode() === 402 => 'Сервис генерации временно недоступен. Мы уже разбираемся.',
            $this->getCode() === 429 => 'Сейчас слишком много запросов. Попробуйте через минуту.',
            $this->getCode() >= 500 => 'На стороне модели сбой. Попробуйте ещё раз через минуту.',
            default => 'Не получилось связаться с моделью. Попробуйте ещё раз через минуту.',
        };
    }

    /** Стоит ли вообще повторять — или повтор только сожжёт деньги */
    public function isRetryable(): bool
    {
        return $this->getCode() === 429 || $this->getCode() >= 500 || $this->getCode() === 0;
    }
}
