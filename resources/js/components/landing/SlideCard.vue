<script setup lang="ts">
/**
 * Мини-слайд в вёрстке настоящей презентации — тот же язык,
 * что и в PDF: тёмная обложка, светлые содержательные слайды,
 * тонкая линейка под заголовком.
 */
defineProps<{
    variant?: 'cover' | 'content';
    heading: string;
    sub?: string;
}>();
</script>

<template>
    <div
        class="ring-rule relative aspect-video overflow-hidden rounded-lg p-5 ring-1"
        :class="
            variant === 'cover'
                ? 'bg-[#17150F] text-[#FBFAF8]'
                : 'bg-white text-[#17150F]'
        "
    >
        <template v-if="variant === 'cover'">
            <div class="bg-brand mb-4 h-[3px] w-9 rounded-full" />
            <p class="font-display text-lg leading-tight font-extrabold tracking-tight">
                {{ heading }}
            </p>
            <p v-if="sub" class="mt-2 text-[11px] text-white/55">{{ sub }}</p>
        </template>

        <template v-else>
            <p class="font-display text-[13px] leading-tight font-bold">
                {{ heading }}
            </p>
            <div class="mt-2 mb-4 h-px w-full bg-black/10" />
            <slot />
        </template>
    </div>
</template>
