<?php

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\Providers\FakeGateway;
use App\Services\Billing\Providers\YooKassaGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Всё, что касается денег: создание платежа, начисление генераций,
 * обработка ответа провайдера.
 */
class Billing
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public static function make(): self
    {
        return new self(match (config('billing.provider')) {
            'yookassa' => new YooKassaGateway,
            'fake' => new FakeGateway,
            default => throw new RuntimeException(
                'Неизвестный провайдер платежей: '.config('billing.provider')
            ),
        });
    }

    /**
     * Заводит платёж и возвращает адрес, куда отправить человека.
     */
    public function start(User $user, Package $package, string $returnUrl): string
    {
        $payment = $user->payments()->create([
            'provider' => config('billing.provider'),
            // Временный идентификатор: провайдер пришлёт свой, и мы его
            // перезапишем. Уникальность колонки требует чего-то заранее.
            'provider_payment_id' => 'pending_'.Str::uuid(),
            'amount' => $package->amount,
            'currency' => config('billing.currency'),
            'credits_granted' => $package->credits,
            'status' => PaymentStatus::Pending,
            'payload' => ['package' => $package->key],
        ]);

        return $this->gateway->checkout($payment, $returnUrl);
    }

    /**
     * Обрабатывает уведомление провайдера.
     * Возвращает true, если генерации были начислены прямо сейчас.
     */
    public function handleWebhook(array $payload, array $headers = []): bool
    {
        $event = $this->gateway->parseWebhook($payload, $headers);

        if (! $event) {
            return false;
        }

        return $this->settle($event['id'], $event['paid'], $payload);
    }

    /**
     * Начисляет генерации по оплаченному платежу.
     *
     * Внутри транзакция с блокировкой строки и проверка текущего
     * статуса: провайдеры регулярно шлют одно уведомление дважды,
     * и без этого кредиты начислились бы повторно.
     */
    public function settle(string $providerPaymentId, bool $paid, array $payload = []): bool
    {
        return DB::transaction(function () use ($providerPaymentId, $paid, $payload): bool {
            $payment = Payment::query()
                ->where('provider_payment_id', $providerPaymentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('Платёж из вебхука не найден', ['id' => $providerPaymentId]);

                return false;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                return false;
            }

            $payment->update([
                'status' => $paid ? PaymentStatus::Paid : PaymentStatus::Failed,
                'payload' => [...($payment->payload ?? []), 'webhook' => $payload],
            ]);

            if (! $paid) {
                return false;
            }

            $payment->user->increment('credits', $payment->credits_granted);

            Log::info('Генерации начислены', [
                'user' => $payment->user_id,
                'credits' => $payment->credits_granted,
            ]);

            return true;
        });
    }
}
