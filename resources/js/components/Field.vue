<script setup lang="ts">
/**
 * Поле формы: подпись, контрол и подсказка под ним.
 *
 * До этого подпись верстали на месте — и по сайту разъехались
 * text-sm, text-xs, font-medium, font-semibold и четыре разных
 * отступа. Здесь это одно решение на все формы.
 *
 * Счётчик символов (`counter`) — в той же строке, что подпись:
 * он относится к полю, а не к тексту под ним, и снизу только мешал бы
 * читать подсказку.
 */
defineProps<{
    label?: string;
    hint?: string;
    error?: string;
    counter?: string;
    for?: string;
}>();
</script>

<template>
    <div class="space-y-2">
        <div v-if="label || counter" class="flex items-baseline justify-between gap-3">
            <label v-if="label" :for="$props.for" class="text-sm font-medium">
                {{ label }}
            </label>
            <span v-if="counter" class="text-muted-foreground text-xs tabular-nums">
                {{ counter }}
            </span>
        </div>

        <slot />

        <p v-if="error" class="text-destructive text-sm">{{ error }}</p>
        <p v-else-if="hint" class="text-muted-foreground text-sm">{{ hint }}</p>
    </div>
</template>
