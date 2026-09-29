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
        public bool $test = false,
    ) {}

    public static function find(string $key): self
    {
        $all = config('billing.packages');

        if (! isset($all[$key])) {
            throw new InvalidArgumentException("Неизвестный пакет: {$key}");
        }

        return self::fromConfig($key, $all[$key]);
    }

    /**
     * Пакеты, доступные этому человеку.
     *
     * Служебный пакет за 10 ₽ видят только почты из
     * billing.test_emails — остальным его не существует ни на
     * витрине, ни при оплате.
     *
     * @return array<int, self>
     */
    public static function all(?string $email = null): array
    {
        $testers = config('billing.test_emails', []);

        // Звёздочка — всем, кто вошёл в аккаунт. Так удобно гонять
        // оплату с чужого аккаунта, пока трафика нет; убрать — значит
        // стереть звёздочку из BILLING_TEST_EMAILS
        $allowed = in_array('*', $testers, true)
            || ($email !== null && in_array(mb_strtolower($email), $testers, true));

        return collect(config('billing.packages'))
            ->map(fn (array $data, string $key) => self::fromConfig($key, $data))
            ->reject(fn (self $p) => $p->test && ! $allowed)
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
            test: $data['test'] ?? false,
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
