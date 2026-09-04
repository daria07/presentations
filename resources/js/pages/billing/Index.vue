<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

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

    <div class="mx-auto w-full max-w-3xl px-4 py-8">
        <div class="border-rule border-b pb-6">
            <h1 class="text-3xl font-extrabold">Тарифы</h1>
            <p class="text-muted-foreground mt-1.5">
                <template v-if="trialAvailable">
                    У вас есть бесплатная пробная генерация.
                </template>
                <template v-else>
                    Осталось:
                    <span class="text-foreground font-medium tabular-nums">
                        {{ credits }}
                    </span>
                </template>
            </p>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div
                v-for="pack in packages"
                :key="pack.key"
                class="flex flex-col rounded-xl border p-6"
                :class="pack.popular ? 'border-brand' : 'border-rule'"
            >
                <div class="flex items-baseline justify-between">
                    <p class="font-display font-bold">{{ pack.title }}</p>
                    <span
                        v-if="pack.popular"
                        class="bg-brand-soft text-brand-ink rounded-full px-2 py-0.5 text-[11px] font-medium"
                    >
                        чаще берут
                    </span>
                </div>

                <p class="mt-4 text-3xl font-extrabold tabular-nums">
                    {{ pack.amount }} <span class="text-xl font-bold">₽</span>
                </p>

                <p class="text-muted-foreground mt-1 text-sm">
                    {{ pack.credits }} генераций · {{ pack.perCredit }} ₽ за штуку
                </p>

                <p class="text-muted-foreground mt-4 flex-1 text-sm leading-relaxed">
                    {{ pack.note }}
                </p>

                <Button
                    class="mt-6 w-full"
                    :variant="pack.popular ? 'default' : 'outline'"
                    :disabled="sending !== null"
                    @click="buy(pack.key)"
                >
                    {{ sending === pack.key ? 'Переходим…' : 'Купить' }}
                </Button>
            </div>
        </div>

        <p class="text-muted-foreground mt-6 text-sm leading-relaxed">
            Генерации не сгорают. Если презентация не собралась по нашей вине,
            списанная генерация возвращается автоматически.
        </p>

        <template v-if="history.length">
            <h2 class="border-rule mt-14 border-b pb-4 text-lg font-bold">
                История платежей
            </h2>

            <ul class="divide-rule divide-y">
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
                                ? 'text-brand-ink'
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
