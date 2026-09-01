<?php

namespace App\Services\Billing;

use InvalidArgumentException;

/**
 * Пакет генераций. Читается из конфига, чтобы цены менялись
 * без правки кода и не расползались по проекту.
 */
readonly class Package
{
    public function __construct(
        public string $key,
        public string $title,
        public int $credits,
        public int $amount,      // в копейках
        public string $note,
        public bool $popular,
    ) {}

    public static function find(string $key): self
    {
        $all = config('billing.packages');

        if (! isset($all[$key])) {
            throw new InvalidArgumentException("Неизвестный пакет: {$key}");
        }

        return self::fromConfig($key, $all[$key]);
    }

    /** @return array<int, self> */
    public static function all(): array
    {
        return collect(config('billing.packages'))
            ->map(fn (array $data, string $key) => self::fromConfig($key, $data))
            ->values()
            ->all();
    }

    private static function fromConfig(string $key, array $data): self
    {
        return new self(
            key: $key,
            title: $data['title'],
            credits: $data['credits'],
            amount: $data['amount'],
            note: $data['note'] ?? '',
            popular: $data['popular'] ?? false,
        );
    }

    /** Цена за одну генерацию — для сравнения пакетов между собой */
    public function pricePerCredit(): int
    {
        return (int) round($this->amount / $this->credits);
    }

    public function amountForHumans(): string
    {
        return number_format($this->amount / 100, 0, ',', ' ');
    }
}
