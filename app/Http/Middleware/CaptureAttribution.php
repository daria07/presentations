<?php

namespace App\Http\Middleware;

use App\Support\Attribution;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Запоминает метки, с которыми человек пришёл, в куке на 90 дней.
 *
 * Пишем только когда есть что писать, и не перетираем уже сохранённые
 * utm-метки: первый источник важнее последнего. Голый реферер меткам
 * уступает — по нему видно только сайт, а не кампанию.
 */
class CaptureAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            $this->remember($request);
        }

        return $next($request);
    }

    private function remember(Request $request): void
    {
        $fresh = Attribution::fromRequest($request);

        if ($fresh === null) {
            return;
        }

        $stored = Attribution::stored($request);

        $hasUtm = collect(Attribution::UTM)
            ->contains(fn (string $key) => filled($stored[$key] ?? null));

        if ($hasUtm || filled($stored['click_id'] ?? null)) {
            return;
        }

        Cookie::queue(Cookie::make(
            Attribution::COOKIE,
            (string) json_encode($fresh, JSON_UNESCAPED_UNICODE),
            Attribution::DAYS * 24 * 60,
        ));
    }
}
