<?php

use App\Http\Middleware\CaptureAttribution;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Вебхук платёжного провайдера приходит от сервера, а не из
        // браузера — CSRF-токена у него нет и быть не может.
        $middleware->validateCsrfTokens(except: ['billing/webhook']);

        $middleware->web(append: [
            CaptureAttribution::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
           Inertia-запрос — это XHR. Страница ошибки, отданная в ответ
           на него, показывается как чужая вёрстка поверх приложения,
           а по-настоящему неприятен тут только текст: «429 Too Many
           Requests» человек, который просто перебирал оформление,
           прочитать не должен. Поэтому на понятные случаи отвечаем
           возвратом назад и всплывающим сообщением на русском.
        */
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->header('X-Inertia')) {
                return $response;
            }

            $toast = match ($response->getStatusCode()) {
                419 => ['warning', 'Страница открыта слишком давно. Обновите её и попробуйте снова.'],
                429 => ['warning', 'Слишком много действий подряд. Подождите минуту и попробуйте снова.'],
                503 => ['info', 'Идут технические работы, зайдите через несколько минут.'],
                default => null,
            };

            if ($toast === null) {
                return $response;
            }

            return back()->with('toast', [
                'type' => $toast[0],
                'message' => $toast[1],
            ]);
        });
    })->create();
