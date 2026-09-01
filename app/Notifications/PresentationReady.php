<?php

namespace App\Notifications;

use App\Models\Presentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Письмо о готовой презентации.
 *
 * Генерация занимает до минуты — за это время человек успевает уйти
 * из вкладки. Письмо возвращает его к результату.
 *
 * Отправка идёт через очередь: если почтовый сервис тормозит, это
 * не должно задерживать саму генерацию.
 */
class PresentationReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Presentation $presentation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->presentation->title ?: $this->presentation->topic;
        $slides = count($this->presentation->outline['slides'] ?? []);

        return (new MailMessage)
            ->subject("Презентация готова: {$title}")
            ->greeting('Готово')
            ->line("«{$title}» — {$slides} слайдов.")
            ->action('Посмотреть', route('presentations.show', $this->presentation))
            ->line('Файл можно скачать или отправить ссылкой — она откроется без входа в аккаунт.')
            ->salutation('С уважением, '.config('app.name'));
    }
}
