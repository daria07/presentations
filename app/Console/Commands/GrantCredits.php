<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Управление генерациями вручную — для тестов и поддержки:
 *   php artisan credits:grant admin@ya.ru 20        начислить 20
 *   php artisan credits:grant admin@ya.ru 0 --set   обнулить
 *   php artisan credits:grant admin@ya.ru --use-trial
 *   php artisan credits:grant --all 5
 */
class GrantCredits extends Command
{
    protected $signature = 'credits:grant
                            {email? : Кому начислить}
                            {amount=10 : Сколько генераций}
                            {--all : Всем пользователям}
                            {--set : Выставить точное значение, а не прибавить}
                            {--reset-trial : Вернуть бесплатную первую генерацию}
                            {--use-trial : Пометить пробную как использованную}';

    protected $description = 'Начисляет или выставляет генерации пользователю';

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
            if ($this->option('set')) {
                $user->forceFill(['credits' => max(0, $amount)])->save();
            } else {
                $user->increment('credits', $amount);
            }

            if ($this->option('reset-trial')) {
                $user->forceFill(['trial_used' => false])->save();
            }

            if ($this->option('use-trial')) {
                $user->forceFill(['trial_used' => true])->save();
            }

            $fresh = $user->fresh();

            $this->components->twoColumnDetail(
                $fresh->email,
                $fresh->credits.' генераций'
                    .($fresh->trial_used ? '' : ' + пробная'),
            );
        }

        return self::SUCCESS;
    }
}
