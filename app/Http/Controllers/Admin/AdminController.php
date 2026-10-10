<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\PresentationStatus;
use App\Http\Controllers\Controller;
use App\Models\ApiCall;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\User;
use App\Services\Billing\Discount;
use App\Support\Attribution;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
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

    /**
     * Периоды для цифр сводки. Первый — по умолчанию: так страница
     * открывается такой же, какой была до появления переключателя.
     */
    public const PERIODS = ['all', 'today', 'yesterday', 'week'];

    public function index(Request $request): Response
    {
        $since = now()->subDays(self::WINDOW - 1)->startOfDay();

        $period = in_array($request->query('period'), self::PERIODS, true)
            ? $request->query('period')
            : self::PERIODS[0];

        [$from, $to] = $this->range($period);

        return Inertia::render('admin/Overview', [
            'period' => $period,
            'cards' => $this->totals($from, $to),
            // С чем сравнивать: сегодня — со вчера, вчера — с позавчера,
            // неделю — с предыдущей неделей. У «всего времени» пары нет.
            'previous' => $period === 'all'
                ? null
                : $this->totals(...$this->range($period, previous: true)),
            'statuses' => $this->statuses($from, $to),
            'days' => $this->daily($since),
            'reviews' => $this->reviews(),
            'promo' => $this->promo(),
            'recent' => Presentation::with('user:id,name,email')
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (Presentation $p) => [
                    'id' => $p->id,
                    'title' => $p->title ?: $p->topic,
                    'status' => $p->status->value,
                    'statusLabel' => $p->status->label(),
                    'rating' => $p->rating,
                    'createdAt' => $p->created_at?->toIso8601String(),
                    'user' => $p->user?->only(['id', 'name', 'email']),
                ])
                ->all(),
        ]);
    }

    /**
     * A/B-тест кнопки скидки: сколько людей увидели каждый текст,
     * сколько кликнули и сколько в итоге оплатили со скидкой.
     *
     * Считаем людей, а не нажатия: показ и клик записываются один
     * раз на человека, иначе тот, кто заходит каждый день, перевесил
     * бы десяток тех, кто зашёл однажды.
     *
     * @return array{percent: int, variants: array<int, array<string, mixed>>}
     */
    private function promo(): array
    {
        $users = User::query()
            ->whereNotNull('promo_variant')
            ->selectRaw('promo_variant as variant')
            ->selectRaw('count(promo_shown_at) as shown')
            ->selectRaw('count(promo_clicked_at) as clicked')
            ->groupBy('promo_variant')
            ->get()
            ->keyBy('variant');

        $paid = Payment::query()
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('payments.status', PaymentStatus::Paid)
            ->where('payments.discount_percent', '>', 0)
            ->selectRaw('users.promo_variant as variant')
            ->selectRaw('count(distinct payments.user_id) as buyers')
            ->selectRaw('sum(payments.amount) as revenue')
            ->groupBy('users.promo_variant')
            ->get()
            ->keyBy('variant');

        return [
            'percent' => Discount::percent(),
            'variants' => collect(Discount::VARIANTS)
                ->map(fn (string $text, string $key) => [
                    'key' => $key,
                    'text' => $text,
                    'shown' => (int) ($users[$key]->shown ?? 0),
                    'clicked' => (int) ($users[$key]->clicked ?? 0),
                    'buyers' => (int) ($paid[$key]->buyers ?? 0),
                    'revenue' => (int) ($paid[$key]->revenue ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Оценки презентаций: средняя за всё время и последние отзывы.
     *
     * @return array{average: float|null, total: int, latest: array<int, array<string, mixed>>}
     */
    private function reviews(): array
    {
        $rated = Presentation::query()->whereNotNull('rating');

        return [
            'average' => (clone $rated)->count()
                ? round((float) (clone $rated)->avg('rating'), 1)
                : null,
            'total' => (clone $rated)->count(),
            'latest' => (clone $rated)
                ->with('user:id,name,email')
                ->latest('reviewed_at')
                ->limit(8)
                ->get()
                ->map(fn (Presentation $p) => [
                    'id' => $p->id,
                    'title' => $p->title ?: $p->topic,
                    'rating' => $p->rating,
                    'review' => $p->review,
                    'reviewedAt' => $p->reviewed_at?->toIso8601String(),
                    'user' => $p->user?->only(['id', 'name', 'email']),
                ])
                ->all(),
        ];
    }

    /**
     * Границы периода в UTC: [с, до), null — без границы.
     *
     * Сутки считаются по часовому поясу админки (config/admin.php),
     * а в базу уходят уже переведёнными в UTC.
     *
     * $previous — такой же отрезок непосредственно перед этим. Для
     * «сегодня» это вчера целиком: сегодняшний день ещё идёт, и к вечеру
     * разница с полным вчерашним днём сама сойдёт на нет.
     *
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    private function range(string $period, bool $previous = false): array
    {
        $today = now(config('admin.timezone'))->startOfDay();

        [$from, $to] = match ($period) {
            'today' => [$today, null],
            'yesterday' => [$today->copy()->subDay(), $today],
            'week' => [$today->copy()->subDays(6), null],
            default => [null, null],
        };

        if ($previous && $from) {
            $length = $period === 'week' ? 7 : 1;
            [$from, $to] = [$from->copy()->subDays($length), $from];
        }

        return [$from?->utc(), $to?->utc()];
    }

    /**
     * Ограничение запроса периодом по created_at.
     *
     * @template T of \Illuminate\Database\Eloquent\Builder
     *
     * @param  T  $query
     * @return T
     */
    private function within($query, ?CarbonInterface $from, ?CarbonInterface $to)
    {
        return $query
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<', $to));
    }

    /** @return array<string, int> */
    private function totals(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $paid = fn () => $this->within(
            Payment::query()->where('status', PaymentStatus::Paid),
            $from,
            $to,
        );

        $presentations = fn () => $this->within(Presentation::query(), $from, $to);

        return [
            'users' => $this->within(User::query(), $from, $to)->count(),
            'presentations' => $presentations()->count(),
            'failed' => $presentations()->where('status', PresentationStatus::Failed)->count(),
            // Суммы в копейках, делим на фронте. Платёж относится к дню,
            // когда его начали: между началом и оплатой обычно минуты
            'revenue' => (int) $paid()->sum('amount'),
            'payments' => $paid()->count(),
            'payingUsers' => (int) $paid()->distinct()->count('user_id'),
            // Себестоимость в сотых доли цента
            'cost' => (int) $this->within(ApiCall::query(), $from, $to)->sum('cost'),
        ];
    }

    /** @return array<int, array{key: string, label: string, total: int}> */
    private function statuses(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $byStatus = $this->within(Presentation::query(), $from, $to)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(PresentationStatus::cases())
            ->map(fn (PresentationStatus $s) => [
                'key' => $s->value,
                'label' => $s->label(),
                'total' => (int) ($byStatus[$s->value] ?? 0),
            ])
            ->all();
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
                'source' => $this->shortSource($u),
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
            // Источник регистрации: пусто у всех, кто завёл аккаунт
            // до того, как мы начали запоминать метки
            'source' => collect($user->only(Attribution::FIELDS))
                ->mapWithKeys(fn (mixed $value, string $key) => [
                    Str::camel($key) => $value,
                ])
                ->all(),
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
                    'rating' => $p->rating,
                    'review' => $p->review,
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
     * Источник одной строкой для списка: метка и кампания, а если
     * рекламы не было — хотя бы сайт, с которого человек пришёл.
     */
    private function shortSource(User $user): ?string
    {
        if (filled($user->utm_source)) {
            return filled($user->utm_campaign)
                ? $user->utm_source.' / '.$user->utm_campaign
                : $user->utm_source;
        }

        if (filled($user->referrer)) {
            $host = parse_url($user->referrer, PHP_URL_HOST);

            return is_string($host) ? $host : null;
        }

        return null;
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
