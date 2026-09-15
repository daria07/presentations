<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DayColumns from '@/components/admin/DayColumns.vue';

type Day = { date: string; users: number; presentations: number };

const props = defineProps<{
    cards: {
        users: number;
        usersWeek: number;
        presentations: number;
        presentationsWeek: number;
        ready: number;
        failed: number;
        revenue: number;
        revenueMonth: number;
        payingUsers: number;
        cost: number;
        costMonth: number;
    };
    statuses: { key: string; label: string; total: number }[];
    days: Day[];
    recent: {
        id: number;
        title: string;
        status: string;
        statusLabel: string;
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

/* Доля тех, кто дошёл до оплаты — главная цифра для продукта */
const conversion = computed(() =>
    props.cards.users
        ? ((props.cards.payingUsers / props.cards.users) * 100).toFixed(1)
        : '0',
);

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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Сводка</h1>
                <p class="text-muted-foreground text-sm">
                    Всё, что накопилось в базе, без прикрас
                </p>
            </div>
            <Link
                href="/admin/users"
                class="border-border hover:bg-secondary cursor-pointer rounded-lg border px-3 py-2 text-sm transition-colors"
            >
                Все пользователи
            </Link>
        </div>

        <!-- Цифры плитками, а не столбиками: это отдельные величины,
             сравнивать их между собой нечем -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Пользователей</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ cards.users }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    +{{ cards.usersWeek }} за неделю
                </p>
            </div>

            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Презентаций</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ cards.presentations }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    +{{ cards.presentationsWeek }} за неделю · {{ cards.failed }} с ошибкой
                </p>
            </div>

            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Выручка</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ rubles(cards.revenue) }} ₽
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    {{ rubles(cards.revenueMonth) }} ₽ за 30 дней ·
                    {{ cards.payingUsers }} платят ({{ conversion }}%)
                </p>
            </div>

            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Расход на модель</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    ${{ dollars(cards.cost) }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    ${{ dollars(cards.costMonth) }} за 30 дней
                </p>
            </div>
        </div>

        <!-- Два ряда, а не один график с двумя шкалами: величины разного
             порядка, и вторая шкала справа позволила бы нарисовать любую
             историю -->
        <div class="border-border bg-card grid gap-8 rounded-xl border p-5 lg:grid-cols-2">
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

        <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
            <div class="border-border bg-card space-y-3 rounded-xl border p-5">
                <h2 class="text-sm font-medium">Статусы презентаций</h2>

                <div
                    v-for="s in statuses"
                    :key="s.key"
                    class="flex items-center gap-3"
                >
                    <span class="text-muted-foreground w-32 shrink-0 text-xs">
                        {{ s.label }}
                    </span>
                    <div class="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                        <div
                            class="bg-series-1 h-full rounded-full"
                            :style="{ width: (s.total / maxStatus) * 100 + '%' }"
                        />
                    </div>
                    <span class="w-10 shrink-0 text-right text-xs tabular-nums">
                        {{ s.total }}
                    </span>
                </div>
            </div>

            <div class="border-border bg-card rounded-xl border p-5">
                <h2 class="mb-3 text-sm font-medium">Последние генерации</h2>

                <div class="divide-border divide-y">
                    <div
                        v-for="item in recent"
                        :key="item.id"
                        class="flex items-baseline justify-between gap-4 py-2 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate">{{ item.title }}</p>
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
                        <div class="text-muted-foreground shrink-0 text-right text-xs">
                            <p>{{ item.statusLabel }}</p>
                            <p class="tabular-nums">{{ when(item.createdAt) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-muted-foreground text-xs">
            Выручка в рублях, расход на модель в долларах — это разные валюты,
            поэтому маржу здесь не считаем: курс меняется, и красивая цифра
            быстро стала бы неправдой.
        </p>
    </div>
</template>
