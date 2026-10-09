<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /*
           По умолчанию светлая, а не системная: кабинет свёрстан по
           светлому макету, и человек с тёмной системой до сих пор
           попадал в тёмную тему, ничего такого не выбирав. Кто хочет
           тёмную — включает её в настройках оформления, там же есть
           и «Системная».
        */
        View::share('appearance', $request->cookie('appearance') ?? 'light');

        return $next($request);
    }
}
