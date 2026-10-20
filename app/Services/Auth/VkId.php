<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Вход через VK ID по OAuth 2.1 с PKCE (https://id.vk.com/about/business/go/docs).
 *
 * 1. authorizeUrl() — ведём человека на id.vk.com. В сессии остаются
 *    state (защита от подмены ответа) и code_verifier (секрет PKCE).
 * 2. VK возвращает на callback код, device_id и тот же state.
 * 3. profile() меняет код на токен и сразу берёт по нему профиль.
 *
 * Секретного ключа приложения здесь нет: при PKCE он не нужен.
 * Почту VK отдаёт, только если она привязана к профилю и человек
 * разрешил её передать, поэтому email может прийти пустым.
 */
final class VkId
{
    /**
     * Через кого входить: VK или почта Mail. Mail.ru — тоже часть VK ID,
     * ответ приходит на тот же callback в том же виде
     */
    public const PROVIDERS = ['vkid', 'mail_ru'];

    private const AUTHORIZE = 'https://id.vk.com/authorize';

    private const TOKEN = 'https://id.vk.com/oauth2/auth';

    private const USER_INFO = 'https://id.vk.com/oauth2/user_info';

    public static function enabled(): bool
    {
        return filled(config('services.vkid.client_id'));
    }

    public static function redirectUri(): string
    {
        return route('auth.vk.callback');
    }

    /** Случайный секрет PKCE: 64 символа из разрешённого алфавита */
    public static function verifier(): string
    {
        return Str::random(64);
    }

    /** S256: base64url от sha256 секрета, без «=» на конце */
    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function authorizeUrl(string $state, string $verifier, string $provider = 'vkid'): string
    {
        return self::AUTHORIZE.'?'.http_build_query([
            'provider' => in_array($provider, self::PROVIDERS, true) ? $provider : 'vkid',
            'response_type' => 'code',
            'client_id' => config('services.vkid.client_id'),
            'redirect_uri' => self::redirectUri(),
            'state' => $state,
            'code_challenge' => self::challenge($verifier),
            'code_challenge_method' => 'S256',
            'scope' => 'email',
        ]);
    }

    /**
     * Код с callback → профиль человека.
     *
     * @return array{id: string, name: string, email: string|null}
     *
     * @throws RuntimeException VK отказал или ответил не тем
     */
    public static function profile(string $code, string $verifier, string $deviceId, string $state): array
    {
        $token = Http::asForm()->timeout(15)->post(self::TOKEN, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $verifier,
            'client_id' => config('services.vkid.client_id'),
            'device_id' => $deviceId,
            'redirect_uri' => self::redirectUri(),
            'state' => $state,
        ]);

        $access = $token->json('access_token');

        if (! $token->successful() || ! is_string($access) || $access === '') {
            throw new RuntimeException('VK ID: не выдал токен — '.self::error($token->json()));
        }

        $info = Http::asForm()->timeout(15)->post(self::USER_INFO, [
            'client_id' => config('services.vkid.client_id'),
            'access_token' => $access,
        ]);

        $user = $info->json('user');
        $id = is_array($user) ? ($user['user_id'] ?? null) : null;

        if (! $info->successful() || ! is_array($user) || ! is_scalar($id) || (string) $id === '') {
            throw new RuntimeException('VK ID: не отдал профиль — '.self::error($info->json()));
        }

        $name = trim(((string) ($user['first_name'] ?? '')).' '.((string) ($user['last_name'] ?? '')));
        $email = mb_strtolower(trim((string) ($user['email'] ?? '')));

        return [
            'id' => (string) $id,
            'name' => $name !== '' ? mb_substr($name, 0, 255) : 'Пользователь VK',
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
        ];
    }

    private static function error(mixed $body): string
    {
        if (! is_array($body)) {
            return 'пустой ответ';
        }

        return (string) ($body['error_description'] ?? $body['error'] ?? 'неизвестная ошибка');
    }
}
