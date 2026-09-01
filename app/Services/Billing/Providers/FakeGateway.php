<?php

namespace App\Services\Billing\Providers;

use App\Models\Payment;
use App\Services\Billing\PaymentGateway;

/**
 * Заглушка для разработки: вместо ухода на сайт банка человек
 * попадает на внутреннюю страницу, где может «оплатить» или
 * «отменить». Позволяет прогонять весь сценарий целиком,
 * включая вебхук, не подключая настоящий эквайринг.
 */
class FakeGateway implements PaymentGateway
{
    public function checkout(Payment $payment, string $returnUrl): string
    {
        return route('billing.sandbox', $payment);
    }

    public function parseWebhook(array $payload, array $headers): ?array
    {
        if (blank($payload['id'] ?? null)) {
            return null;
        }

        return [
            'id' => (string) $payload['id'],
            'paid' => ($payload['status'] ?? null) === 'succeeded',
        ];
    }
}
