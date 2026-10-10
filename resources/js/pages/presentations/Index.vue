<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Star, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import ReviewForm from '@/components/ReviewForm.vue';
import { slides } from '@/lib/plural';
import StatusBadge from '@/components/StatusBadge.vue';
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
    reviewUrl: string | null;
    rating: number | null;
    review: string | null;
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

/* Оценка: звёзды в строке — кнопка, открывающая окно с формой.
   При наведении они подсвечиваются до курсора, но это только отклик:
   оценка не выбирается и не сохраняется, пока её не отправят из окна */
const rating = ref<Item | null>(null);
const hovered = ref<{ id: number; n: number } | null>(null);

function starFilled(item: Item, n: number): boolean {
    if (hovered.value?.id === item.id) return n <= hovered.value.n;

    return n <= (item.rating ?? 0);
}

function openRating(item: Item) {
    // Сбрасываем подсветку: окно встаёт поверх, и курсор «уходит» со
    // звёзд без mouseleave — без сброса они остались бы закрашенными
    hovered.value = null;
    rating.value = item;
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
</script>

<template>
    <Head title="Презентации" />

    <!-- Полотно во всю ширину с отступами 38/56 из макета:
         узкая колонка сжимала плашки и ломала ритм строки -->
    <div class="w-full px-4 py-6 sm:px-6 sm:py-9 lg:px-14">
        <PageHeader title="Презентации" dot>
            <template #meta>
                <template v-if="trialAvailable">
                    Первая — бесплатно, карта не нужна.
                </template>
                <template v-else>
                    Осталось генераций:
                    <span class="text-foreground font-bold tabular-nums">
                        {{ credits }}
                    </span>
                </template>
            </template>

            <template #actions>
                <Button
                    as-child
                    class="h-12 w-full px-[22px] text-base shadow-[0_6px_16px_rgba(21,22,26,.2)] sm:w-auto"
                >
                    <Link href="/presentations/new">
                        <Plus class="size-[17px]" />
                        Создать
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Плашками, а не строками с линейками: так в макете, и так
             у каждой презентации своя область нажатия -->
        <ul v-if="presentations.data.length" class="flex flex-col gap-2">
            <li
                v-for="item in presentations.data"
                :key="item.id"
                class="group border-rule bg-card hover:border-action relative flex items-center gap-3 rounded-[13px] border px-3 py-3 transition-all hover:shadow-[0_6px_18px_rgba(43,74,203,.1)] sm:gap-[18px] sm:px-5 sm:py-4"
            >
                <!-- Заглушка слайда: три полоски вместо картинки,
                     превью первой страницы у нас нет. На самых узких
                     экранах её нет совсем — она ничего не сообщает,
                     а место под название отнимает. Прижата к верху:
                     когда под названием два ряда, по центру она
                     повисала между ними -->
                <div
                    class="border-rule bg-sidebar hidden h-[34px] w-12 flex-none self-start rounded-[7px] border px-[6px] py-1.5 min-[380px]:block sm:h-[38px] sm:w-14 sm:px-[7px]"
                    aria-hidden="true"
                >
                    <div class="h-1 w-[70%] rounded-sm bg-[#C9C3B6]" />
                    <div
                        class="mt-[5px] h-[3px] w-[46%] rounded-sm bg-[#DDD7CB]"
                    />
                    <div class="mt-1 h-[3px] w-[56%] rounded-sm bg-[#DDD7CB]" />
                </div>

                <div class="min-w-0 flex-1">
                    <!-- Ссылка растянута на всю плашку псевдоэлементом:
                         внутри <a> нельзя класть кнопки, а звёзды и
                         удаление — кнопки. Они лежат поверх (z-10) -->
                    <Link
                        :href="item.url"
                        class="block truncate text-[16px] font-semibold tracking-[-0.01em] outline-none after:absolute after:inset-0 after:rounded-[13px] focus-visible:after:ring-2 focus-visible:after:ring-ring sm:text-[18px]"
                    >
                        {{ item.title }}
                    </Link>
                    <!-- До 1024px дата и звёзды живут здесь, в общем ряду
                         с состоянием: отдельными колонками справа они
                         съедали ширину, и от названия оставалось «Екат…».
                         Ряд переносится, если не помещается в строку -->
                    <div
                        class="mt-[7px] flex flex-wrap items-center gap-x-2.5 gap-y-1"
                    >
                        <StatusBadge
                            :status="item.status"
                            :label="item.statusLabel"
                        />
                        <span class="text-muted-foreground text-sm">
                            {{ slides(item.slideCount) }}
                        </span>
                        <span
                            class="text-muted-foreground text-sm tabular-nums lg:hidden"
                        >
                            {{ formatDate(item.createdAt) }}
                        </span>
                        <button
                            v-if="item.reviewUrl"
                            type="button"
                            class="relative z-10 -mx-0.5 inline-flex cursor-pointer gap-0.5 rounded p-0.5 focus-visible:outline-2 lg:hidden"
                            :aria-label="
                                item.rating
                                    ? `Ваша оценка: ${item.rating} из 5. Изменить`
                                    : 'Оценить презентацию'
                            "
                            @mouseleave="hovered = null"
                            @click="openRating(item)"
                        >
                            <Star
                                v-for="n in 5"
                                :key="n"
                                class="size-[18px] transition-colors"
                                :class="
                                    starFilled(item, n)
                                        ? 'fill-amber-400 text-amber-400'
                                        : 'text-muted-foreground/40'
                                "
                                @mouseenter="hovered = { id: item.id, n }"
                            />
                        </button>
                    </div>
                </div>

                <button
                    v-if="item.reviewUrl"
                    type="button"
                    class="relative z-10 hidden flex-none cursor-pointer items-center gap-0.5 rounded-md p-1 focus-visible:outline-2 lg:flex"
                    :aria-label="
                        item.rating
                            ? `Ваша оценка: ${item.rating} из 5. Изменить`
                            : 'Оценить презентацию'
                    "
                    :title="item.rating ? 'Изменить оценку' : 'Оценить'"
                    @mouseleave="hovered = null"
                    @blur="hovered = null"
                    @click="openRating(item)"
                >
                    <Star
                        v-for="n in 5"
                        :key="n"
                        class="size-5 transition-colors"
                        :class="
                            starFilled(item, n)
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-muted-foreground/40'
                        "
                        @mouseenter="hovered = { id: item.id, n }"
                    />
                </button>

                <span
                    class="text-muted-foreground hidden flex-none text-sm tabular-nums lg:block"
                >
                    {{ formatDate(item.createdAt) }}
                </span>
                <ChevronRight
                    class="text-rule hidden size-[18px] flex-none xl:block"
                />

                <!--
                    На мыши кнопка появляется при наведении, чтобы не шуметь
                    в спокойном состоянии. На касании наведения не бывает,
                    и там она видна всегда — иначе удалить презентацию
                    с телефона было бы нечем.
                -->
                <Button
                    variant="ghost"
                    size="icon-sm"
                    class="text-muted-foreground hover:text-destructive relative z-10 flex-none opacity-60 transition-opacity focus-visible:opacity-100 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100"
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
                @click="
                    presentations.prevUrl && router.get(presentations.prevUrl)
                "
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
                @click="
                    presentations.nextUrl && router.get(presentations.nextUrl)
                "
            >
                Дальше
                <ChevronRight class="size-4" />
            </Button>
        </nav>

        <div v-if="!presentations.data.length" class="py-24 text-center">
            <p
                class="text-muted-foreground mx-auto max-w-[38ch] leading-relaxed"
            >
                Пока пусто. Опишите тему одной строкой — структуру, факты и
                вёрстку возьмём на себя.
            </p>
            <Button as-child variant="outline" class="mt-6">
                <Link href="/presentations/new">Создать первую</Link>
            </Button>
        </div>

        <Dialog :open="rating !== null" @update:open="rating = null">
            <!-- grid-cols-[minmax(0,1fr)]: иначе колонка окна растягивается
                 под самую длинную строку, и на телефоне длинное название
                 выталкивало поле и кнопку за край экрана -->
            <DialogContent
                class="grid-cols-[minmax(0,1fr)] p-5 sm:max-w-md sm:p-6"
            >
                <DialogHeader class="pr-6 text-left">
                    <DialogTitle>Как вам презентация?</DialogTitle>
                    <DialogDescription class="line-clamp-2 break-words">
                        «{{ rating?.title }}»
                    </DialogDescription>
                </DialogHeader>

                <ReviewForm
                    v-if="rating?.reviewUrl"
                    :key="rating.id"
                    :url="rating.reviewUrl"
                    :rating="rating.rating"
                    :review="rating.review"
                    @saved="rating = null"
                    @cancel="rating = null"
                />
            </DialogContent>
        </Dialog>

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
