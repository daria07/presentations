<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use App\Support\Attribution;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Общее у входа через VK ID и Google: создать аккаунт, впустить,
 * вернуть на вход с объяснением.
 *
 * Пароля у такого аккаунта нет (записан случайный). Захочет входить
 * по почте — «Забыли пароль?» задаст его.
 */
trait SignsInSocially
{
    /**
     * @param  array<string, string>  $link  номер профиля: ['vk_id' => …] или ['google_id' => …]
     */
    protected function createSocialUser(Request $request, array $link, string $name, string $email): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Str::random(48),
        ]);

        // Те же метки рекламы, что и при обычной регистрации
        $user->forceFill([...Attribution::forUser($request), ...$link])->save();

        event(new Registered($user));

        return $user;
    }

    protected function login(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    protected function fail(string $message): RedirectResponse
    {
        return to_route('login')->with('status', $message);
    }
}
