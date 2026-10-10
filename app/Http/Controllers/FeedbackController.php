<?php

namespace App\Http\Controllers;

use App\Mail\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Отзыв о сервисе.
 *
 * Одно поле и ничего больше: всё, что можно спросить дополнительно,
 * мы и так знаем — кто написал, сколько у него генераций и презентаций
 * подставляется в письмо само.
 */
class FeedbackController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Feedback');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'body.required' => 'Напишите пару слов — иначе отправлять нечего.',
            'body.min' => 'Слишком коротко, добавьте хотя бы несколько слов.',
            'body.max' => 'Слишком длинно — уложитесь в 5000 знаков.',
        ]);

        Mail::to(config('mail.feedback_to'))->send(
            new Feedback($request->user(), $data['body'])
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Спасибо, письмо отправлено. Мы прочитаем всё до строчки.',
        ]);
    }
}
