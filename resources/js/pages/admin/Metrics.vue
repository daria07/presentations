<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';

type Filters = {
    from: string;
    to: string;
    source: string;
    medium: string;
    campaign: string;
    content: string;
    paid: '' | 'yes' | 'no';
    trial: '' | 'yes' | 'no';
    discount: '' | 'unseen' | 'shown' | 'clicked' | 'used';
    edited: '' | 'yes' | 'no';
    search: string;
    sort: 'new' | 'revenue';
};

type Group = {
    source: string | null;
    medium: string | null;
    campaign: string | null;
    users: number;
    made: number;
    paying: number;
    revenue: number;
};

type Row = {
    id: number;
    email: string;
    name: string;
    createdAt: string | null;
    source: string | null;
    medium: string | null;
    campaign: string | null;
    content: string | null;
    term: string | null;
    clickId: string | null;
    referrer: string | null;
    referrerHost: string | null;
    landing: string | null;
    presentations: number;
    trialPresentations: number;
    paidPresentations: number;
    trialUsed: boolean;
    editedPresentations: number;
    edits: number;
    discount: {
        variant: string | null;
        shownAt: string | null;
        clickedAt: string | null;
        usedAt: string | null;
    };
    payments: number;
    paid: number;
    firstPaidAt: string | null;
    lastSeen: string | null;
    credits: number;
};

const props = defineProps<{
    filters: Filters;
    options: {
        source: string[];
        medium: string[];
        campaign: string[];
        content: string[];
    };
    totals: {
        users: number;
        made: number;
        paying: number;
        revenue: number;
        payments: number;
        repeaters: number;
        daysToPay: number | null;
    };
    cohorts: {
        week: string;
        users: number;
        paying: number;
        revenue: number;
        repeaters: number;
    }[];
    groups: Group[];
    users: {
        items: Row[];
        total: number;
        currentPage: number;
        lastPage: number;
        prevUrl: string | null;
        nextUrl: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Админка', href: '/admin' },
            { title: 'Метрики', href: '/admin/metrics' },
        ],
    },
});

/* Значение «без меток» — то же, что MetricsController::NONE */
const NONE = '__none';

const form = reactive<Filters>({ ...props.filters });

/* Пустые поля в адрес не пишем: ссылку на выборку удобно пересылать */
function query(values: Filters): Record<string, string> {
    return Object.fromEntries(
        Object.entries(values).filter(
            ([key, value]) =>
                value !== '' && !(key === 'sort' && value === 'new'),
        ),
    );
}

function apply() {
    router.get('/admin/metrics', query(form), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/* Списки и даты — сразу, поиск — через полсекунды после последней буквы */
watch(
    () => [
        form.from,
        form.to,
        form.source,
        form.medium,
        form.campaign,
        form.content,
        form.paid,
        form.trial,
        form.discount,
        form.edited,
        form.sort,
    ],
    apply,
);

let timer: number | undefined;
watch(
    () => form.search,
    () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(apply, 500);
    },
);

/* Быстрые периоды: даты — по Москве, как и вся админка */
function day(offset: number): string {
    const date = new Date(
        new Date().toLocaleString('en-US', { timeZone: 'Europe/Moscow' }),
    );
    date.setDate(date.getDate() - offset);

    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

const PRESETS = [
    { label: 'Сегодня', from: () => day(0), to: () => day(0) },
    { label: 'Вчера', from: () => day(1), to: () => day(1) },
    { label: '7 дней', from: () => day(6), to: () => '' },
    { label: '30 дней', from: () => day(29), to: () => '' },
    { label: 'Всё время', from: () => '', to: () => '' },
];

function preset(p: (typeof PRESETS)[number]) {
    form.from = p.from();
    form.to = p.to();
}

function isPreset(p: (typeof PRESETS)[number]) {
    return form.from === p.from() && form.to === p.to();
}

function reset() {
    Object.assign(form, {
        from: '',
        to: '',
        source: '',
        medium: '',
        campaign: '',
        content: '',
        paid: '',
        trial: '',
        discount: '',
        edited: '',
        search: '',
        sort: 'new',
    });
}

/* Клик по строке сводки — та же выборка в списке людей ниже */
function pick(g: Group) {
    form.source = g.source ?? NONE;
    form.medium = g.medium ?? NONE;
    form.campaign = g.campaign ?? NONE;
    form.content = '';
    document.getElementById('people')?.scrollIntoView({ behavior: 'smooth' });
}

const exportUrl = computed(
    () =>
        '/admin/metrics/export?' + new URLSearchParams(query(form)).toString(),
);

const rubles = (kopecks: number) =>
    (kopecks / 100).toLocaleString('ru-RU', { maximumFractionDigits: 0 });

/* Деление для LTV и среднего чека: на ноль — прочерк */
const per = (kopecks: number, count: number) =>
    count ? rubles(kopecks / count) + ' ₽' : '—';

/* Неделя когорты: «13–19 окт» */
function week(start: string): string {
    const from = new Date(start + 'T00:00:00');
    const to = new Date(from);
    to.setDate(to.getDate() + 6);

    const day = (d: Date) => d.getDate();
    const month = (d: Date) =>
        d.toLocaleDateString('ru-RU', { month: 'short' }).replace('.', '');

    return from.getMonth() === to.getMonth()
        ? `${day(from)}–${day(to)} ${month(to)}`
        : `${day(from)} ${month(from)} – ${day(to)} ${month(to)}`;
}

const percent = (part: number, whole: number) =>
    whole ? ((part / whole) * 100).toFixed(1).replace('.', ',') + '%' : '—';

function when(iso: string | null): string {
    if (!iso) return '—';

    return new Date(iso).toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const label = (value: string | null) => value ?? 'без меток';

const selectClass =
    'border-border bg-background focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-sm outline-none focus:ring-2';
</script>

<template>
    <Head title="Метрики — админка" />

    <div class="w-full space-y-6 px-4 py-8 lg:px-8">
        <PageHeader title="Метрики">
            <template #meta>
                Метки, с которыми люди пришли на сайт, и что из них вышло
            </template>
            <template #actions>
                <a
                    :href="exportUrl"
                    class="border-border hover:bg-secondary cursor-pointer rounded-lg border px-3 py-1.5 text-sm transition-colors"
                >
                    Скачать CSV
                </a>
            </template>
        </PageHeader>

        <!-- Фильтры -->
        <div class="border-border bg-card space-y-3 rounded-xl border p-4">
            <div class="flex flex-wrap items-center gap-1.5">
                <button
                    v-for="p in PRESETS"
                    :key="p.label"
                    type="button"
                    class="cursor-pointer rounded-md border px-2.5 py-1 text-xs transition-colors"
                    :class="
                        isPreset(p)
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border hover:bg-secondary'
                    "
                    @click="preset(p)"
                >
                    {{ p.label }}
                </button>
                <span class="text-muted-foreground ml-2 text-xs">с</span>
                <input
                    v-model="form.from"
                    type="date"
                    class="border-border bg-background rounded-md border px-2 py-1 text-xs"
                />
                <span class="text-muted-foreground text-xs">по</span>
                <input
                    v-model="form.to"
                    type="date"
                    class="border-border bg-background rounded-md border px-2 py-1 text-xs"
                />
            </div>

            <div
                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5"
            >
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Источник</span>
                    <select v-model="form.source" :class="selectClass">
                        <option value="">Все</option>
                        <option :value="NONE">Без меток</option>
                        <option v-for="v in options.source" :key="v" :value="v">
                            {{ v }}
                        </option>
                    </select>
                </label>
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Канал</span>
                    <select v-model="form.medium" :class="selectClass">
                        <option value="">Все</option>
                        <option :value="NONE">Без меток</option>
                        <option v-for="v in options.medium" :key="v" :value="v">
                            {{ v }}
                        </option>
                    </select>
                </label>
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Кампания</span>
                    <select v-model="form.campaign" :class="selectClass">
                        <option value="">Все</option>
                        <option :value="NONE">Без меток</option>
                        <option
                            v-for="v in options.campaign"
                            :key="v"
                            :value="v"
                        >
                            {{ v }}
                        </option>
                    </select>
                </label>
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Объявление</span>
                    <select v-model="form.content" :class="selectClass">
                        <option value="">Все</option>
                        <option :value="NONE">Без меток</option>
                        <option
                            v-for="v in options.content"
                            :key="v"
                            :value="v"
                        >
                            {{ v }}
                        </option>
                    </select>
                </label>
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Оплата</span>
                    <select v-model="form.paid" :class="selectClass">
                        <option value="">Все</option>
                        <option value="yes">Платили</option>
                        <option value="no">Не платили</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Пробная</span>
                    <select v-model="form.trial" :class="selectClass">
                        <option value="">Все</option>
                        <option value="yes">Делали пробную</option>
                        <option value="no">Не делали</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Скидка</span>
                    <select v-model="form.discount" :class="selectClass">
                        <option value="">Все</option>
                        <option value="unseen">Не видели</option>
                        <option value="shown">Видели</option>
                        <option value="clicked">Нажали</option>
                        <option value="used">Купили со скидкой</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Редактор</span>
                    <select v-model="form.edited" :class="selectClass">
                        <option value="">Все</option>
                        <option value="yes">Правили</option>
                        <option value="no">Не правили</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Порядок</span>
                    <select v-model="form.sort" :class="selectClass">
                        <option value="new">Сначала новые</option>
                        <option value="revenue">Больше заплатили</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs">
                    <span class="text-muted-foreground">Почта или имя</span>
                    <input
                        v-model="form.search"
                        type="search"
                        placeholder="Поиск…"
                        :class="selectClass"
                    />
                </label>
            </div>

            <button
                type="button"
                class="text-muted-foreground hover:text-foreground cursor-pointer text-xs underline-offset-2 hover:underline"
                @click="reset"
            >
                Сбросить фильтры
            </button>
        </div>

        <!-- Итог по выборке -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Регистраций</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ totals.users }}
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Сделали презентацию</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ totals.made }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{ percent(totals.made, totals.users) }} от регистраций
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Заплатили</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ totals.paying }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{ percent(totals.paying, totals.users) }} от регистраций
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Выручка</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ rubles(totals.revenue) }} ₽
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">
                    Выручка на регистрацию
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{
                        totals.users
                            ? rubles(totals.revenue / totals.users)
                            : '—'
                    }}
                    ₽
                </p>
            </div>
        </div>

        <!-- LTV: сколько в среднем приносит человек за всё время -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">LTV платящего</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ per(totals.revenue, totals.paying) }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    сколько в среднем принёс один заплативший
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Средний чек</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ per(totals.revenue, totals.payments) }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    оплат: {{ totals.payments }}
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Купили повторно</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ percent(totals.repeaters, totals.paying) }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    {{ totals.repeaters }} из {{ totals.paying }} платящих
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">До первой оплаты</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{
                        totals.daysToPay === null
                            ? '—'
                            : totals.daysToPay.toLocaleString('ru-RU') + ' дн.'
                    }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    в среднем от регистрации
                </p>
            </div>
        </div>

        <!-- Когорты: люди по неделе регистрации и что они принесли на сегодня -->
        <div class="border-border bg-card min-w-0 rounded-xl border p-4 sm:p-5">
            <div
                class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
            >
                <h2 class="text-sm font-medium">
                    Когорты по неделе регистрации
                </h2>
                <p class="text-muted-foreground text-xs">
                    Выручка — за всё время до сегодня. Свежие недели ещё
                    дозревают
                </p>
            </div>

            <div v-if="cohorts.length" class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-sm">
                    <thead>
                        <tr
                            class="text-muted-foreground border-border border-b text-left text-xs"
                        >
                            <th class="py-2 pr-4 font-medium">Неделя</th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Регистраций
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Заплатили
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Конверсия
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Повторно
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Выручка
                            </th>
                            <th
                                class="py-2 pr-4 text-right font-medium"
                                title="Выручка на одну регистрацию"
                            >
                                На регистрацию
                            </th>
                            <th class="py-2 text-right font-medium">
                                LTV платящего
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        <tr v-for="c in cohorts" :key="c.week">
                            <td class="py-2 pr-4 whitespace-nowrap">
                                {{ week(c.week) }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ c.users }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ c.paying }}
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-medium tabular-nums"
                            >
                                {{ percent(c.paying, c.users) }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ c.repeaters }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ rubles(c.revenue) }} ₽
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ per(c.revenue, c.users) }}
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ per(c.revenue, c.paying) }}
                            </td>
                        </tr>
                    </tbody>
                    <!-- Итог — по всей выборке под фильтры -->
                    <tfoot>
                        <tr class="border-border border-t-2 font-semibold">
                            <td class="py-2 pr-4">Итого</td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ totals.users }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ totals.paying }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ percent(totals.paying, totals.users) }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ totals.repeaters }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ rubles(totals.revenue) }} ₽
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ per(totals.revenue, totals.users) }}
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ per(totals.revenue, totals.paying) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p v-else class="text-muted-foreground py-4 text-sm">
                Под фильтры никто не попал
            </p>
        </div>

        <!-- Сводка по источникам -->
        <div class="border-border bg-card min-w-0 rounded-xl border p-4 sm:p-5">
            <div
                class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
            >
                <h2 class="text-sm font-medium">По источникам</h2>
                <p class="text-muted-foreground text-xs">
                    Нажмите на строку — ниже останутся только эти люди
                </p>
            </div>

            <div v-if="groups.length" class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr
                            class="text-muted-foreground border-border border-b text-left text-xs"
                        >
                            <th class="py-2 pr-4 font-medium">Источник</th>
                            <th class="py-2 pr-4 font-medium">Канал</th>
                            <th class="py-2 pr-4 font-medium">Кампания</th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Регистраций
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                С презентацией
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Заплатили
                            </th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Конверсия
                            </th>
                            <th class="py-2 text-right font-medium">Выручка</th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        <tr
                            v-for="(g, i) in groups"
                            :key="i"
                            class="hover:bg-secondary/60 cursor-pointer"
                            @click="pick(g)"
                        >
                            <td
                                class="py-2 pr-4"
                                :class="g.source ? '' : 'text-muted-foreground'"
                            >
                                {{ label(g.source) }}
                            </td>
                            <td
                                class="py-2 pr-4"
                                :class="g.medium ? '' : 'text-muted-foreground'"
                            >
                                {{ g.medium ?? '—' }}
                            </td>
                            <td
                                class="py-2 pr-4"
                                :class="
                                    g.campaign ? '' : 'text-muted-foreground'
                                "
                            >
                                {{ g.campaign ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ g.users }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ g.made }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ g.paying }}
                            </td>
                            <td
                                class="py-2 pr-4 text-right font-medium tabular-nums"
                            >
                                {{ percent(g.paying, g.users) }}
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ rubles(g.revenue) }} ₽
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-border border-t-2 font-semibold">
                            <td class="py-2 pr-4" colspan="3">Итого</td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ totals.users }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ totals.made }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ totals.paying }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">
                                {{ percent(totals.paying, totals.users) }}
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ rubles(totals.revenue) }} ₽
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p v-else class="text-muted-foreground py-4 text-sm">
                Под фильтры никто не попал
            </p>
        </div>

        <!-- Люди -->
        <div
            id="people"
            class="border-border bg-card min-w-0 scroll-mt-4 rounded-xl border p-4 sm:p-5"
        >
            <div
                class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
            >
                <h2 class="text-sm font-medium">Пользователи</h2>
                <p class="text-muted-foreground text-xs tabular-nums">
                    {{ users.total }}
                </p>
            </div>

            <div v-if="users.items.length" class="overflow-x-auto">
                <table class="w-full min-w-[1280px] text-sm">
                    <thead>
                        <tr
                            class="text-muted-foreground border-border border-b text-left text-xs"
                        >
                            <th class="py-2 pr-4 font-medium">Регистрация</th>
                            <th class="py-2 pr-4 font-medium">Пользователь</th>
                            <th class="py-2 pr-4 font-medium">Метки</th>
                            <th class="py-2 pr-4 font-medium">
                                Переход и вход
                            </th>
                            <th class="py-2 pr-4 font-medium">Презентации</th>
                            <th class="py-2 pr-4 font-medium">Скидка</th>
                            <th class="py-2 pr-4 text-right font-medium">
                                Оплаты
                            </th>
                            <th
                                class="py-2 text-right font-medium"
                                title="Генераций на счету"
                            >
                                Осталось
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y align-top">
                        <tr v-for="u in users.items" :key="u.id">
                            <td
                                class="py-2.5 pr-4 text-xs whitespace-nowrap tabular-nums"
                            >
                                {{ when(u.createdAt) }}
                                <p
                                    v-if="u.lastSeen"
                                    class="text-muted-foreground"
                                    title="Последняя презентация"
                                >
                                    был: {{ when(u.lastSeen) }}
                                </p>
                            </td>
                            <td class="max-w-[220px] py-2.5 pr-4">
                                <Link
                                    :href="`/admin/users/${u.id}`"
                                    class="block truncate hover:underline"
                                    >{{ u.email }}</Link
                                >
                                <p
                                    class="text-muted-foreground truncate text-xs"
                                >
                                    {{ u.name }}
                                </p>
                            </td>
                            <td class="max-w-[260px] py-2.5 pr-4 text-xs">
                                <template
                                    v-if="u.source || u.medium || u.campaign"
                                >
                                    <p class="truncate">
                                        <span class="text-muted-foreground"
                                            >source:</span
                                        >
                                        {{ u.source ?? '—' }}
                                    </p>
                                    <p class="truncate">
                                        <span class="text-muted-foreground"
                                            >medium:</span
                                        >
                                        {{ u.medium ?? '—' }}
                                    </p>
                                    <p class="truncate">
                                        <span class="text-muted-foreground"
                                            >campaign:</span
                                        >
                                        {{ u.campaign ?? '—' }}
                                    </p>
                                    <p v-if="u.content" class="truncate">
                                        <span class="text-muted-foreground"
                                            >content:</span
                                        >
                                        {{ u.content }}
                                    </p>
                                    <p v-if="u.term" class="truncate">
                                        <span class="text-muted-foreground"
                                            >term:</span
                                        >
                                        {{ u.term }}
                                    </p>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >без меток</span
                                >
                                <p
                                    v-if="u.clickId"
                                    class="text-muted-foreground truncate"
                                    :title="u.clickId"
                                >
                                    клик: {{ u.clickId }}
                                </p>
                            </td>
                            <td class="max-w-[260px] py-2.5 pr-4 text-xs">
                                <p class="truncate" :title="u.referrer ?? ''">
                                    <span class="text-muted-foreground"
                                        >с:</span
                                    >
                                    {{ u.referrerHost ?? '—' }}
                                </p>
                                <p
                                    class="text-muted-foreground truncate"
                                    :title="u.landing ?? ''"
                                >
                                    {{ u.landing ?? '' }}
                                </p>
                            </td>
                            <td class="py-2.5 pr-4 text-xs whitespace-nowrap">
                                <p class="tabular-nums">
                                    всего {{ u.presentations }}
                                </p>
                                <p class="text-muted-foreground tabular-nums">
                                    оплаченных {{ u.paidPresentations
                                    }}<template v-if="u.trialPresentations"
                                        >, пробных
                                        {{ u.trialPresentations }}</template
                                    >
                                </p>
                                <p
                                    :class="
                                        u.trialUsed
                                            ? ''
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        u.trialUsed
                                            ? 'пробная сделана'
                                            : 'пробную не делал'
                                    }}
                                </p>
                                <p
                                    :class="
                                        u.editedPresentations
                                            ? ''
                                            : 'text-muted-foreground'
                                    "
                                >
                                    <template v-if="u.editedPresentations">
                                        правил {{ u.editedPresentations }},
                                        сохранений {{ u.edits }}
                                    </template>
                                    <template v-else>не правил</template>
                                </p>
                            </td>
                            <td class="py-2.5 pr-4 text-xs whitespace-nowrap">
                                <template v-if="u.discount.shownAt">
                                    <p>
                                        видел<span
                                            v-if="u.discount.variant"
                                            class="text-muted-foreground"
                                        >
                                            · текст
                                            {{ u.discount.variant }}</span
                                        >
                                    </p>
                                    <p
                                        :class="
                                            u.discount.clickedAt
                                                ? ''
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            u.discount.clickedAt
                                                ? 'нажал ' +
                                                  when(u.discount.clickedAt)
                                                : 'не нажимал'
                                        }}
                                    </p>
                                    <p
                                        v-if="u.discount.usedAt"
                                        class="font-medium text-emerald-700 dark:text-emerald-400"
                                    >
                                        купил со скидкой
                                    </p>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >не видел</span
                                >
                            </td>
                            <td
                                class="py-2.5 pr-4 text-right whitespace-nowrap tabular-nums"
                            >
                                <template v-if="u.payments">
                                    {{ rubles(u.paid) }} ₽
                                    <p class="text-muted-foreground text-xs">
                                        {{ u.payments }} шт., первая
                                        {{ when(u.firstPaidAt) }}
                                    </p>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="py-2.5 text-right tabular-nums">
                                {{ u.credits }}
                            </td>
                        </tr>
                    </tbody>
                    <!-- Итог — по всем людям под фильтры, не только
                         по этой странице списка -->
                    <tfoot>
                        <tr class="border-border border-t-2 font-semibold">
                            <td class="py-2.5 pr-4" colspan="6">
                                Итого: {{ totals.users }}
                                <span class="text-muted-foreground font-normal"
                                    >— по всем страницам списка</span
                                >
                            </td>
                            <td
                                class="py-2.5 pr-4 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ rubles(totals.revenue) }} ₽
                                <p
                                    class="text-muted-foreground text-xs font-normal"
                                >
                                    {{ totals.payments }} шт.
                                </p>
                            </td>
                            <td class="py-2.5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p v-else class="text-muted-foreground py-4 text-sm">
                Под фильтры никто не попал
            </p>

            <div
                v-if="users.lastPage > 1"
                class="border-border mt-3 flex items-center justify-between border-t pt-3 text-sm"
            >
                <Link
                    v-if="users.prevUrl"
                    :href="users.prevUrl"
                    :only="['users']"
                    preserve-scroll
                    preserve-state
                    class="border-border hover:bg-secondary cursor-pointer rounded-lg border px-3 py-1.5 transition-colors"
                >
                    Назад
                </Link>
                <span v-else />
                <span class="text-muted-foreground tabular-nums"
                    >{{ users.currentPage }} из {{ users.lastPage }}</span
                >
                <Link
                    v-if="users.nextUrl"
                    :href="users.nextUrl"
                    :only="['users']"
                    preserve-scroll
                    preserve-state
                    class="border-border hover:bg-secondary cursor-pointer rounded-lg border px-3 py-1.5 transition-colors"
                >
                    Вперёд
                </Link>
                <span v-else />
            </div>
        </div>
    </div>
</template>
