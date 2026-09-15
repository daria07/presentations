<script setup lang="ts">
/**
 * Шапка страницы: заголовок, строка под ним и кнопки справа.
 *
 * Кегль заголовка и отступы жили на четырёх страницах в четырёх
 * вариантах — от text-2xl до text-3xl. Здесь одно место.
 */
defineProps<{
    title: string;
    /** Точка акцентом у строки под заголовком: отмечает живую цифру */
    dot?: boolean;
}>();
</script>

<template>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
        <div class="min-w-0">
            <h1 class="text-[40px] leading-none font-bold tracking-[-0.02em]">
                {{ title }}
            </h1>
            <p
                v-if="$slots.meta"
                class="text-muted-foreground mt-2.5 flex items-center gap-2 text-[15px]"
            >
                <span v-if="dot" class="bg-action size-[7px] flex-none rounded-full" />
                <slot name="meta" />
            </p>
        </div>

        <div v-if="$slots.actions" class="flex flex-none flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
