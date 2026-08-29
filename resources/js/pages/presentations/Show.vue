<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Download, ExternalLink, Link2, RotateCcw } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Question = {
    key: string | null;
    question: string;
    options: string[];
    answer: string | null;
};

type Theme = { key: string; name: string; accent: string; cover: string };

type Presentation = {
    id: number;
    title: string;
    topic: string;
    status: string;
    statusLabel: string;
    slideCount: number;
    questions: Question[] | null;
    isPending: boolean;
    isReady: boolean;
    error: string | null;
    shareUrl: string | null;
    downloadUrl: string | null;
};

const props = defineProps<{
    presentation: Presentation;
    themes: Theme[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Презентации', href: '/presentations' }],
    },
});

const current = ref<Presentation>(props.presentation);

/* ---------- Ответы на уточняющие вопросы ---------- */

const answers = ref<Record<string, string>>({});
const theme = ref(props.themes[0]?.key ?? 'graphite');
const sending = ref(false);

const allAnswered = computed(
    () => (current.value.questions ?? []).every((q, i) => answers.value[q.key ?? String(i)]),
);

function submitAnswers() {
    sending.value = true;

    router.post(
        `/presentations/${current.value.id}/answers`,
        { answers: answers.value, theme: theme.value },
        { onFinish: () => (sending.value = false) },
    );
}

/* ---------- Опрос статуса, пока идёт генерация ---------- */

let timer: number | undefined;

async function poll() {
    const response = await fetch(`/presentations/${current.value.id}/status`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) return;

    const fresh: Presentation = await response.json();
    const changed = fresh.status !== current.value.status;
    current.value = fresh;

    // Статус сменился на конечный — перезагружаем страницу целиком,
    // чтобы подтянуть готовые данные и остановить опрос
    if (changed && !fresh.isPending) {
        router.reload();
    }
}

onMounted(() => {
    if (current.value.isPending) {
        timer = window.setInterval(poll, 2500);
    }
});

onBeforeUnmount(() => window.clearInterval(timer));

/* ---------- Публичная ссылка ---------- */

const copied = ref(false);

function copyShare() {
    if (!current.value.shareUrl) return;

    void navigator.clipboard.writeText(current.value.shareUrl);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Head :title="current.title" />

    <div class="mx-auto w-full max-w-3xl p-4">
        <!-- Ждём: готовим вопросы или генерируем -->
        <div v-if="current.isPending" class="flex flex-col items-center gap-5 py-24 text-center">
            <Spinner class="size-7" />
            <div class="space-y-1">
                <p class="text-lg font-medium">{{ current.statusLabel }}…</p>
                <p class="text-muted-foreground text-sm">
                    {{
                        current.status === 'draft'
                            ? 'Читаем тему и подбираем вопросы'
                            : 'Собираем слайды и печатаем файл. Обычно это меньше минуты.'
                    }}
                </p>
            </div>
            <p class="text-muted-foreground max-w-md text-sm">{{ current.topic }}</p>
        </div>

        <!-- Уточняющие вопросы -->
        <div v-else-if="current.questions?.length" class="space-y-8">
            <Heading
                title="Уточним детали"
                description="Пара ответов — и структура получится точнее."
            />

            <div class="space-y-7">
                <div v-for="(q, i) in current.questions" :key="q.key ?? i" class="space-y-3">
                    <p class="font-medium">{{ q.question }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in q.options"
                            :key="option"
                            type="button"
                            class="border-border rounded-lg border px-4 py-2 text-sm transition-colors"
                            :class="
                                answers[q.key ?? String(i)] === option
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'hover:border-foreground/40'
                            "
                            @click="answers[q.key ?? String(i)] = option"
                        >
                            {{ option }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-3 border-t pt-6">
                <p class="font-medium">Оформление</p>
                <div class="flex gap-3">
                    <button
                        v-for="t in themes"
                        :key="t.key"
                        type="button"
                        class="border-border flex items-center gap-3 rounded-lg border px-4 py-3 text-sm transition-colors"
                        :class="theme === t.key ? 'border-foreground' : 'hover:border-foreground/40'"
                        @click="theme = t.key"
                    >
                        <span class="flex gap-1">
                            <span class="size-4 rounded-full" :style="{ background: t.cover }" />
                            <span class="size-4 rounded-full" :style="{ background: t.accent }" />
                        </span>
                        {{ t.name }}
                    </button>
                </div>
            </div>

            <div class="flex justify-end border-t pt-6">
                <Button size="lg" :disabled="!allAnswered || sending" @click="submitAnswers">
                    {{ sending ? 'Отправляем…' : 'Собрать презентацию' }}
                </Button>
            </div>
        </div>

        <!-- Готово -->
        <div v-else-if="current.isReady" class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-muted-foreground flex items-center gap-1.5 text-sm">
                        <CheckCircle2 class="size-4" />
                        Готово · {{ current.slideCount }} слайдов
                    </p>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ current.title }}</h1>
                </div>

                <div class="flex flex-none gap-2">
                    <Button variant="outline" size="sm" @click="copyShare">
                        <Link2 class="size-4" />
                        {{ copied ? 'Скопировано' : 'Ссылка' }}
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="current.shareUrl!" target="_blank" rel="noopener">
                            <ExternalLink class="size-4" />
                            Открыть
                        </a>
                    </Button>
                    <Button size="sm" as-child>
                        <a :href="current.downloadUrl!">
                            <Download class="size-4" />
                            Скачать
                        </a>
                    </Button>
                </div>
            </div>

            <div class="border-border group relative overflow-hidden rounded-xl border bg-neutral-100 dark:bg-neutral-900">
                <iframe
                    :src="current.shareUrl!"
                    class="aspect-video w-full"
                    title="Просмотр презентации"
                />

                <a
                    :href="current.shareUrl!"
                    target="_blank"
                    rel="noopener"
                    class="bg-background/90 text-foreground pointer-events-auto absolute top-3 right-3 flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium opacity-0 shadow-sm backdrop-blur transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                >
                    <ExternalLink class="size-3.5" />
                    Во весь экран
                </a>
            </div>
        </div>

        <!-- Ошибка или неожиданное состояние -->
        <div v-else class="space-y-5 py-16 text-center">
            <div class="space-y-1">
                <p class="text-lg font-medium">Не получилось</p>
                <p class="text-muted-foreground mx-auto max-w-md text-sm">
                    {{ current.error ?? 'Что-то пошло не так во время генерации.' }}
                </p>
                <p class="text-muted-foreground/70 text-xs">
                    Состояние: {{ current.statusLabel }}
                </p>
            </div>
            <div class="flex justify-center gap-2">
                <Button
                    v-if="current.status === 'failed'"
                    variant="outline"
                    @click="router.post(`/presentations/${current.id}/retry`)"
                >
                    <RotateCcw class="size-4" />
                    Попробовать снова
                </Button>
                <Button variant="ghost" as-child>
                    <Link href="/presentations">К списку</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
