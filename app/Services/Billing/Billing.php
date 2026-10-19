<?php

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Presentation;
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
    /**
     * $unlock — пробная презентация, которую открыть сразу после оплаты.
     */
    public function start(User $user, Package $package, string $returnUrl, ?Presentation $unlock = null): string
    {
        // Скидка считается здесь, на сервере, а не берётся с витрины:
        // цену из запроса подменить можно, а условие скидки — нет
        $current = Discount::current($user);
        $discount = $current['percent'] ?? 0;

        $payment = $user->payments()->create([
            'provider' => config('billing.provider'),
            // Временный идентификатор: провайдер пришлёт свой, и мы его
            // перезапишем. Уникальность колонки требует чего-то заранее.
            'provider_payment_id' => 'pending_'.Str::uuid(),
            'amount' => $discount ? Discount::apply($package->amount, $discount) : $package->amount,
            'discount_percent' => $discount,
            'currency' => config('billing.currency'),
            'credits_granted' => $package->credits,
            'status' => PaymentStatus::Pending,
            'payload' => array_filter([
                'package' => $package->key,
                'unlock' => $unlock?->id,
                // Какой скидкой платят: после оплаты гасим именно её
                'discount' => $current['source'] ?? null,
            ]),
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

        return $this->settle(
            $event['id'],
            $event['paid'],
            $payload,
            $event['amount'] ?? null,
        );
    }

    /**
     * Начисляет генерации по оплаченному платежу.
     *
     * Внутри транзакция с блокировкой строки и проверка текущего
     * статуса: провайдеры регулярно шлют одно уведомление дважды,
     * и без этого кредиты начислились бы повторно.
     *
     * $paidAmount — сколько, по данным провайдера, действительно
     * принято, в копейках. Если не совпадает с суммой платежа,
     * генерации не начисляются: расхождение значит, что платёж
     * подменили или мы смотрим не на тот заказ.
     */
    public function settle(
        string $providerPaymentId,
        bool $paid,
        array $payload = [],
        ?int $paidAmount = null,
    ): bool {
        return DB::transaction(function () use ($providerPaymentId, $paid, $payload, $paidAmount): bool {
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

            if ($paidAmount !== null && $paidAmount !== $payment->amount) {
                Log::error('Сумма платежа не совпала с ожидаемой', [
                    'payment' => $payment->id,
                    'ожидали' => $payment->amount,
                    'пришло' => $paidAmount,
                ]);

                $payment->update(['status' => PaymentStatus::Failed]);

                return false;
            }

            $payment->user->increment('credits', $payment->credits_granted);

            // Скидка — на одну покупку: оплатили со скидкой, значит
            // она использована, и кнопка больше не появится
            if ($payment->discount_percent > 0) {
                $source = $payment->payload['discount'] ?? 'button';
                $field = $source === 'update' ? 'update_offer_used_at' : 'discount_used_at';

                if ($payment->user->{$field} === null) {
                    $payment->user->forceFill([$field => now()])->save();
                }
            }

            Log::info('Генерации начислены', [
                'user' => $payment->user_id,
                'credits' => $payment->credits_granted,
            ]);

            // Платили, чтобы открыть пробную презентацию, — открываем
            // сразу, без второго нажатия. Только свою и только закрытую
            $unlock = $payment->payload['unlock'] ?? null;
            $presentation = is_int($unlock)
                ? Presentation::query()->where('user_id', $payment->user_id)->find($unlock)
                : null;

            if ($presentation !== null) {
                UnlockPresentation::handle($presentation);
            }

            return true;
        });
    }
}
