<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    payment: { id: number; amount: string; credits: number };
}>();

defineOptions({ layout: { breadcrumbs: [] } });

const sending = ref(false);

function settle(paid: boolean) {
    sending.value = true;
    router.post(`/billing/sandbox/${props.payment.id}`, { paid });
}
</script>

<template>
    <Head title="Тестовая оплата" />

    <div class="mx-auto w-full max-w-md px-4 py-16">
        <div class="border-rule rounded-xl border p-8 text-center">
            <p
                class="text-muted-foreground font-display mb-6 text-xs font-bold tracking-[0.16em] uppercase"
            >
                Тестовая оплата
            </p>

            <p class="text-4xl font-extrabold tabular-nums">
                {{ payment.amount }} <span class="text-2xl">₽</span>
            </p>
            <p class="text-muted-foreground mt-2">
                {{ payment.credits }} генераций
            </p>

            <div class="mt-8 flex flex-col gap-2">
                <Button size="lg" :disabled="sending" @click="settle(true)">
                    Оплатить
                </Button>
                <Button
                    size="lg"
                    variant="ghost"
                    :disabled="sending"
                    @click="settle(false)"
                >
                    Отменить
                </Button>
            </div>
        </div>

        <p class="text-muted-foreground mt-6 text-center text-sm leading-relaxed">
            Это заглушка для разработки. Настоящий эквайринг подключается сменой
            одной строки в настройках.
        </p>
    </div>
</template>
