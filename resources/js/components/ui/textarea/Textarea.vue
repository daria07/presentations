<script setup lang="ts">
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Многострочное поле в том же оформлении, что и Input.
 *
 * Заведено, потому что до этого каждая страница верстала textarea
 * заново — и на сайте жило шесть разных вариантов рамки, скругления
 * и кегля. Отличается от Input только высотой и тем, что тянется.
 */
const props = defineProps<{
    defaultValue?: string | number | null;
    modelValue?: string | number | null;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number | null): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <textarea
        v-model="modelValue"
        data-slot="textarea"
        :class="
            cn(
                'placeholder:text-muted-foreground border-input bg-card dark:bg-input/30 field-sizing-content min-h-24 w-full resize-y rounded-lg border px-3.5 py-2.5 text-[15px] leading-relaxed transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                props.class,
            )
        "
    />
</template>
