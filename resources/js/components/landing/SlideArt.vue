<script setup lang="ts">
/**
 * Условная «иллюстрация» для мини-слайдов на лендинге.
 *
 * Настоящих картинок здесь нет: они рисуются под тему каждой
 * презентации. Вместо них — простой пейзаж в цветах выбранной гаммы,
 * чтобы было видно, где на слайде живёт картинка, и чтобы при смене
 * гаммы он перекрашивался вместе со слайдом.
 */
defineProps<{
    scene?: 'sea' | 'hills';
}>();
</script>

<template>
    <svg
        viewBox="0 0 320 200"
        preserveAspectRatio="xMidYMid slice"
        class="block size-full"
        aria-hidden="true"
    >
        <defs>
            <linearGradient id="art-sky" x1="0" y1="0" x2="0" y2="1">
                <stop
                    offset="0"
                    :style="{
                        stopColor:
                            'color-mix(in srgb, var(--p-cover) 70%, #9fb4c7)',
                    }"
                />
                <stop
                    offset="1"
                    :style="{
                        stopColor:
                            'color-mix(in srgb, var(--p-accent-soft) 60%, #f3e3c8)',
                    }"
                />
            </linearGradient>
        </defs>

        <rect width="320" height="200" fill="url(#art-sky)" />
        <circle
            cx="230"
            cy="70"
            r="22"
            :style="{
                fill: 'color-mix(in srgb, var(--p-cover-accent) 55%, #fff4dc)',
            }"
        />

        <template v-if="scene === 'hills'">
            <path
                d="M0 140 Q60 95 120 128 T240 118 T320 126 V200 H0Z"
                :style="{
                    fill: 'color-mix(in srgb, var(--p-accent) 45%, #6b7f6a)',
                }"
            />
            <path
                d="M0 165 Q80 130 170 158 T320 150 V200 H0Z"
                :style="{
                    fill: 'color-mix(in srgb, var(--p-accent) 30%, #3e4d3c)',
                }"
            />
        </template>

        <template v-else>
            <rect
                y="132"
                width="320"
                height="68"
                :style="{
                    fill: 'color-mix(in srgb, var(--p-cover) 75%, #2c4a63)',
                }"
            />
            <path
                d="M0 150 Q40 144 80 150 T160 150 T240 150 T320 150"
                fill="none"
                stroke="rgba(255,255,255,.18)"
                stroke-width="2"
            />
            <!-- Силуэт корабля -->
            <path
                d="M196 132 L262 132 L254 142 L204 142 Z M214 132 V104 M232 132 V96 M214 106 L230 128 M232 98 L250 128"
                :style="{
                    fill: 'color-mix(in srgb, var(--p-cover) 85%, #000)',
                    stroke: 'color-mix(in srgb, var(--p-cover) 85%, #000)',
                }"
                stroke-width="2"
            />
        </template>
    </svg>
</template>
