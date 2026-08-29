<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
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
    presentations: { data: Item[]; links: unknown };
    credits: number;
    trialAvailable: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Презентации', href: '/presentations' }],
    },
});

const tone: Record<string, string> = {
    ready: 'default',
    failed: 'destructive',
};

function formatDate(iso: string | null): string {
    if (!iso) return '';

    return new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
    });
}
</script>

<template>
    <Head title="Презентации" />

    <div class="mx-auto w-full max-w-4xl p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Презентации"
                :description="
                    trialAvailable
                        ? 'Первая презентация — бесплатно.'
                        : `Осталось генераций: ${credits}`
                "
            />
            <Button as-child class="flex-none">
                <Link href="/presentations/new">
                    <Plus class="size-4" />
                    Создать
                </Link>
            </Button>
        </div>

        <div v-if="presentations.data.length" class="mt-6 flex flex-col gap-2">
            <Link
                v-for="item in presentations.data"
                :key="item.id"
                :href="item.url"
                class="border-border hover:border-foreground/30 group flex items-center gap-4 rounded-xl border px-5 py-4 transition-colors"
            >
                <FileText class="text-muted-foreground size-5 flex-none" />

                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ item.title }}</p>
                    <p class="text-muted-foreground text-sm">
                        {{ item.slideCount }} слайдов · {{ formatDate(item.createdAt) }}
                    </p>
                </div>

                <Badge
                    v-if="item.status !== 'ready'"
                    :variant="(tone[item.status] as never) ?? 'secondary'"
                    class="flex-none"
                >
                    {{ item.statusLabel }}
                </Badge>
            </Link>
        </div>

        <div
            v-else
            class="border-border mt-6 flex flex-col items-center gap-4 rounded-xl border border-dashed py-20 text-center"
        >
            <p class="text-muted-foreground text-sm">
                Пока пусто. Опишите тему — остальное соберём сами.
            </p>
            <Button as-child variant="outline">
                <Link href="/presentations/new">Создать первую</Link>
            </Button>
        </div>
    </div>
</template>
