<?php

namespace App\Mail;

use App\Http\Controllers\UnsubscribeController;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Служебное письмо «Мы обновились». Шлёт команда news:update.
 *
 * $user — получатель; null — пробная отправка на произвольный адрес,
 * тогда ссылка отписки ведёт на главную.
 */
class UpdateNews extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ?User $user = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Слайдуша обновилась: обложки, картинки и источники');
    }

    /**
     * Отписка в один клик прямо из почтового клиента: Яндекс, Mail.ru
     * и Gmail показывают её кнопкой рядом с отправителем. Без неё
     * недовольные жмут «Спам», и письма начинают туда падать у всех.
     */
    public function headers(): Headers
    {
        if ($this->user === null) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.UnsubscribeController::url($this->user).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.news.update',
            with: [
                'siteUrl' => url('/presentations'),
                'site' => (string) config('legal.site'),
                'legalName' => (string) config('legal.name'),
                'legalInn' => (string) config('legal.inn'),
                'unsubscribeUrl' => $this->user ? UnsubscribeController::url($this->user) : url('/'),
            ],
        );
    }
}
