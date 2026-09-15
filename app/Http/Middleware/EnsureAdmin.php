<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пускает в кабинет только тех, кто перечислен в ADMIN_EMAILS.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // 404, а не 403: посторонний не должен узнать, что по этому
        // адресу вообще что-то есть
        abort_unless($request->user()?->isAdmin(), 404);

        return $next($request);
    }
}
