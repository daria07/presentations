<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Отзыв из кабинета — письмо владельцу сервиса.
 *
 * Отправитель остаётся нашим: письмо от чужого адреса почтовые службы
 * считают подделкой и кладут в спам. Адрес автора уходит в replyTo —
 * тогда «Ответить» в почтовом клиенте пишет прямо человеку.
 */
class Feedback extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $author,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Отзыв о сервисе от '.$this->author->email,
            replyTo: [new Address($this->author->email, $this->author->name)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.feedback');
    }
}
