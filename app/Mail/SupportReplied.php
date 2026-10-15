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
 * Ответ администратора — письмо человеку: на сайте он мог уже не сидеть.
 */
class SupportReplied extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SupportMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ответ на ваше сообщение — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support-replied');
    }
}
