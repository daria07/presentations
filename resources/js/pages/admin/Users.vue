<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

type Row = {
    id: number;
    name: string;
    email: string;
    credits: number;
    trialUsed: boolean;
    presentations: number;
    paid: number;
    spent: number;
    createdAt: string | null;
    lastSeen: string | null;
    url: string;
};

const props = defineProps<{
    users: {
        data: Row[];
        currentPage: number;
        lastPage: number;
        total: number;
        prevUrl: string | null;
        nextUrl: string | null;
    };
    search: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Админка', href: '/admin' },
            { title: 'Пользователи', href: '/admin/users' },
        ],
    },
});

const query = ref(props.search);
let timer: number | undefined;

/* Полсекунды после последней буквы: список небольшой,
   но дёргать сервер на каждое нажатие всё равно незачем */
watch(query, (value) => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        router.get(
            '/admin/users',
            value ? { search: value } : {},
            { preserveState: true, replace: true },
        );
    }, 500);
});

const rubles = (kopecks: number) =>
    (kopecks / 100).toLocaleString('ru-RU', { maximumFractionDigits: 0 });

const dollars = (hundredths: number) =>
    (hundredths / 10000).toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

function day(iso: string | null): string {
    if (!iso) return '—';

    return new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short',
        year: '2-digit',
    });
}
</script>

<template>
    <Head title="Пользователи" />

    <div class="w-full space-y-5 px-4 py-8 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Пользователи</h1>
                <p class="text-muted-foreground text-sm tabular-nums">
                    Всего {{ users.total }}
                </p>
            </div>

            <input
                v-model="query"
                type="search"
                placeholder="Имя или почта"
                class="border-input bg-background w-64 rounded-lg border px-3 py-2 text-sm"
            />
        </div>

        <div class="border-border bg-card overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="text-muted-foreground border-border border-b text-left text-xs">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Пользователь</th>
                        <th class="px-4 py-2.5 text-right font-medium">Презентаций</th>
                        <th class="px-4 py-2.5 text-right font-medium">Остаток</th>
                        <th class="px-4 py-2.5 text-right font-medium">Оплачено</th>
                        <th class="px-4 py-2.5 text-right font-medium">Расход</th>
                        <th class="px-4 py-2.5 text-right font-medium">Регистрация</th>
                        <th class="px-4 py-2.5 text-right font-medium">Последняя работа</th>
                    </tr>
                </thead>
                <tbody class="divide-border divide-y">
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="hover:bg-secondary/50 transition-colors"
                    >
                        <td class="px-4 py-2.5">
                            <Link :href="user.url" class="cursor-pointer hover:underline">
                                {{ user.name }}
                            </Link>
                            <p class="text-muted-foreground text-xs">{{ user.email }}</p>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ user.presentations }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ user.credits }}
                            <span v-if="!user.trialUsed" class="text-muted-foreground text-xs">
                                + проба
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ user.paid ? rubles(user.paid) + ' ₽' : '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ user.spent ? '$' + dollars(user.spent) : '—' }}
                        </td>
                        <td class="text-muted-foreground px-4 py-2.5 text-right tabular-nums">
                            {{ day(user.createdAt) }}
                        </td>
                        <td class="text-muted-foreground px-4 py-2.5 text-right tabular-nums">
                            {{ day(user.lastSeen) }}
                        </td>
                    </tr>

                    <tr v-if="!users.data.length">
                        <td colspan="7" class="text-muted-foreground px-4 py-10 text-center">
                            Никого не нашлось
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="users.lastPage > 1" class="flex items-center justify-between text-sm">
            <Link
                v-if="users.prevUrl"
                :href="users.prevUrl"
                class="border-border hover:bg-secondary cursor-pointer rounded-lg border px-3 py-1.5 transition-colors"
            >
                Назад
            </Link>
            <span v-else />

            <span class="text-muted-foreground tabular-nums">
                {{ users.currentPage }} из {{ users.lastPage }}
            </span>

            <Link
                v-if="users.nextUrl"
                :href="users.nextUrl"
                class="border-border hover:bg-secondary cursor-pointer rounded-lg border px-3 py-1.5 transition-colors"
            >
                Вперёд
            </Link>
            <span v-else />
        </div>
    </div>
</template>
