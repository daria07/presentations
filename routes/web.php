<?php

use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Presentations\PresentationController;
use App\Http\Controllers\Presentations\PublicPresentationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

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

        Route::get('{presentation}/download', [PresentationController::class, 'download'])->name('download');
        Route::delete('{presentation}', [PresentationController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/settings.php';
