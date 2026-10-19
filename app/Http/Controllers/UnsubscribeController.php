<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Отписка от писем о новостях сервиса — без входа в аккаунт.
 *
 * Ссылка подписана: подделать её для чужого адреса нельзя.
 * GET показывает страницу с кнопкой, а отписывает только POST:
 * почтовые сканеры открывают ссылки из писем сами, и по GET
 * человек отписался бы, ничего не нажимая. Почтовые клиенты
 * отписывают кнопкой «Отписаться» как раз POST-запросом
 * (заголовок List-Unsubscribe-Post в письме).
 */
class UnsubscribeController extends Controller
{
    public static function url(User $user): string
    {
        return URL::signedRoute('news.unsubscribe', ['user' => $user->id]);
    }

    public function show(User $user): View
    {
        return view('unsubscribe', [
            'done' => $user->news_unsubscribed_at !== null,
            'action' => self::url($user),
        ]);
    }

    public function store(Request $request, User $user): View
    {
        if ($user->news_unsubscribed_at === null) {
            $user->forceFill(['news_unsubscribed_at' => now()])->save();
        }

        return view('unsubscribe', ['done' => true, 'action' => self::url($user)]);
    }
}
