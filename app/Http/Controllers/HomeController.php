<?php

namespace App\Http\Controllers;

use App\Services\Deck\Looks;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Лендинг.
 *
 * Темы и гаммы отдаём из того же конфига, что и продукт: иначе через
 * месяц на лендинге останутся семь гамм, а внутри будет десять.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'themes' => Looks::themes(),
            'palettes' => Looks::palettes(),
        ]);
    }
}
