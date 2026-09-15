<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight, Plus, Trash2 } from '@lucide/vue';
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

/*
   Статус — бейдж, а не просто цветной текст: в списке из десяти строк
   глаз ищет состояние пятном, а не читает слово. Зелёный и красный
   взяты из макета, остальные состояния идут нейтральным.
*/
const statusTone: Record<string, string> = {
    ready: 'bg-[#E4F3EC] text-[#1D7A55]',
    failed: 'bg-[#FDECEE] text-[#C2364A]',
    default: 'bg-secondary text-muted-foreground',
};
</script>

<template>
    <Head title="Презентации" />

    <!-- Полотно во всю ширину с отступами 38/56 из макета:
         узкая колонка сжимала плашки и ломала ритм строки -->
    <div class="w-full px-6 py-9 lg:px-14">
        <div class="mb-6 flex items-end justify-between gap-6">
            <div>
                <h1 class="text-[40px] leading-none font-bold tracking-[-0.02em]">
                    Презентации
                </h1>
                <p class="text-muted-foreground mt-2.5 flex items-center gap-2 text-[15px]">
                    <!-- Точка акцентом: в макете она отмечает живую цифру -->
                    <span class="bg-action size-[7px] flex-none rounded-full" />
                    <template v-if="trialAvailable">
                        Первая — бесплатно, карта не нужна.
                    </template>
                    <template v-else>
                        Осталось генераций:
                        <span class="text-foreground font-bold tabular-nums">
                            {{ credits }}
                        </span>
                    </template>
                </p>
            </div>

            <Button as-child class="h-12 flex-none px-[22px] text-base shadow-[0_6px_16px_rgba(21,22,26,.2)]">
                <Link href="/presentations/new">
                    <Plus class="size-[17px]" />
                    Создать
                </Link>
            </Button>
        </div>

        <!-- Плашками, а не строками с линейками: так в макете, и так
             у каждой презентации своя область нажатия -->
        <ul v-if="presentations.data.length" class="flex flex-col gap-2">
            <li
                v-for="item in presentations.data"
                :key="item.id"
                class="group border-rule bg-card hover:border-action hover:shadow-[0_6px_18px_rgba(43,74,203,.1)] relative flex items-center gap-[18px] rounded-[13px] border px-5 py-4 transition-all"
            >
                <Link :href="item.url" class="flex min-w-0 flex-1 items-center gap-[18px]">
                    <!-- Заглушка слайда: три полоски вместо картинки,
                         превью первой страницы у нас нет -->
                    <div
                        class="border-rule bg-sidebar h-[38px] w-14 flex-none rounded-[7px] border px-[7px] py-1.5"
                        aria-hidden="true"
                    >
                        <div class="h-1 w-[70%] rounded-sm bg-[#C9C3B6]" />
                        <div class="mt-[5px] h-[3px] w-[46%] rounded-sm bg-[#DDD7CB]" />
                        <div class="mt-1 h-[3px] w-[56%] rounded-sm bg-[#DDD7CB]" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[18px] font-semibold tracking-[-0.01em]">
                            {{ item.title }}
                        </p>
                        <p class="mt-[7px] flex items-center gap-2.5">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[13px] font-semibold"
                                :class="statusTone[item.status] ?? statusTone.default"
                            >
                                <Check v-if="item.status === 'ready'" class="size-3" />
                                {{ item.statusLabel }}
                            </span>
                            <span class="text-muted-foreground text-sm">
                                {{ item.slideCount }} слайдов
                            </span>
                        </p>
                    </div>

                    <span class="text-muted-foreground flex-none text-sm tabular-nums">
                        {{ formatDate(item.createdAt) }}
                    </span>
                    <ChevronRight class="text-rule size-[18px] flex-none" />
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
