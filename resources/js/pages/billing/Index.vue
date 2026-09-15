<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Check,
    Clock,
    CircleDollarSign,
    Download,
    Info,
    Presentation,
    ReceiptText,
} from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';

type Package = {
    key: string;
    title: string;
    credits: number;
    amount: string;
    perCredit: string;
    note: string;
    popular: boolean;
};

type Payment = {
    id: number;
    amount: string;
    credits: number;
    status: string;
    statusLabel: string;
    date: string | null;
};

defineProps<{
    packages: Package[];
    credits: number;
    trialAvailable: boolean;
    history: Payment[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Тарифы', href: '/billing' }],
    },
});

const sending = ref<string | null>(null);

function buy(key: string) {
    sending.value = key;
    router.post('/billing/checkout', { package: key }, {
        onFinish: () => (sending.value = null),
    });
}

function formatDate(iso: string | null): string {
    if (!iso) return '';

    return new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
    });
}
</script>

<template>
    <Head title="Тарифы" />

    <!-- Поле содержимого как на остальных страницах: 38/56 из макета,
         без собственного ограничения по ширине — три карточки должны
         разойтись по всему полотну, а не жаться в колонку по центру -->
    <div class="w-full px-6 py-9 lg:px-14">
        <PageHeader title="Тарифы" dot>
            <template #meta>
                <template v-if="trialAvailable">
                    У вас есть бесплатная пробная генерация.
                </template>
                <template v-else>
                    Осталось генераций:
                    <strong class="text-foreground font-bold tabular-nums">
                        {{ credits }}
                    </strong>
                </template>
            </template>

            <template v-if="history.length" #actions>
                <a
                    href="#history"
                    class="border-rule bg-card text-muted-foreground hover:border-action hover:text-action flex cursor-pointer items-center gap-2 rounded-[10px] border px-3.5 py-2.5 text-sm font-semibold transition-colors"
                >
                    <ReceiptText class="size-4" />
                    История платежей
                </a>
            </template>
        </PageHeader>

        <!-- Размеры из макета: скругление 15, поля 26/28/24, промежуток 20.
             Ходовой тариф отличается белой подложкой, синей рамкой и тенью:
             это единственное место на странице, где акцент уместен -->
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="pack in packages"
                :key="pack.key"
                class="hover:border-action flex flex-col rounded-[15px] border px-7 pt-[26px] pb-6 transition-colors"
                :class="
                    pack.popular
                        ? 'border-action bg-card shadow-[0_10px_30px_rgba(43,74,203,.1)]'
                        : 'border-rule bg-panel'
                "
            >
                <div class="flex min-h-[26px] items-center gap-2.5">
                    <span class="text-[19px] font-bold tracking-[-0.01em]">
                        {{ pack.title }}
                    </span>
                    <span
                        v-if="pack.popular"
                        class="bg-action-soft text-action-ink rounded-full px-2.5 py-1 text-[12.5px] font-bold"
                    >
                        чаще берут
                    </span>
                </div>

                <p class="mt-[18px] flex items-baseline gap-[7px]">
                    <span
                        class="text-[44px] leading-none font-bold tracking-[-0.03em] tabular-nums"
                    >
                        {{ pack.amount }}
                    </span>
                    <span class="text-muted-foreground text-xl font-semibold">₽</span>
                </p>

                <div class="bg-rule mt-5 mb-4 h-px" />

                <ul class="flex flex-col gap-2.5 text-[15.5px]">
                    <li class="flex items-center gap-[9px]">
                        <Presentation class="text-action size-[17px] flex-none" />
                        <span>
                            <strong class="font-semibold">{{ pack.credits }}</strong>
                            генераций
                        </span>
                    </li>
                    <li class="flex items-center gap-[9px]">
                        <CircleDollarSign class="text-action size-[17px] flex-none" />
                        <span>{{ pack.perCredit }} ₽ за презентацию</span>
                    </li>
                    <li class="text-muted-foreground flex items-center gap-[9px]">
                        <Download class="size-[17px] flex-none" />
                        <span>PDF и редактирование</span>
                    </li>
                </ul>

                <p
                    class="text-muted-foreground mt-[18px] min-h-10 flex-1 text-[14.5px] leading-[1.4]"
                >
                    {{ pack.note }}
                </p>

                <button
                    type="button"
                    class="mt-1.5 cursor-pointer rounded-[11px] border p-3.5 text-center text-base font-semibold transition-colors disabled:cursor-default disabled:opacity-60"
                    :class="
                        pack.popular
                            ? 'bg-foreground text-background border-transparent shadow-[0_6px_16px_rgba(21,22,26,.2)] hover:bg-action'
                            : 'bg-card border-input hover:border-action hover:text-action'
                    "
                    :disabled="sending !== null"
                    @click="buy(pack.key)"
                >
                    {{ sending === pack.key ? 'Переходим…' : 'Купить' }}
                </button>
            </div>
        </div>

        <!-- Две сноски в ряд, как в макете: до 1060px, дальше не тянутся —
             строка в 14.5px шире этого читается тяжело -->
        <div class="mt-[26px] flex max-w-[1060px] flex-col gap-3.5 sm:flex-row">
            <p
                class="border-rule bg-panel flex flex-1 gap-3 rounded-[13px] border px-[18px] py-4 text-[14.5px] leading-[1.5]"
            >
                <Clock class="text-action mt-0.5 size-[18px] flex-none" />
                <span>
                    Генерации не сгорают. Если презентация не собралась по нашей
                    вине, списанная генерация возвращается автоматически.
                </span>
            </p>

            <p
                class="border-rule bg-panel flex flex-1 gap-3 rounded-[13px] border px-[18px] py-4 text-[14.5px] leading-[1.5]"
            >
                <Info class="text-action mt-0.5 size-[18px] flex-none" />
                <span>
                    Оплата означает принятие
                    <a href="/offer" target="_blank" class="underline underline-offset-2">
                        условий оферты</a>. Чек придёт на вашу почту,
                    неиспользованные генерации можно вернуть.
                </span>
            </p>
        </div>

        <template v-if="history.length">
            <h2
                id="history"
                class="border-rule mt-14 border-b pb-4 text-lg font-bold"
            >
                История платежей
            </h2>

            <ul class="divide-rule max-w-[1060px] divide-y">
                <li
                    v-for="item in history"
                    :key="item.id"
                    class="flex items-baseline gap-4 py-4 text-sm"
                >
                    <span class="flex-1">
                        {{ item.credits }} генераций
                        <span class="text-muted-foreground">
                            · {{ formatDate(item.date) }}
                        </span>
                    </span>

                    <span
                        class="flex items-center gap-1.5"
                        :class="
                            item.status === 'paid'
                                ? 'text-action-ink'
                                : 'text-muted-foreground'
                        "
                    >
                        <Check v-if="item.status === 'paid'" class="size-3.5" />
                        {{ item.statusLabel }}
                    </span>

                    <span class="w-20 text-right font-medium tabular-nums">
                        {{ item.amount }} ₽
                    </span>
                </li>
            </ul>
        </template>
    </div>
</template>
