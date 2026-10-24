<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\SignsInSocially;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\Google;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Вход и регистрация через Google — одной кнопкой.
 *
 * Кого впускаем:
 * 1. аккаунт, уже привязанный к этому аккаунту Google;
 * 2. аккаунт с той же почтой — привязываем Google. Только если Google
 *    подтвердил почту: иначе по чужому адресу можно было бы войти
 *    в чужой аккаунт;
 * 3. иначе создаём аккаунт.
 */
class GoogleController extends Controller
{
    use SignsInSocially;

    private const SESSION = 'google_auth';

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(Google::enabled(), 404);

        $state = Str::random(40);
        $verifier = Google::verifier();
        $request->session()->put(self::SESSION, ['state' => $state, 'verifier' => $verifier]);

        return redirect()->away(Google::authorizeUrl($state, $verifier));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(Google::enabled(), 404);

        /** @var array{state?: string, verifier?: string} $saved */
        $saved = (array) $request->session()->pull(self::SESSION, []);
        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        // Отказался на странице Google или пришёл по старой ссылке
        if ($code === '' || ! isset($saved['state'], $saved['verifier']) || ! hash_equals($saved['state'], $state)) {
            return $this->fail('Вход через Google не завершён. Попробуйте ещё раз.');
        }

        try {
            $profile = Google::profile($code, $saved['verifier']);
        } catch (RuntimeException $e) {
            Log::warning($e->getMessage());

            return $this->fail('Google не ответил. Попробуйте ещё раз или войдите по почте.');
        }

        if ($profile['email'] === null || ! $profile['verified']) {
            return $this->fail('Google не подтвердил почту этого аккаунта. Войдите другим способом.');
        }

        $user = User::where('google_id', $profile['id'])->first();

        if ($user === null) {
            $user = User::whereRaw('lower(email) = ?', [$profile['email']])->first();
            $user?->forceFill(['google_id' => $profile['id']])->save();
        }

        $user ??= $this->createSocialUser($request, ['google_id' => $profile['id']], $profile['name'], $profile['email']);

        return $this->login($request, $user);
    }
}
