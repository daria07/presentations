<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import DayColumns from '@/components/admin/DayColumns.vue';

type Day = { date: string; users: number; presentations: number };

type Period = 'all' | 'today' | 'yesterday' | 'week';

type Totals = {
    users: number;
    presentations: number;
    failed: number;
    revenue: number;
    payments: number;
    payingUsers: number;
    cost: number;
};

const props = defineProps<{
    period: Period;
    cards: Totals;
    /* Тот же отрезок перед выбранным; у «всего времени» его нет */
    previous: Totals | null;
    statuses: { key: string; label: string; total: number }[];
    days: Day[];
    reviews: {
        average: number | null;
        total: number;
        latest: {
            id: number;
            title: string;
            rating: number;
            review: string | null;
            reviewedAt: string | null;
            user: { id: number; name: string; email: string } | null;
        }[];
    };
    recent: {
        id: number;
        title: string;
        status: string;
        statusLabel: string;
        rating: number | null;
        createdAt: string | null;
        user: { id: number; name: string; email: string } | null;
    }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Админка', href: '/admin' }] },
});

/* Суммы приходят в копейках, себестоимость — в сотых доли цента */
const rubles = (kopecks: number) =>
    (kopecks / 100).toLocaleString('ru-RU', { maximumFractionDigits: 0 });

const dollars = (hundredths: number) =>
    (hundredths / 10000).toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const registrations = computed(() =>
    props.days.map((d) => ({ date: d.date, value: d.users })),
);

const generations = computed(() =>
    props.days.map((d) => ({ date: d.date, value: d.presentations })),
);

const PERIODS: { key: Period; label: string; versus: string }[] = [
    { key: 'today', label: 'Сегодня', versus: 'вчера' },
    { key: 'yesterday', label: 'Вчера', versus: 'позавчера' },
    { key: 'week', label: '7 дней', versus: 'прошлые 7 дней' },
    { key: 'all', label: 'Всё время', versus: '' },
];

const periodLabel = computed(
    () => PERIODS.find((p) => p.key === props.period)?.label ?? '',
);

const versus = computed(
    () => PERIODS.find((p) => p.key === props.period)?.versus ?? '',
);

/* Доля тех, кто дошёл до оплаты — главная цифра для продукта. Считаем
   только за всё время: за день плательщики и регистрации — разные
   люди, и такой процент ничего не значит */
const conversion = computed(() =>
    props.cards.users
        ? ((props.cards.payingUsers / props.cards.users) * 100).toFixed(1)
        : '0',
);

/* Сравнение с прошлым отрезком: «вчера: 12 (+3)» */
function compare(key: keyof Totals, format = (n: number) => String(n)) {
    if (!props.previous) return '';

    const now = props.cards[key];
    const before = props.previous[key];
    const diff = now - before;
    const sign = diff > 0 ? '+' : diff < 0 ? '−' : '±';

    return `${versus.value}: ${format(before)} (${sign}${format(Math.abs(diff))})`;
}

const maxStatus = computed(() =>
    Math.max(1, ...props.statuses.map((s) => s.total)),
);

function when(iso: string | null): string {
    if (!iso) return '';

    return new Date(iso).toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Админка" />

    <div class="w-full space-y-8 px-4 py-8 lg:px-8">
        <PageHeader title="Сводка">
            <template #meta>Всё, что накопилось в базе, без прикрас</template>

            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link href="/admin/receipts">Чеки</Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link href="/admin/users">Все пользователи</Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Период: ссылками, а не состоянием на фронте — его видно
             в адресе, и сводку за вчера можно открыть по закладке -->
        <nav
            class="bg-muted inline-flex max-w-full flex-wrap gap-1 rounded-lg p-1"
            aria-label="Период"
        >
            <Link
                v-for="p in PERIODS"
                :key="p.key"
                :href="p.key === 'all' ? '/admin' : `/admin?period=${p.key}`"
                preserve-scroll
                preserve-state
                class="cursor-pointer rounded-md px-3 py-1.5 text-sm transition-colors"
                :class="
                    p.key === period
                        ? 'bg-card text-foreground font-medium shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                :aria-current="p.key === period ? 'page' : undefined"
            >
                {{ p.label }}
            </Link>
        </nav>

        <!-- Цифры плитками, а не столбиками: это отдельные величины,
             сравнивать их между собой нечем -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">
                    {{ previous ? 'Регистраций' : 'Пользователей' }}
                </p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ cards.users }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    {{ compare('users') }}
                </p>
            </div>

            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Презентаций</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ cards.presentations }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    {{ cards.failed }} с ошибкой<template v-if="previous">
                        · {{ compare('presentations') }}</template
                    >
                </p>
            </div>

            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Выручка</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ rubles(cards.revenue) }} ₽
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    <template v-if="previous">
                        {{ cards.payments }} оплат ·
                        {{ compare('revenue', (n) => rubles(n) + ' ₽') }}
                    </template>
                    <template v-else>
                        {{ cards.payingUsers }} платят ({{ conversion }}%)
                    </template>
                </p>
            </div>

            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Расход на модель</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    ${{ dollars(cards.cost) }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    {{ compare('cost', (n) => '$' + dollars(n)) }}
                </p>
            </div>
        </div>

        <!-- Два ряда, а не один график с двумя шкалами: величины разного
             порядка, и вторая шкала справа позволила бы нарисовать любую
             историю -->
        <div
            class="border-border bg-card grid min-w-0 gap-8 rounded-xl border p-4 sm:p-5 lg:grid-cols-2"
        >
            <DayColumns
                title="Регистрации по дням"
                :days="registrations"
                :tone="1"
            />
            <DayColumns
                title="Презентации по дням"
                :days="generations"
                :tone="2"
            />
        </div>

        <!-- minmax(0, …), а не просто 1fr: у 1fr нижняя граница — ширина
             самого длинного содержимого, и строка с заголовком в абзац
             раздвигала колонку за край экрана, не давая сработать truncate -->
        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]"
        >
            <div
                class="border-border bg-card min-w-0 space-y-3 rounded-xl border p-4 sm:p-5"
            >
                <h2 class="text-sm font-medium">
                    Статусы презентаций
                    <span
                        v-if="period !== 'all'"
                        class="text-muted-foreground font-normal"
                    >
                        · {{ periodLabel.toLowerCase() }}
                    </span>
                </h2>

                <div
                    v-for="s in statuses"
                    :key="s.key"
                    class="flex items-center gap-3"
                >
                    <span
                        class="text-muted-foreground w-28 shrink-0 text-xs sm:w-32"
                    >
                        {{ s.label }}
                    </span>
                    <div
                        class="bg-muted h-2 flex-1 overflow-hidden rounded-full"
                    >
                        <div
                            class="bg-series-1 h-full rounded-full"
                            :style="{
                                width: (s.total / maxStatus) * 100 + '%',
                            }"
                        />
                    </div>
                    <span class="w-10 shrink-0 text-right text-xs tabular-nums">
                        {{ s.total }}
                    </span>
                </div>
            </div>

            <div
                class="border-border bg-card min-w-0 rounded-xl border p-4 sm:p-5"
            >
                <h2 class="mb-3 text-sm font-medium">Последние генерации</h2>

                <div class="divide-border divide-y">
                    <div
                        v-for="item in recent"
                        :key="item.id"
                        class="flex items-baseline justify-between gap-4 py-2 text-sm"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate" :title="item.title">
                                {{ item.title }}
                            </p>
                            <p class="text-muted-foreground truncate text-xs">
                                <Link
                                    v-if="item.user"
                                    :href="`/admin/users/${item.user.id}`"
                                    class="cursor-pointer hover:underline"
                                >
                                    {{ item.user.email }}
                                </Link>
                                <span v-else>пользователь удалён</span>
                            </p>
                        </div>
                        <div
                            class="text-muted-foreground shrink-0 text-right text-xs"
                        >
                            <p>
                                <span
                                    v-if="item.rating"
                                    class="mr-1.5 text-amber-500"
                                    >★ {{ item.rating }}</span
                                >{{ item.statusLabel }}
                            </p>
                            <p class="tabular-nums">
                                {{ when(item.createdAt) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Отзывы: средняя за всё время и последние оценки с текстом -->
        <div class="border-border bg-card min-w-0 rounded-xl border p-4 sm:p-5">
            <div
                class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
            >
                <h2 class="text-sm font-medium">Оценки презентаций</h2>
                <p
                    v-if="reviews.average !== null"
                    class="text-muted-foreground text-xs tabular-nums"
                >
                    <span class="text-amber-500">★</span>
                    {{ reviews.average.toLocaleString('ru-RU') }} в среднем ·
                    {{ reviews.total }} оценок
                </p>
            </div>

            <div v-if="reviews.latest.length" class="divide-border divide-y">
                <div
                    v-for="r in reviews.latest"
                    :key="r.id"
                    class="flex items-baseline justify-between gap-4 py-2 text-sm"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate" :title="r.title">
                            <span class="mr-1.5 text-amber-500 tabular-nums"
                                >{{ '★'.repeat(r.rating)
                                }}<span class="text-muted-foreground/40">{{
                                    '★'.repeat(5 - r.rating)
                                }}</span></span
                            >
                            {{ r.title }}
                        </p>
                        <p
                            v-if="r.review"
                            class="mt-0.5 text-sm break-words whitespace-pre-line"
                        >
                            {{ r.review }}
                        </p>
                        <p class="text-muted-foreground truncate text-xs">
                            <Link
                                v-if="r.user"
                                :href="`/admin/users/${r.user.id}`"
                                class="cursor-pointer hover:underline"
                            >
                                {{ r.user.email }}
                            </Link>
                            <span v-else>пользователь удалён</span>
                        </p>
                    </div>
                    <p
                        class="text-muted-foreground shrink-0 text-xs tabular-nums"
                    >
                        {{ when(r.reviewedAt) }}
                    </p>
                </div>
            </div>
            <p v-else class="text-muted-foreground py-4 text-sm">
                Оценок пока нет
            </p>
        </div>

        <p class="text-muted-foreground text-xs">
            Выручка в рублях, расход на модель в долларах — это разные валюты,
            поэтому маржу здесь не считаем: курс меняется, и красивая цифра
            быстро стала бы неправдой.
        </p>
    </div>
</template>
