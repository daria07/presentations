<script setup lang="ts">
/**
 * Мини-слайд в вёрстке настоящей презентации.
 *
 * Цвета и шрифты не свои, а унаследованные: родитель ставит те же
 * переменные, что шаблон презентации ставит на <html>. Поэтому при
 * смене гаммы карточки перекрашиваются вместе с продуктом, а не
 * расходятся с ним, как это было с захардкоженными цветами.
 */
defineProps<{
    variant?: 'cover' | 'content';
    heading: string;
    sub?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <div
        class="relative aspect-video overflow-hidden rounded-lg p-5 ring-1 transition-colors duration-300"
        :style="
            variant === 'cover'
                ? {
                      background: 'var(--p-cover)',
                      color: 'var(--p-cover-ink)',
                      '--tw-ring-color': 'var(--p-cover)',
                  }
                : {
                      background: 'var(--p-paper)',
                      color: 'var(--p-ink)',
                      '--tw-ring-color': 'var(--p-rule)',
                  }
        "
    >
        <template v-if="variant === 'cover'">
            <p
                v-if="eyebrow"
                class="mb-3 text-[9px] font-bold tracking-[0.16em] uppercase"
                :style="{ color: 'var(--p-cover-title)' }"
            >
                {{ eyebrow }}
            </p>
            <p
                class="text-lg leading-tight font-extrabold tracking-tight"
                :style="{ fontFamily: 'var(--t-display)' }"
            >
                {{ heading }}
            </p>
            <div
                class="mt-3 h-[3px] w-9 rounded-full"
                :style="{ background: 'var(--p-cover-accent)' }"
            />
            <p
                v-if="sub"
                class="mt-3 text-[11px] opacity-70"
                :style="{ fontFamily: 'var(--t-body)' }"
            >
                {{ sub }}
            </p>
        </template>

        <template v-else>
            <p
                class="text-[13px] leading-tight font-bold"
                :style="{ fontFamily: 'var(--t-display)' }"
            >
                {{ heading }}
            </p>
            <div
                class="mt-2 mb-4 h-px w-full"
                :style="{ background: 'var(--p-rule)' }"
            />
            <div :style="{ fontFamily: 'var(--t-body)' }">
                <slot />
            </div>
        </template>
    </div>
</template>
