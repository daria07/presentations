<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Лендинги под рекламу: статические страницы в public/l/<slug>.
 *
 * Маршрут нужен, чтобы адрес работал и без завершающего слэша:
 * иначе всё зависело бы от директивы index в nginx, а она на
 * локальной машине и на сервере разная.
 *
 * Контроллером, а не замыканием: `route:cache` на замыканиях падает,
 * а он входит в деплой.
 */
class LandingController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $file = public_path("l/{$slug}/index.html");

        abort_unless(is_file($file), 404);

        return response(file_get_contents($file))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}
