<?php

use App\Http\Controllers\Presentations\PresentationController;
use App\Http\Controllers\Presentations\PublicPresentationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Публичная ссылка на готовую презентацию — без авторизации
Route::get('p/{token}', [PublicPresentationController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('presentations.public');

Route::middleware(['auth', 'verified'])->group(function () {
    // Оставлено ради ссылок из стартер-кита: после входа человек
    // сразу попадает к своим презентациям.
    Route::redirect('dashboard', '/presentations')->name('dashboard');

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
