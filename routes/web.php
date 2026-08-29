<?php

use App\Http\Controllers\Presentations\PresentationController;
use App\Http\Controllers\Presentations\PublicPresentationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Публичная ссылка на готовую презентацию — без авторизации
Route::get('p/{token}', [PublicPresentationController::class, 'show'])
    ->name('presentations.public');

Route::middleware(['auth', 'verified'])->group(function () {
    // Оставлено ради ссылок из стартер-кита: после входа человек
    // сразу попадает к своим презентациям.
    Route::redirect('dashboard', '/presentations')->name('dashboard');

    Route::prefix('presentations')->name('presentations.')->group(function () {
        Route::get('/', [PresentationController::class, 'index'])->name('index');
        Route::get('new', [PresentationController::class, 'create'])->name('create');
        Route::post('/', [PresentationController::class, 'store'])->name('store');

        Route::get('{presentation}', [PresentationController::class, 'show'])->name('show');
        Route::get('{presentation}/status', [PresentationController::class, 'status'])->name('status');
        Route::post('{presentation}/answers', [PresentationController::class, 'answers'])->name('answers');
        Route::post('{presentation}/retry', [PresentationController::class, 'retry'])->name('retry');
        Route::get('{presentation}/download', [PresentationController::class, 'download'])->name('download');
        Route::delete('{presentation}', [PresentationController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/settings.php';
