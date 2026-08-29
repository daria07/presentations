<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Начислить генерации вручную — для тестов и поддержки:
 *   php artisan credits:grant d.ilina@ffintech.com 20
 *   php artisan credits:grant --all 5
 */
class GrantCredits extends Command
{
    protected $signature = 'credits:grant
                            {email? : Кому начислить}
                            {amount=10 : Сколько генераций}
                            {--all : Всем пользователям}
                            {--reset-trial : Заодно вернуть бесплатную первую генерацию}';

    protected $description = 'Начисляет генерации пользователю';

    public function handle(): int
    {
        $amount = (int) $this->argument('amount');

        $users = $this->option('all')
            ? User::all()
            : User::where('email', $this->argument('email'))->get();

        if ($users->isEmpty()) {
            $this->error('Пользователь не найден. Укажите email или добавьте --all.');

            return self::FAILURE;
        }

        foreach ($users as $user) {
            $user->increment('credits', $amount);

            if ($this->option('reset-trial')) {
                $user->forceFill(['trial_used' => false])->save();
            }

            $this->components->twoColumnDetail(
                $user->email,
                $user->fresh()->credits.' генераций',
            );
        }

        return self::SUCCESS;
    }
}
