<?php

namespace App\Http\Controllers;

use App\Mail\SupportMessageReceived;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Чат с администратором — сторона пользователя.
 *
 * Окошко чата опрашивает переписку, пока открыто, как страница
 * презентации опрашивает статус: настоящий «живой» канал для чата
 * поддержки, где ответ приходит через минуты, был бы лишней
 * инфраструктурой.
 */
class SupportController extends Controller
{
    /** Через сколько минут о новом сообщении в том же диалоге снова пишем на почту */
    public const NOTIFY_EVERY_MINUTES = 10;

    /**
     * Переписка. after — id последнего сообщения, которое уже есть
     * у окошка: при опросе отдаём только новые.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $after = max(0, (int) $request->query('after', '0'));

        $messages = $user->supportMessages()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(200)
            ->get();

        // Окошко открыто — значит, ответы прочитаны
        $user->supportMessages()->unreadByUser()->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map->toChat()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.SupportMessage::MAX_LENGTH],
        ], [
            'body.required' => 'Напишите сообщение.',
            'body.max' => 'Слишком длинно — уложитесь в '.SupportMessage::MAX_LENGTH.' знаков.',
        ]);

        $user = $request->user();
        $body = trim($data['body']);

        abort_if($body === '', 422, 'Напишите сообщение.');

        // Письмо — только на первое сообщение после затишья: если человек
        // пишет пятью сообщениями подряд, пять писем не нужны
        $recent = $user->supportMessages()
            ->unreadByAdmin()
            ->where('created_at', '>=', now()->subMinutes(self::NOTIFY_EVERY_MINUTES))
            ->exists();

        /** @var SupportMessage $message */
        $message = $user->supportMessages()->create(['from_admin' => false, 'body' => $body]);

        if (! $recent && filled(config('mail.feedback_to'))) {
            Mail::to(config('mail.feedback_to'))->send(new SupportMessageReceived($message));
        }

        return response()->json(['message' => $message->toChat()], 201);
    }
}
