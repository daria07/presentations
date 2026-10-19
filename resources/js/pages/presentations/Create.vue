<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Lock } from '@lucide/vue';
import { computed, ref } from 'vue';
import Field from '@/components/Field.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = defineProps<{
    credits: number;
    trialAvailable: boolean;
    maxSource: number;
    /* Цена самой дешёвой покупки с учётом скидки, без «₽» */
    entryPrice: string;
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

const slideCount = ref(8);
const counts = [6, 8, 10, 12, 15];

/* Пробная версия — до 8 слайдов (как FREE_MAX_SLIDES на сервере).
   Большие варианты видны, но закрыты замком, пока нет купленных генераций */
const FREE_MAX_SLIDES = 8;
const isClosed = (n: number) => props.credits < 1 && n > FREE_MAX_SLIDES;

/* Блок с примерами тем под полем. Выключен, пока не решили, как его подать */
const SHOW_EXAMPLES = false;

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
        <!--
            Возврат к списку. Хлебные крошки в шапке есть, но они мелкие
            и на телефоне их не видно совсем — отсюда человек уходит
            либо кнопкой браузера, либо никак.
        -->
        <Link
            href="/presentations"
            class="text-muted-foreground hover:text-foreground mb-4 inline-flex items-center gap-1.5 text-sm transition-colors"
        >
            <ChevronLeft class="size-4" />
            Все презентации
        </Link>
        <div class="border-rule border-b pb-6">
            <h1 class="text-3xl font-extrabold">Новая презентация</h1>
            <p class="text-muted-foreground mt-1.5 max-w-lg leading-relaxed">
                Достаточно одной строки, чтобы создать презентацию. А если есть
                особые пожелания — расскажите подробнее, и мы учтём их при
                создании.
            </p>
        </div>

        <!-- Переключатель сценария -->
        <div class="border-rule mt-8 flex gap-1 rounded-lg border p-1">
            <button
                type="button"
                class="flex-1 cursor-pointer rounded-md px-4 py-2.5 text-sm font-medium transition-colors"
                :class="
                    mode === 'topic'
                        ? 'bg-foreground text-background'
                        : 'hover:bg-secondary'
                "
                @click="mode = 'topic'"
            >
                По теме
            </button>
            <button
                type="button"
                class="flex-1 cursor-pointer rounded-md px-4 py-2.5 text-sm font-medium transition-colors"
                :class="
                    mode === 'text'
                        ? 'bg-foreground text-background'
                        : 'hover:bg-secondary'
                "
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
                <!-- Поле подсказывает, что писать можно много: короткая
                     тема тоже сработает, но пожелания модель угадать
                     не может — их стоит назвать -->
                <Field
                    for="topic"
                    label="О чём презентация"
                    :counter="`${topic.length} из 1000`"
                    :error="errors.topic"
                    hint="Для конкретики добавьте: для кого выступление, сколько минут, что обязательно показать, какие факты или цифры не забыть, чего не нужно."
                >
                    <Textarea
                        id="topic"
                        name="topic"
                        v-model="topic"
                        rows="5"
                        autofocus
                        maxlength="1000"
                        placeholder="Можно коротко: «Пётр I и его реформы».&#10;&#10;А можно подробно: «Пётр I и его реформы. Доклад для 9 класса на 7 минут. Главное — флот, Петербург и табель о рангах, в конце — что изменилось для простых людей»."
                    />
                </Field>

                <!-- Примеры тем пока скрыты: придумаем, как их подать.
                     Включить — SHOW_EXAMPLES = true -->
                <div v-if="SHOW_EXAMPLES" class="flex flex-wrap gap-2 pt-1">
                    <button
                        v-for="example in examples"
                        :key="example"
                        type="button"
                        class="text-muted-foreground hover:text-foreground hover:border-foreground/30 border-border cursor-pointer rounded-full border px-3 py-1 text-xs transition-colors"
                        @click="topic = example"
                    >
                        {{
                            example.length > 42
                                ? example.slice(0, 42) + '…'
                                : example
                        }}
                    </button>
                </div>

                <!-- Учебники почти все платные: прочитать книгу сами мы
                     не можем, поэтому «строго по учебнику» — только
                     по тексту, который человек вставит сам -->
                <p class="text-muted-foreground pt-1 text-xs leading-relaxed">
                    Нужно строго по учебнику или методичке? Вставьте главу во
                    вкладке
                    <button
                        type="button"
                        class="cursor-pointer underline underline-offset-2"
                        @click="mode = 'text'"
                    >
                        «По готовому тексту»
                    </button>
                    — соберём презентацию только по ней.
                </p>
            </div>

            <!-- Сценарий «по готовому тексту» -->
            <div v-else class="space-y-6">
                <Field
                    for="source_text"
                    label="Текст"
                    :counter="`${sourceText.length.toLocaleString('ru-RU')} из ${maxSource.toLocaleString('ru-RU')}`"
                    :error="errors.source_text"
                    hint="Работаем строго по вашему тексту: фактов и цифр, которых в нём нет, в презентации не появится."
                >
                    <Textarea
                        id="source_text"
                        name="source_text"
                        v-model="sourceText"
                        rows="12"
                        autofocus
                        placeholder="Вставьте статью, реферат, конспект или расшифровку встречи. Мы отберём главное и разложим по слайдам, ничего не дописывая от себя."
                        :aria-invalid="charsLeft < 0 || undefined"
                    />
                </Field>

                <Field for="focus" label="На что сделать акцент">
                    <Input
                        id="focus"
                        name="topic"
                        v-model="focus"
                        type="text"
                        placeholder="Необязательно. Например: выводы и практическая польза"
                    />
                </Field>
            </div>

            <!-- Количество слайдов -->
            <div class="space-y-3">
                <Label>Сколько слайдов</Label>
                <input type="hidden" name="slide_count" :value="slideCount" />
                <!-- Закрытые варианты видны, но выбрать их нельзя:
                     вместо выбора — подсказка и переход к тарифам -->
                <TooltipProvider :delay-duration="150">
                    <div class="flex gap-2">
                        <template v-for="n in counts" :key="n">
                            <Tooltip v-if="isClosed(n)">
                                <TooltipTrigger as-child>
                                    <!-- Закрытый вариант ведёт к тарифам -->
                                    <Link
                                        href="/billing"
                                        class="border-border text-muted-foreground/60 hover:border-foreground/40 relative flex h-11 flex-1 items-center justify-center rounded-lg border text-sm font-medium transition-colors"
                                        :aria-label="`${n} слайдов — открыть за ${entryPrice} ₽, перейти к тарифам`"
                                    >
                                        {{ n }}
                                        <span
                                            class="bg-foreground text-background pointer-events-none absolute -top-1.5 -right-1.5 flex size-4 items-center justify-center rounded-full"
                                        >
                                            <Lock class="size-2.5" />
                                        </span>
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent>
                                    Открыть за {{ entryPrice }} ₽
                                </TooltipContent>
                            </Tooltip>

                            <button
                                v-else
                                type="button"
                                class="border-border h-11 flex-1 cursor-pointer rounded-lg border text-sm font-medium transition-colors"
                                :class="
                                    slideCount === n
                                        ? 'border-action bg-action text-white'
                                        : 'hover:border-foreground/40'
                                "
                                @click="slideCount = n"
                            >
                                {{ n }}
                            </button>
                        </template>
                    </div>
                </TooltipProvider>
                <InputError :message="errors.slide_count" />
            </div>

            <div class="flex items-center justify-between gap-4 border-t pt-6">
                <p class="text-muted-foreground text-sm">
                    <template v-if="trialAvailable && credits < 1">
                        Пробную презентацию соберём бесплатно — до 8 слайдов, с
                        водяным знаком.
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

                <Button
                    type="submit"
                    :disabled="processing || !canSubmit"
                    size="lg"
                >
                    {{ processing ? 'Готовим…' : 'Продолжить' }}
                </Button>
            </div>

            <!-- Предупреждение стоит до нажатия, а не после: человек
                 должен знать, что получит черновик, который надо
                 вычитать, — а не узнавать это, когда уже заплатил -->
            <p class="text-muted-foreground text-sm leading-relaxed">
                Текст и структуру собирает нейросеть. Она может ошибаться в
                фактах, датах, числах и именах, поэтому не гарантирует точность,
                полноту и актуальность написанного. Проверьте презентацию перед
                тем, как показывать её или принимать по ней решения.
            </p>
        </Form>
    </div>
</template>
