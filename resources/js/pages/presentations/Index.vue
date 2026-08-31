<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';

type Item = {
    id: number;
    title: string;
    status: string;
    statusLabel: string;
    slideCount: number;
    createdAt: string | null;
    url: string;
};

defineProps<{
    presentations: { data: Item[] };
    credits: number;
    trialAvailable: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Презентации', href: '/presentations' }],
    },
});

function formatDate(iso: string | null): string {
    if (!iso) return '';

    return new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
    });
}

const statusTone: Record<string, string> = {
    failed: 'text-destructive',
    ready: 'text-muted-foreground',
};
</script>

<template>
    <Head title="Презентации" />

    <div class="mx-auto w-full max-w-3xl px-4 py-8">
        <div class="border-rule flex items-end justify-between gap-6 border-b pb-6">
            <div>
                <h1 class="text-3xl font-extrabold">Презентации</h1>
                <p class="text-muted-foreground mt-1.5 text-sm">
                    <template v-if="trialAvailable">
                        Первая — бесплатно, карта не нужна.
                    </template>
                    <template v-else>
                        Осталось генераций:
                        <span class="text-foreground font-medium tabular-nums">
                            {{ credits }}
                        </span>
                    </template>
                </p>
            </div>

            <Button as-child class="flex-none">
                <Link href="/presentations/new">
                    <Plus class="size-4" />
                    Создать
                </Link>
            </Button>
        </div>

        <!-- Список строками с волосяными линейками — по-редакторски,
             без визуального шума от карточек -->
        <ul v-if="presentations.data.length" class="divide-rule divide-y">
            <li v-for="item in presentations.data" :key="item.id">
                <Link
                    :href="item.url"
                    class="group flex items-baseline gap-4 py-5 transition-colors"
                >
                    <div class="min-w-0 flex-1">
                        <p
                            class="group-hover:text-brand-ink truncate font-medium transition-colors"
                        >
                            {{ item.title }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            <span :class="statusTone[item.status] ?? 'text-brand-ink'">
                                {{ item.statusLabel }}
                            </span>
                            <span class="mx-1.5">·</span>
                            {{ item.slideCount }} слайдов
                        </p>
                    </div>

                    <span
                        class="text-muted-foreground flex-none text-sm tabular-nums"
                    >
                        {{ formatDate(item.createdAt) }}
                    </span>
                </Link>
            </li>
        </ul>

        <div v-else class="py-24 text-center">
            <p class="text-muted-foreground mx-auto max-w-[38ch] leading-relaxed">
                Пока пусто. Опишите тему одной строкой — структуру, факты и вёрстку
                возьмём на себя.
            </p>
            <Button as-child variant="outline" class="mt-6">
                <Link href="/presentations/new">Создать первую</Link>
            </Button>
        </div>
    </div>
</template>
