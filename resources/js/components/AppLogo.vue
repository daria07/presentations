<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const name = usePage().props.name;
</script>

<template>
    <!-- Тот же знак, что в фавиконе: два листа слайда с плашкой заголовка -->
    <svg
        viewBox="0 0 64 64"
        class="size-8 shrink-0"
        role="img"
        :aria-label="String(name)"
    >
        <rect width="64" height="64" rx="14" fill="#231A16" />
        <rect x="13" y="13" width="28" height="38" rx="3" fill="#FDFBF7" opacity=".32" />
        <rect x="22" y="16" width="29" height="35" rx="3" fill="#FDFBF7" />
        <rect x="27" y="22" width="15" height="4.5" rx="2.25" fill="#B4502A" />
        <rect x="27" y="32" width="19" height="2.6" rx="1.3" fill="#C9BDB4" />
        <rect x="27" y="39" width="12" height="2.6" rx="1.3" fill="#C9BDB4" />
    </svg>

    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="font-display mb-0.5 truncate leading-tight font-bold">
            {{ name }}
        </span>
    </div>
</template>
