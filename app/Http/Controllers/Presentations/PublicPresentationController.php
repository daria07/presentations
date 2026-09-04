<?php

namespace App\Http\Controllers\Presentations;

use App\Http\Controllers\Controller;
use App\Models\Presentation;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Страница по публичной ссылке — открывается без входа в аккаунт.
 */
class PublicPresentationController extends Controller
{
    public function show(string $token): Response
    {
        $presentation = Presentation::where('share_token', $token)->firstOrFail();

        abort_unless($presentation->isReady(), 404);

        $disk = Storage::disk(config('deck.disk'));
        $name = ($presentation->title ?: 'presentation').'.pdf';

        // Встроенная смотрелка PDF не качает файл целиком: переход по
        // миниатюре — это запрос куска через заголовок Range. Потоковый
        // ответ такого не умеет, и навигация начинает промахиваться.
        // BinaryFileResponse отдаёт диапазоны сам.
        if (method_exists($disk, 'path')) {
            $path = $disk->path($presentation->file_path);

            if (is_file($path)) {
                $response = new BinaryFileResponse($path);
                $response->headers->set('Content-Type', 'application/pdf');
                $response->setContentDisposition('inline', $name, 'presentation.pdf');
                $response->setAutoLastModified();

                return $response;
            }
        }

        // Запасной путь для хранилищ без локального пути
        return $disk->response($presentation->file_path, $name, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => $disk->size($presentation->file_path),
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
