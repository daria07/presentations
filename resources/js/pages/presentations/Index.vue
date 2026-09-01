<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Item = {
    id: number;
    title: string;
    status: string;
    statusLabel: string;
    slideCount: number;
    createdAt: string | null;
    url: string;
};

type Paginated = {
    data: Item[];
    currentPage: number;
    lastPage: number;
    total: number;
    prevUrl: string | null;
    nextUrl: string | null;
};

defineProps<{
    presentations: Paginated;
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

/* Один диалог на весь список — помним, что именно удаляем */
const pending = ref<Item | null>(null);
const deleting = ref(false);

function destroy() {
    if (!pending.value) return;

    deleting.value = true;
    router.delete(`/presentations/${pending.value.id}`, {
        onFinish: () => {
            deleting.value = false;
            pending.value = null;
        },
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
            <li
                v-for="item in presentations.data"
                :key="item.id"
                class="group flex items-baseline gap-4"
            >
                <Link :href="item.url" class="flex min-w-0 flex-1 items-baseline gap-4 py-5">
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

                    <span class="text-muted-foreground flex-none text-sm tabular-nums">
                        {{ formatDate(item.createdAt) }}
                    </span>
                </Link>

                <!-- Появляется при наведении, чтобы не шуметь в спокойном состоянии -->
                <Button
                    variant="ghost"
                    size="icon-sm"
                    class="text-muted-foreground hover:text-destructive flex-none opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                    :aria-label="`Удалить «${item.title}»`"
                    @click="pending = item"
                >
                    <Trash2 class="size-4" />
                </Button>
            </li>
        </ul>

        <nav
            v-if="presentations.lastPage > 1"
            class="border-rule flex items-center justify-between border-t pt-5"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!presentations.prevUrl"
                @click="presentations.prevUrl && router.get(presentations.prevUrl)"
            >
                <ChevronLeft class="size-4" />
                Назад
            </Button>

            <span class="text-muted-foreground text-sm tabular-nums">
                {{ presentations.currentPage }} из {{ presentations.lastPage }}
            </span>

            <Button
                variant="outline"
                size="sm"
                :disabled="!presentations.nextUrl"
                @click="presentations.nextUrl && router.get(presentations.nextUrl)"
            >
                Дальше
                <ChevronRight class="size-4" />
            </Button>
        </nav>

        <div v-if="!presentations.data.length" class="py-24 text-center">
            <p class="text-muted-foreground mx-auto max-w-[38ch] leading-relaxed">
                Пока пусто. Опишите тему одной строкой — структуру, факты и вёрстку
                возьмём на себя.
            </p>
            <Button as-child variant="outline" class="mt-6">
                <Link href="/presentations/new">Создать первую</Link>
            </Button>
        </div>

        <Dialog :open="pending !== null" @update:open="pending = null">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Удалить презентацию?</DialogTitle>
                    <DialogDescription>
                        «{{ pending?.title }}» — файл и публичная ссылка
                        перестанут работать. Отменить это действие нельзя.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="pending = null">
                        Оставить
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleting"
                        @click="destroy"
                    >
                        Удалить
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
