<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    Plus,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

type Bullet = { title: string; text: string };
type Stat = { value: string; label: string };
type Quote = { text: string; author: string } | null;

type Slide = {
    layout: string;
    heading: string;
    subheading: string | null;
    bullets: Bullet[];
    stats: Stat[];
    quote: Quote;
    notes: string | null;
};

const props = defineProps<{
    presentation: {
        id: number;
        title: string;
        subtitle: string | null;
        slides: Slide[];
        previewUrl: string;
        showUrl: string;
    };
    layouts: { key: string; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Презентации', href: '/presentations' }],
    },
});

/*
   Работаем с копией: пока не сохранили, оригинал не трогаем.
   structuredClone тут не подходит — свойства Inertia обёрнуты
   в реактивные Proxy, а он такие объекты клонировать не умеет.
*/
const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

const title = ref(props.presentation.title);
const subtitle = ref(props.presentation.subtitle ?? '');
const slides = ref<Slide[]>(clone(props.presentation.slides));

const active = ref(0);
const saving = ref(false);
const errors = ref<Record<string, string>>({});

/* ---------- Несохранённые правки ---------- */

/*
   Слепок последнего сохранённого состояния. Сравнение строк грубовато,
   но данные тут простые, а любая правка меняет строку — этого хватает,
   чтобы не выпустить человека с потерянной работой.
*/
const snapshot = ref('');

function currentState(): string {
    return JSON.stringify({
        title: title.value,
        subtitle: subtitle.value,
        slides: slides.value,
    });
}

const isDirty = computed(() => snapshot.value !== currentState());

const leaving = ref(false);

onMounted(() => (snapshot.value = currentState()));

// Закрытие вкладки браузер перехватывает сам, но только если
// на странице есть незавершённая работа
function warnBeforeUnload(event: BeforeUnloadEvent) {
    if (!isDirty.value) return;

    event.preventDefault();
    event.returnValue = '';
}

window.addEventListener('beforeunload', warnBeforeUnload);
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeUnload));

function done() {
    if (isDirty.value) {
        leaving.value = true;

        return;
    }

    router.visit(props.presentation.showUrl);
}

function saveAndLeave() {
    leaving.value = false;
    save(() => router.visit(props.presentation.showUrl));
}

function leaveWithoutSaving() {
    leaving.value = false;
    snapshot.value = currentState();
    router.visit(props.presentation.showUrl);
}

/* ---------- Живое превью ---------- */

/*
   Слайды рисует сервер тем же Blade, что идёт в печать, — так
   не появляется второй реализации вёрстки, которая рано или поздно
   разойдётся с первой. Черновик отправляется в теле запроса и
   до базы не доходит.
*/
const previewHtml = ref('');
const refreshing = ref(false);

let debounce: number | undefined;

function csrfToken(): string {
    const raw = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return raw ? decodeURIComponent(raw.split('=')[1]) : '';
}

async function refreshPreview() {
    refreshing.value = true;

    try {
        const response = await fetch(props.presentation.previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                title: title.value,
                subtitle: subtitle.value || null,
                slides: slides.value,
            }),
        });

        if (response.ok) {
            previewHtml.value = await response.text();
        }
    } catch {
        // Превью не критично: не получилось — оставляем прежнее
    } finally {
        refreshing.value = false;
    }
}

function schedulePreview() {
    window.clearTimeout(debounce);
    debounce = window.setTimeout(refreshPreview, 500);
}

// Полсекунды после последнего нажатия — чтобы не дёргать сервер
// на каждую букву, но и не заставлять ждать
watch([title, subtitle, slides], schedulePreview, { deep: true });

void refreshPreview();

onBeforeUnmount(() => window.clearTimeout(debounce));

const layoutName = (key: string) =>
    props.layouts.find((l) => l.key === key)?.name ?? key;

/* Какие поля показывать — зависит от типа вёрстки */
const usesBullets = (layout: string) =>
    ['bullets', 'comparison', 'process', 'matrix', 'closing'].includes(layout);

const usesStats = (layout: string) =>
    ['stats', 'timeline', 'bignumber'].includes(layout);

const usesQuote = (layout: string) => layout === 'quote';

/* ---------- Операции над слайдами ---------- */

function addSlide() {
    slides.value.splice(active.value + 1, 0, {
        layout: 'bullets',
        heading: 'Новый слайд',
        subheading: null,
        bullets: [{ title: '', text: '' }],
        stats: [],
        quote: null,
        notes: null,
    });
    active.value += 1;
}

function removeSlide(index: number) {
    if (slides.value.length === 1) return;

    slides.value.splice(index, 1);
    active.value = Math.max(0, Math.min(active.value, slides.value.length - 1));
}

function move(index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= slides.value.length) return;

    const [slide] = slides.value.splice(index, 1);
    slides.value.splice(target, 0, slide);
    active.value = target;
}

function addBullet(slide: Slide) {
    if (slide.bullets.length >= 6) return;
    slide.bullets.push({ title: '', text: '' });
}

function addStat(slide: Slide) {
    if (slide.stats.length >= 4) return;
    slide.stats.push({ value: '', label: '' });
}

/* ---------- Сохранение ---------- */

function save(then?: () => void) {
    saving.value = true;
    errors.value = {};

    router.put(
        `/presentations/${props.presentation.id}/outline`,
        {
            title: title.value,
            subtitle: subtitle.value || null,
            slides: slides.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                snapshot.value = currentState();
                refreshPreview();
                then?.();
            },
            onError: (e) => (errors.value = e),
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`Правка — ${title}`" />

    <div class="flex h-[calc(100vh-4rem)] flex-col">
        <!-- Шапка редактора -->
        <div class="border-rule flex flex-none items-center gap-4 border-b px-4 py-3">
            <input
                v-model="title"
                class="min-w-0 flex-1 bg-transparent text-lg font-bold outline-none"
                placeholder="Название презентации"
            />

            <span
                v-if="isDirty"
                class="text-muted-foreground flex-none text-sm"
            >
                Есть несохранённые правки
            </span>

            <Button variant="ghost" size="sm" @click="done">Готово</Button>

            <Button size="sm" :disabled="saving || !isDirty" @click="save()">
                {{ saving ? 'Сохраняем…' : 'Сохранить' }}
            </Button>
        </div>

        <p
            v-if="Object.keys(errors).length"
            class="text-destructive border-rule flex-none border-b px-4 py-2 text-sm"
        >
            {{ Object.values(errors)[0] }}
        </p>

        <div class="flex min-h-0 flex-1">
            <!-- Список слайдов -->
            <aside class="border-rule w-56 flex-none overflow-y-auto border-r p-2">
                <button
                    v-for="(slide, i) in slides"
                    :key="i"
                    type="button"
                    class="mb-1 w-full rounded-lg px-3 py-2.5 text-left transition-colors"
                    :class="active === i ? 'bg-secondary' : 'hover:bg-secondary/60'"
                    @click="active = i"
                >
                    <p class="truncate text-sm font-medium">
                        {{ slide.heading || 'Без заголовка' }}
                    </p>
                    <p class="text-muted-foreground mt-0.5 text-xs">
                        {{ i + 1 }} · {{ layoutName(slide.layout) }}
                    </p>
                </button>

                <Button variant="ghost" size="sm" class="mt-1 w-full" @click="addSlide">
                    <Plus class="size-4" />
                    Слайд
                </Button>
            </aside>

            <!-- Правка выбранного слайда -->
            <section
                v-if="slides[active]"
                class="min-w-0 flex-1 overflow-y-auto p-6"
            >
                <div class="mx-auto max-w-xl space-y-6">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <select
                                v-model="slides[active].layout"
                                class="border-input bg-background w-full appearance-none rounded-lg border px-3 py-2 pr-9 text-sm"
                            >
                                <option v-for="l in layouts" :key="l.key" :value="l.key">
                                    {{ l.name }}
                                </option>
                            </select>
                            <ChevronDown
                                class="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2"
                            />
                        </div>

                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Выше"
                            :disabled="active === 0"
                            @click="move(active, -1)"
                        >
                            <ArrowUp class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Ниже"
                            :disabled="active === slides.length - 1"
                            @click="move(active, 1)"
                        >
                            <ArrowDown class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="hover:text-destructive"
                            aria-label="Удалить слайд"
                            :disabled="slides.length === 1"
                            @click="removeSlide(active)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <Label>Заголовок</Label>
                        <input
                            v-model="slides[active].heading"
                            class="border-input bg-background w-full rounded-lg border px-3 py-2"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label>Подзаголовок</Label>
                        <input
                            v-model="slides[active].subheading"
                            placeholder="Необязательно"
                            class="border-input bg-background w-full rounded-lg border px-3 py-2"
                        />
                    </div>

                    <!-- Пункты -->
                    <div v-if="usesBullets(slides[active].layout)" class="space-y-3">
                        <Label>Пункты</Label>

                        <div
                            v-for="(bullet, bi) in slides[active].bullets"
                            :key="bi"
                            class="border-rule space-y-2 rounded-lg border p-3"
                        >
                            <div class="flex gap-2">
                                <input
                                    v-model="bullet.title"
                                    placeholder="Коротко"
                                    class="border-input bg-background flex-1 rounded-md border px-3 py-1.5 text-sm font-medium"
                                />
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Убрать пункт"
                                    @click="slides[active].bullets.splice(bi, 1)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                            <textarea
                                v-model="bullet.text"
                                rows="2"
                                placeholder="Одно предложение"
                                class="border-input bg-background w-full resize-none rounded-md border px-3 py-1.5 text-sm"
                            />
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="slides[active].bullets.length >= 6"
                            @click="addBullet(slides[active])"
                        >
                            <Plus class="size-4" />
                            Пункт
                        </Button>
                    </div>

                    <!-- Числа -->
                    <div v-if="usesStats(slides[active].layout)" class="space-y-3">
                        <Label>Числа</Label>

                        <div
                            v-for="(stat, si) in slides[active].stats"
                            :key="si"
                            class="flex gap-2"
                        >
                            <input
                                v-model="stat.value"
                                placeholder="1682"
                                class="border-input bg-background w-28 rounded-md border px-3 py-1.5 text-sm font-medium tabular-nums"
                            />
                            <input
                                v-model="stat.label"
                                placeholder="Что это значит"
                                class="border-input bg-background flex-1 rounded-md border px-3 py-1.5 text-sm"
                            />
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Убрать"
                                @click="slides[active].stats.splice(si, 1)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="slides[active].stats.length >= 4"
                            @click="addStat(slides[active])"
                        >
                            <Plus class="size-4" />
                            Число
                        </Button>
                    </div>

                    <!-- Цитата -->
                    <div v-if="usesQuote(slides[active].layout)" class="space-y-3">
                        <Label>Цитата</Label>
                        <textarea
                            :value="slides[active].quote?.text ?? ''"
                            rows="3"
                            class="border-input bg-background w-full resize-none rounded-lg border px-3 py-2"
                            @input="
                                slides[active].quote = {
                                    text: ($event.target as HTMLTextAreaElement).value,
                                    author: slides[active].quote?.author ?? '',
                                }
                            "
                        />
                        <input
                            :value="slides[active].quote?.author ?? ''"
                            placeholder="Автор"
                            class="border-input bg-background w-full rounded-lg border px-3 py-2"
                            @input="
                                slides[active].quote = {
                                    text: slides[active].quote?.text ?? '',
                                    author: ($event.target as HTMLInputElement).value,
                                }
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label>Заметка для выступающего</Label>
                        <textarea
                            v-model="slides[active].notes"
                            rows="2"
                            placeholder="Не попадёт на слайд"
                            class="border-input bg-background w-full resize-none rounded-lg border px-3 py-2 text-sm"
                        />
                    </div>
                </div>
            </section>

            <!-- Превью -->
            <aside class="border-rule hidden w-[38%] flex-none border-l xl:block">
                <div
                    class="border-rule text-muted-foreground flex items-center justify-between border-b px-4 py-2 text-xs"
                >
                    <span>{{ refreshing ? 'Обновляем…' : 'Превью' }}</span>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Обновить превью"
                        :disabled="refreshing"
                        @click="refreshPreview"
                    >
                        <RefreshCw class="size-3.5" :class="refreshing && 'animate-spin'" />
                    </Button>
                </div>
                <iframe
                    :srcdoc="previewHtml"
                    class="h-[calc(100%-2.5rem)] w-full"
                    title="Превью презентации"
                />
            </aside>
        </div>

        <Dialog :open="leaving" @update:open="leaving = false">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Сохранить правки?</DialogTitle>
                    <DialogDescription>
                        Вы что-то поменяли, но не сохранили. Если выйти сейчас,
                        изменения пропадут.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" @click="leaveWithoutSaving">
                        Выйти без сохранения
                    </Button>
                    <Button variant="outline" @click="leaving = false">
                        Остаться
                    </Button>
                    <Button :disabled="saving" @click="saveAndLeave">
                        Сохранить и выйти
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
