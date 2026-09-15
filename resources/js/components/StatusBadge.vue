<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Состояние презентации пятном, а не словом.
 *
 * Один компонент на список, страницу презентации и админку: раньше
 * в каждом из трёх мест статус красили по-своему, и «Готово» выглядело
 * тремя разными способами.
 */
const props = defineProps<{
    status: string;
    label: string;
}>();

const TONES: Record<string, string> = {
    ready: 'bg-[#E4F3EC] text-[#1D7A55]',
    failed: 'bg-[#FDECEE] text-[#C2364A]',
};

const tone = computed(() => TONES[props.status] ?? 'bg-secondary text-muted-foreground');
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[13px] font-semibold"
        :class="tone"
    >
        <Check v-if="status === 'ready'" class="size-3" />
        {{ label }}
    </span>
</template>
