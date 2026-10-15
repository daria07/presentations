<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SupportController as UserSupport;
use App\Mail\SupportReplied;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Чат с пользователями — сторона администратора.
 *
 * Единственное место админки, кроме чеков, где она что-то пишет:
 * ответы. Слева список диалогов, справа выбранный.
 */
class SupportController extends Controller
{
    private const PER_PAGE = 30;

    public function index(): Response
    {
        return $this->page(null);
    }

    public function show(User $user): Response
    {
        // Открыли диалог — сообщения человека прочитаны
        $user->supportMessages()->unreadByAdmin()->update(['read_at' => now()]);

        return $this->page($user);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.SupportMessage::MAX_LENGTH],
        ], [
            'body.required' => 'Напишите ответ.',
            'body.max' => 'Слишком длинно — уложитесь в '.SupportMessage::MAX_LENGTH.' знаков.',
        ]);

        // Письмо — на первый ответ, а не на каждый из нескольких подряд
        $recent = $user->supportMessages()
            ->unreadByUser()
            ->where('created_at', '>=', now()->subMinutes(UserSupport::NOTIFY_EVERY_MINUTES))
            ->exists();

        /** @var SupportMessage $message */
        $message = $user->supportMessages()->create([
            'from_admin' => true,
            'body' => trim($data['body']),
        ]);

        // Отвечая, администратор заодно прочитал всё, что было до ответа
        $user->supportMessages()->unreadByAdmin()->update(['read_at' => now()]);

        if (! $recent) {
            Mail::to($user->email)->send(new SupportReplied($message));
        }

        return to_route('admin.support.show', $user);
    }

    private function page(?User $user): Response
    {
        return Inertia::render('admin/Support', [
            'conversations' => fn () => $this->conversations(),
            'thread' => fn () => $user ? $this->thread($user) : null,
        ]);
    }

    /**
     * Диалоги: свежие сверху, с последним сообщением и числом
     * непрочитанных.
     *
     * @return array{items: array<int, array<string, mixed>>, currentPage: int, lastPage: int, prevUrl: string|null, nextUrl: string|null}
     */
    private function conversations(): array
    {
        $page = SupportMessage::query()
            ->selectRaw('user_id, max(id) as last_id')
            ->selectRaw('sum(case when from_admin then 0 when read_at is null then 1 else 0 end) as unread')
            ->groupBy('user_id')
            ->orderByDesc('last_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $rows = collect($page->items());
        $last = SupportMessage::query()->whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');
        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get(['id', 'name', 'email'])->keyBy('id');

        return [
            'items' => $rows->map(function ($row) use ($last, $users) {
                // Вычисляемые колонки из запроса, а не поля модели
                $message = $last[$row->getAttribute('last_id')] ?? null;
                $user = $users[$row->user_id] ?? null;

                return [
                    'userId' => (int) $row->user_id,
                    'email' => $user?->email,
                    'name' => $user?->name,
                    'unread' => (int) $row->getAttribute('unread'),
                    'preview' => $message ? Str::limit($message->body, 80) : '',
                    'fromAdmin' => (bool) $message?->from_admin,
                    'at' => $message?->created_at?->toIso8601String(),
                ];
            })->values()->all(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'prevUrl' => $page->previousPageUrl(),
            'nextUrl' => $page->nextPageUrl(),
        ];
    }

    /** @return array<string, mixed> */
    private function thread(User $user): array
    {
        $paid = $user->payments()->where('status', PaymentStatus::Paid);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'credits' => $user->credits,
                'presentations' => $user->presentations()->count(),
                'payments' => (clone $paid)->count(),
                'paidTotal' => (int) (clone $paid)->sum('amount'),
                'createdAt' => $user->created_at?->toIso8601String(),
            ],
            'messages' => $user->supportMessages()
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map->toChat()
                ->values()
                ->all(),
        ];
    }
}
