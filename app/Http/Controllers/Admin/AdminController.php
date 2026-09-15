<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\PresentationStatus;
use App\Http\Controllers\Controller;
use App\Models\ApiCall;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Кабинет администратора. Только чтение: ни одной кнопки, которая
 * что-то меняет. Всё, что здесь есть, уже лежит в базе — мы только
 * складываем это в читаемые цифры.
 */
class AdminController extends Controller
{
    /** Сколько дней показываем на графике */
    private const WINDOW = 30;

    public function index(): Response
    {
        $since = now()->subDays(self::WINDOW - 1)->startOfDay();
        $week = now()->subDays(7);

        $byStatus = Presentation::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $paid = Payment::query()->where('status', PaymentStatus::Paid);

        return Inertia::render('admin/Overview', [
            'cards' => [
                'users' => User::count(),
                'usersWeek' => User::where('created_at', '>=', $week)->count(),
                'presentations' => Presentation::count(),
                'presentationsWeek' => Presentation::where('created_at', '>=', $week)->count(),
                'ready' => (int) ($byStatus[PresentationStatus::Ready->value] ?? 0),
                'failed' => (int) ($byStatus[PresentationStatus::Failed->value] ?? 0),
                // Суммы в копейках, делим на фронте
                'revenue' => (int) (clone $paid)->sum('amount'),
                'revenueMonth' => (int) (clone $paid)->where('created_at', '>=', $since)->sum('amount'),
                'payingUsers' => (int) (clone $paid)->distinct()->count('user_id'),
                // Себестоимость в сотых доли цента
                'cost' => (int) ApiCall::sum('cost'),
                'costMonth' => (int) ApiCall::where('created_at', '>=', $since)->sum('cost'),
            ],
            'statuses' => collect(PresentationStatus::cases())
                ->map(fn (PresentationStatus $s) => [
                    'key' => $s->value,
                    'label' => $s->label(),
                    'total' => (int) ($byStatus[$s->value] ?? 0),
                ])
                ->all(),
            'days' => $this->daily($since),
            'recent' => Presentation::with('user:id,name,email')
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (Presentation $p) => [
                    'id' => $p->id,
                    'title' => $p->title ?: $p->topic,
                    'status' => $p->status->value,
                    'statusLabel' => $p->status->label(),
                    'createdAt' => $p->created_at?->toIso8601String(),
                    'user' => $p->user?->only(['id', 'name', 'email']),
                ])
                ->all(),
        ]);
    }

    public function users(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace('%', '\%', mb_strtolower($search)).'%';

                $q->where(fn ($w) => $w
                    ->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(email) like ?', [$like]));
            })
            ->withCount('presentations')
            // Подзапросами, а не join: join по двум hasMany размножил бы
            // строки и суммы поехали бы в разы
            ->addSelect([
                'paid_total' => Payment::selectRaw('coalesce(sum(amount), 0)')
                    ->whereColumn('user_id', 'users.id')
                    ->where('status', PaymentStatus::Paid),
                'spent_total' => ApiCall::selectRaw('coalesce(sum(cost), 0)')
                    ->whereColumn('user_id', 'users.id'),
                'last_seen' => Presentation::select('created_at')
                    ->whereColumn('user_id', 'users.id')
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->latest('users.created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'credits' => $u->credits,
                'trialUsed' => (bool) $u->trial_used,
                'presentations' => (int) $u->presentations_count,
                'paid' => (int) $u->paid_total,
                'spent' => (int) $u->spent_total,
                'createdAt' => $u->created_at?->toIso8601String(),
                'lastSeen' => $u->last_seen ? Date::parse($u->last_seen)->toIso8601String() : null,
                'url' => route('admin.user', $u),
            ]);

        return Inertia::render('admin/Users', [
            // Та же форма, что у списка презентаций: фронт листает
            // одинаково везде
            'users' => [
                'data' => $users->items(),
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'total' => $users->total(),
                'prevUrl' => $users->previousPageUrl(),
                'nextUrl' => $users->nextPageUrl(),
            ],
            'search' => $search,
        ]);
    }

    public function user(User $user): Response
    {
        $calls = ApiCall::query()
            ->where('user_id', $user->id)
            ->selectRaw('purpose, count(*) as total, sum(cost) as cost, sum(input_tokens) as input, sum(output_tokens) as output')
            ->groupBy('purpose')
            ->get();

        return Inertia::render('admin/User', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'credits' => $user->credits,
                'trialUsed' => (bool) $user->trial_used,
                'verified' => $user->email_verified_at !== null,
                'twoFactor' => $user->two_factor_confirmed_at !== null,
                'createdAt' => $user->created_at?->toIso8601String(),
            ],
            'presentations' => $user->presentations()
                ->get()
                ->map(fn (Presentation $p) => [
                    'id' => $p->id,
                    'title' => $p->title ?: $p->topic,
                    'status' => $p->status->value,
                    'statusLabel' => $p->status->label(),
                    'slides' => count($p->outline['slides'] ?? []) ?: $p->slide_count,
                    'theme' => $p->theme,
                    'palette' => $p->palette,
                    'createdAt' => $p->created_at?->toIso8601String(),
                ])
                ->all(),
            'payments' => $user->payments()
                ->latest('id')
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'credits' => $p->credits_granted,
                    'status' => $p->status->value,
                    'statusLabel' => $p->status->label(),
                    'provider' => $p->provider,
                    'createdAt' => $p->created_at?->toIso8601String(),
                ])
                ->all(),
            'calls' => $calls
                ->map(fn ($row) => [
                    'purpose' => $row->purpose,
                    'total' => (int) $row->total,
                    'cost' => (int) $row->cost,
                    'input' => (int) $row->input,
                    'output' => (int) $row->output,
                ])
                ->all(),
        ]);
    }

    /**
     * Регистрации и генерации по дням. Пустые дни добиваем нулями:
     * без них график врёт — провал выглядит как отсутствие данных.
     *
     * Дни считаем сдвигом от начала окна, а не наращиванием счётчика:
     * в приложении включены неизменяемые даты, и привычный
     * `$day->addDay()` в условии цикла просто ничего не менял бы.
     *
     * @return array<int, array{date: string, users: int, presentations: int}>
     */
    private function daily(CarbonInterface $since): array
    {
        $users = $this->countByDay(User::query(), $since);
        $decks = $this->countByDay(Presentation::query(), $since);

        $days = [];

        for ($i = 0; $i < self::WINDOW; $i++) {
            $key = $since->addDays($i)->toDateString();

            $days[] = [
                'date' => $key,
                'users' => (int) ($users[$key] ?? 0),
                'presentations' => (int) ($decks[$key] ?? 0),
            ];
        }

        return $days;
    }

    /** @return array<string, int> */
    private function countByDay($query, CarbonInterface $since): array
    {
        return $query
            ->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->mapWithKeys(fn ($total, $day) => [(string) $day => (int) $total])
            ->all();
    }
}
