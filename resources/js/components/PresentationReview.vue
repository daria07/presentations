<script setup lang="ts">
/**
 * Оценка под готовой презентацией. Та же форма, что в окне на списке
 * (ReviewForm), только встроенная в страницу.
 *
 * Пока оценки нет — форма с пустыми звёздами. После отправки —
 * благодарность с сохранённой оценкой и кнопка «Изменить».
 */
import { Star } from '@lucide/vue';
import { ref } from 'vue';
import ReviewForm from '@/components/ReviewForm.vue';

const props = defineProps<{
    url: string;
    rating: number | null;
    review: string | null;
}>();

const editing = ref(false);
</script>

<template>
    <section
        class="border-border bg-card max-w-xl rounded-xl border p-4 sm:p-5"
        aria-label="Оценка презентации"
    >
        <!-- Оценка сохранена -->
        <div v-if="props.rating && !editing" class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium">Спасибо за оценку!</p>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground cursor-pointer text-sm transition-colors"
                    @click="editing = true"
                >
                    Изменить
                </button>
            </div>
            <div
                class="flex items-center gap-0.5"
                :aria-label="`${props.rating} из 5`"
            >
                <Star
                    v-for="n in 5"
                    :key="n"
                    class="size-5"
                    :class="
                        n <= props.rating
                            ? 'fill-amber-400 text-amber-400'
                            : 'text-muted-foreground/40'
                    "
                />
            </div>
            <p
                v-if="props.review"
                class="text-muted-foreground text-sm leading-relaxed break-words whitespace-pre-line"
            >
                {{ props.review }}
            </p>
        </div>

        <!-- Форма: новая оценка или правка -->
        <div v-else class="space-y-3">
            <div>
                <p class="text-sm font-medium">Как вам презентация?</p>
                <p class="text-muted-foreground text-xs">
                    Оценка помогает нам делать генерацию лучше
                </p>
            </div>

            <ReviewForm
                :url="props.url"
                :rating="props.rating"
                :review="props.review"
                :cancelable="props.rating !== null"
                @saved="editing = false"
                @cancel="editing = false"
            />
        </div>
    </section>
</template>
