<?php

namespace App\Services\Billing\Providers;

use App\Models\Payment;
use App\Services\Billing\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
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
                    'value' => $this->money($payment->amount),
                    'currency' => $payment->currency,
                ],
                'capture' => true,
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $returnUrl,
                ],
                'description' => "Пакет генераций: {$payment->credits_granted} шт.",
                'metadata' => ['payment_id' => $payment->id],
                'receipt' => $this->receipt($payment),
            ]);

        if ($response->failed()) {
            Log::error('ЮKassa: не удалось создать платёж', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Платёжный сервис недоступен. Попробуйте позже.');
        }

        $payment->update(['provider_payment_id' => $response->json('id')]);

        return (string) $response->json('confirmation.confirmation_url');
    }

    /**
     * Разбирает уведомление — и НЕ верит ему.
     *
     * Адрес вебхука открыт всему интернету: без входа в аккаунт и без
     * проверки CSRF, иначе ЮKassa до него не достучится. Если верить
     * телу запроса, любой может завести платёж, подсмотреть его
     * идентификатор, не заплатить и прислать сюда «succeeded».
     * Идемпотентность от этого не спасает: она защищает от повторного
     * начисления, а не от поддельного.
     *
     * Поэтому уведомление здесь — только сигнал «сходи проверь».
     * Статус и сумму берём из ответа API ЮKassa на наш собственный
     * запрос, с нашим же ключом.
     *
     * @return array{id: string, paid: bool, amount: int, currency: string}|null
     */
    public function parseWebhook(array $payload, array $headers): ?array
    {
        $id = $payload['object']['id'] ?? null;

        if (blank($id) || ! is_string($id)) {
            return null;
        }

        $response = $this->request()->get(self::ENDPOINT.'/'.urlencode($id));

        if ($response->status() === 404) {
            Log::warning('ЮKassa: в уведомлении платёж, которого у провайдера нет', [
                'id' => $id,
            ]);

            return null;
        }

        if ($response->failed()) {
            // Не молчим: пусть контроллер ответит ошибкой, а ЮKassa
            // повторит уведомление позже. Если проглотить, платёж
            // навсегда останется в состоянии «ожидает».
            throw new RuntimeException(
                'ЮKassa: не удалось проверить платёж '.$id.', код '.$response->status()
            );
        }

        $status = $response->json('status');

        // pending и waiting_for_capture — платёж ещё в пути, решать рано
        if (! in_array($status, ['succeeded', 'canceled'], true)) {
            return null;
        }

        return [
            'id' => (string) $response->json('id'),
            'paid' => $status === 'succeeded',
            'amount' => (int) round(((float) $response->json('amount.value')) * 100),
            'currency' => (string) $response->json('amount.currency'),
        ];
    }

    /**
     * Чек для покупателя.
     *
     * В оферте обещано, что чек придёт на почту, а самозанятый обязан
     * выдать его по закону. ЮKassa делает это сама, но только если
     * состав заказа передан здесь и в личном кабинете включена выдача
     * чеков НПД.
     *
     * @return array<string, mixed>
     */
    private function receipt(Payment $payment): array
    {
        return [
            'customer' => ['email' => $payment->user->email],
            'items' => [[
                'description' => "Генерации презентаций, {$payment->credits_granted} шт.",
                'quantity' => '1.00',
                'amount' => [
                    'value' => $this->money($payment->amount),
                    'currency' => $payment->currency,
                ],
                // 1 — без НДС: самозанятый его не платит
                'vat_code' => 1,
                'payment_subject' => 'service',
                'payment_mode' => 'full_payment',
            ]],
        ];
    }

    /** Копейки в строку вида «199.00», как того требует API */
    private function money(int $kopecks): string
    {
        return number_format($kopecks / 100, 2, '.', '');
    }

    private function request(): PendingRequest
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
