<script setup lang="ts">
import { computed } from 'vue';

/*
   Какая версия презентации: по ней видно, к чему относится отзыв —
   к пробной с водяным знаком или к полной.
*/
type Access = 'trial' | 'unlocked' | 'paid';

const props = defineProps<{
    access: Access | null;
}>();

const BADGES: Record<Access, { label: string; class: string }> = {
    trial: {
        label: 'пробная',
        class: 'bg-muted text-muted-foreground',
    },
    unlocked: {
        label: 'пробная → оплачена',
        class: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    },
    paid: {
        label: 'оплаченная',
        class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
};

const badge = computed(() => (props.access ? BADGES[props.access] : null));
</script>

<template>
    <span
        v-if="badge"
        class="inline-block rounded px-1.5 py-px align-middle text-[11px] leading-4 font-medium whitespace-nowrap"
        :class="badge.class"
    >
        {{ badge.label }}
    </span>
</template>
