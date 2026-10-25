<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DeckViewer from '@/components/DeckViewer.vue';
import PresentationReview from '@/components/PresentationReview.vue';
import {
    Check,
    CheckCircle2,
    ChevronLeft,
    Ellipsis,
    Download,
    ExternalLink,
    Link2,
    Lock,
    Maximize,
    Mic,
    Pencil,
    RotateCcw,
    Trash2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { goal } from '@/lib/metrika';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { slides } from '@/lib/plural';

type Question = {
    key: string | null;
    question: string;
    options: string[];
    answer: string | null;
};

type Theme = {
    key: string;
    name: string;
    note: string;
    style: string;
    font: string;
};

type Palette = {
    key: string;
    name: string;
    note: string;
    accent: string;
    cover: string;
    paper: string;
};

/* Шаг генерации на экране ожидания — его считает сервер */
type Step = { label: string; state: 'done' | 'current' | 'todo' };

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
    /** null — слайда с источниками нет; иначе показан ли он */
    sourcesSlide: boolean | null;
    steps: Step[] | null;
    error: string | null;
    shareUrl: string | null;
    previewUrl: string | null;
    downloadUrl: string | null;
    speechUrl: string | null;
    editUrl: string | null;
    reviewUrl: string | null;
    rating: number | null;
    review: string | null;
    theme: string;
    palette: string;
    /** Пробная версия: водяной знак, действия закрыты до оплаты */
    locked: boolean;
};

const props = defineProps<{
    presentation: Presentation;
    themes: Theme[];
    palettes: Palette[];
    /* Цена самой дешёвой покупки с учётом скидки, без «₽» */
    entryPrice: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Презентации', href: '/presentations' }],
    },
});

const current = ref<Presentation>(props.presentation);

/* ---------- Ответы на уточняющие вопросы ---------- */

const answers = ref<Record<string, string>>({});
const theme = ref(
    props.presentation.theme ?? props.themes[0]?.key ?? 'precise',
);
const palette = ref(
    props.presentation.palette ?? props.palettes[0]?.key ?? 'fog',
);
const sending = ref(false);
const formError = ref<string | null>(null);

const allAnswered = computed(() =>
    (current.value.questions ?? []).every(
        (q, i) => answers.value[q.key ?? String(i)],
    ),
);

function submitAnswers() {
    sending.value = true;
    formError.value = null;

    router.post(
        `/presentations/${current.value.id}/answers`,
        { answers: answers.value, theme: theme.value, palette: palette.value },
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

   Но считаем не всё время целиком, а время без движения: с поиском
   источников и картинками генерация идёт 4–5 минут, и общий лимит
   в 4 минуты объявлял «затянулось» живой задаче. Пока шаги меняются,
   работа идёт. Зависла — это когда один шаг стоит дольше, чем задаче
   вообще позволено работать (5 минут у воркера плюс запас).
*/
const POLL_INTERVAL = 2500;
const POLL_LIMIT_MS = 4 * 60 * 1000;
const STEP_LIMIT_MS = 6 * 60 * 1000;

let timer: number | undefined;
let progressAt = Date.now();
let progressKey = '';

/** Что считается движением: смена статуса или текущего шага */
const progressOf = (p: Presentation) =>
    p.status + '|' + (p.steps ?? []).map((step) => step.state).join(',');

const stalled = ref(false);
const offline = ref(false);

function stopPolling() {
    window.clearInterval(timer);
    timer = undefined;
}

function startPolling() {
    if (timer) return;

    progressAt = Date.now();
    progressKey = progressOf(current.value);
    stalled.value = false;
    timer = window.setInterval(poll, POLL_INTERVAL);
}

async function poll() {
    if (Date.now() - progressAt > STEP_LIMIT_MS) {
        stopPolling();
        stalled.value = true;

        return;
    }

    let fresh: Presentation;

    try {
        const response = await fetch(
            `/presentations/${current.value.id}/status`,
            {
                headers: { Accept: 'application/json' },
            },
        );

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

    const key = progressOf(fresh);

    if (key !== progressKey) {
        progressKey = key;
        progressAt = Date.now();
    }

    // Статус стал конечным — перезагружаем страницу целиком,
    // чтобы подтянуть готовые данные, и прекращаем опрос
    if (changed && !fresh.isPending) {
        stopPolling();

        // Цель Метрики: человек получил готовую презентацию. Именно
        // здесь, а не в момент отправки формы — иначе в цель попадут
        // и неудачные генерации
        if (fresh.isReady) {
            goal('presentation_ready', { slides: fresh.slideCount });
        }

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

/*
   Экран перекрашивается мгновенно, а PDF на сервере печатается заново
   и занимает до минуты. Пока он не готов, «Открыть» и «Скачать PDF»
   отдали бы файл в прежнем оформлении — честнее подождать.
*/
const reprinting = ref(false);

/*
   Номер последнего запроса на смену оформления. Клики идут быстрее,
   чем печатается PDF, поэтому циклов опроса может крутиться несколько.
   Ответ устаревшего цикла вернул бы прежнюю гамму поверх новой — такой
   цикл просто выходит, не трогая экран.
*/
let lookRequest = 0;

async function waitForFile() {
    const request = ++lookRequest;

    reprinting.value = true;

    const startedAt = Date.now();

    while (Date.now() - startedAt < POLL_LIMIT_MS) {
        await new Promise((resolve) => setTimeout(resolve, POLL_INTERVAL));

        try {
            const response = await fetch(
                `/presentations/${current.value.id}/status`,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) continue;

            const fresh = await response.json();

            if (request !== lookRequest) return;

            if (!fresh.isPending) {
                // Забираем свежие ссылки, оформление на экране не трогаем:
                // человек уже видит то, что выбрал, и обе оси выбирает он
                current.value = {
                    ...fresh,
                    theme: current.value.theme,
                    palette: current.value.palette,
                };
                break;
            }
        } catch {
            // Сеть моргнула — пробуем на следующем круге
        }
    }

    if (request === lookRequest) {
        reprinting.value = false;
    }
}

/** Файл оформления, соответствующий теме */
function styleOf(themeKey: string): string {
    return props.themes.find((t) => t.key === themeKey)?.style ?? 'precise';
}

/*
   Просмотрщик перекрашивается сам: он следит за темой и гаммой и меняет
   атрибуты на контейнерах со слайдами. Ни запроса, ни перерисовки.
   На сервер уходим отдельно и молча — там перепечатывается PDF, чтобы
   скачанный файл совпадал с тем, что человек видит.

   Обе оси сохраняются одной ручкой: печать всё равно одна.

   Отправку придерживаем: оформление выбирают перебором, щёлкая гаммы
   одну за другой. Слать запрос на каждый клик — это десяток лишних
   перепечаток, каждая со своим запуском Chrome на сервере, и упор
   в ограничение частоты на ровном месте. Ждём, пока человек
   остановится, и сохраняем то, на чём он остановился.
*/
const LOOK_SAVE_DELAY = 900;

let lookTimer: number | undefined;

// Уходим со страницы — отложенное сохранение уже некуда применять
onBeforeUnmount(() => window.clearTimeout(lookTimer));

function switchLook(next: { theme?: string; palette?: string }) {
    const themeKey = next.theme ?? current.value.theme;
    const paletteKey = next.palette ?? current.value.palette;

    if (
        themeKey === current.value.theme &&
        paletteKey === current.value.palette
    ) {
        return;
    }

    current.value = { ...current.value, theme: themeKey, palette: paletteKey };
    switching.value = themeKey + paletteKey;

    window.clearTimeout(lookTimer);
    lookTimer = window.setTimeout(
        () => saveLook(themeKey, paletteKey),
        LOOK_SAVE_DELAY,
    );
}

function saveLook(themeKey: string, paletteKey: string) {
    // XSRF-токен Laravel кладёт в cookie; в заголовок он идёт
    // раскодированным, иначе проверка не сойдётся
    const token = decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );

    fetch(`/presentations/${current.value.id}/theme`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ theme: themeKey, palette: paletteKey }),
    })
        .catch(() => {
            // Не сохранилось — экран уже перекрашен, но файл останется
            // прежним. Честнее перезагрузить и показать правду.
            router.reload();
        })
        .then(() => waitForFile())
        .finally(() => {
            switching.value = null;
        });
}

/* ---------- Слайд с источниками ---------- */

/*
   Слайд не удаляется, а скрывается: передумал — вернул галочку.
   Как и смена оформления, это перепечатка PDF, поэтому дальше тот же
   путь: ждём файл, а потом забираем свежий просмотр уже без слайда.
*/
function toggleSources(visible: boolean) {
    if (current.value.sourcesSlide === visible) return;

    current.value = { ...current.value, sourcesSlide: visible };

    const token = decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );

    fetch(`/presentations/${current.value.id}/sources`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ visible }),
    })
        .then(() => waitForFile())
        .catch(() => router.reload());
}

/* ---------- Пробная версия ---------- */

/*
   В пробной версии действия видны там же, где обычно, но закрыты
   замком: человек должен видеть, что именно он получит после оплаты.
   Порядок и подписи — как в открытом ряду.
*/
const lockedActions = [
    { label: 'На весь экран', icon: Maximize, primary: false },
    { label: 'Ссылка', icon: Link2, primary: false },
    { label: 'Открыть', icon: ExternalLink, primary: false },
    { label: 'Речь докладчика', icon: Mic, primary: false },
    { label: 'Скачать PDF', icon: Download, primary: true },
];

const unlocking = ref(false);

/* ---------- Показ на весь экран ---------- */

const viewer = ref<InstanceType<typeof DeckViewer> | null>(null);

function present() {
    viewer.value?.present();
}

function onPresent() {
    goal('presentation_present');
}

/* Купленные генерации есть — закрытые действия открывают полную
   версию сразу, а не отправляют к тарифам */
const page = usePage();
const hasCredits = computed(() => Number(page.props.auth?.credits ?? 0) > 0);

function unlock() {
    unlocking.value = true;
    router.post(
        `/presentations/${current.value.id}/unlock`,
        {},
        { onFinish: () => (unlocking.value = false) },
    );
}

/* ---------- Удаление ---------- */

const deleting = ref(false);

/* Диалог удаления один на две раскладки: на широком экране его
   открывает корзина в ряду кнопок, на узком — пункт меню */
const removing = ref(false);

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

    <!-- Ширину ограничивают сами экраны: тексту узкая колонка нужна,
         просмотру слайдов — нет -->
    <div class="w-full px-4 py-8">
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

        <!-- Ждём: готовим вопросы или генерируем -->
        <div
            v-if="current.isPending"
            class="mx-auto flex w-full max-w-3xl flex-col items-center gap-5 py-24 text-center"
        >
            <template v-if="stalled">
                <p class="text-lg font-medium">Что-то затянулось</p>
                <p
                    class="text-muted-foreground max-w-md text-sm leading-relaxed"
                >
                    Обычно всё занимает меньше пяти минут. Похоже, задача
                    потерялась — можно запустить её заново, генерация при этом
                    не спишется.
                </p>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        @click="
                            router.post(`/presentations/${current.id}/retry`)
                        "
                    >
                        <RotateCcw class="size-4" />
                        Запустить заново
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link href="/presentations">К списку</Link>
                    </Button>
                </div>
            </template>

            <template v-else>
                <!-- Пока есть шаги, крутится тот, что идёт сейчас:
                     второй большой индикатор над списком лишний -->
                <Spinner v-if="!current.steps?.length" class="size-7" />
                <div class="space-y-1">
                    <p class="text-lg font-medium">
                        {{ current.statusLabel }}…
                    </p>
                    <p
                        v-if="current.status === 'draft'"
                        class="text-muted-foreground text-sm"
                    >
                        Читаем тему и подбираем вопросы
                    </p>
                </div>

                <!-- Шаги вместо подписей по кругу: картинки рисуются
                     ещё полминуты после структуры, и без списка это
                     время выглядит как зависание -->
                <ol
                    v-if="current.status !== 'draft' && current.steps?.length"
                    class="w-fit space-y-2.5 text-left text-sm"
                    aria-live="polite"
                >
                    <li
                        v-for="step in current.steps"
                        :key="step.label"
                        class="flex items-center gap-2.5 transition-colors"
                        :class="{
                            'text-muted-foreground': step.state === 'done',
                            'font-medium': step.state === 'current',
                            'text-muted-foreground/50': step.state === 'todo',
                        }"
                    >
                        <span
                            class="flex size-4 flex-none items-center justify-center"
                        >
                            <Check
                                v-if="step.state === 'done'"
                                class="size-4 text-emerald-600"
                            />
                            <Spinner
                                v-else-if="step.state === 'current'"
                                class="size-4"
                            />
                            <span
                                v-else
                                class="size-1.5 rounded-full bg-current"
                            />
                        </span>
                        {{ step.label }}
                    </li>
                </ol>

                <p class="text-muted-foreground max-w-md text-sm">
                    {{ current.topic }}
                </p>

                <!-- Два коротких предложения вместо плашки: человеку
                     здесь нужно разрешение уйти и причина ждать,
                     а не абзац, который он не станет читать. -->
                <p
                    v-if="current.status !== 'draft'"
                    class="text-muted-foreground max-w-sm text-sm leading-relaxed"
                >
                    Обычно это меньше пяти минут. Страницу можно закрыть —
                    пришлём письмо, когда будет готово, и презентация появится в
                    <Link
                        href="/presentations"
                        class="underline underline-offset-2"
                        >списке</Link
                    >.
                </p>
                <p v-if="offline" class="text-muted-foreground text-xs">
                    Связь пропала — ждём восстановления
                </p>
            </template>
        </div>

        <!-- Уточняющие вопросы -->
        <div
            v-else-if="current.questions?.length"
            class="mx-auto w-full max-w-3xl space-y-8"
        >
            <div class="border-rule border-b pb-6">
                <h1 class="text-3xl font-extrabold">Уточним детали</h1>
                <p class="text-muted-foreground mt-1.5 leading-relaxed">
                    Пара ответов — и структура получится точнее.
                </p>
            </div>

            <div class="space-y-7">
                <div
                    v-for="(q, i) in current.questions"
                    :key="q.key ?? i"
                    class="space-y-3"
                >
                    <p class="font-medium">{{ q.question }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in q.options"
                            :key="option"
                            type="button"
                            class="border-border cursor-pointer rounded-lg border px-4 py-2 text-left text-sm transition-colors"
                            :class="
                                answers[q.key ?? String(i)] === option
                                    ? 'border-action bg-action text-white'
                                    : 'hover:border-foreground/40'
                            "
                            @click="answers[q.key ?? String(i)] = option"
                        >
                            {{ option }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4 border-t pt-6">
                <div class="space-y-2">
                    <p class="font-medium">Тема</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="t in themes"
                            :key="t.key"
                            type="button"
                            class="border-border cursor-pointer rounded-lg border px-4 py-2.5 text-left text-sm transition-colors"
                            :class="
                                theme === t.key
                                    ? 'border-foreground'
                                    : 'hover:border-foreground/40'
                            "
                            @click="theme = t.key"
                        >
                            {{ t.name }}
                            <span class="text-muted-foreground block text-xs">
                                {{ t.note }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="font-medium">Цветовая гамма</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="p in palettes"
                            :key="p.key"
                            type="button"
                            :title="p.note"
                            class="border-border flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
                            :class="
                                palette === p.key
                                    ? 'border-foreground'
                                    : 'hover:border-foreground/40'
                            "
                            @click="palette = p.key"
                        >
                            <span class="flex gap-1">
                                <span
                                    class="size-4 rounded-full"
                                    :style="{ background: p.cover }"
                                />
                                <span
                                    class="size-4 rounded-full"
                                    :style="{ background: p.accent }"
                                />
                            </span>
                            {{ p.name }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4 border-t pt-6">
                <p v-if="formError" class="text-destructive flex-1 text-sm">
                    {{ formError }}
                </p>
                <Button
                    size="lg"
                    :disabled="!allAnswered || sending"
                    @click="submitAnswers"
                >
                    {{ sending ? 'Отправляем…' : 'Собрать презентацию' }}
                </Button>
            </div>
        </div>

        <!-- Готово -->
        <!-- Поля по бокам только на широких экранах: на телефоне
             они съели бы и без того узкую колонку -->
        <div v-else-if="current.isReady" class="space-y-6 lg:px-16">
            <!-- Кнопки строкой выше заголовка: их подписи меняются
                 («Скачать PDF» → «Обновляем файл…»), и в одной строке
                 с заголовком они отбирали бы у него ширину -->
            <div class="space-y-3">
                <div
                    v-if="current.locked"
                    class="hidden flex-wrap justify-end gap-2 sm:flex"
                >
                    <Button
                        v-if="current.editUrl"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="current.editUrl">
                            <Pencil class="size-4" />
                            Редактировать
                        </Link>
                    </Button>

                    <!-- Закрытые действия: видны, но ведут к тарифам -->
                    <TooltipProvider :delay-duration="150">
                        <Tooltip
                            v-for="action in lockedActions"
                            :key="action.label"
                        >
                            <TooltipTrigger as-child>
                                <!-- Есть купленные генерации — открываем
                                     полную версию сразу, без тарифов -->
                                <Button
                                    v-if="hasCredits"
                                    :variant="
                                        action.primary ? 'default' : 'outline'
                                    "
                                    size="sm"
                                    class="relative opacity-50 transition-opacity hover:opacity-80"
                                    :disabled="unlocking"
                                    @click="unlock"
                                >
                                    <component
                                        :is="action.icon"
                                        class="size-4"
                                    />
                                    {{ action.label }}
                                    <span
                                        class="bg-foreground text-background pointer-events-none absolute -top-1.5 -right-1.5 flex size-4 items-center justify-center rounded-full"
                                    >
                                        <Lock class="size-2.5" />
                                    </span>
                                </Button>
                                <Button
                                    v-else
                                    :variant="
                                        action.primary ? 'default' : 'outline'
                                    "
                                    size="sm"
                                    class="relative opacity-50 transition-opacity hover:opacity-80"
                                    as-child
                                >
                                    <Link :href="`/billing?for=${current.id}`">
                                        <component
                                            :is="action.icon"
                                            class="size-4"
                                        />
                                        {{ action.label }}
                                        <span
                                            class="bg-foreground text-background pointer-events-none absolute -top-1.5 -right-1.5 flex size-4 items-center justify-center rounded-full"
                                        >
                                            <Lock class="size-2.5" />
                                        </span>
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {{
                                    hasCredits
                                        ? 'Открыть полную версию за 1 генерацию'
                                        : `Открыть за ${props.entryPrice} ₽`
                                }}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Удалить презентацию"
                        @click="removing = true"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>

                <div v-else class="hidden flex-wrap justify-end gap-2 sm:flex">
                    <Button
                        v-if="current.editUrl"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="current.editUrl">
                            <Pencil class="size-4" />
                            Редактировать
                        </Link>
                    </Button>
                    <!-- Для выступления: слайды на весь экран прямо здесь,
                         без скачивания и чужих смотрелок PDF -->
                    <Button variant="outline" size="sm" @click="present">
                        <Maximize class="size-4" />
                        На весь экран
                    </Button>
                    <!-- Ссылка ведёт на ту же страницу, что и «Открыть»,
                         а там лежит ещё старый файл: дать её сейчас значит
                         отправить человека смотреть прошлое оформление -->
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="reprinting"
                        :title="reprinting ? 'Файл ещё печатается' : undefined"
                        @click="copyShare"
                    >
                        <Link2 class="size-4" />
                        {{ copied ? 'Скопировано' : 'Ссылка' }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        as-child
                        :class="reprinting && 'pointer-events-none opacity-50'"
                        :title="reprinting ? 'Файл ещё печатается' : undefined"
                    >
                        <a
                            :href="current.shareUrl!"
                            target="_blank"
                            rel="noopener"
                        >
                            <ExternalLink class="size-4" />
                            Открыть
                        </a>
                    </Button>
                    <!-- Речь печатается на лету, поэтому ссылка ведёт
                         прямо на маршрут: пара секунд ожидания браузера.
                         Открываем во вкладке — читать, а не складывать -->
                    <Button
                        v-if="current.speechUrl"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <a
                            :href="current.speechUrl"
                            target="_blank"
                            rel="noopener"
                        >
                            <Mic class="size-4" />
                            Речь докладчика
                        </a>
                    </Button>
                    <Button
                        size="sm"
                        as-child
                        :class="reprinting && 'pointer-events-none opacity-50'"
                        :title="reprinting ? 'Файл ещё печатается' : undefined"
                    >
                        <a :href="current.downloadUrl!">
                            <Download class="size-4" />
                            {{ reprinting ? 'Обновляем файл…' : 'Скачать PDF' }}
                        </a>
                    </Button>

                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Удалить презентацию"
                        @click="removing = true"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>

                <!--
                    На узком экране те же действия — списком в меню.
                    В ряд они не помещаются: пять подписей переносятся
                    в три строки и занимают пол-экрана выше заголовка.
                -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            class="w-full justify-between sm:hidden"
                        >
                            Действия
                            <Ellipsis class="size-5" />
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        align="end"
                        class="w-[calc(100vw-2rem)] max-w-xs sm:hidden"
                    >
                        <template v-if="current.locked">
                            <DropdownMenuItem v-if="current.editUrl" as-child>
                                <Link :href="current.editUrl">
                                    <Pencil class="size-4" />
                                    Редактировать
                                </Link>
                            </DropdownMenuItem>

                            <template v-if="hasCredits">
                                <DropdownMenuItem
                                    v-for="action in lockedActions"
                                    :key="action.label"
                                    class="text-muted-foreground"
                                    :disabled="unlocking"
                                    @select="unlock"
                                >
                                    <component
                                        :is="action.icon"
                                        class="size-4"
                                    />
                                    {{ action.label }}
                                    <Lock class="ml-auto size-3.5" />
                                </DropdownMenuItem>
                            </template>
                            <template v-else>
                                <DropdownMenuItem
                                    v-for="action in lockedActions"
                                    :key="action.label"
                                    class="text-muted-foreground"
                                    as-child
                                >
                                    <Link :href="`/billing?for=${current.id}`">
                                        <component
                                            :is="action.icon"
                                            class="size-4"
                                        />
                                        {{ action.label }}
                                        <Lock class="ml-auto size-3.5" />
                                    </Link>
                                </DropdownMenuItem>
                            </template>
                        </template>

                        <template v-else>
                            <DropdownMenuItem v-if="current.editUrl" as-child>
                                <Link :href="current.editUrl">
                                    <Pencil class="size-4" />
                                    Редактировать
                                </Link>
                            </DropdownMenuItem>

                            <DropdownMenuItem @select="present">
                                <Maximize class="size-4" />
                                На весь экран
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                :disabled="reprinting"
                                @select="copyShare"
                            >
                                <Link2 class="size-4" />
                                {{
                                    copied
                                        ? 'Скопировано'
                                        : 'Скопировать ссылку'
                                }}
                            </DropdownMenuItem>

                            <DropdownMenuItem :disabled="reprinting" as-child>
                                <a
                                    :href="current.shareUrl!"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <ExternalLink class="size-4" />
                                    Открыть
                                </a>
                            </DropdownMenuItem>

                            <DropdownMenuItem v-if="current.speechUrl" as-child>
                                <a
                                    :href="current.speechUrl"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <Mic class="size-4" />
                                    Речь докладчика
                                </a>
                            </DropdownMenuItem>

                            <DropdownMenuItem :disabled="reprinting" as-child>
                                <a :href="current.downloadUrl!">
                                    <Download class="size-4" />
                                    {{
                                        reprinting
                                            ? 'Обновляем файл…'
                                            : 'Скачать PDF'
                                    }}
                                </a>
                            </DropdownMenuItem>
                        </template>

                        <DropdownMenuSeparator />

                        <DropdownMenuItem
                            variant="destructive"
                            @select="removing = true"
                        >
                            <Trash2 class="size-4" />
                            Удалить
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Dialog v-model:open="removing">
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

                <div class="space-y-1">
                    <p
                        class="text-muted-foreground flex items-center gap-1.5 text-sm"
                    >
                        <CheckCircle2 class="size-4" />
                        Готово · {{ slides(current.slideCount) }}
                    </p>
                    <h1 class="text-2xl font-extrabold">{{ current.title }}</h1>
                    <!-- Рядом с «Готово»: именно здесь человек решает,
                         нести ли это дальше как есть -->
                    <p class="text-muted-foreground max-w-prose pt-1 text-sm">
                        Собрано нейросетью — проверьте факты, числа и имена
                        перед выступлением.
                    </p>
                </div>
            </div>

            <!-- Пробная версия: что закрыто и как открыть -->
            <div
                v-if="current.locked"
                class="border-rule bg-secondary flex flex-col gap-3 rounded-lg border px-4 py-3 sm:flex-row sm:items-center"
            >
                <Lock
                    class="text-muted-foreground hidden size-4 flex-none sm:block"
                />
                <p class="text-muted-foreground flex-1 text-sm leading-relaxed">
                    Это пробная версия: слайды с водяным знаком, а показ на весь
                    экран, скачивание, ссылка и речь докладчика закрыты. Полная
                    версия стоит одну генерацию.
                </p>
                <Button
                    size="sm"
                    class="flex-none"
                    :disabled="unlocking"
                    @click="unlock"
                >
                    Открыть полную версию
                </Button>
            </div>

            <div
                v-if="copyFailed"
                class="border-rule bg-secondary flex items-center gap-3 rounded-lg border px-4 py-3"
            >
                <p class="text-muted-foreground flex-none text-sm">Ссылка:</p>
                <input
                    :value="current.shareUrl"
                    readonly
                    class="w-full flex-1 bg-transparent font-mono text-base outline-none sm:text-sm"
                    @focus="($event.target as HTMLInputElement).select()"
                />
            </div>

            <!-- Просмотр и оформление рядом: слева слайды с лентой
                 миниатюр, справа выбор темы и гаммы -->
            <div class="flex flex-col gap-5 lg:flex-row">
                <DeckViewer
                    ref="viewer"
                    :src="current.previewUrl!"
                    :theme="current.theme"
                    :palette="current.palette"
                    :deck-style="styleOf(current.theme)"
                    :locked="current.locked"
                    :presentable="!current.locked"
                    @present="onPresent"
                    class="h-[300px] w-full min-w-0 sm:h-[440px] lg:h-[560px] lg:flex-1"
                />

                <!-- Оформление меняется бесплатно: структура уже готова,
                     перепечатывается только файл -->
                <aside class="flex w-full flex-none flex-col gap-6 lg:w-56">
                    <div class="space-y-2">
                        <p
                            class="text-muted-foreground text-xs tracking-wide uppercase"
                        >
                            Тема
                        </p>
                        <div class="flex flex-col gap-1.5">
                            <button
                                v-for="t in themes"
                                :key="t.key"
                                type="button"
                                class="border-border cursor-pointer rounded-lg border px-3 py-2 text-left text-sm transition-colors"
                                :class="
                                    current.theme === t.key
                                        ? 'border-foreground'
                                        : 'hover:border-foreground/40'
                                "
                                @click="switchLook({ theme: t.key })"
                            >
                                {{ t.name }}
                                <span
                                    class="text-muted-foreground block text-xs"
                                >
                                    {{ t.note }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p
                            class="text-muted-foreground text-xs tracking-wide uppercase"
                        >
                            Цветовая гамма
                        </p>
                        <div class="grid grid-cols-2 gap-1.5">
                            <button
                                v-for="p in palettes"
                                :key="p.key"
                                type="button"
                                :title="p.note"
                                class="border-border flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-2 text-sm transition-colors"
                                :class="
                                    current.palette === p.key
                                        ? 'border-foreground'
                                        : 'hover:border-foreground/40'
                                "
                                @click="switchLook({ palette: p.key })"
                            >
                                <span class="flex gap-0.5">
                                    <span
                                        class="size-3 rounded-full"
                                        :style="{ background: p.cover }"
                                    />
                                    <span
                                        class="size-3 rounded-full"
                                        :style="{ background: p.accent }"
                                    />
                                </span>
                                <span class="truncate">{{ p.name }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Источники нужны не всем: в школьном докладе или на
                         работе их иногда просят убрать. Скрываем, а не
                         удаляем — вернуть можно той же галочкой -->
                    <div v-if="current.sourcesSlide !== null" class="space-y-2">
                        <p
                            class="text-muted-foreground text-xs tracking-wide uppercase"
                        >
                            Слайды
                        </p>
                        <div class="flex items-start gap-2.5">
                            <Checkbox
                                id="sources-slide"
                                class="mt-0.5"
                                :model-value="current.sourcesSlide"
                                :disabled="reprinting"
                                @update:model-value="
                                    toggleSources($event === true)
                                "
                            />
                            <label
                                for="sources-slide"
                                class="cursor-pointer text-sm leading-snug"
                            >
                                Слайд с источниками
                                <span
                                    class="text-muted-foreground block text-xs"
                                >
                                    Без галочки его не будет ни в просмотре, ни
                                    в PDF
                                </span>
                            </label>
                        </div>
                    </div>
                </aside>
            </div>

            <PresentationReview
                v-if="current.reviewUrl"
                :url="current.reviewUrl"
                :rating="current.rating"
                :review="current.review"
            />
        </div>

        <!-- Ошибка или неожиданное состояние -->
        <div
            v-else
            class="mx-auto w-full max-w-3xl space-y-5 py-16 text-center"
        >
            <div class="space-y-1">
                <p class="text-lg font-medium">Не получилось</p>
                <p class="text-muted-foreground mx-auto max-w-md text-sm">
                    {{
                        current.error ??
                        'Что-то пошло не так во время генерации.'
                    }}
                </p>
                <p class="text-muted-foreground/70 text-xs">
                    Состояние: {{ current.statusLabel }}
                </p>
            </div>
            <!-- На телефоне три кнопки в ряд не влезают: «Попробовать
                 снова» — главное действие, отдельной строкой во всю
                 ширину, остальные две под ним -->
            <div
                class="mx-auto flex w-full max-w-xs flex-col gap-2 sm:max-w-none sm:flex-row sm:justify-center"
            >
                <Button
                    v-if="current.status === 'failed'"
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="router.post(`/presentations/${current.id}/retry`)"
                >
                    <RotateCcw class="size-4" />
                    Попробовать снова
                </Button>
                <div class="flex justify-center gap-2">
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
    </div>
</template>
