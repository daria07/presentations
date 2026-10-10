<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Row = {
    id: number;
    amount: string;
    credits: number;
    date: string | null;
    email: string | null;
    name: string | null;
    receiptUrl: string | null;
    hasFile: boolean;
    addedAt: string | null;
};

defineProps<{
    payments: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    onlyPending: boolean;
    waiting: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Админка', href: '/admin' },
            { title: 'Чеки', href: '/admin/receipts' },
        ],
    },
});

/** Какой строке сейчас добавляют чек — открыта одна за раз */
const editing = ref<number | null>(null);

const form = useForm<{ url: string; file: File | null }>({
    url: '',
    file: null,
});

function open(id: number) {
    editing.value = editing.value === id ? null : id;
    form.reset();
    form.clearErrors();
}

function submit(id: number) {
    form.post(`/admin/receipts/${id}`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
            form.reset();
        },
    });
}

function pickFile(event: Event) {
    const input = event.target as HTMLInputElement;
    form.file = input.files?.[0] ?? null;
}

function formatDate(iso: string | null): string {
    if (!iso) return '';

    return new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
    });
}

function toggleAll(all: boolean) {
    router.get('/admin/receipts', all ? { all: 1 } : {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Чеки" />

    <div class="w-full px-4 py-8 sm:px-6 sm:py-9 lg:px-14">
        <PageHeader title="Чеки" dot>
            <template #meta>
                <template v-if="waiting > 0">
                    Ждут чека:
                    <strong class="text-foreground">{{ waiting }}</strong>
                </template>
                <template v-else>Все оплаченные платежи с чеками</template>
            </template>

            <template #actions>
                <Button
                    variant="outline"
                    size="sm"
                    class="cursor-pointer"
                    @click="toggleAll(onlyPending)"
                >
                    {{ onlyPending ? 'Показать все' : 'Только без чека' }}
                </Button>
            </template>
        </PageHeader>

        <!-- Порядок работы: выбить чек в «Мой налог», скопировать оттуда
             ссылку (или скачать файл) и принести сюда -->
        <p
            class="text-muted-foreground border-rule bg-panel mb-6 max-w-[1060px] rounded-[13px] border px-[18px] py-4 text-[14.5px] leading-[1.5]"
        >
            Чек выбивается в приложении «Мой налог»: доход, «Физическому лицу»,
            сумма платежа. Дальше скопируйте оттуда ссылку на чек или сохраните
            его файлом и добавьте здесь — покупатель увидит чек в своей истории
            платежей. Срок по закону — не позднее 9-го числа месяца, следующего
            за месяцем оплаты.
        </p>

        <div
            v-if="payments.data.length === 0"
            class="text-muted-foreground py-12 text-center"
        >
            Ничего не ждёт чека.
        </div>

        <ul v-else class="divide-rule max-w-[1060px] divide-y">
            <li v-for="item in payments.data" :key="item.id" class="py-4">
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <span class="font-semibold tabular-nums"
                        >{{ item.amount }} ₽</span
                    >

                    <span class="text-muted-foreground text-sm">
                        {{ item.credits }} генераций ·
                        {{ formatDate(item.date) }}
                    </span>

                    <span class="min-w-0 flex-1 truncate text-sm">
                        {{ item.email }}
                    </span>

                    <a
                        v-if="item.receiptUrl"
                        :href="item.receiptUrl"
                        target="_blank"
                        rel="noopener"
                        class="text-action text-sm hover:underline"
                    >
                        чек по ссылке
                    </a>
                    <a
                        v-else-if="item.hasFile"
                        :href="`/billing/receipt/${item.id}`"
                        class="text-action text-sm hover:underline"
                    >
                        чек файлом
                    </a>
                    <span v-else class="text-destructive text-sm"
                        >без чека</span
                    >

                    <Button
                        variant="outline"
                        size="sm"
                        class="cursor-pointer"
                        @click="open(item.id)"
                    >
                        {{
                            editing === item.id
                                ? 'Отмена'
                                : item.addedAt
                                  ? 'Заменить'
                                  : 'Добавить'
                        }}
                    </Button>
                </div>

                <form
                    v-if="editing === item.id"
                    class="mt-4 flex flex-wrap items-start gap-3"
                    @submit.prevent="submit(item.id)"
                >
                    <div class="w-full min-w-0 sm:w-auto sm:min-w-[280px] sm:flex-1">
                        <Input
                            v-model="form.url"
                            type="url"
                            placeholder="Ссылка на чек из «Мой налог»"
                        />
                        <p
                            v-if="form.errors.url"
                            class="text-destructive mt-1 text-sm"
                        >
                            {{ form.errors.url }}
                        </p>
                    </div>

                    <div class="w-full min-w-0 sm:w-auto sm:min-w-[280px] sm:flex-1">
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="border-input bg-card h-11 w-full cursor-pointer rounded-lg border px-3 py-2 text-[15px] file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium"
                            @change="pickFile"
                        />
                        <p
                            v-if="form.errors.file"
                            class="text-destructive mt-1 text-sm"
                        >
                            {{ form.errors.file }}
                        </p>
                    </div>

                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="cursor-pointer"
                    >
                        {{ form.processing ? 'Сохраняем…' : 'Сохранить' }}
                    </Button>
                </form>
            </li>
        </ul>

        <div v-if="payments.last_page > 1" class="mt-8 flex flex-wrap gap-2">
            <Link
                v-for="link in payments.links"
                :key="link.label"
                :href="link.url ?? '#'"
                class="border-rule rounded-lg border px-3 py-1.5 text-sm"
                :class="
                    link.active
                        ? 'bg-foreground text-background'
                        : 'hover:border-action'
                "
                v-html="link.label"
            />
        </div>
    </div>
</template>
