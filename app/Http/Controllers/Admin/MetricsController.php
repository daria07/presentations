<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Метрики: откуда приходят люди и что из них выходит.
 *
 * Своя замена Яндекс.Метрике по самому важному: с какими метками
 * человек зарегистрировался (их запоминает App\Support\Attribution),
 * сделал ли презентацию и сколько заплатил. Сводка по источникам и
 * список людей, оба — по одним и тем же фильтрам.
 */
class MetricsController extends Controller
{
    /** Значение фильтра «без меток»: поле пустое */
    public const NONE = '__none';

    /** Поля меток, по которым можно фильтровать и группировать */
    private const UTM = ['source' => 'utm_source', 'medium' => 'utm_medium', 'campaign' => 'utm_campaign'];

    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('admin/Metrics', [
            'filters' => $filters,
            'options' => fn () => $this->options(),
            'totals' => fn () => $this->totals($filters),
            'groups' => fn () => $this->groups($filters),
            'users' => fn () => $this->users($filters),
        ]);
    }

    /** Тот же список людей таблицей — для Excel или Google Таблиц */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $tz = config('admin.timezone');

        $columns = [
            'Дата регистрации', 'Почта', 'Имя', 'utm_source', 'utm_medium', 'utm_campaign',
            'utm_content', 'utm_term', 'Клик (yclid/gclid)', 'Переход с', 'Точка входа',
            'Презентаций', 'Из них пробных', 'Из них оплаченных', 'Пробная использована',
            'Правил презентаций', 'Сохранений правок',
            'Оплат', 'Оплачено, ₽', 'Первая оплата', 'Остаток генераций',
            'Скидка: вариант', 'Скидку видел', 'Скидку нажал', 'Купил со скидкой',
        ];

        return response()->streamDownload(function () use ($filters, $columns, $tz) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // BOM — чтобы Excel открыл кириллицу без танцев с кодировкой
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns, ';');

            // Без сортировки по дате: lazyById листает по id, и чужой порядок
            // перед ним сбил бы страницы — строки терялись бы и повторялись
            $this->usersQuery($filters)->reorder()->lazyById(500, 'users.id', 'id')->each(function (User $u) use ($out, $tz) {
                fputcsv($out, [
                    $u->created_at?->timezone($tz)->format('Y-m-d H:i'),
                    $u->email,
                    $u->name,
                    $u->utm_source,
                    $u->utm_medium,
                    $u->utm_campaign,
                    $u->utm_content,
                    $u->utm_term,
                    $u->click_id,
                    $u->referrer,
                    $u->landing_url,
                    (int) $u->getAttribute('presentations_count'),
                    (int) $u->getAttribute('trial_presentations_count'),
                    (int) $u->getAttribute('paid_presentations_count'),
                    $u->trial_used ? 'да' : 'нет',
                    (int) $u->getAttribute('edited_presentations_count'),
                    (int) $u->getAttribute('edits_total'),
                    (int) $u->getAttribute('payments_count'),
                    number_format((int) $u->getAttribute('paid_total') / 100, 0, '.', ''),
                    $u->getAttribute('first_paid_at') ? Date::parse($u->getAttribute('first_paid_at'))->timezone($tz)->format('Y-m-d H:i') : '',
                    $u->credits,
                    $u->promo_variant,
                    $u->promo_shown_at?->timezone($tz)->format('Y-m-d H:i'),
                    $u->promo_clicked_at?->timezone($tz)->format('Y-m-d H:i'),
                    $u->discount_used_at?->timezone($tz)->format('Y-m-d H:i'),
                ], ';');
            });

            fclose($out);
        }, 'metrics-'.now(config('admin.timezone'))->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Фильтры из адреса — только известные значения.
     *
     * @return array{from: string, to: string, source: string, medium: string, campaign: string, paid: string, trial: string, discount: string, edited: string, search: string, sort: string}
     */
    private function filters(Request $request): array
    {
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) === 1
            ? (string) $request->query($key)
            : '';

        $paid = (string) $request->query('paid', '');
        $trial = (string) $request->query('trial', '');
        $discount = (string) $request->query('discount', '');
        $edited = (string) $request->query('edited', '');
        $sort = (string) $request->query('sort', '');

        return [
            'from' => $date('from'),
            'to' => $date('to'),
            'source' => mb_substr(trim((string) $request->query('source', '')), 0, 255),
            'medium' => mb_substr(trim((string) $request->query('medium', '')), 0, 255),
            'campaign' => mb_substr(trim((string) $request->query('campaign', '')), 0, 255),
            'paid' => in_array($paid, ['yes', 'no'], true) ? $paid : '',
            'trial' => in_array($trial, ['yes', 'no'], true) ? $trial : '',
            // Кнопка скидки: не видел, видел, нажал, купил со скидкой
            'discount' => in_array($discount, ['unseen', 'shown', 'clicked', 'used'], true) ? $discount : '',
            'edited' => in_array($edited, ['yes', 'no'], true) ? $edited : '',
            'search' => mb_substr(trim((string) $request->query('search', '')), 0, 100),
            'sort' => in_array($sort, ['new', 'revenue'], true) ? $sort : 'new',
        ];
    }

    /**
     * Пользователи под фильтры. Колонки users.* — с таблицей: ниже к
     * запросу присоединяются платежи и презентации.
     *
     * @param  array<string, string>  $filters
     * @return Builder<User>
     */
    private function filtered(array $filters): Builder
    {
        $tz = config('admin.timezone');
        $query = User::query();

        // Даты — сутки по часовому поясу админки, «по» включительно
        if ($filters['from'] !== '') {
            $query->where('users.created_at', '>=', Carbon::parse($filters['from'], $tz)->startOfDay()->utc());
        }

        if ($filters['to'] !== '') {
            $query->where('users.created_at', '<', Carbon::parse($filters['to'], $tz)->addDay()->startOfDay()->utc());
        }

        foreach (self::UTM as $key => $column) {
            $value = $filters[$key];

            if ($value === self::NONE) {
                $query->whereNull('users.'.$column);
            } elseif ($value !== '') {
                $query->where('users.'.$column, $value);
            }
        }

        $paid = fn ($q) => $q->where('status', PaymentStatus::Paid);

        if ($filters['paid'] === 'yes') {
            $query->whereHas('payments', $paid);
        } elseif ($filters['paid'] === 'no') {
            $query->whereDoesntHave('payments', $paid);
        }

        if ($filters['trial'] !== '') {
            $query->where('users.trial_used', $filters['trial'] === 'yes');
        }

        match ($filters['discount']) {
            'unseen' => $query->whereNull('users.promo_shown_at'),
            'shown' => $query->whereNotNull('users.promo_shown_at'),
            'clicked' => $query->whereNotNull('users.promo_clicked_at'),
            'used' => $query->whereNotNull('users.discount_used_at'),
            default => null,
        };

        // Правил хоть одну свою презентацию в редакторе
        $edited = fn ($q) => $q->where('edits_count', '>', 0);

        if ($filters['edited'] === 'yes') {
            $query->whereHas('presentations', $edited);
        } elseif ($filters['edited'] === 'no') {
            $query->whereDoesntHave('presentations', $edited);
        }

        if ($filters['search'] !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($filters['search'])).'%';

            $query->where(fn ($w) => $w
                ->whereRaw('lower(users.email) like ?', [$like])
                ->orWhereRaw('lower(users.name) like ?', [$like]));
        }

        return $query;
    }

    /**
     * Люди со всем, что о них известно: метки, презентации, оплаты.
     *
     * @param  array<string, string>  $filters
     * @return Builder<User>
     */
    private function usersQuery(array $filters): Builder
    {
        $paid = fn () => Payment::query()
            ->whereColumn('user_id', 'users.id')
            ->where('status', PaymentStatus::Paid);

        $query = $this->filtered($filters)
            ->select('users.*')
            ->withCount([
                'presentations',
                // Пробные, которые так и не открыли, и сделанные за купленные генерации
                'presentations as trial_presentations_count' => fn ($q) => $q->where('paid_with', 'trial'),
                'presentations as paid_presentations_count' => fn ($q) => $q->whereIn('paid_with', ['credit', 'unlocked']),
                'presentations as edited_presentations_count' => fn ($q) => $q->where('edits_count', '>', 0),
            ])
            ->withSum('presentations as edits_total', 'edits_count')
            // Подзапросами, а не join: два hasMany в join размножили бы строки
            ->addSelect([
                'payments_count' => $paid()->selectRaw('count(*)'),
                'paid_total' => $paid()->selectRaw('coalesce(sum(amount), 0)'),
                'first_paid_at' => $paid()->selectRaw('min(created_at)'),
                'last_seen' => Presentation::select('created_at')
                    ->whereColumn('user_id', 'users.id')
                    ->latest('created_at')
                    ->limit(1),
            ]);

        return $filters['sort'] === 'revenue'
            ? $query->orderByDesc('paid_total')->orderByDesc('users.id')
            : $query->orderByDesc('users.created_at')->orderByDesc('users.id');
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function users(array $filters): array
    {
        $tz = config('admin.timezone');

        $page = $this->usersQuery($filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'email' => $u->email,
                'name' => $u->name,
                'createdAt' => $u->created_at?->toIso8601String(),
                'source' => $u->utm_source,
                'medium' => $u->utm_medium,
                'campaign' => $u->utm_campaign,
                'content' => $u->utm_content,
                'term' => $u->utm_term,
                'clickId' => $u->click_id,
                'referrer' => $u->referrer,
                'referrerHost' => $u->referrer ? parse_url($u->referrer, PHP_URL_HOST) : null,
                'landing' => $u->landing_url,
                'presentations' => (int) $u->getAttribute('presentations_count'),
                'trialPresentations' => (int) $u->getAttribute('trial_presentations_count'),
                'paidPresentations' => (int) $u->getAttribute('paid_presentations_count'),
                'trialUsed' => (bool) $u->trial_used,
                'editedPresentations' => (int) $u->getAttribute('edited_presentations_count'),
                'edits' => (int) $u->getAttribute('edits_total'),
                'discount' => [
                    'variant' => $u->promo_variant,
                    'shownAt' => $u->promo_shown_at?->toIso8601String(),
                    'clickedAt' => $u->promo_clicked_at?->toIso8601String(),
                    'usedAt' => $u->discount_used_at?->toIso8601String(),
                ],
                'payments' => (int) $u->getAttribute('payments_count'),
                'paid' => (int) $u->getAttribute('paid_total'),
                'firstPaidAt' => $u->getAttribute('first_paid_at')
                    ? Date::parse($u->getAttribute('first_paid_at'))->toIso8601String()
                    : null,
                'lastSeen' => $u->getAttribute('last_seen')
                    ? Date::parse($u->getAttribute('last_seen'))->toIso8601String()
                    : null,
                'credits' => $u->credits,
            ]);

        return [
            'items' => $page->items(),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'prevUrl' => $page->previousPageUrl(),
            'nextUrl' => $page->nextPageUrl(),
        ];
    }

    /**
     * Запрос с присоединёнными итогами по человеку: сделал ли
     * презентацию, сколько заплатил. По одной строке на человека.
     *
     * @param  array<string, string>  $filters
     * @return Builder<User>
     */
    private function withOutcomes(array $filters): Builder
    {
        $paid = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->selectRaw('user_id, sum(amount) as amount')
            ->groupBy('user_id');

        $made = Presentation::query()
            ->selectRaw('user_id, count(*) as made')
            ->groupBy('user_id');

        return $this->filtered($filters)
            ->leftJoinSub($paid, 'pa', 'pa.user_id', '=', 'users.id')
            ->leftJoinSub($made, 'pr', 'pr.user_id', '=', 'users.id');
    }

    /**
     * Итог по выборке.
     *
     * @param  array<string, string>  $filters
     * @return array{users: int, made: int, paying: int, revenue: int}
     */
    private function totals(array $filters): array
    {
        $row = $this->withOutcomes($filters)
            ->toBase()
            ->selectRaw('count(*) as users, count(pr.user_id) as made, count(pa.user_id) as paying, coalesce(sum(pa.amount), 0) as revenue')
            ->first();

        return [
            'users' => (int) ($row->users ?? 0),
            'made' => (int) ($row->made ?? 0),
            'paying' => (int) ($row->paying ?? 0),
            'revenue' => (int) ($row->revenue ?? 0),
        ];
    }

    /**
     * Сводка по источникам: источник / канал / кампания, сколько
     * пришло, сколько сделали презентацию, сколько заплатили и сколько.
     *
     * @param  array<string, string>  $filters
     * @return list<array{source: string|null, medium: string|null, campaign: string|null, users: int, made: int, paying: int, revenue: int}>
     */
    private function groups(array $filters): array
    {
        return array_values($this->withOutcomes($filters)
            ->toBase()
            ->selectRaw('users.utm_source as source, users.utm_medium as medium, users.utm_campaign as campaign')
            ->selectRaw('count(*) as users, count(pr.user_id) as made, count(pa.user_id) as paying, coalesce(sum(pa.amount), 0) as revenue')
            ->groupBy('users.utm_source', 'users.utm_medium', 'users.utm_campaign')
            ->orderByDesc('users')
            ->limit(200)
            ->get()
            ->map(fn ($row) => [
                // null — без меток; остальное приходит строкой
                'source' => $row->source === null ? null : (string) $row->source,
                'medium' => $row->medium === null ? null : (string) $row->medium,
                'campaign' => $row->campaign === null ? null : (string) $row->campaign,
                'users' => (int) $row->users,
                'made' => (int) $row->made,
                'paying' => (int) $row->paying,
                'revenue' => (int) $row->revenue,
            ])
            ->values()
            ->all());
    }

    /**
     * Значения для выпадающих списков фильтров.
     *
     * @return array<string, list<string>>
     */
    private function options(): array
    {
        $values = fn (string $column) => User::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->limit(300)
            ->pluck($column)
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        // array_values — чтобы список был списком и для phpstan
        return collect(self::UTM)->map(fn (string $column) => array_values($values($column)))->all();
    }
}
