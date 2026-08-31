<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property int $credits
 * @property bool $trial_used
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'trial_used' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Внешний ключ снёс бы презентации каскадом на уровне базы,
        // в обход событий модели — и PDF остались бы на диске.
        static::deleting(function (User $user) {
            $user->presentations()->cursor()->each->delete();
        });
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function apiCalls(): HasMany
    {
        return $this->hasMany(ApiCall::class);
    }

    public function hasCredits(): bool
    {
        return $this->credits > 0 || ! $this->trial_used;
    }

    /**
     * Списывает одну генерацию. Первая — за счёт пробного доступа.
     * Возвращает false, если списывать нечего.
     *
     * Всё внутри транзакции с блокировкой строки: без неё два
     * одновременных запроса успевают оба пройти проверку остатка
     * и списать по кредиту с одного и того же баланса.
     */
    public function spendCredit(): bool
    {
        $spent = DB::transaction(function (): bool {
            $locked = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return false;
            }

            if (! $locked->trial_used) {
                $locked->forceFill(['trial_used' => true])->save();

                return true;
            }

            if ($locked->credits < 1) {
                return false;
            }

            $locked->decrement('credits');

            return true;
        });

        if ($spent) {
            $this->refresh();
        }

        return $spent;
    }

    /** Возврат кредита, если генерация упала по нашей вине */
    public function refundCredit(): void
    {
        $this->increment('credits');
    }
}
