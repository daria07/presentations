<script setup lang="ts">
import { computed, ref } from 'vue';

/*
   Один ряд столбцов: одна серия, свой масштаб по вертикали.
   Две метрики разного порядка (регистрации и генерации) рисуем
   двумя такими рядами один под другим, а не на общих осях со второй
   шкалой справа: вторая шкала позволяет подогнать любые два ряда под
   любую историю, и читатель не может её проверить.
*/
const props = defineProps<{
    title: string;
    days: { date: string; value: number }[];
    /* Слот категориальной палитры: 1 — синий, 2 — оранжевый.
       Не «slot»: это слово Vue держит за собой под разметку */
    tone: 1 | 2;
}>();

const HEIGHT = 96;

const max = computed(() => Math.max(1, ...props.days.map((d) => d.value)));
const total = computed(() => props.days.reduce((sum, d) => sum + d.value, 0));

const hovered = ref<number | null>(null);

function height(value: number): number {
    if (value === 0) return 0;

    // Единицу всё равно видно: иначе редкий день неотличим от пустого
    return Math.max(3, Math.round((value / max.value) * HEIGHT));
}

function label(iso: string): string {
    if (!iso) return '';

    return new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <figure class="space-y-2">
        <figcaption class="flex items-baseline justify-between gap-3">
            <span class="text-sm font-medium">{{ title }}</span>
            <span class="text-muted-foreground text-xs tabular-nums">
                {{ total }} за 30 дней · пик {{ max }}
            </span>
        </figcaption>

        <!-- Столбцы: воздух между соседями даёт сама сетка,
             скругляем только верх — низ стоит на базовой линии -->
        <div
            class="flex items-end gap-0.5"
            :style="{ height: HEIGHT + 'px' }"
            @mouseleave="hovered = null"
        >
            <div
                v-for="(day, i) in days"
                :key="day.date"
                class="relative flex h-full flex-1 items-end"
                @mouseenter="hovered = i"
            >
                <div
                    class="w-full rounded-t-[4px] transition-opacity"
                    :class="[
                        tone === 1 ? 'bg-series-1' : 'bg-series-2',
                        hovered !== null && hovered !== i ? 'opacity-40' : '',
                    ]"
                    :style="{ height: height(day.value) + 'px' }"
                />

                <!-- Пустой день тоже ловит курсор: иначе провал
                     в данных нечем объяснить -->
                <div class="absolute inset-0" />

                <div
                    v-if="hovered === i"
                    class="bg-popover text-popover-foreground border-border pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 -translate-x-1/2 rounded-md border px-2 py-1 text-xs whitespace-nowrap shadow-sm"
                >
                    {{ label(day.date) }} ·
                    <span class="tabular-nums">{{ day.value }}</span>
                </div>
            </div>
        </div>

        <div class="text-muted-foreground flex justify-between text-[10px]">
            <span>{{ label(days[0]?.date ?? '') }}</span>
            <span>{{ label(days[days.length - 1]?.date ?? '') }}</span>
        </div>
    </figure>
</template>
