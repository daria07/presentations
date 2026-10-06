<script setup lang="ts">
import { ChevronLeft, ChevronRight, LayoutGrid } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Адрес, по которому отдаётся вёрстка слайдов */
        src?: string;
        /** Готовая вёрстка вместо адреса: в редакторе черновик
            приходит ответом на POST, ссылки на него нет */
        html?: string;
        /* Оформление. Не передали — оставляем то, что проставил сервер:
           в редакторе тему не переключают */
        theme?: string;
        palette?: string;
        /* Имя style занято самим Vue под инлайновые стили */
        deckStyle?: string;
        /** Лента миниатюр слева */
        rail?: boolean;
        /** Стрелки и счётчик под слайдом */
        controls?: boolean;
        /** Своя рамка со скруглением. В редакторе просмотр вставлен
            в колонку, у которой рамка уже есть */
        framed?: boolean;
        /** Номер показанного слайда — для связи с внешним списком */
        active?: number;
        /** Разрешать перестроение под узкий экран */
        feed?: boolean;
    }>(),
    { rail: true, controls: true, framed: true, feed: true },
);

const emit = defineEmits<{ 'update:active': [number] }>();

/*
   На узком экране деление «лента миниатюр слева + сцена» не работает:
   ленте нужно 120px, и слайду остаётся полоска, на которой ничего не
   прочесть. Там слайд занимает всю ширину, а лента переезжает в
   панель, которая выдвигается снизу по кнопке «Слайды».

   Порог 640px — та же граница, что у остальной вёрстки (sm в Tailwind).
   Слушаем медиазапрос, а не ширину окна: он срабатывает ровно на
   переходе через границу, а не на каждом пикселе перетаскивания.
*/
const COMPACT_BELOW = '(max-width: 639px)';

const narrow = ref(false);
const compact = computed(() => props.feed && props.rail && narrow.value);

/** Панель с миниатюрами открыта (только в узком режиме) */
const sheet = ref(false);

let media: MediaQueryList | null = null;

function onMediaChange(event: MediaQueryListEvent) {
    narrow.value = event.matches;
}

/*
   Слайды приходят готовой вёрсткой — той же, что уходит в печать.
   Класть её прямо в страницу нельзя: у неё свои h1, h2 и .slide,
   они бы смешались со стилями сайта. Shadow DOM изолирует стили
   в обе стороны, поэтому iframe здесь не нужен.

   Корней два: сцена и лента миниатюр. Второй обязателен — узлы,
   созданные в обычной разметке, стилей слайдов не видят и остаются
   пустыми рамками.
*/
const stageHost = ref<HTMLDivElement | null>(null);
const railHost = ref<HTMLDivElement | null>(null);
const stage = ref<HTMLDivElement | null>(null);

const total = ref(0);
const index = ref(0);
const loading = ref(true);
const failed = ref(false);

let stageShadow: ShadowRoot | null = null;
let railShadow: ShadowRoot | null = null;
let deck: HTMLElement | null = null;
let slides: HTMLElement[] = [];
let thumbs: HTMLElement[] = [];

/* Последняя разметка: смена режима — это пересборка из неё, без сети */
let markup: string | null = null;

/** Размер слайда в пикселях при 96 dpi */
let slideWidth = 1280;
let slideHeight = 720;

const THUMB_WIDTH = 104;

/** Свой корень на каждый узел: после пересборки узлы новые */
function rootFor(host: HTMLElement, current: ShadowRoot | null): ShadowRoot {
    if (current && current.host === host) return current;

    return host.shadowRoot ?? host.attachShadow({ mode: 'open' });
}

async function load() {
    if (!stageHost.value || !railHost.value) return;

    loading.value = true;
    failed.value = false;

    if (props.html !== undefined) {
        if (!props.html) {
            loading.value = true;

            return;
        }

        markup = props.html;
    } else {
        try {
            const response = await fetch(props.src ?? '', {
                headers: { Accept: 'text/html' },
            });

            if (!response.ok) throw new Error(String(response.status));

            markup = await response.text();
        } catch {
            failed.value = true;
            loading.value = false;

            return;
        }
    }

    render();
}

function render() {
    if (!stageHost.value || !railHost.value || markup === null) return;

    const parsed = new DOMParser().parseFromString(markup, 'text/html');

    stageShadow = rootFor(stageHost.value, stageShadow);
    railShadow = rootFor(railHost.value, railShadow);
    stageShadow.innerHTML = '';
    railShadow.innerHTML = '';

    /*
       @font-face внутри Shadow DOM браузеры игнорируют, поэтому шрифты
       переносим в документ. Они вшиты в base64, сеть не задействована.
    */
    const styles = Array.from(parsed.querySelectorAll('style'));
    const fonts = styles.shift();

    if (fonts && !document.getElementById('deck-fonts')) {
        const holder = document.createElement('style');
        holder.id = 'deck-fonts';
        holder.textContent = fonts.textContent ?? '';
        document.head.appendChild(holder);
    }

    const layout = `
        .deck--screen { display: contents; }
        .slide { display: none; flex: none; transform-origin: center center; }
        .slide.is-active { display: flex; }

        /* В колонке миниатюры идут одна под другой, в выдвижной
           панели — сеткой: там ширины хватает на две-три в ряд */
        .rail {
            display: flex; gap: 8px; padding: 8px;
            flex-direction: ${compact.value ? 'row' : 'column'};
            flex-wrap: ${compact.value ? 'wrap' : 'nowrap'};
            justify-content: ${compact.value ? 'center' : 'flex-start'};
        }
        /* Миниатюра — это кнопка, а у кнопок браузер сам ставит
           выравнивание по центру и свой шрифт. Внутри лежит клон
           слайда, и всё это наследуется: гасим. */
        .thumb {
            position: relative; flex: none; padding: 0; overflow: hidden;
            width: ${THUMB_WIDTH}px; border-radius: 4px; cursor: pointer;
            border: 1px solid #E2DCD1; background: #fff;
            text-align: left; font: inherit; color: inherit;
        }
        /* Значения здесь именно значениями, а не токенами: лента живёт
           в теневом корне, куда переменные страницы не достают.
           Индиго — акцент интерфейса, тёплый серый — линейка. */
        .thumb[aria-current="true"] { border-color: #2B4ACB; box-shadow: 0 0 0 1px #2B4ACB; }
        .thumb .slide { display: flex; transform-origin: top left; pointer-events: none; }
    `;

    for (const root of [stageShadow, railShadow]) {
        styles.forEach((style) => root.appendChild(style.cloneNode(true)));

        const own = document.createElement('style');
        own.textContent = layout;
        root.appendChild(own);
    }

    // Обычно слайды лежат в контейнере .deck — он несёт атрибуты
    // оформления. Если его нет, берём тело документа: так просмотр
    // переживает и старую разметку из кэша.
    const source = parsed.querySelector('.deck') ?? parsed.body;

    if (!source || !source.querySelector('.slide')) {
        failed.value = true;
        loading.value = false;

        return;
    }

    deck = source.cloneNode(true) as HTMLElement;
    deck.classList.add('deck', 'deck--screen');
    applyLook(deck);
    stageShadow.appendChild(deck);

    slides = Array.from(deck.querySelectorAll<HTMLElement>('.slide'));
    total.value = slides.length;

    if (props.rail) {
        buildThumbs();
    } else {
        thumbs = [];
    }

    show(Math.min(props.active ?? index.value, slides.length - 1));
    fit();

    loading.value = false;
}

function applyLook(el: HTMLElement) {
    if (props.theme) el.dataset.theme = props.theme;
    if (props.palette) el.dataset.palette = props.palette;
    if (props.deckStyle) el.dataset.style = props.deckStyle;
}

/** Миниатюра — клон настоящего слайда, уменьшенный трансформацией */
function buildThumbs() {
    if (!railShadow) return;

    const rail = document.createElement('div');
    rail.className = 'rail deck';
    applyLook(rail);

    const scale = THUMB_WIDTH / slideWidth;
    thumbs = [];

    slides.forEach((slide, i) => {
        const box = document.createElement('button');
        box.type = 'button';
        box.className = 'thumb';
        box.style.height = `${Math.round(slideHeight * scale)}px`;
        box.setAttribute('aria-label', `Слайд ${i + 1}`);

        const clone = slide.cloneNode(true) as HTMLElement;
        clone.classList.remove('is-active');
        clone.style.transform = `scale(${scale})`;
        box.appendChild(clone);

        box.addEventListener('click', () => {
            show(i);
            sheet.value = false;
        });
        rail.appendChild(box);
        thumbs.push(box);
    });

    railShadow.appendChild(rail);
}

function show(next: number) {
    if (!slides.length) return;

    index.value = Math.max(0, Math.min(slides.length - 1, next));

    slides.forEach((slide, i) =>
        slide.classList.toggle('is-active', i === index.value),
    );
    thumbs.forEach((thumb, i) =>
        thumb.setAttribute(
            'aria-current',
            i === index.value ? 'true' : 'false',
        ),
    );

    thumbs[index.value]?.scrollIntoView({ block: 'nearest' });

    if (index.value !== props.active) emit('update:active', index.value);
}

/** Слайд не резиновый: подгоняем масштабом по меньшей стороне */
function fit() {
    if (!stage.value || !slides.length) return;

    // Снизу оставляем полосу под кнопки листания, иначе слайд
    // наезжает на них на невысоком окне
    const box = stage.value.getBoundingClientRect();
    const scale = Math.min(
        (box.width - 32) / slideWidth,
        (box.height - (props.controls ? 72 : 24)) / slideHeight,
    );

    slides.forEach((slide) => {
        slide.style.transform = `scale(${Math.max(scale, 0.05)})`;
    });
}

function keys(event: KeyboardEvent) {
    // В редакторе рядом живут поля ввода: стрелки там двигают курсор,
    // а не слайды
    const target = event.target as HTMLElement | null;

    if (
        target?.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName ?? '')
    ) {
        return;
    }

    const step: Record<string, number> = {
        ArrowRight: 1,
        ArrowDown: 1,
        PageDown: 1,
        ArrowLeft: -1,
        ArrowUp: -1,
        PageUp: -1,
    };

    if (event.key === 'Home') return show(0);
    if (event.key === 'End') return show(slides.length - 1);
    if (step[event.key] === undefined) return;

    event.preventDefault();
    show(index.value + step[event.key]);
}

/* Смена оформления — это атрибуты на контейнерах, без перезагрузки */
watch(
    () => [props.theme, props.palette, props.deckStyle],
    () => {
        if (deck) applyLook(deck);

        const rail = railShadow?.querySelector<HTMLElement>('.rail');

        if (rail) applyLook(rail);
    },
);

watch(() => [props.src, props.html], load);

/* Внешний список выбрал другой слайд — показываем его */
watch(
    () => props.active,
    (next) => {
        if (next !== undefined && next !== index.value) show(next);
    },
);

/* Смена режима — это другая раскладка миниатюр, то есть пересборка.
   Разметка уже в памяти, в сеть не ходим */
watch(compact, () => {
    sheet.value = false;
    render();
});

onMounted(() => {
    media = window.matchMedia(COMPACT_BELOW);
    narrow.value = media.matches;
    media.addEventListener('change', onMediaChange);

    load();
    window.addEventListener('resize', fit);
    document.addEventListener('keydown', keys);
});

onBeforeUnmount(() => {
    media?.removeEventListener('change', onMediaChange);
    window.removeEventListener('resize', fit);
    document.removeEventListener('keydown', keys);
});
</script>

<template>
    <div
        class="bg-muted/40 flex overflow-hidden"
        :class="framed && 'border-border rounded-xl border'"
    >
        <!--
            Лента миниатюр: свой изолированный корень со стилями слайдов.
            Узел один на оба режима — колонку слева и выдвижную панель.
            Если бы их было два, у каждого был бы свой теневой корень,
            и миниатюры пришлось бы строить дважды.
        -->
        <div
            v-show="rail && (!compact || sheet)"
            :class="
                compact
                    ? 'bg-card fixed inset-x-0 bottom-0 z-50 max-h-[70vh] overflow-y-auto rounded-t-2xl border-t shadow-2xl'
                    : 'bg-muted/70 border-rule w-[120px] flex-none overflow-y-auto border-r'
            "
            :style="
                compact
                    ? { paddingBottom: 'env(safe-area-inset-bottom, 0px)' }
                    : undefined
            "
        >
            <div
                v-if="compact"
                class="bg-card border-rule sticky top-0 flex items-center justify-between border-b px-4 py-3"
            >
                <p class="text-sm font-semibold">Слайды</p>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground cursor-pointer text-sm"
                    @click="sheet = false"
                >
                    Закрыть
                </button>
            </div>

            <div ref="railHost" />
        </div>

        <!-- Затемнение под панелью: отдельным узлом, чтобы нажатие мимо
             миниатюр закрывало её, а не проваливалось на слайд -->
        <div
            v-if="compact && sheet"
            class="fixed inset-0 z-40 bg-black/40"
            @click="sheet = false"
        />

        <!-- Сцена -->
        <div ref="stage" class="relative min-w-0 flex-1">
            <div
                ref="stageHost"
                class="absolute inset-0 flex items-center justify-center"
                :class="controls ? 'pb-12' : 'p-3'"
            />

            <p
                v-if="failed"
                class="text-muted-foreground absolute inset-0 flex items-center justify-center text-sm"
            >
                Не удалось загрузить слайды
            </p>

            <!-- Управление в пустом поле под слайдом -->
            <div
                v-else-if="controls && !loading"
                class="absolute bottom-4 left-1/2 flex -translate-x-1/2 items-center gap-3 whitespace-nowrap"
            >
                <button
                    type="button"
                    class="border-border bg-background/80 text-foreground flex size-8 cursor-pointer items-center justify-center rounded-md border transition-colors disabled:cursor-default disabled:opacity-40"
                    :disabled="index === 0"
                    aria-label="Предыдущий слайд"
                    @click="show(index - 1)"
                >
                    <ChevronLeft class="size-4" />
                </button>

                <!-- В узком режиме счётчик заодно открывает панель
                     с миниатюрами: отдельной кнопке рядом места нет -->
                <!-- whitespace-nowrap обязателен: на 343px ряду не
                     хватает ширины, и дробь ломается на две строки -->
                <button
                    v-if="compact"
                    type="button"
                    class="border-border bg-background/80 text-muted-foreground hover:text-foreground flex h-8 flex-none cursor-pointer items-center gap-1.5 rounded-md border px-2.5 text-sm leading-none whitespace-nowrap tabular-nums"
                    aria-label="Все слайды"
                    @click="sheet = true"
                >
                    <LayoutGrid class="size-3.5 flex-none" />
                    {{ index + 1 }} / {{ total }}
                </button>

                <p
                    v-else
                    class="text-muted-foreground w-14 text-center text-sm tabular-nums"
                >
                    {{ index + 1 }} / {{ total }}
                </p>

                <button
                    type="button"
                    class="border-border bg-background/80 text-foreground flex size-8 cursor-pointer items-center justify-center rounded-md border transition-colors disabled:cursor-default disabled:opacity-40"
                    :disabled="index === total - 1"
                    aria-label="Следующий слайд"
                    @click="show(index + 1)"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>
        </div>
    </div>
</template>
