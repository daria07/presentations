<?php

namespace App\Services\Billing\Providers;

use App\Models\Payment;
use App\Services\Billing\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ЮKassa. Работает и с самозанятыми.
 *
 * Идемпотентность обеспечена дважды: заголовком Idempotence-Key при
 * создании платежа и уникальным provider_payment_id в базе при
 * начислении. Повторный вебхук не удвоит кредиты.
 */
class YooKassaGateway implements PaymentGateway
{
    private const ENDPOINT = 'https://api.yookassa.ru/v3/payments';

    public function checkout(Payment $payment, string $returnUrl): string
    {
        $response = $this->request()
            ->withHeaders(['Idempotence-Key' => (string) $payment->id])
            ->post(self::ENDPOINT, [
                'amount' => [
                    'value' => number_format($payment->amount / 100, 2, '.', ''),
                    'currency' => $payment->currency,
                ],
                'capture' => true,
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $returnUrl,
                ],
                'description' => "Пакет генераций: {$payment->credits_granted} шт.",
                'metadata' => ['payment_id' => $payment->id],
            ]);

        if ($response->failed()) {
            Log::error('ЮKassa: не удалось создать платёж', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Платёжный сервис недоступен. Попробуйте позже.');
        }

        $payment->update(['provider_payment_id' => $response->json('id')]);

        return $response->json('confirmation.confirmation_url');
    }

    public function parseWebhook(array $payload, array $headers): ?array
    {
        $object = $payload['object'] ?? [];

        if (blank($object['id'] ?? null)) {
            return null;
        }

        return [
            'id' => (string) $object['id'],
            'paid' => ($object['status'] ?? null) === 'succeeded',
        ];
    }

    private function request()
    {
        $config = config('billing.yookassa');

        if (blank($config['shop_id']) || blank($config['secret_key'])) {
            throw new RuntimeException('В .env не заданы YOOKASSA_SHOP_ID и YOOKASSA_SECRET_KEY.');
        }

        return Http::withBasicAuth($config['shop_id'], $config['secret_key'])
            ->timeout(30)
            ->acceptJson();
    }
}
