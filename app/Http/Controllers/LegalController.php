<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Оферта и политика конфиденциальности.
 *
 * Контроллером, а не замыканием в routes/web.php: `route:cache`
 * не умеет сериализовать замыкания и падает на них, а он входит
 * в деплой. Один маршрут-замыкание ломает выкат целиком.
 */
class LegalController extends Controller
{
    public function offer(): Response
    {
        return Inertia::render('legal/Offer', [
            'legal' => config('legal'),
            'packages' => collect(config('billing.packages'))
                ->map(fn (array $pack) => [
                    'title' => $pack['title'],
                    'credits' => $pack['credits'],
                    'price' => number_format($pack['amount'] / 100, 0, ',', ' ').' ₽',
                ])
                ->values(),
        ]);
    }

    public function privacy(): Response
    {
        return Inertia::render('legal/Privacy', [
            'legal' => config('legal'),
        ]);
    }
}
