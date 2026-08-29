<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Учёт каждого обращения к Claude — чтобы знать реальную
 * себестоимость одной презентации.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $presentation_id
 * @property string $purpose
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cached_tokens
 * @property int $cost
 */
#[Fillable([
    'user_id', 'presentation_id', 'purpose', 'model',
    'input_tokens', 'output_tokens', 'cached_tokens', 'cost',
])]
class ApiCall extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    /** Стоимость в долларах, для отчётов */
    public function costInDollars(): float
    {
        return round($this->cost / 10000, 4);
    }
}
