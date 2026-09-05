<?php

namespace App\Services\Claude;

use RuntimeException;

class ClaudeException extends RuntimeException
{
    /**
     * Осмысленная для пользователя ошибка — не сбой связи, а содержательный
     * тупик: модель не смогла собрать структуру по этой теме. Повторять
     * такое бессмысленно, а текст можно показывать как есть.
     */
    public static function content(string $message): self
    {
        $e = new self($message);
        $e->contentIssue = true;

        return $e;
    }

    private bool $contentIssue = false;

    /**
     * Сообщение, которое не стыдно показать пользователю.
     * Код — это HTTP-статус ответа шлюза, если ошибка пришла оттуда.
     */
    public function forUser(): string
    {
        if ($this->contentIssue) {
            return $this->getMessage();
        }

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
        // Повтор чинит обрыв связи и перегрузку, но не содержательный тупик:
        // на ту же тему модель ответит так же, только за новые деньги
        if ($this->contentIssue) {
            return false;
        }

        return $this->getCode() === 429 || $this->getCode() >= 500 || $this->getCode() === 0;
    }
}
