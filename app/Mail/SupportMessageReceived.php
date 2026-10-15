<?php

namespace App\Mail;

use App\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Новое сообщение в чате — письмо администратору. Отвечать — в админке:
 * ответ из почты до человека на сайте не дойдёт.
 */
class SupportMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SupportMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Сообщение в чате от '.$this->message->user->email);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support-received');
    }
}
