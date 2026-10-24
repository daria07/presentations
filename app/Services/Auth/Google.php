<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Вход через Google: OAuth 2.0 / OpenID Connect с PKCE
 * (https://developers.google.com/identity/protocols/oauth2/web-server).
 *
 * Как и у VK ID: authorizeUrl() ведёт на accounts.google.com, в сессии
 * остаются state и code_verifier, Google возвращает код на callback,
 * profile() меняет код на токен и берёт по нему почту и имя.
 *
 * В отличие от VK, Google требует секрет приложения и всегда отдаёт
 * почту — вместе с отметкой, подтвердил ли её Google.
 */
final class Google
{
    private const AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    private const USER_INFO = 'https://openidconnect.googleapis.com/v1/userinfo';

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public static function redirectUri(): string
    {
        return route('auth.google.callback');
    }

    public static function verifier(): string
    {
        return Str::random(64);
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function authorizeUrl(string $state, string $verifier): string
    {
        return self::AUTHORIZE.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => self::redirectUri(),
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => self::challenge($verifier),
            'code_challenge_method' => 'S256',
            // Выбор аккаунта: на одном телефоне их часто несколько
            'prompt' => 'select_account',
        ]);
    }

    /**
     * @return array{id: string, name: string, email: string|null, verified: bool}
     *
     * @throws RuntimeException Google отказал или ответил не тем
     */
    public static function profile(string $code, string $verifier): array
    {
        $token = Http::asForm()->timeout(15)->post(self::TOKEN, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $verifier,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => self::redirectUri(),
        ]);

        $access = $token->json('access_token');

        if (! $token->successful() || ! is_string($access) || $access === '') {
            throw new RuntimeException('Google: не выдал токен — '.self::error($token->json()));
        }

        $info = Http::withToken($access)->timeout(15)->get(self::USER_INFO);
        $user = $info->json();
        $id = is_array($user) ? ($user['sub'] ?? null) : null;

        if (! $info->successful() || ! is_array($user) || ! is_scalar($id) || (string) $id === '') {
            throw new RuntimeException('Google: не отдал профиль — '.self::error($user));
        }

        $name = trim((string) ($user['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($user['email'] ?? '')));

        return [
            'id' => (string) $id,
            'name' => $name !== '' ? mb_substr($name, 0, 255) : 'Пользователь Google',
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'verified' => filter_var($user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
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
