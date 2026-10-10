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
 * @property string|null $promo_variant
 * @property Carbon|null $promo_shown_at
 * @property Carbon|null $promo_clicked_at
 * @property Carbon|null $discount_until
 * @property Carbon|null $discount_used_at
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
            'promo_shown_at' => 'datetime',
            'promo_clicked_at' => 'datetime',
            'discount_until' => 'datetime',
            'discount_used_at' => 'datetime',
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

    /**
     * Права администратора живут в ADMIN_EMAILS, а не в базе:
     * колонку можно выставить миграцией, сидом или дырой в приложении,
     * а окружение на сервере меняют руками.
     */
    public function isAdmin(): bool
    {
        return in_array(mb_strtolower($this->email), config('admin.emails', []), true);
    }

    /** @return HasMany<Presentation, $this> */
    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class)->latest();
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<ApiCall, $this> */
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
     *
     * Возвращает, чем именно заплатили: 'trial' — пробной генерацией,
     * 'credit' — кредитом с баланса, null — платить было нечем.
     * Это важно знать для возврата: пробная генерация не стоила денег,
     * и возвращать за неё кредит нельзя.
     *
     * Всё внутри транзакции с блокировкой строки: без неё два
     * одновременных запроса успевают оба пройти проверку остатка
     * и списать по кредиту с одного и того же баланса.
     */
    public function spendCredit(): ?string
    {
        $spent = DB::transaction(function (): ?string {
            $locked = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return null;
            }

            if (! $locked->trial_used) {
                $locked->forceFill(['trial_used' => true])->save();

                return 'trial';
            }

            if ($locked->credits < 1) {
                return null;
            }

            $locked->decrement('credits');

            return 'credit';
        });

        if ($spent) {
            $this->refresh();
        }

        return $spent;
    }

    /**
     * Возврат, если генерация упала по нашей вине.
     *
     * Возвращаем ровно то, что списали. Раньше здесь всегда
     * прибавлялся кредит — и человек, у которого сгорела пробная
     * генерация, получал взамен настоящий кредит. Несколько сбоев
     * подряд — и на балансе три-четыре генерации без единой оплаты.
     *
     * null означает, что не списывали ничего (перезапуск застрявшей
     * задачи) — тогда и возвращать нечего.
     */
    public function refundCredit(?string $spent): void
    {
        match ($spent) {
            'trial' => $this->forceFill(['trial_used' => false])->save(),
            'credit' => $this->increment('credits'),
            default => null,
        };
    }
}
