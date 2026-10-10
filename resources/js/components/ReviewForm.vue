<script setup lang="ts">
/**
 * Форма оценки презентации: пять звёзд и пара слов.
 * Живёт в модальном окне на списке презентаций.
 *
 * Новая оценка начинается с пустых звёзд. Если оценка уже сохранена,
 * окно открывается с ней — чтобы поправить, а не ставить заново.
 */
import { router } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import Field from '@/components/Field.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { goal } from '@/lib/metrika';

const props = defineProps<{
    url: string;
    rating: number | null;
    review: string | null;
    /* Кнопка «Отмена»: в окне она закрывает его, под презентацией
       нужна только при правке уже сохранённой оценки */
    cancelable?: boolean;
}>();

const emit = defineEmits<{ saved: []; cancel: [] }>();

const MAX = 1000;

const LABELS = ['', 'Плохо', 'Так себе', 'Нормально', 'Хорошо', 'Отлично'];

const picked = ref(props.rating ?? 0);
const hovered = ref(0);
const text = ref(props.review ?? '');
const sending = ref(false);
const error = ref<string | null>(null);

const shown = computed(() => hovered.value || picked.value);
const left = computed(() => MAX - text.value.length);

function submit() {
    if (!picked.value || sending.value) return;

    router.post(
        props.url,
        { rating: picked.value, review: text.value.trim() || null },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                sending.value = true;
                error.value = null;
            },
            onSuccess: () => {
                goal('presentation_rated', { rating: picked.value });
                emit('saved');
            },
            onError: (errors) => {
                error.value =
                    errors.rating ??
                    errors.review ??
                    'Не получилось сохранить. Попробуйте ещё раз.';
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <!-- Подсветка — только от мыши, не от фокуса: окно при открытии
                 само ставит фокус на первую звезду, и она выглядела бы
                 выбранной. Фокус с клавиатуры виден по обводке -->
            <div
                class="-ml-1 flex items-center"
                role="radiogroup"
                aria-label="Оценка от 1 до 5"
                @mouseleave="hovered = 0"
            >
                <button
                    v-for="n in 5"
                    :key="n"
                    type="button"
                    role="radio"
                    :aria-checked="picked === n"
                    :aria-label="`${n} из 5 — ${LABELS[n]}`"
                    class="cursor-pointer rounded-md p-1 transition-transform hover:scale-110 focus-visible:outline-2"
                    @mouseenter="hovered = n"
                    @click="picked = n"
                >
                    <Star
                        class="size-8 transition-colors"
                        :class="
                            n <= shown
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-muted-foreground/40'
                        "
                    />
                </button>
            </div>
            <span
                class="text-muted-foreground min-w-20 text-sm"
                aria-live="polite"
            >
                {{ LABELS[shown] }}
            </span>
        </div>

        <Field
            for="review-text"
            :counter="left < 200 ? String(left) : undefined"
        >
            <Textarea
                id="review-text"
                v-model="text"
                rows="4"
                :maxlength="MAX"
                placeholder="Что понравилось, что поправить? Можно не писать"
                class="resize-none"
            />
        </Field>

        <p v-if="error" class="text-destructive text-sm">{{ error }}</p>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button
                v-if="cancelable !== false"
                type="button"
                variant="outline"
                @click="emit('cancel')"
            >
                Отмена
            </Button>
            <Button type="submit" :disabled="!picked || sending">
                {{ sending ? 'Сохраняем…' : 'Оценить' }}
            </Button>
        </div>
    </form>
</template>
