<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Сообщение в чате с администратором.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $from_admin
 * @property string $body
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'from_admin', 'body', 'read_at'])]
class SupportMessage extends Model
{
    /** Сколько знаков можно в одном сообщении */
    public const MAX_LENGTH = 2000;

    protected function casts(): array
    {
        return [
            'from_admin' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Непрочитанные администратором: сообщения людей без read_at.
     *
     * @param  Builder<SupportMessage>  $query
     */
    public function scopeUnreadByAdmin(Builder $query): void
    {
        $query->where('from_admin', false)->whereNull('read_at');
    }

    /**
     * Непрочитанные человеком: ответы администратора без read_at.
     *
     * @param  Builder<SupportMessage>  $query
     */
    public function scopeUnreadByUser(Builder $query): void
    {
        $query->where('from_admin', true)->whereNull('read_at');
    }

    /** @return array{id: int, fromAdmin: bool, body: string, createdAt: string|null} */
    public function toChat(): array
    {
        return [
            'id' => $this->id,
            // (bool): у только что созданной модели без явного значения поле
            // ещё null — база подставит false, а объект об этом не знает
            'fromAdmin' => (bool) $this->from_admin,
            'body' => $this->body,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
