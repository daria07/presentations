<?php

namespace App\Services\Billing;

use App\Models\Payment;

/**
 * Контракт платёжного провайдера.
 *
 * Всё остальное приложение знает только про этот интерфейс, поэтому
 * смена ЮKassa на что-то другое не заденет ни начисление кредитов,
 * ни историю платежей, ни интерфейс.
 */
interface PaymentGateway
{
    /**
     * Создаёт платёж на стороне провайдера и возвращает адрес,
     * куда отправить человека платить.
     */
    public function checkout(Payment $payment, string $returnUrl): string;

    /**
     * Разбирает входящий вебхук. Возвращает идентификатор платежа
     * у провайдера и признак успеха, либо null, если сообщение
     * не про оплату или подпись не сошлась.
     *
     * @return array{id: string, paid: bool}|null
     */
    public function parseWebhook(array $payload, array $headers): ?array;
}
