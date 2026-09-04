<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Download,
    ExternalLink,
    Link2,
    Pencil,
    RotateCcw,
    Trash2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
    previewUrl: string | null;
    downloadUrl: string | null;
    editUrl: string | null;
    theme: string;
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
const theme = ref(props.presentation.theme ?? props.themes[0]?.key ?? 'clay');
const sending = ref(false);
const formError = ref<string | null>(null);

const allAnswered = computed(
    () => (current.value.questions ?? []).every((q, i) => answers.value[q.key ?? String(i)]),
);

function submitAnswers() {
    sending.value = true;
    formError.value = null;

    router.post(
        `/presentations/${current.value.id}/answers`,
        { answers: answers.value, theme: theme.value },
        {
            onError: (errors) => {
                formError.value =
                    Object.values(errors)[0] ??
                    'Не получилось отправить. Попробуйте ещё раз.';
            },
            onFinish: () => (sending.value = false),
        },
    );
}

/* ---------- Опрос статуса, пока идёт генерация ---------- */

/*
   Ограничение по времени обязательно: если воркер не запущен или
   упал молча, без него человек будет бесконечно смотреть на
   крутящийся индикатор и не поймёт, что случилось.
*/
const POLL_INTERVAL = 2500;
const POLL_LIMIT_MS = 4 * 60 * 1000;

let timer: number | undefined;
let startedAt = Date.now();

const stalled = ref(false);
const offline = ref(false);

function stopPolling() {
    window.clearInterval(timer);
    timer = undefined;
}

function startPolling() {
    if (timer) return;

    startedAt = Date.now();
    stalled.value = false;
    timer = window.setInterval(poll, POLL_INTERVAL);
}

async function poll() {
    if (Date.now() - startedAt > POLL_LIMIT_MS) {
        stopPolling();
        stalled.value = true;

        return;
    }

    let fresh: Presentation;

    try {
        const response = await fetch(`/presentations/${current.value.id}/status`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) return;

        fresh = await response.json();
        offline.value = false;
    } catch {
        // Сеть отвалилась — молчим и пробуем на следующем тике
        offline.value = true;

        return;
    }

    const changed = fresh.status !== current.value.status;
    current.value = fresh;

    // Статус стал конечным — перезагружаем страницу целиком,
    // чтобы подтянуть готовые данные, и прекращаем опрос
    if (changed && !fresh.isPending) {
        stopPolling();
        router.reload();
    }
}

/*
   Inertia не пересоздаёт компонент при переходе на ту же страницу,
   поэтому локальную копию нужно синхронизировать руками. Без этого
   после отправки ответов экран так и остаётся на вопросах.
*/
watch(
    () => props.presentation,
    (fresh) => {
        current.value = fresh;

        if (fresh.isPending) {
            startPolling();
        } else {
            stopPolling();
        }
    },
);

onMounted(() => {
    if (current.value.isPending) {
        startPolling();
    }
});

onBeforeUnmount(stopPolling);

/* ---------- Смена оформления у готовой презентации ---------- */

const switching = ref<string | null>(null);

function switchTheme(key: string) {
    if (key === current.value.theme || switching.value) return;

    switching.value = key;
    router.post(
        `/presentations/${current.value.id}/theme`,
        { theme: key },
        { onFinish: () => (switching.value = null) },
    );
}

/* ---------- Удаление ---------- */

const deleting = ref(false);

function destroy() {
    deleting.value = true;
    router.delete(`/presentations/${current.value.id}`, {
        onFinish: () => (deleting.value = false),
    });
}

/* ---------- Публичная ссылка ---------- */

const copied = ref(false);
const copyFailed = ref(false);

/*
   navigator.clipboard существует только в защищённом контексте:
   по http его нет вообще. Поэтому запасной путь через скрытое поле
   и execCommand — он старый, но работает везде.
*/
async function copyToClipboard(text: string): Promise<boolean> {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Пользователь мог не дать разрешение — пробуем иначе
        }
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();

    let ok = false;

    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }

    document.body.removeChild(field);

    return ok;
}

async function copyShare() {
    if (!current.value.shareUrl) return;

    const ok = await copyToClipboard(current.value.shareUrl);

    if (ok) {
        copied.value = true;
        copyFailed.value = false;
        window.setTimeout(() => (copied.value = false), 2000);

        return;
    }

    // Скопировать не вышло — показываем ссылку, чтобы взять руками
    copyFailed.value = true;
}

</script>

<template>
    <Head :title="current.title" />

    <div class="mx-auto w-full max-w-3xl px-4 py-8">
        <!-- Ждём: готовим вопросы или генерируем -->
        <div v-if="current.isPending" class="flex flex-col items-center gap-5 py-24 text-center">
            <template v-if="stalled">
                <p class="text-lg font-medium">Что-то затянулось</p>
                <p class="text-muted-foreground max-w-md text-sm leading-relaxed">
                    Обычно всё занимает меньше минуты. Мы продолжим работу в
                    фоне — обновите страницу через пару минут или вернитесь к
                    списку, презентация появится там сама.
                </p>
                <div class="flex gap-2">
                    <Button variant="outline" @click="router.reload()">
                        Обновить
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link href="/presentations">К списку</Link>
                    </Button>
                </div>
            </template>

            <template v-else>
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
                <p v-if="offline" class="text-muted-foreground text-xs">
                    Связь пропала — ждём восстановления
                </p>
            </template>
        </div>

        <!-- Уточняющие вопросы -->
        <div v-else-if="current.questions?.length" class="space-y-8">
            <div class="border-rule border-b pb-6">
                <h1 class="text-3xl font-extrabold">Уточним детали</h1>
                <p class="text-muted-foreground mt-1.5 leading-relaxed">
                    Пара ответов — и структура получится точнее.
                </p>
            </div>

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
                                    ? 'border-brand bg-brand text-white'
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
                <div class="flex flex-wrap gap-2">
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

            <div class="flex items-center justify-end gap-4 border-t pt-6">
                <p v-if="formError" class="text-destructive flex-1 text-sm">
                    {{ formError }}
                </p>
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
                    <h1 class="text-2xl font-extrabold">{{ current.title }}</h1>
                </div>

                <div class="flex flex-none gap-2">
                    <Button v-if="current.editUrl" variant="outline" size="sm" as-child>
                        <Link :href="current.editUrl">
                            <Pencil class="size-4" />
                            Править
                        </Link>
                    </Button>
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

                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Удалить презентацию"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </DialogTrigger>

                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Удалить презентацию?</DialogTitle>
                                <DialogDescription>
                                    Файл и публичная ссылка перестанут работать.
                                    Отменить это действие нельзя.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="outline">Оставить</Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    :disabled="deleting"
                                    @click="destroy"
                                >
                                    Удалить
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <div
                v-if="copyFailed"
                class="border-rule bg-secondary flex items-center gap-3 rounded-lg border px-4 py-3"
            >
                <p class="text-muted-foreground flex-none text-sm">Ссылка:</p>
                <input
                    :value="current.shareUrl"
                    readonly
                    class="w-full flex-1 bg-transparent font-mono text-sm outline-none"
                    @focus="($event.target as HTMLInputElement).select()"
                />
            </div>

            <div class="border-border group relative overflow-hidden rounded-xl border bg-neutral-100 dark:bg-neutral-900">
                <iframe
                    :key="current.previewUrl!"
                    :src="current.previewUrl!"
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

            <!-- Оформление меняется бесплатно: структура уже готова,
                 перепечатывается только файл -->
            <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                <p class="text-muted-foreground text-sm">Оформление</p>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="t in themes"
                        :key="t.key"
                        type="button"
                        class="border-border flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm transition-colors disabled:opacity-50"
                        :class="
                            current.theme === t.key
                                ? 'border-foreground'
                                : 'hover:border-foreground/40'
                        "
                        :disabled="switching !== null"
                        @click="switchTheme(t.key)"
                    >
                        <span class="flex gap-1">
                            <span class="size-3.5 rounded-full" :style="{ background: t.cover }" />
                            <span class="size-3.5 rounded-full" :style="{ background: t.accent }" />
                        </span>
                        {{ switching === t.key ? 'Меняем…' : t.name }}
                    </button>
                </div>
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
                <Button
                    variant="ghost"
                    class="text-muted-foreground"
                    :disabled="deleting"
                    @click="destroy"
                >
                    <Trash2 class="size-4" />
                    Удалить
                </Button>
            </div>
        </div>
    </div>
</template>
