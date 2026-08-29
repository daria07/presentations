<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_payment_id
 * @property int $amount
 * @property string $currency
 * @property int $credits_granted
 * @property PaymentStatus $status
 * @property array|null $payload
 */
#[Fillable([
    'user_id', 'provider', 'provider_payment_id',
    'amount', 'currency', 'credits_granted', 'status', 'payload',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Сумма в рублях/долларах для показа пользователю */
    public function amountForHumans(): string
    {
        return number_format($this->amount / 100, 2, ',', ' ');
    }
}
