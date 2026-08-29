<?php

namespace App\Http\Controllers\Presentations;

use App\Http\Controllers\Controller;
use App\Models\Presentation;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Страница по публичной ссылке — открывается без входа в аккаунт.
 */
class PublicPresentationController extends Controller
{
    public function show(string $token): StreamedResponse
    {
        $presentation = Presentation::where('share_token', $token)->firstOrFail();

        abort_unless($presentation->isReady(), 404);

        // Отдаём PDF инлайном — браузер покажет его во встроенной смотрелке
        return Storage::disk(config('deck.disk'))->response(
            $presentation->file_path,
            ($presentation->title ?: 'presentation').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
