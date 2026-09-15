<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Открытые страницы: оферта, политика конфиденциальности и тарифы.
 *
 * Тарифы продублированы наружу не ради красоты: платёжный провайдер
 * требует, чтобы цены, условия и реквизиты были видны без входа в
 * аккаунт. Источник цен один — config/billing.php, поэтому открытая
 * страница и кабинет не могут разойтись.
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

    public function pricing(): Response
    {
        return Inertia::render('legal/Pricing', [
            'legal' => config('legal'),
            'packages' => collect(config('billing.packages'))
                ->map(fn (array $pack, string $key) => [
                    'key' => $key,
                    'title' => $pack['title'],
                    'credits' => $pack['credits'],
                    'note' => $pack['note'],
                    'popular' => $pack['popular'] ?? false,
                    'price' => number_format($pack['amount'] / 100, 0, ',', ' '),
                    'perCredit' => number_format(
                        $pack['amount'] / 100 / $pack['credits'], 0, ',', ' ',
                    ),
                ])
                ->values(),
        ]);
    }
}
