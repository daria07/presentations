<?php

namespace App\Models;

use App\Enums\PresentationStatus;
use App\Services\Deck\DeckRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string $topic
 * @property string|null $source_text
 * @property int $slide_count
 * @property array|null $clarifications
 * @property array<string, mixed>|null $brief
 * @property array|null $outline
 * @property PresentationStatus $status
 * @property string|null $file_path
 * @property string|null $file_format
 * @property string|null $theme
 * @property string $share_token
 * @property string|null $error_message
 * @property list<array{id: int, url: string, title: string, domain: string, text: string}>|null $sources
 * @property int|null $rating
 * @property string|null $review
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $generated_at
 */
#[Fillable([
    'user_id', 'title', 'topic', 'source_text', 'slide_count',
    'clarifications', 'brief', 'sources', 'outline', 'status',
    'file_path', 'file_format', 'theme', 'palette', 'generated_at', 'error_message',
    'rating', 'review', 'reviewed_at',
])]
class Presentation extends Model
{
    protected function casts(): array
    {
        return [
            'clarifications' => 'array',
            'brief' => 'array',
            'sources' => 'array',
            'outline' => 'array',
            'status' => PresentationStatus::class,
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Presentation $presentation) {
            $presentation->share_token ??= Str::lower(Str::random(12));
        });

        // Удаление записи забирает с собой файл — где бы удаление
        // ни случилось: в контроллере, в консоли или каскадом.
        static::deleting(function (Presentation $presentation) {
            $presentation->deleteFile();
        });
    }

    /** Есть ли хоть одна заполненная заметка докладчика */
    public function hasSpeakerNotes(): bool
    {
        foreach ($this->outline['slides'] ?? [] as $slide) {
            if (trim((string) ($slide['notes'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Удаляет картинки, на которые больше не ссылается ни один слайд:
     * человек убрал их в редакторе. Без этого файлы копились бы на
     * диске до удаления всей презентации.
     */
    public function pruneImages(): void
    {
        $disk = Storage::disk(config('deck.disk'));
        $used = [];

        foreach ($this->outline['slides'] ?? [] as $slide) {
            if (is_array($slide) && is_array($slide['image'] ?? null) && filled($slide['image']['path'] ?? null)) {
                $used[] = $slide['image']['path'];
            }
        }

        foreach ($disk->files(DeckRenderer::imageDir($this)) as $file) {
            if (! in_array($file, $used, true)) {
                $disk->delete($file);
            }
        }
    }

    /** Убирает готовый PDF с диска, если он там есть */
    public function deleteFile(): void
    {
        $disk = Storage::disk(config('deck.disk'));

        // Картинки слайдов лежат отдельной папкой — уходят вместе с файлом
        $disk->deleteDirectory(DeckRenderer::imageDir($this));

        if (blank($this->file_path)) {
            return;
        }

        $disk->delete($this->file_path);
    }

    /** @return BelongsTo<User, $this> */
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
        // По ссылке открывается сам PDF во встроенной смотрелке браузера.
        // Без подсказки Chrome ставит масштаб по ширине, и широкий слайд
        // 16:9 не влезает в окно по высоте. view=Fit — страница целиком.
        // Сервер этот хвост не видит, его читает только смотрелка
        return url('/p/'.$this->share_token).'#view=Fit';
    }

    /** Собираем по готовому тексту, а не по одной теме */
    public function hasSourceText(): bool
    {
        return filled($this->source_text);
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
