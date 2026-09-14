<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    /** Адрес, по которому отдаётся вёрстка слайдов */
    src: string;
    theme: string;
    palette: string;
    /* Имя style занято самим Vue под инлайновые стили */
    deckStyle: string;
}>();

/*
   Слайды приходят готовой вёрсткой — той же, что уходит в печать.
   Класть её прямо в страницу нельзя: у неё свои h1, h2 и .slide,
   они бы смешались со стилями сайта. Shadow DOM изолирует стили
   в обе стороны, поэтому iframe здесь не нужен.
*/
const host = ref<HTMLDivElement | null>(null);
const stage = ref<HTMLDivElement | null>(null);
const rail = ref<HTMLDivElement | null>(null);

const total = ref(0);
const index = ref(0);
const loading = ref(true);
const failed = ref(false);

let shadow: ShadowRoot | null = null;
let deck: HTMLElement | null = null;
let slides: HTMLElement[] = [];
let thumbs: HTMLElement[] = [];

/** Размер слайда в пикселях при 96 dpi — из конфига через вёрстку */
let slideWidth = 1280;
let slideHeight = 720;

const THUMB_WIDTH = 104;

async function load() {
    if (!host.value) return;

    loading.value = true;
    failed.value = false;

    let markup: string;

    try {
        const response = await fetch(props.src, { headers: { Accept: 'text/html' } });

        if (!response.ok) throw new Error(String(response.status));

        markup = await response.text();
    } catch {
        failed.value = true;
        loading.value = false;

        return;
    }

    const parsed = new DOMParser().parseFromString(markup, 'text/html');

    shadow ??= host.value.attachShadow({ mode: 'open' });
    shadow.innerHTML = '';

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

    styles.forEach((style) => shadow!.appendChild(style.cloneNode(true)));

    const source = parsed.querySelector('.deck');

    if (!source) {
        failed.value = true;
        loading.value = false;

        return;
    }

    // Слайды показываем по одному, поэтому раскладываем их сами
    deck = source.cloneNode(true) as HTMLElement;
    deck.classList.add('deck--screen');

    const layout = document.createElement('style');
    layout.textContent = `
        .deck--screen { display: contents; }
        .slide { display: none; flex: none; transform-origin: center center; }
        .slide.is-active { display: flex; }
        .thumb-deck .slide { display: flex; transform-origin: top left; }
    `;
    shadow.appendChild(layout);
    shadow.appendChild(deck);

    slides = Array.from(deck.querySelectorAll<HTMLElement>('.slide'));
    total.value = slides.length;

    if (slides[0]) {
        slideWidth = slides[0].offsetWidth || slideWidth;
        slideHeight = slides[0].offsetHeight || slideHeight;
    }

    buildThumbs();
    show(Math.min(index.value, slides.length - 1));
    fit();

    loading.value = false;
}

/** Миниатюра — клон настоящего слайда, уменьшенный трансформацией */
function buildThumbs() {
    if (!rail.value || !shadow) return;

    rail.value.innerHTML = '';
    thumbs = [];

    const scale = THUMB_WIDTH / slideWidth;

    slides.forEach((slide, i) => {
        const box = document.createElement('button');
        box.type = 'button';
        box.className = 'thumb';
        box.setAttribute('aria-label', `Слайд ${i + 1}`);
        box.style.cssText = `
            position: relative; flex: none; padding: 0; cursor: pointer;
            width: ${THUMB_WIDTH}px; height: ${Math.round(slideHeight * scale)}px;
            overflow: hidden; border-radius: 4px; background: #fff;
        `;

        const inner = document.createElement('div');
        inner.className = 'thumb-deck';
        inner.dataset.palette = props.palette;
        inner.dataset.theme = props.theme;
        inner.dataset.style = props.deckStyle;

        const clone = slide.cloneNode(true) as HTMLElement;
        clone.classList.remove('is-active');
        clone.style.transform = `scale(${scale})`;
        clone.style.pointerEvents = 'none';
        inner.appendChild(clone);
        box.appendChild(inner);

        box.addEventListener('click', () => show(i));
        rail.value!.appendChild(box);
        thumbs.push(box);
    });
}

function show(next: number) {
    if (!slides.length) return;

    index.value = Math.max(0, Math.min(slides.length - 1, next));

    slides.forEach((slide, i) => slide.classList.toggle('is-active', i === index.value));
    thumbs.forEach((thumb, i) => {
        thumb.style.outline = i === index.value ? '2px solid #3B82F6' : '1px solid #DCD8D3';
        thumb.style.outlineOffset = '-1px';
    });

    thumbs[index.value]?.scrollIntoView({ block: 'nearest' });
}

/** Слайд не резиновый: подгоняем масштабом по меньшей стороне */
function fit() {
    if (!stage.value || !slides.length) return;

    const box = stage.value.getBoundingClientRect();
    const scale = Math.min(
        (box.width - 24) / slideWidth,
        (box.height - 24) / slideHeight,
    );

    slides.forEach((slide) => {
        slide.style.transform = `scale(${Math.max(scale, 0.05)})`;
    });
}

function keys(event: KeyboardEvent) {
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
    ([theme, palette, style]) => {
        for (const el of [deck, ...(rail.value?.querySelectorAll<HTMLElement>('.thumb-deck') ?? [])]) {
            if (!el) continue;

            el.dataset.theme = theme;
            el.dataset.palette = palette;
            el.dataset.style = style;
        }
    },
);

watch(() => props.src, load);

onMounted(() => {
    load();
    window.addEventListener('resize', fit);
    document.addEventListener('keydown', keys);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', fit);
    document.removeEventListener('keydown', keys);
});
</script>

<template>
    <div
        class="border-border bg-muted/40 relative flex overflow-hidden rounded-xl border"
    >
        <!-- Лента миниатюр -->
        <div
            ref="rail"
            class="bg-muted/70 border-rule flex w-[120px] flex-none flex-col gap-2 overflow-y-auto border-r p-2"
        />

        <!-- Сцена -->
        <div ref="stage" class="relative flex min-w-0 flex-1 items-center justify-center p-3">
            <div ref="host" class="contents" />

            <p v-if="loading" class="text-muted-foreground text-sm">Готовим просмотр…</p>
            <p v-else-if="failed" class="text-muted-foreground text-sm">
                Не удалось загрузить слайды
            </p>

            <template v-if="!loading && !failed">
                <button
                    type="button"
                    class="bg-background/90 text-foreground absolute top-1/2 left-3 flex size-8 -translate-y-1/2 items-center justify-center rounded-full shadow-sm transition-opacity disabled:invisible"
                    :disabled="index === 0"
                    aria-label="Предыдущий слайд"
                    @click="show(index - 1)"
                >
                    <ChevronLeft class="size-4" />
                </button>
                <button
                    type="button"
                    class="bg-background/90 text-foreground absolute top-1/2 right-3 flex size-8 -translate-y-1/2 items-center justify-center rounded-full shadow-sm transition-opacity disabled:invisible"
                    :disabled="index === total - 1"
                    aria-label="Следующий слайд"
                    @click="show(index + 1)"
                >
                    <ChevronRight class="size-4" />
                </button>

                <p
                    class="text-muted-foreground absolute right-4 bottom-2 text-xs tabular-nums"
                >
                    {{ index + 1 }} / {{ total }}
                </p>
            </template>
        </div>
    </div>
</template>
