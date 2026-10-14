<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Presentations\PresentationController;
use App\Services\Billing\Package;
use App\Services\Deck\Looks;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Лендинг.
 *
 * Темы, гаммы и тарифы отдаём из того же конфига, что и продукт:
 * иначе через месяц на лендинге останутся семь гамм и старые цены.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $packages = Package::all();
        $cheapest = min(array_map(fn (Package $p) => $p->amount, $packages) ?: [0]);

        return Inertia::render('Welcome', [
            'themes' => Looks::themes(),
            'palettes' => Looks::palettes(),
            // Тарифы — из того же конфига, что и страница оплаты:
            // цена на лендинге не разойдётся с ценой при покупке
            'packages' => array_map(fn (Package $p) => [
                'key' => $p->key,
                'title' => $p->title,
                'credits' => $p->credits,
                'amount' => number_format($p->amount / 100, 0, ',', ' '),
                'perCredit' => number_format($p->pricePerCredit() / 100, 0, ',', ' '),
                'note' => $p->note,
                'popular' => $p->popular,
            ], $packages),
            'freeSlides' => PresentationController::FREE_MAX_SLIDES,
        ])->withViewData([
            // Заголовок и описание для поисковой выдачи: что это, что
            // получит человек, сколько стоит. Тот же заголовок ставит
            // и страница (Welcome.vue), чтобы вкладка не мигала
            'seoTitle' => self::TITLE.' - '.config('app.name'),
            'seoDescription' => 'Презентация по теме или вашему тексту за 5 минут: '
                .'факты из источников, список литературы, иллюстрации, '
                .'10 видов слайдов и речь докладчика. Первая — бесплатно, '
                .'полная версия от '.intdiv($cheapest, 100).' ₽.',
            'seoJsonLd' => self::jsonLd($cheapest),
        ]);
    }

    /** Заголовок лендинга — общий для сервера и страницы */
    public const TITLE = 'Нейросеть для презентаций: тема → готовые слайды';

    /**
     * Разметка schema.org: поисковики понимают, что это сервис, сколько
     * он стоит и как называется, — и могут показать это в выдаче.
     *
     * @return array<string, mixed>
     */
    private static function jsonLd(int $cheapest): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    'name' => config('app.name'),
                    'url' => url('/'),
                    'inLanguage' => 'ru',
                ],
                [
                    '@type' => 'SoftwareApplication',
                    'name' => config('app.name'),
                    'url' => url('/'),
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'description' => 'Сервис собирает презентацию по теме или по готовому тексту: '
                        .'ищет источники, пишет слайды, рисует иллюстрации и верстает PDF.',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => number_format($cheapest / 100, 0, '.', ''),
                        'priceCurrency' => 'RUB',
                    ],
                ],
            ],
        ];
    }
}
