<?php

namespace App\Console\Commands;

use App\Mail\UpdateNews;
use App\Models\User;
use App\Services\Billing\UpdateOffer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Письмо «Мы обновились» тем, кто зарегистрировался до обновления.
 *
 *   php artisan news:update                         — сколько и кому, ничего не шлёт
 *   php artisan news:update --test=me@example.com   — одно письмо сразу, на этот адрес
 *   php artisan news:update --send                  — всем, через очередь
 *
 * Шлём только подтвердившим почту и не отписавшимся: письма на
 * непроверенные адреса возвращаются, и почтовые службы начинают
 * считать нас спамерами. Кому письмо уже ушло, записано в mail_sends —
 * повторный --send продолжит с того же места, а не начнёт сначала.
 */
class SendUpdateNews extends Command
{
    public const CAMPAIGN = 'update-2026-10';

    protected $signature = 'news:update
                            {--test= : Отправить одно письмо на этот адрес, сразу}
                            {--send : Отправить всем получателям через очередь}
                            {--per-minute=20 : Сколько писем в минуту, чтобы не попасть в спам}
                            {--force : Не спрашивать подтверждения}';

    protected $description = 'Служебное письмо «Мы обновились» давним пользователям';

    public function handle(): int
    {
        if ($test = $this->option('test')) {
            return $this->test((string) $test);
        }

        $cutoff = UpdateOffer::cutoff();

        if ($cutoff === null) {
            $this->error('Не задана граница регистрации: UPDATE_OFFER_REGISTERED_BEFORE.');

            return self::FAILURE;
        }

        $total = $this->recipients()->count();

        $this->components->twoColumnDetail('Зарегистрировались до', $cutoff->timezone(config('admin.timezone'))->format('d.m.Y H:i').' (МСК)');
        $this->components->twoColumnDetail('Уже получили', (string) DB::table('mail_sends')->where('campaign', self::CAMPAIGN)->count());
        $this->components->twoColumnDetail('Осталось отправить', (string) $total);

        foreach ($this->recipients()->limit(5)->pluck('email') as $email) {
            $this->line('  · '.$email);
        }

        if (! $this->option('send')) {
            $this->newLine();
            $this->line('Ничего не отправлено. Пробное письмо: --test=адрес, всем: --send');

            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('Отправлять некому.');

            return self::SUCCESS;
        }

        $perMinute = max(1, (int) $this->option('per-minute'));
        $minutes = (int) ceil($total / $perMinute);

        if (! $this->option('force') && ! $this->confirm("Отправить {$total} писем? Уйдут примерно за {$minutes} мин.")) {
            return self::FAILURE;
        }

        $queued = 0;

        // Порциями по id: список может быть длинным
        $this->recipients()->chunkById(200, function ($users) use (&$queued, $perMinute) {
            foreach ($users as $user) {
                // Сначала запись, потом очередь: упадёт посередине —
                // повторный запуск не пришлёт письмо второй раз
                $inserted = DB::table('mail_sends')->insertOrIgnore([
                    'campaign' => self::CAMPAIGN,
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);

                if ($inserted === 0) {
                    continue;
                }

                Mail::to($user->email)->later(
                    now()->addSeconds((int) floor($queued * 60 / $perMinute)),
                    new UpdateNews($user),
                );

                $queued++;
            }
        });

        $this->info("Поставлено в очередь: {$queued}. Отправляет воркер, за ходом следите в storage/logs.");

        return self::SUCCESS;
    }

    /**
     * Получатели: до обновления, почта подтверждена, не отписались,
     * письмо ещё не уходило.
     *
     * @return Builder<User>
     */
    private function recipients(): Builder
    {
        return User::query()
            ->where('created_at', '<', UpdateOffer::cutoff())
            ->whereNotNull('email_verified_at')
            ->whereNull('news_unsubscribed_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('mail_sends')
                ->whereColumn('mail_sends.user_id', 'users.id')
                ->where('mail_sends.campaign', self::CAMPAIGN))
            ->orderBy('id');
    }

    /** Одно письмо сразу, мимо очереди: чтобы увидеть ошибку почты здесь же */
    private function test(string $email): int
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Это не похоже на адрес почты.');

            return self::FAILURE;
        }

        // Адрес чей-то из пользователей — ссылка отписки будет настоящей
        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();

        try {
            Mail::to($email)->sendNow(new UpdateNews($user));
        } catch (Throwable $e) {
            $this->error('Не отправилось: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Отправлено на {$email}. Почтовый драйвер: ".config('mail.default').'.');

        if (config('mail.default') === 'log') {
            $this->warn('Драйвер log: письмо не ушло, а записано в storage/logs/laravel.log.');
        }

        return self::SUCCESS;
    }
}
