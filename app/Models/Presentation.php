<?php

namespace App\Models;

use App\Enums\PresentationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string $topic
 * @property int $slide_count
 * @property array|null $clarifications
 * @property array|null $outline
 * @property PresentationStatus $status
 * @property string|null $file_path
 * @property string|null $file_format
 * @property string $share_token
 * @property string|null $error_message
 * @property Carbon|null $generated_at
 */
#[Fillable([
    'user_id', 'title', 'topic', 'slide_count',
    'clarifications', 'outline', 'status',
    'file_path', 'file_format', 'generated_at', 'error_message',
])]
class Presentation extends Model
{
    protected function casts(): array
    {
        return [
            'clarifications' => 'array',
            'outline' => 'array',
            'status' => PresentationStatus::class,
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Presentation $presentation) {
            $presentation->share_token ??= Str::lower(Str::random(12));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiCalls(): HasMany
    {
        return $this->hasMany(ApiCall::class);
    }

    /** Публичная ссылка вида /p/a7f3k9x2 */
    public function shareUrl(): string
    {
        return url('/p/'.$this->share_token);
    }

    public function isReady(): bool
    {
        return $this->status === PresentationStatus::Ready && $this->file_path !== null;
    }

    /** Сколько всего потрачено на эту презентацию, в сотых цента */
    public function totalCost(): int
    {
        return (int) $this->apiCalls()->sum('cost');
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => PresentationStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
