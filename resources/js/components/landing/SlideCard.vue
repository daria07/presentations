<script setup lang="ts">
/**
 * Мини-слайд в вёрстке настоящей презентации.
 *
 * Цвета и шрифты не свои, а унаследованные: родитель ставит те же
 * переменные, что шаблон презентации ставит на <html>. Поэтому при
 * смене гаммы карточки перекрашиваются вместе с продуктом, а не
 * расходятся с ним, как это было с захардкоженными цветами.
 */
import SlideArt from '@/components/landing/SlideArt.vue';

defineProps<{
    /** photo — обложка с картинкой во весь слайд, как в продукте */
    variant?: 'cover' | 'photo' | 'content';
    heading: string;
    sub?: string;
    eyebrow?: string;
    /** Картинка на половине слайда, как у слайдов с иллюстрацией */
    image?: 'left' | 'right';
    /** Настоящая картинка; без неё — условный пейзаж в цветах гаммы */
    src?: string;
}>();
</script>

<template>
    <div
        class="relative aspect-video overflow-hidden rounded-lg p-5 ring-1 transition-colors duration-300"
        :style="
            variant === 'cover' || variant === 'photo'
                ? {
                      background: 'var(--p-cover)',
                      color: 'var(--p-cover-ink)',
                      '--tw-ring-color': 'var(--p-cover)',
                  }
                : {
                      background: 'var(--p-paper)',
                      color: 'var(--p-ink)',
                      '--tw-ring-color': 'var(--p-rule)',
                  }
        "
    >
        <!-- Обложка с картинкой: пейзаж во весь слайд, затемнение слева
             под название — как в шаблоне презентации -->
        <template v-if="variant === 'photo'">
            <div class="absolute inset-0">
                <img
                    v-if="src"
                    :src="src"
                    alt=""
                    loading="lazy"
                    class="size-full object-cover object-right"
                />
                <SlideArt v-else scene="sea" />
                <div
                    class="absolute inset-0"
                    style="
                        background: linear-gradient(
                            90deg,
                            rgba(10, 12, 16, 0.78) 0%,
                            rgba(10, 12, 16, 0.5) 45%,
                            rgba(10, 12, 16, 0.05) 80%
                        );
                    "
                />
            </div>
            <div class="relative flex h-full flex-col justify-center text-white">
                <p
                    class="max-w-[52%] text-lg leading-tight font-extrabold tracking-tight"
                    :style="{ fontFamily: 'var(--t-display)' }"
                >
                    {{ heading }}
                </p>
                <p
                    v-if="sub"
                    class="mt-2 max-w-[48%] text-[11px] opacity-80"
                    :style="{ fontFamily: 'var(--t-body)' }"
                >
                    {{ sub }}
                </p>
            </div>
        </template>

        <template v-else-if="variant === 'cover'">
            <p
                v-if="eyebrow"
                class="mb-3 text-[9px] font-bold tracking-[0.16em] uppercase"
                :style="{ color: 'var(--p-cover-title)' }"
            >
                {{ eyebrow }}
            </p>
            <p
                class="text-lg leading-tight font-extrabold tracking-tight"
                :style="{ fontFamily: 'var(--t-display)' }"
            >
                {{ heading }}
            </p>
            <div
                class="mt-3 h-[3px] w-9 rounded-full"
                :style="{ background: 'var(--p-cover-accent)' }"
            />
            <p
                v-if="sub"
                class="mt-3 text-[11px] opacity-70"
                :style="{ fontFamily: 'var(--t-body)' }"
            >
                {{ sub }}
            </p>
        </template>

        <template v-else>
            <div
                v-if="image"
                class="absolute inset-y-3 w-[40%] overflow-hidden rounded-md"
                :class="image === 'left' ? 'left-3' : 'right-3'"
            >
                <img
                    v-if="src"
                    :src="src"
                    alt=""
                    loading="lazy"
                    class="size-full object-cover"
                />
                <SlideArt v-else scene="hills" />
            </div>
            <div :class="image === 'left' ? 'ml-[48%]' : image ? 'mr-[48%]' : ''">
            <p
                class="text-[13px] leading-tight font-bold"
                :style="{ fontFamily: 'var(--t-display)' }"
            >
                {{ heading }}
            </p>
            <div
                class="mt-2 mb-4 h-px w-full"
                :style="{ background: 'var(--p-rule)' }"
            />
            <div :style="{ fontFamily: 'var(--t-body)' }">
                <slot />
            </div>
            </div>
        </template>
    </div>
</template>
