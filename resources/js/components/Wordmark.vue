<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

/**
 * Логотип: звезда плюс название.
 *
 * Отдельным компонентом, а не двумя строчками в шапке и подвале:
 * знак, размноженный копипастой, рано или поздно разъезжается —
 * где-то звезду забудут, где-то она окажется другого размера.
 *
 * Кегль и насыщенность наследуются от места, куда логотип поставлен,
 * а звезда считается от них: 1.2em оптически совпадает с высотой
 * прописных, поэтому знак не мельчает в подвале и не давит в шапке.
 */
const name = usePage().props.name;
</script>

<template>
    <span class="font-display inline-flex items-center gap-[.38em] font-extrabold tracking-tight">
        <!-- Знак декоративный: скринридеру достаточно названия -->
        <span class="text-brand text-[1.2em] leading-none" aria-hidden="true">✦</span>
        <span>{{ name }}</span>
    </span>
</template>
