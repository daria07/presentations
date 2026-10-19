<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\Attribution;
use App\Support\MarketingConsent;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        /*
           Источник регистрации. Метки запомнила кука на первом заходе
           (App\Http\Middleware\CaptureAttribution), здесь переносим их
           в аккаунт — кука живёт 90 дней, а знать, откуда пришёл
           платящий человек, нужно и через год.

           forceFill, а не create: эти поля намеренно не в fillable,
           чтобы их нельзя было подставить формой регистрации.
        */
        $user->forceFill(Attribution::forUser(request()))->save();

        // Отдельная галочка, снятая по умолчанию: без неё регистрация идёт как обычно
        if (filter_var($input['marketing'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            MarketingConsent::grant($user, request(), 'register');
        }

        return $user;
    }
}
