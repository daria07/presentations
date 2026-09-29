<?php

namespace App\Services\Billing\Providers;

use App\Models\Payment;
use App\Services\Billing\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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
        $body = [
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
        ];

        // Состав заказа для чека передаём, только если фискализация
        // включена. Подробности — в config/billing.php
        if (config('billing.yookassa.receipts')) {
            $body['receipt'] = $this->receipt($payment);
        }

        $response = $this->request()
            // Ключ привязан к нашему платежу, а не случайный: если
            // человек нажал «Купить» дважды или запрос оборвался,
            // ЮKassa вернёт тот же платёж, а не заведёт второй.
            // Документация разрешает любое значение до 64 символов.
            ->withHeaders(['Idempotence-Key' => 'payment-'.$payment->id])
            ->post(self::ENDPOINT, $body);

        if ($response->failed()) {
            Log::error('ЮKassa: не удалось создать платёж', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Платёжный сервис недоступен. Попробуйте позже.');
        }

        $payment->update(['provider_payment_id' => $response->json('id')]);

        $url = $response->json('confirmation.confirmation_url');

        if (blank($url)) {
            // Платёж создан, но идти некуда: ЮKassa вернула его сразу
            // в финальном статусе. Это не наша ошибка формата, а редкий
            // случай — например, отказ ещё на этапе создания.
            Log::error('ЮKassa: платёж без адреса подтверждения', [
                'payment' => $payment->id,
                'status' => $response->json('status'),
            ]);

            throw new RuntimeException('Не удалось начать оплату. Попробуйте позже.');
        }

        return (string) $url;
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
     * Сейчас спит: включается настройкой billing.yookassa.receipts,
     * которая выключена, потому что 29 декабря 2025 года ЮKassa
     * закрыла выдачу чеков самозанятым. Метод оставлен готовым —
     * состав заказа собран так, как того требует их API.
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
            ->timeout(35)
            ->acceptJson()
            // По документации ЮKassa 500 не означает неудачу: результат
            // операции просто неизвестен, и его положено переспросить.
            // 429 — мы слишком частим. Оба случая лечатся повтором, а
            // 4xx повторять бессмысленно: запрос не станет правильнее.
            // Повтор безопасен: создание платежа защищено ключом
            // идемпотентности, а проверка статуса — обычный GET.
            ->retry(3, 300, function (Throwable $exception): bool {
                // Не RequestException — значит ответа не было вовсе:
                // обрыв связи или таймаут. Результат неизвестен, пробуем
                if (! $exception instanceof RequestException) {
                    return true;
                }

                $status = $exception->response->status();

                return $status === 429 || $status >= 500;
            }, throw: false);
    }
}
