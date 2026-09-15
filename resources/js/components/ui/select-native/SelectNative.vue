<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Обычный <select> в оформлении Input.
 *
 * Рядом живёт ui/select на Reka — он нужен там, где список сложный:
 * со значками, группами, поиском. Для простого выбора из десятка
 * значений родной список лучше: он работает с клавиатуры и на телефоне
 * открывается системным барабаном. Раньше такой select верстали руками
 * на месте, вместе со стрелкой.
 */
const props = defineProps<{
    modelValue?: string | number | null;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number | null): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, { passive: true });
</script>

<template>
    <div class="relative">
        <select
            v-model="modelValue"
            data-slot="select-native"
            :class="
                cn(
                    'border-input bg-card dark:bg-input/30 h-11 w-full cursor-pointer appearance-none rounded-lg border py-2 pr-10 pl-3.5 text-[15px] font-medium transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    props.class,
                )
            "
        >
            <slot />
        </select>

        <ChevronDown
            class="text-muted-foreground pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2"
        />
    </div>
</template>
