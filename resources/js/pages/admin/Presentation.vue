<script setup lang="ts">
import { duration } from '@/lib/duration';
import { Head, Link } from '@inertiajs/vue3';
import AccessBadge from '@/components/admin/AccessBadge.vue';
import DeckViewer from '@/components/DeckViewer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { slides } from '@/lib/plural';

defineProps<{
    presentation: {
        id: number;
        title: string;
        topic: string;
        statusLabel: string;
        slides: number;
        theme: string | null;
        palette: string | null;
        rating: number | null;
        review: string | null;
        access: 'trial' | 'unlocked' | 'paid' | null;
        duration: number | null;
        createdAt: string | null;
        previewUrl: string;
        user: { id: number; name: string; email: string } | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Админка', href: '/admin' },
            { title: 'Презентация', href: '#' },
        ],
    },
});

function when(iso: string | null): string {
    if (!iso) return '';

    return new Date(iso).toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="presentation.title" />

    <div class="w-full space-y-6 px-4 py-8 lg:px-8">
        <PageHeader :title="presentation.title">
            <template #meta>
                <Link
                    v-if="presentation.user"
                    :href="`/admin/users/${presentation.user.id}`"
                    class="cursor-pointer hover:underline"
                >
                    {{ presentation.user.email }}
                </Link>
                <span v-else>пользователь удалён</span>
                · {{ when(presentation.createdAt) }}
            </template>
        </PageHeader>

        <div
            class="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
        >
            <AccessBadge :access="presentation.access" />
            <span>{{ presentation.statusLabel }}</span>
            <span>· {{ slides(presentation.slides) }}</span>
            <span v-if="presentation.duration">
                · генерация {{ duration(presentation.duration) }}
            </span>
            <span>
                · {{ presentation.theme ?? '—' }} /
                {{ presentation.palette ?? '—' }}
            </span>
            <span v-if="presentation.rating" class="text-amber-500">
                · ★ {{ presentation.rating }}
            </span>
        </div>

        <p
            v-if="presentation.topic !== presentation.title"
            class="text-muted-foreground max-w-prose text-sm"
        >
            Тема: {{ presentation.topic }}
        </p>

        <blockquote
            v-if="presentation.review"
            class="border-border max-w-prose border-l-2 pl-3 text-sm whitespace-pre-line"
        >
            {{ presentation.review }}
        </blockquote>

        <DeckViewer
            :src="presentation.previewUrl"
            class="h-[300px] w-full sm:h-[440px] lg:h-[600px]"
        />
    </div>
</template>
