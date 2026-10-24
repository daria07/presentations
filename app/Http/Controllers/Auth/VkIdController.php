<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\SignsInSocially;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\VkId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Вход и регистрация через VK ID — одной кнопкой.
 *
 * Кого впускаем после ответа VK:
 * 1. аккаунт, уже привязанный к этому профилю VK;
 * 2. аккаунт с той же почтой, что отдал VK, — привязываем профиль.
 *    Почту VK подтверждает сам, поэтому это тот же человек;
 * 3. иначе создаём аккаунт. Если VK почту не отдал, спрашиваем её
 *    отдельной страницей: без почты не уйдут чеки и письма.
 *
 * Создание аккаунта и вход — общие с Google (Concerns\SignsInSocially).
 */
class VkIdController extends Controller
{
    use SignsInSocially;

    private const SESSION = 'vkid';

    private const PENDING = 'vkid_pending';

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(VkId::enabled(), 404);

        $state = Str::random(40);
        $verifier = VkId::verifier();
        $request->session()->put(self::SESSION, ['state' => $state, 'verifier' => $verifier]);

        return redirect()->away(VkId::authorizeUrl($state, $verifier, (string) $request->query('provider', 'vkid')));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(VkId::enabled(), 404);

        /** @var array{state?: string, verifier?: string} $saved */
        $saved = (array) $request->session()->pull(self::SESSION, []);

        // VK отдаёт параметры в адресе, а в части сценариев — одним JSON в payload
        $payload = json_decode((string) $request->query('payload', ''), true);
        $param = fn (string $key): string => (string) (is_array($payload) && isset($payload[$key])
            ? $payload[$key]
            : $request->query($key, ''));

        $code = $param('code');
        $state = $param('state');
        $deviceId = $param('device_id');

        // Отказался на странице VK или пришёл по старой ссылке
        if ($code === '' || $deviceId === '' || ! isset($saved['state'], $saved['verifier'])
            || ! hash_equals($saved['state'], $state)) {
            return $this->fail('Вход через VK не завершён. Попробуйте ещё раз.');
        }

        try {
            $profile = VkId::profile($code, $saved['verifier'], $deviceId, $state);
        } catch (RuntimeException $e) {
            Log::warning($e->getMessage());

            return $this->fail('VK не ответил. Попробуйте ещё раз или войдите по почте.');
        }

        $user = User::where('vk_id', $profile['id'])->first();

        if ($user === null && $profile['email'] !== null) {
            $user = User::whereRaw('lower(email) = ?', [$profile['email']])->first();
            $user?->forceFill(['vk_id' => $profile['id']])->save();
        }

        if ($user === null && $profile['email'] !== null) {
            $user = $this->register($request, $profile['id'], $profile['name'], $profile['email']);
        }

        if ($user === null) {
            $request->session()->put(self::PENDING, ['id' => $profile['id'], 'name' => $profile['name']]);

            return to_route('auth.vk.email');
        }

        return $this->login($request, $user);
    }

    /** VK не отдал почту — спрашиваем её сами */
    public function email(Request $request): Response|RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);

        if (! is_array($pending)) {
            return to_route('login');
        }

        return Inertia::render('auth/VkEmail', [
            'name' => $pending['name'] ?? '',
        ]);
    }

    public function storeEmail(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);

        if (! is_array($pending) || ! isset($pending['id'], $pending['name'])) {
            return to_route('login');
        }

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        // Эту почту VK не подтверждал, поэтому к чужому аккаунту её
        // не привязываем: войти в него можно только паролем
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
        ], [
            'email.unique' => 'Этот адрес уже зарегистрирован. Войдите по почте и паролю.',
        ]);

        // Пока человек вводил почту, этот профиль VK мог войти в другой вкладке
        $user = User::where('vk_id', $pending['id'])->first()
            ?? $this->register($request, (string) $pending['id'], (string) $pending['name'], (string) $data['email']);

        $request->session()->forget(self::PENDING);

        return $this->login($request, $user);
    }

    private function register(Request $request, string $vkId, string $name, string $email): User
    {
        return $this->createSocialUser($request, ['vk_id' => $vkId], $name, $email);
    }
}
