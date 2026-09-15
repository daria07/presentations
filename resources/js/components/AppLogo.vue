<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoMark from '@/components/AppLogoMark.vue';

const name = usePage().props.name;
</script>

<template>
    <!-- Размеры из макета: знак 30px, название 19px -->
    <AppLogoMark class="size-[30px] shrink-0" />

    <div class="grid flex-1 text-left">
        <span
            class="font-display truncate text-[19px] leading-tight font-bold tracking-[-0.01em]"
        >
            {{ name }}
        </span>
    </div>
</template>
