<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    credits: number;
    trialAvailable: boolean;
    maxSource: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Презентации', href: '/presentations' },
            { title: 'Новая', href: '/presentations/new' },
        ],
    },
});

/* Два сценария: придумать с нуля или уложить готовый материал */
type Mode = 'topic' | 'text';
const mode = ref<Mode>('topic');

const topic = ref('');
const sourceText = ref('');
const focus = ref('');

const slideCount = ref(10);
const counts = [6, 8, 10, 12, 15];

const examples = [
    'Пётр I и его реформы государственного управления',
    'Как работает фотосинтез — для восьмого класса',
    'Итоги квартала: продажи, отток, планы на следующий',
];

const charsLeft = computed(() => props.maxSource - sourceText.value.length);

const canSubmit = computed(() =>
    mode.value === 'topic'
        ? topic.value.trim().length >= 3
        : sourceText.value.trim().length >= 200 && charsLeft.value >= 0,
);
</script>

<template>
    <Head title="Новая презентация" />

    <div class="mx-auto w-full max-w-2xl px-4 py-8">
        <div class="border-rule border-b pb-6">
            <h1 class="text-3xl font-extrabold">Новая презентация</h1>
            <p class="text-muted-foreground mt-1.5 max-w-[48ch] leading-relaxed">
                Опишите тему одной строкой — или вставьте готовый текст, и мы
                разложим его по слайдам.
            </p>
        </div>

        <!-- Переключатель сценария -->
        <div class="border-rule mt-8 flex gap-1 rounded-lg border p-1">
            <button
                type="button"
                class="flex-1 rounded-md px-4 py-2.5 text-sm font-medium transition-colors"
                :class="mode === 'topic' ? 'bg-foreground text-background' : 'hover:bg-secondary'"
                @click="mode = 'topic'"
            >
                По теме
            </button>
            <button
                type="button"
                class="flex-1 rounded-md px-4 py-2.5 text-sm font-medium transition-colors"
                :class="mode === 'text' ? 'bg-foreground text-background' : 'hover:bg-secondary'"
                @click="mode = 'text'"
            >
                По готовому тексту
            </button>
        </div>

        <Form
            action="/presentations"
            method="post"
            class="mt-8 space-y-9"
            v-slot="{ errors, processing }"
        >
            <!-- Сценарий «по теме» -->
            <div v-if="mode === 'topic'" class="space-y-2">
                <Label for="topic" class="sr-only">Тема</Label>
                <textarea
                    id="topic"
                    name="topic"
                    v-model="topic"
                    rows="3"
                    autofocus
                    placeholder="Например: Пётр I и его реформы государственного управления"
                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full resize-none rounded-lg border px-4 py-3 text-base leading-relaxed focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                />
                <InputError :message="errors.topic" />

                <div class="flex flex-wrap gap-2 pt-1">
                    <button
                        v-for="example in examples"
                        :key="example"
                        type="button"
                        class="text-muted-foreground hover:text-foreground hover:border-foreground/30 border-border rounded-full border px-3 py-1 text-xs transition-colors"
                        @click="topic = example"
                    >
                        {{ example.length > 42 ? example.slice(0, 42) + '…' : example }}
                    </button>
                </div>
            </div>

            <!-- Сценарий «по готовому тексту» -->
            <div v-else class="space-y-6">
                <div class="space-y-2">
                    <div class="flex items-baseline justify-between">
                        <Label for="source_text">Текст</Label>
                        <span
                            class="text-xs tabular-nums"
                            :class="charsLeft < 0 ? 'text-destructive' : 'text-muted-foreground'"
                        >
                            {{ sourceText.length.toLocaleString('ru-RU') }} из
                            {{ maxSource.toLocaleString('ru-RU') }}
                        </span>
                    </div>

                    <textarea
                        id="source_text"
                        name="source_text"
                        v-model="sourceText"
                        rows="12"
                        autofocus
                        placeholder="Вставьте статью, реферат, конспект или расшифровку встречи. Мы отберём главное и разложим по слайдам, ничего не дописывая от себя."
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full resize-y rounded-lg border px-4 py-3 text-[15px] leading-relaxed focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    />
                    <InputError :message="errors.source_text" />

                    <p class="text-muted-foreground text-sm leading-relaxed">
                        Работаем строго по вашему тексту: фактов и цифр, которых
                        в нём нет, в презентации не появится.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="focus">На что сделать акцент</Label>
                    <input
                        id="focus"
                        name="topic"
                        v-model="focus"
                        type="text"
                        placeholder="Необязательно. Например: выводы и практическая польза"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-lg border px-4 py-2.5 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    />
                </div>
            </div>

            <!-- Количество слайдов -->
            <div class="space-y-3">
                <Label>Сколько слайдов</Label>
                <input type="hidden" name="slide_count" :value="slideCount" />
                <div class="flex gap-2">
                    <button
                        v-for="n in counts"
                        :key="n"
                        type="button"
                        class="border-border h-11 flex-1 rounded-lg border text-sm font-medium transition-colors"
                        :class="
                            slideCount === n
                                ? 'border-brand bg-brand text-white'
                                : 'hover:border-foreground/40'
                        "
                        @click="slideCount = n"
                    >
                        {{ n }}
                    </button>
                </div>
                <InputError :message="errors.slide_count" />
            </div>

            <div class="flex items-center justify-between gap-4 border-t pt-6">
                <p class="text-muted-foreground text-sm">
                    <template v-if="trialAvailable">
                        Первая презентация — бесплатно.
                    </template>
                    <template v-else-if="credits > 0">
                        Осталось генераций:
                        <span class="text-foreground font-medium tabular-nums">
                            {{ credits }}
                        </span>
                    </template>
                    <template v-else>
                        Генерации закончились — пополните счёт.
                    </template>
                </p>

                <Button type="submit" :disabled="processing || !canSubmit" size="lg">
                    {{ processing ? 'Готовим…' : 'Продолжить' }}
                </Button>
            </div>
        </Form>
    </div>
</template>
