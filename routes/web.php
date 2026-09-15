<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Presentations\PresentationController;
use App\Http\Controllers\Presentations\PublicPresentationController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

// Правовые документы: открыты всем, реквизиты берутся из config/legal.php
Route::get('offer', fn () => Inertia::render('legal/Offer', [
    'legal' => config('legal'),
    'packages' => collect(config('billing.packages'))
        ->map(fn (array $pack) => [
            'title' => $pack['title'],
            'credits' => $pack['credits'],
            'price' => number_format($pack['amount'] / 100, 0, ',', ' ').' ₽',
        ])
        ->values(),
]))->name('legal.offer');

Route::get('privacy', fn () => Inertia::render('legal/Privacy', [
    'legal' => config('legal'),
]))->name('legal.privacy');

// Публичная ссылка на готовую презентацию — без авторизации
Route::get('p/{token}', [PublicPresentationController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('presentations.public');

// Вебхук провайдера: без входа в аккаунт и без CSRF —
// это машина-машина, подпись проверяется внутри провайдера.
Route::post('billing/webhook', [BillingController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('billing.webhook');

Route::middleware(['auth', 'verified'])->group(function () {
    // Оставлено ради ссылок из стартер-кита: после входа человек
    // сразу попадает к своим презентациям.
    Route::redirect('dashboard', '/presentations')->name('dashboard');

    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::post('checkout', [BillingController::class, 'checkout'])
            ->middleware('throttle:10,1')
            ->name('checkout');

        // Песочница видна только при провайдере fake
        Route::get('sandbox/{payment}', [BillingController::class, 'sandbox'])->name('sandbox');
        Route::post('sandbox/{payment}', [BillingController::class, 'sandboxSettle'])->name('sandbox.settle');
    });

    // Кабинет администратора: только чтение, доступ по ADMIN_EMAILS
    Route::middleware(EnsureAdmin::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('users', [AdminController::class, 'users'])->name('users');
        Route::get('users/{user}', [AdminController::class, 'user'])->name('user');
    });

    Route::prefix('presentations')->name('presentations.')->group(function () {
        Route::get('/', [PresentationController::class, 'index'])->name('index');
        Route::get('new', [PresentationController::class, 'create'])->name('create');

        // Каждое из этих действий стоит денег — ограничиваем частоту,
        // чтобы один аккаунт не мог выжечь баланс в цикле.
        Route::post('/', [PresentationController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');
        Route::post('{presentation}/answers', [PresentationController::class, 'answers'])
            ->middleware('throttle:20,1')
            ->name('answers');
        Route::post('{presentation}/retry', [PresentationController::class, 'retry'])
            ->middleware('throttle:10,1')
            ->name('retry');

        // Перепечатка бесплатна, но всё же не бесконечна
        Route::post('{presentation}/theme', [PresentationController::class, 'theme'])
            ->middleware('throttle:20,1')
            ->name('theme');

        Route::get('{presentation}', [PresentationController::class, 'show'])->name('show');

        // Фронт опрашивает раз в 2,5 секунды — с запасом на несколько вкладок
        Route::get('{presentation}/status', [PresentationController::class, 'status'])
            ->middleware('throttle:120,1')
            ->name('status');

        Route::get('{presentation}/edit', [PresentationController::class, 'edit'])->name('edit');
        Route::put('{presentation}/outline', [PresentationController::class, 'updateOutline'])
            ->middleware('throttle:60,1')
            ->name('outline');
        // GET — сохранённая версия, POST — черновик из редактора
        Route::match(['get', 'post'], '{presentation}/preview', [PresentationController::class, 'preview'])
            ->middleware('throttle:240,1')
            ->name('preview');

        Route::get('{presentation}/download', [PresentationController::class, 'download'])->name('download');

        // Печатается на лету: свой Chrome на каждый запрос, поэтому
        // ограничение строже, чем у готового файла
        Route::get('{presentation}/speech', [PresentationController::class, 'speech'])
            ->middleware('throttle:20,1')
            ->name('speech');
        Route::delete('{presentation}', [PresentationController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/settings.php';
