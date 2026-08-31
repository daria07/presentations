<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const name = usePage().props.name;
</script>

<template>
    <div
        class="bg-foreground text-background font-display flex aspect-square size-8 items-center justify-center rounded-md text-sm font-extrabold"
    >
        Т
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="font-display mb-0.5 truncate leading-tight font-bold">
            {{ name }}
        </span>
    </div>
</template>
