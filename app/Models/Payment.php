<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
 * @property string|null $receipt_url
 * @property string|null $receipt_path
 * @property Carbon|null $receipt_added_at
 */
#[Fillable([
    'user_id', 'provider', 'provider_payment_id',
    'amount', 'currency', 'credits_granted', 'status', 'payload',
    'receipt_url', 'receipt_path', 'receipt_added_at',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'receipt_added_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /** Чек выдан: либо ссылкой на «Мой налог», либо файлом */
    public function hasReceipt(): bool
    {
        return filled($this->receipt_url) || filled($this->receipt_path);
    }

    /** @return BelongsTo<User, $this> */
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
