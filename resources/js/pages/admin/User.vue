<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import { slides } from '@/lib/plural';
import { computed } from 'vue';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        credits: number;
        trialUsed: boolean;
        verified: boolean;
        twoFactor: boolean;
        createdAt: string | null;
    };
    source: {
        utmSource: string | null;
        utmMedium: string | null;
        utmCampaign: string | null;
        utmContent: string | null;
        utmTerm: string | null;
        clickId: string | null;
        referrer: string | null;
        landingUrl: string | null;
    };
    presentations: {
        id: number;
        title: string;
        status: string;
        statusLabel: string;
        slides: number;
        theme: string | null;
        palette: string | null;
        rating: number | null;
        review: string | null;
        createdAt: string | null;
    }[];
    payments: {
        id: number;
        amount: number;
        currency: string;
        credits: number;
        status: string;
        statusLabel: string;
        provider: string;
        createdAt: string | null;
    }[];
    calls: {
        purpose: string;
        total: number;
        cost: number;
        input: number;
        output: number;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Админка', href: '/admin' },
            { title: 'Пользователи', href: '/admin/users' },
        ],
    },
});

const rubles = (kopecks: number) =>
    (kopecks / 100).toLocaleString('ru-RU', { maximumFractionDigits: 2 });

const dollars = (hundredths: number) =>
    (hundredths / 10000).toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const paidTotal = computed(() =>
    props.payments
        .filter((p) => p.status === 'paid')
        .reduce((sum, p) => sum + p.amount, 0),
);

const spentTotal = computed(() =>
    props.calls.reduce((sum, c) => sum + c.cost, 0),
);

const tokens = computed(() =>
    props.calls.reduce((sum, c) => sum + c.input + c.output, 0),
);

/* Источник регистрации: показываем только заполненное */
const SOURCE_LABELS: [keyof typeof props.source, string][] = [
    ['utmSource', 'Источник'],
    ['utmMedium', 'Канал'],
    ['utmCampaign', 'Кампания'],
    ['utmContent', 'Объявление'],
    ['utmTerm', 'Ключевое слово'],
    ['clickId', 'ID клика'],
    ['referrer', 'Переход с'],
    ['landingUrl', 'Точка входа'],
];

const source = computed(() =>
    SOURCE_LABELS.filter(([key]) => props.source?.[key]).map(
        ([key, label]) => ({ label, value: props.source[key] as string }),
    ),
);

const PURPOSE: Record<string, string> = {
    clarify: 'Уточняющие вопросы',
    brief: 'Конспект темы',
    outline: 'Структура',
    retry: 'Повтор',
};

function when(iso: string | null): string {
    if (!iso) return '—';

    return new Date(iso).toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="user.name" />

    <div class="w-full space-y-6 px-4 py-8 lg:px-8">
        <PageHeader :title="user.name">
            <template #meta>{{ user.email }}</template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Остаток генераций</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ user.credits }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{
                        user.trialUsed
                            ? 'проба использована'
                            : 'проба не использована'
                    }}
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Презентаций</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ presentations.length }}
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Оплачено</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ rubles(paidTotal) }} ₽
                </p>
            </div>
            <div class="border-border bg-card rounded-xl border p-4">
                <p class="text-muted-foreground text-xs">Стоил нам</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    ${{ dollars(spentTotal) }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs tabular-nums">
                    {{ tokens.toLocaleString('ru-RU') }} токенов
                </p>
            </div>
        </div>

        <div
            class="border-border bg-card text-muted-foreground flex flex-wrap gap-x-6 gap-y-1 rounded-xl border p-4 text-xs"
        >
            <span>Регистрация: {{ when(user.createdAt) }}</span>
            <span
                >Почта:
                {{ user.verified ? 'подтверждена' : 'не подтверждена' }}</span
            >
            <span
                >Двухфакторка:
                {{ user.twoFactor ? 'включена' : 'выключена' }}</span
            >
        </div>

        <div class="border-border bg-card min-w-0 rounded-xl border p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-medium">Источник регистрации</h2>

            <dl
                v-if="source.length"
                class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2"
            >
                <div
                    v-for="row in source"
                    :key="row.label"
                    class="flex min-w-0 items-baseline gap-2"
                >
                    <dt class="text-muted-foreground shrink-0 text-xs">
                        {{ row.label }}
                    </dt>
                    <dd class="min-w-0 truncate" :title="row.value">
                        {{ row.value }}
                    </dd>
                </div>
            </dl>
            <p v-else class="text-muted-foreground text-sm">
                Меток нет — прямой заход или регистрация до того, как мы начали
                их запоминать
            </p>
        </div>

        <!-- minmax(0, …): иначе длинное название презентации раздвигает
             колонку за край экрана -->
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <div
                class="border-border bg-card min-w-0 rounded-xl border p-4 sm:p-5"
            >
                <h2 class="mb-3 text-sm font-medium">Презентации</h2>

                <div v-if="presentations.length" class="divide-border divide-y">
                    <div
                        v-for="p in presentations"
                        :key="p.id"
                        class="flex items-baseline justify-between gap-4 py-2 text-sm"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate" :title="p.title">
                                {{ p.title }}
                            </p>
                            <p
                                class="text-muted-foreground text-xs tabular-nums"
                            >
                                {{ slides(p.slides) }} · {{ p.theme ?? '—' }} /
                                {{ p.palette ?? '—' }}
                                <template v-if="p.rating">
                                    ·
                                    <span class="text-amber-500"
                                        >★ {{ p.rating }}</span
                                    >
                                </template>
                            </p>
                            <p
                                v-if="p.review"
                                class="text-muted-foreground mt-1 text-xs break-words whitespace-pre-line"
                            >
                                «{{ p.review }}»
                            </p>
                        </div>
                        <div
                            class="text-muted-foreground shrink-0 text-right text-xs"
                        >
                            <p>{{ p.statusLabel }}</p>
                            <p class="tabular-nums">{{ when(p.createdAt) }}</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-muted-foreground py-4 text-sm">
                    Пока ничего не создавал
                </p>
            </div>

            <div class="min-w-0 space-y-6">
                <div class="border-border bg-card rounded-xl border p-4 sm:p-5">
                    <h2 class="mb-3 text-sm font-medium">Оплаты</h2>

                    <div v-if="payments.length" class="divide-border divide-y">
                        <div
                            v-for="p in payments"
                            :key="p.id"
                            class="flex items-baseline justify-between gap-3 py-2 text-sm"
                        >
                            <div>
                                <p class="tabular-nums">
                                    {{ rubles(p.amount) }} {{ p.currency }}
                                </p>
                                <p
                                    class="text-muted-foreground text-xs tabular-nums"
                                >
                                    +{{ p.credits }} генераций ·
                                    {{ when(p.createdAt) }}
                                </p>
                            </div>
                            <span
                                class="text-muted-foreground shrink-0 text-xs"
                            >
                                {{ p.statusLabel }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground py-4 text-sm">
                        Не платил
                    </p>
                </div>

                <div class="border-border bg-card rounded-xl border p-4 sm:p-5">
                    <h2 class="mb-3 text-sm font-medium">Обращения к модели</h2>

                    <div v-if="calls.length" class="divide-border divide-y">
                        <div
                            v-for="c in calls"
                            :key="c.purpose"
                            class="flex items-baseline justify-between gap-3 py-2 text-sm"
                        >
                            <span class="min-w-0 truncate">{{
                                PURPOSE[c.purpose] ?? c.purpose
                            }}</span>
                            <span
                                class="text-muted-foreground shrink-0 text-xs tabular-nums"
                            >
                                {{ c.total }} раз · ${{ dollars(c.cost) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground py-4 text-sm">
                        Вызовов не было
                    </p>
                </div>
            </div>
        </div>

        <Link
            href="/admin/users"
            class="text-muted-foreground hover:text-foreground cursor-pointer text-sm transition-colors"
        >
            ← Ко всем пользователям
        </Link>
    </div>
</template>
