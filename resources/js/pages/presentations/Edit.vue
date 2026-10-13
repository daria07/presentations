<script setup lang="ts">
import DeckViewer from '@/components/DeckViewer.vue';
import Field from '@/components/Field.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ImageOff,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
    Undo2,
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectNative } from '@/components/ui/select-native';
import { Textarea } from '@/components/ui/textarea';

type Bullet = { title: string; text: string };
type Stat = { value: string; label: string };
type Quote = { text: string; author: string } | null;
type Source = { title: string; url: string };

/* Картинку рисует нейросеть при генерации; здесь её можно только убрать */
type SlideImage = {
    path?: string | null;
    side?: string;
    scene?: string;
    format?: string;
    failed?: boolean;
};

type Slide = {
    layout: string;
    heading: string;
    subheading: string | null;
    bullets: Bullet[];
    stats: Stat[];
    quote: Quote;
    notes: string | null;
    image?: SlideImage | null;
    sources?: Source[];
    /** Скрыт со страницы презентации: в просмотр и PDF не попадает */
    hidden?: boolean;
};

const props = defineProps<{
    presentation: {
        id: number;
        title: string;
        subtitle: string | null;
        slides: Slide[];
        previewUrl: string;
        showUrl: string;
        /** Пробная версия: водяной знак и запрет копирования */
        locked: boolean;
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
const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

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
onBeforeUnmount(() =>
    window.removeEventListener('beforeunload', warnBeforeUnload),
);

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
// Почему черновик не нарисовался. Без подписи превью просто
// замирает на старом, и непонятно, что мешает
const previewError = ref<string | null>(null);

/* «slides.3.heading» → «Слайд 4: …», чтобы было видно, где искать */
function describeError(key: string, message: string): string {
    const index = /^slides\.(\d+)\./.exec(key)?.[1];

    return index === undefined
        ? message
        : `Слайд ${Number(index) + 1}: ${message}`;
}

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
            previewError.value = null;
        } else if (response.status === 422) {
            const body = await response.json().catch(() => null);
            const [key, messages] = Object.entries(
                (body?.errors ?? {}) as Record<string, string[]>,
            )[0] ?? ['', []];

            previewError.value = describeError(
                key,
                messages[0] ?? 'Не получилось обновить превью',
            );
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

const usesSources = (layout: string) => layout === 'sources';

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

function addSource(slide: Slide) {
    slide.sources ??= [];
    if (slide.sources.length >= 12) return;
    slide.sources.push({ title: '', url: '' });
}

function addStat(slide: Slide) {
    if (slide.stats.length >= 4) return;
    slide.stats.push({ value: '', label: '' });
}

/* ---------- Иллюстрации ---------- */

/*
   Убранную картинку держим до сохранения, чтобы её можно было
   вернуть: файл на сервере удаляется только при сохранении.
   Ключ — сам слайд, а не номер: слайды двигаются и удаляются.
*/
const removedImages = ref(new WeakMap<Slide, SlideImage>());

const hasImage = (slide: Slide) =>
    Boolean(slide.image?.path) && !slide.image?.failed;

function removeImage(slide: Slide) {
    if (!slide.image) return;

    removedImages.value.set(slide, slide.image);
    slide.image = null;
}

function restoreImage(slide: Slide) {
    const image = removedImages.value.get(slide);
    if (!image) return;

    slide.image = image;
    removedImages.value.delete(slide);
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
                // Файлы убранных картинок сервер уже удалил
                removedImages.value = new WeakMap();
                refreshPreview();
                then?.();
            },
            onError: (e) => (errors.value = e),
            onFinish: () => (saving.value = false),
        },
    );
}

/*
   Ниже 1024px три колонки рядом не помещаются: списку нужно 224px,
   правке — поля во всю ширину, просмотру — хотя бы треть экрана.

   Поэтому там главное — просмотр: он показывает результат, ради
   которого всё и затевалось. Список слайдов живёт в самом просмотре
   (кнопка с сеткой открывает миниатюры), а правка выезжает поверх
   экрана по кнопке и занимает его целиком.
*/
const NARROW = '(max-width: 1023px)';

const narrow = ref(false);
const editing = ref(false);

let media: MediaQueryList | null = null;

function onNarrowChange(event: MediaQueryListEvent) {
    narrow.value = event.matches;

    // Вернулись на широкий экран — накладка больше не нужна,
    // правка и так видна колонкой
    if (!event.matches) editing.value = false;
}

onMounted(() => {
    media = window.matchMedia(NARROW);
    narrow.value = media.matches;
    media.addEventListener('change', onNarrowChange);
});

onBeforeUnmount(() => media?.removeEventListener('change', onNarrowChange));
</script>

<template>
    <Head :title="`Правка — ${title}`" />

    <div class="flex h-[calc(100vh-4rem)] flex-col">
        <!-- Шапка редактора -->
        <div
            class="border-rule flex flex-none flex-wrap items-center gap-x-4 gap-y-2 border-b px-4 py-3"
        >
            <!-- Прозрачное поле, а не Input: это заголовок страницы,
                 который можно править, а не элемент формы.
                 На телефоне занимает всю строку: в одном ряду с тремя
                 кнопками от него оставалось полтора слова -->
            <!--
                textarea, а не input: заголовок бывает длинным, и в
                однострочном поле он уезжает за край, а прочитать его
                можно только прокруткой. field-sizing-content растит
                поле по содержимому, перенос по словам.

                Enter перехватываем: это название, а не текст, и
                переносы строк в нём не нужны — но строка должна
                переноситься сама, по ширине.
            -->
            <textarea
                v-model="title"
                rows="1"
                class="focus-visible:ring-ring/50 order-1 field-sizing-content w-full min-w-0 resize-none rounded-md bg-transparent px-1 text-lg leading-snug font-bold outline-none focus-visible:ring-[3px] sm:w-auto sm:flex-1"
                placeholder="Название презентации"
                @keydown.enter.prevent
            />

            <!-- Полную фразу читать некогда и негде: на узком экране
                 о несохранённом говорит точка у кнопки «Сохранить» -->
            <span
                v-if="isDirty"
                class="text-muted-foreground order-2 hidden flex-none text-sm sm:inline"
            >
                Есть несохранённые правки
            </span>

            <div class="order-3 ml-auto flex flex-none items-center gap-2">
                <Button variant="ghost" size="sm" @click="done">
                    Выйти
                    <span class="hidden sm:inline">из редактора</span>
                </Button>

                <Button
                    size="sm"
                    :disabled="saving || !isDirty"
                    @click="save()"
                >
                    <span
                        v-if="isDirty && !saving"
                        class="bg-background size-1.5 rounded-full sm:hidden"
                        aria-hidden="true"
                    />
                    {{ saving ? 'Сохраняем…' : 'Сохранить' }}
                </Button>
            </div>
        </div>

        <!-- Действия над слайдом на узком экране: сам список живёт
             в просмотре, кнопкой с сеткой. Выше 1024px всё это есть
             в колонках, и панель не нужна -->
        <div class="border-rule flex flex-none gap-2 border-b p-2 lg:hidden">
            <Button
                variant="outline"
                size="sm"
                class="flex-1"
                @click="editing = true"
            >
                <Pencil class="size-4" />
                Править слайд
            </Button>

            <Button variant="ghost" size="sm" @click="addSlide">
                <Plus class="size-4" />
                Слайд
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
            <aside
                class="border-rule hidden w-56 flex-none overflow-y-auto p-2 lg:block lg:border-r"
            >
                <button
                    v-for="(slide, i) in slides"
                    :key="i"
                    type="button"
                    class="mb-1 w-full cursor-pointer rounded-lg px-3 py-2.5 text-left transition-colors"
                    :class="
                        active === i ? 'bg-secondary' : 'hover:bg-secondary/60'
                    "
                    @click="active = i"
                >
                    <p class="truncate text-sm font-medium">
                        {{ slide.heading || 'Без заголовка' }}
                    </p>
                    <p class="text-muted-foreground mt-0.5 text-xs">
                        {{ i + 1 }} · {{ layoutName(slide.layout) }}
                        <template v-if="slide.hidden"> · скрыт</template>
                    </p>
                </button>

                <Button
                    variant="ghost"
                    size="sm"
                    class="mt-1 w-full"
                    @click="addSlide"
                >
                    <Plus class="size-4" />
                    Слайд
                </Button>
            </aside>

            <!-- Правка выбранного слайда -->
            <!--
                Один и тот же узел: на широком экране — колонка,
                на узком — накладка во весь экран. Так поля правки
                существуют в единственном экземпляре: две копии
                разошлись бы при первой же правке одной из них.
            -->
            <section
                v-if="slides[active]"
                class="bg-background hidden min-w-0 flex-1 overflow-y-auto p-4 sm:p-6 lg:block"
                :class="
                    editing &&
                    'max-lg:fixed max-lg:inset-0 max-lg:z-50 max-lg:block'
                "
            >
                <div class="mx-auto max-w-xl space-y-6">
                    <!-- Закрыть накладку. На широком экране закрывать
                         нечего: правка там и так колонка -->
                    <div
                        v-if="editing"
                        class="bg-background border-rule sticky -top-4 z-10 -mx-4 mb-2 flex items-center justify-between border-b px-4 py-3 sm:-top-6 lg:hidden"
                    >
                        <p class="text-sm font-semibold">
                            Слайд {{ active + 1 }} из {{ slides.length }}
                        </p>
                        <Button size="sm" @click="editing = false">
                            Готово
                        </Button>
                    </div>

                    <Field label="Тип слайда">
                        <div class="flex items-center gap-2">
                            <SelectNative
                                v-model="slides[active].layout"
                                class="flex-1"
                            >
                                <option
                                    v-for="l in layouts"
                                    :key="l.key"
                                    :value="l.key"
                                >
                                    {{ l.name }}
                                </option>
                            </SelectNative>

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
                    </Field>

                    <Field label="Заголовок">
                        <Input v-model="slides[active].heading" />
                    </Field>

                    <Field label="Подзаголовок">
                        <Input
                            v-model="slides[active].subheading"
                            placeholder="Необязательно"
                        />
                    </Field>

                    <!-- Иллюстрация. Убрать можно, нарисовать новую — нет:
                         это делается только при генерации -->
                    <div
                        v-if="
                            hasImage(slides[active]) ||
                            removedImages.has(slides[active])
                        "
                        class="border-rule flex items-center justify-between gap-3 rounded-lg border p-3"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium">Иллюстрация</p>
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                <template v-if="!hasImage(slides[active])">
                                    Убрана — пропадёт после сохранения
                                </template>
                                <template
                                    v-else-if="
                                        slides[active].image?.side === 'cover'
                                    "
                                >
                                    Фото на фоне обложки
                                </template>
                                <template v-else>
                                    Занимает половину слайда
                                </template>
                            </p>
                        </div>

                        <Button
                            v-if="hasImage(slides[active])"
                            variant="outline"
                            size="sm"
                            class="flex-none"
                            @click="removeImage(slides[active])"
                        >
                            <ImageOff class="size-4" />
                            Убрать картинку
                        </Button>
                        <Button
                            v-else
                            variant="ghost"
                            size="sm"
                            class="flex-none"
                            @click="restoreImage(slides[active])"
                        >
                            <Undo2 class="size-4" />
                            Вернуть
                        </Button>
                    </div>

                    <!-- Пункты -->
                    <div
                        v-if="usesBullets(slides[active].layout)"
                        class="space-y-3"
                    >
                        <Label>Пункты</Label>

                        <div
                            v-for="(bullet, bi) in slides[active].bullets"
                            :key="bi"
                            class="border-rule space-y-2 rounded-lg border p-3"
                        >
                            <div class="flex gap-2">
                                <Input
                                    v-model="bullet.title"
                                    placeholder="Коротко"
                                    class="h-10 flex-1 font-medium"
                                />
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Убрать пункт"
                                    @click="
                                        slides[active].bullets.splice(bi, 1)
                                    "
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                            <Textarea
                                v-model="bullet.text"
                                rows="2"
                                placeholder="Одно предложение до 120 знаков"
                                class="min-h-16 resize-none"
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
                    <div
                        v-if="usesStats(slides[active].layout)"
                        class="space-y-3"
                    >
                        <Label>Числа</Label>

                        <div
                            v-for="(stat, si) in slides[active].stats"
                            :key="si"
                            class="flex gap-2"
                        >
                            <Input
                                v-model="stat.value"
                                placeholder="1682"
                                class="h-10 w-28 font-medium tabular-nums"
                            />
                            <Input
                                v-model="stat.label"
                                placeholder="Что это значит"
                                class="h-10 flex-1"
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
                    <div
                        v-if="usesQuote(slides[active].layout)"
                        class="space-y-3"
                    >
                        <Label>Цитата</Label>
                        <Textarea
                            :model-value="slides[active].quote?.text ?? ''"
                            rows="3"
                            class="resize-none"
                            @input="
                                slides[active].quote = {
                                    text: ($event.target as HTMLTextAreaElement)
                                        .value,
                                    author: slides[active].quote?.author ?? '',
                                }
                            "
                        />
                        <Input
                            :model-value="slides[active].quote?.author ?? ''"
                            placeholder="Автор"
                            @input="
                                slides[active].quote = {
                                    text: slides[active].quote?.text ?? '',
                                    author: ($event.target as HTMLInputElement)
                                        .value,
                                }
                            "
                        />
                    </div>

                    <!-- Источники: на слайде название ссылкой и сайт -->
                    <div
                        v-if="usesSources(slides[active].layout)"
                        class="space-y-3"
                    >
                        <Label>Источники</Label>

                        <div
                            v-for="(source, si) in slides[active].sources ?? []"
                            :key="si"
                            class="border-rule space-y-2 rounded-lg border p-3"
                        >
                            <div class="flex gap-2">
                                <Input
                                    v-model="source.title"
                                    placeholder="Название страницы"
                                    class="h-10 flex-1 font-medium"
                                />
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Убрать источник"
                                    @click="
                                        slides[active].sources?.splice(si, 1)
                                    "
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                            <Input
                                v-model="source.url"
                                type="url"
                                placeholder="https://"
                                class="h-10"
                            />
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="
                                (slides[active].sources?.length ?? 0) >= 12
                            "
                            @click="addSource(slides[active])"
                        >
                            <Plus class="size-4" />
                            Источник
                        </Button>
                    </div>

                    <!-- Речь докладчика собирается из этого поля, поэтому
                         его пишут, а не подписывают: даём высоту под
                         несколько предложений и счётчик под ограничение -->
                    <Field
                        label="Заметка для выступающего"
                        :counter="`${(slides[active].notes ?? '').length} / 1500`"
                    >
                        <Textarea
                            v-model="slides[active].notes"
                            rows="8"
                            placeholder="Не попадёт на слайд — это текст для выступления"
                            class="min-h-32"
                        />
                    </Field>
                </div>
            </section>

            <!-- Превью -->
            <!-- Ниже 1024px — главная область: ради результата сюда
                 и приходят. На 1024–1279 места нет, скрыт. С 1280px —
                 боковая колонка рядом с правкой -->
            <aside
                class="border-rule flex min-w-0 flex-1 flex-col lg:hidden xl:flex xl:w-[38%] xl:flex-none xl:border-l"
            >
                <div
                    class="border-rule text-muted-foreground flex flex-none items-center justify-between border-b px-4 py-2 text-xs"
                >
                    <span
                        v-if="previewError && !refreshing"
                        class="text-destructive min-w-0 truncate"
                        :title="previewError"
                    >
                        {{ previewError }}
                    </span>
                    <span v-else>{{
                        refreshing ? 'Обновляем…' : 'Превью'
                    }}</span>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Обновить превью"
                        :disabled="refreshing"
                        @click="refreshPreview"
                    >
                        <RefreshCw
                            class="size-3.5"
                            :class="refreshing && 'animate-spin'"
                        />
                    </Button>
                </div>
                <!-- Тот же просмотрщик, что и на странице презентации:
                     слайд вписывается в колонку, а не торчит за край,
                     и следит за выбранным в списке слайдом -->
                <!-- Лента миниатюр включается только на узком экране:
                     там она и есть список слайдов, кнопка с сеткой
                     открывает её поверх просмотра. На широком список
                     стоит отдельной колонкой слева -->
                <DeckViewer
                    v-model:active="active"
                    :html="previewHtml"
                    :rail="narrow"
                    :framed="false"
                    :locked="presentation.locked"
                    class="min-h-0 w-full flex-1"
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
