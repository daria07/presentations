<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

defineProps<{
    credits: number;
    trialAvailable: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Презентации', href: '/presentations' },
            { title: 'Новая', href: '/presentations/new' },
        ],
    },
});

const slideCount = ref(10);
const counts = [6, 8, 10, 12, 15];

const examples = [
    'Пётр I и его реформы государственного управления',
    'Как работает фотосинтез — для восьмого класса',
    'Итоги квартала: продажи, отток, планы на следующий',
];

const topic = ref('');
</script>

<template>
    <Head title="Новая презентация" />

    <div class="mx-auto w-full max-w-2xl px-4 py-8">
        <div class="border-rule border-b pb-6">
            <h1 class="text-3xl font-extrabold">О чём презентация?</h1>
            <p class="text-muted-foreground mt-1.5 max-w-[46ch] leading-relaxed">
                Опишите тему своими словами. Дальше уточним детали парой вопросов.
            </p>
        </div>

        <Form
            action="/presentations"
            method="post"
            class="mt-8 space-y-9"
            v-slot="{ errors, processing }"
        >
            <div class="space-y-2">
                <Label for="topic" class="sr-only">Тема</Label>
                <textarea
                    id="topic"
                    name="topic"
                    v-model="topic"
                    rows="4"
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
                        Осталось генераций: <span class="text-foreground font-medium">{{ credits }}</span>
                    </template>
                    <template v-else>
                        Генерации закончились — пополните счёт.
                    </template>
                </p>

                <Button type="submit" :disabled="processing || topic.trim().length < 3" size="lg">
                    {{ processing ? 'Готовим…' : 'Продолжить' }}
                </Button>
            </div>
        </Form>
    </div>
</template>
