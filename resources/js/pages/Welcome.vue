<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Mic, Pencil, Wand2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import CookieNotice from '@/components/CookieNotice.vue';
import SlideCard from '@/components/landing/SlideCard.vue';
import { login, register } from '@/routes';

type Theme = {
    key: string;
    name: string;
    note: string;
    style: string;
    font: string;
    fontBody: string;
};

type Palette = {
    key: string;
    name: string;
    note: string;
    paper: string;
    ink: string;
    muted: string;
    rule: string;
    accent: string;
    accentInk: string;
    accentSoft: string;
    cover: string;
    coverInk: string;
    coverAccent: string;
};

const props = defineProps<{
    themes: Theme[];
    palettes: Palette[];
}>();

/* Гаммы и темы приходят из того же конфига, что и продукт,
   поэтому лендинг не может отстать от него по составу */
const palette = ref<Palette>(props.palettes[0]);
const theme = ref<Theme>(props.themes[0]);

/* Контраст по WCAG: на светлой обложке акцентный тон не годится
   для мелкой надписи — там же, где в шаблоне, отступаем к основному
   цвету текста. Считаем на месте, чтобы новая гамма починилась сама */
function luminance(hex: string): number {
    const channels = [1, 3, 5].map((i) => {
        const value = parseInt(hex.slice(i, i + 2), 16) / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function contrast(a: string, b: string): number {
    const x = luminance(a);
    const y = luminance(b);

    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

const vars = computed(() => ({
    '--p-paper': palette.value.paper,
    '--p-ink': palette.value.ink,
    '--p-muted': palette.value.muted,
    '--p-rule': palette.value.rule,
    '--p-accent': palette.value.accent,
    '--p-accent-ink': palette.value.accentInk,
    '--p-accent-soft': palette.value.accentSoft,
    '--p-cover': palette.value.cover,
    '--p-cover-ink': palette.value.coverInk,
    '--p-cover-accent': palette.value.coverAccent,
    '--p-cover-title':
        contrast(palette.value.cover, palette.value.coverAccent) >= 4.5
            ? palette.value.coverAccent
            : palette.value.coverInk,
    '--t-display': `"${theme.value.font}", sans-serif`,
    '--t-body': `"${theme.value.fontBody}", sans-serif`,
}));

const combos = computed(() => props.palettes.length * props.themes.length);

const steps = [
    {
        title: 'Дайте тему или текст',
        text: 'Одной строкой — «Пётр I и его реформы для восьмого класса». Или вставьте готовую статью, реферат, конспект.',
    },
    {
        title: 'Ответьте на два-три вопроса',
        text: 'Кто аудитория, какой угол важнее, насколько глубоко копать. Варианты готовы — нужен один клик.',
    },
    {
        title: 'Заберите готовый файл',
        text: 'PDF со сверстанными слайдами. Смотрите в браузере, скачивайте, отправляйте ссылкой.',
    },
];

const layouts = [
    'Титульный',
    'Пункты',
    'Числа',
    'Крупная цифра',
    'Хронология',
    'Этапы',
    'Сравнение',
    'Матрица',
    'Цитата',
    'Финальный',
];

const audiences = [
    {
        title: 'Учителям',
        text: 'Урок по новой теме — за время перемены. Материал под возраст класса, без воды и без ночной вёрстки.',
    },
    {
        title: 'Студентам',
        text: 'Защита, семинар, курсовая. Структура выстроена, факты на местах, оформление не стыдно показать.',
    },
    {
        title: 'На работу',
        text: 'Отчёт, питч, планёрка. Черновик, от которого можно отталкиваться, вместо пустого первого слайда.',
    },
];
</script>

<template>
    <Head title="Презентация из одной строки" />

    <div class="landing bg-paper text-foreground min-h-screen">
        <!-- ── Шапка ── -->
        <header class="border-rule border-b">
            <div
                class="mx-auto flex h-16 max-w-5xl items-center justify-between px-6"
            >
                <Link href="/" class="font-display text-lg font-extrabold tracking-tight">
                    Слайдуша
                </Link>

                <nav class="flex items-center gap-1 text-sm">
                    <template v-if="$page.props.auth.user">
                        <Link
                            href="/presentations"
                            class="hover:bg-secondary rounded-md px-4 py-2 font-medium transition-colors"
                        >
                            Мои презентации
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="hover:bg-secondary rounded-md px-4 py-2 transition-colors"
                        >
                            Войти
                        </Link>
                        <Link
                            :href="register()"
                            class="bg-foreground text-background rounded-md px-4 py-2 font-medium transition-opacity hover:opacity-90"
                        >
                            Начать
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- ── Первый экран ── -->
        <section class="mx-auto max-w-5xl px-6 pt-20 pb-16 md:pt-28">
            <p
                class="text-brand-ink font-display mb-6 text-xs font-bold tracking-[0.18em] uppercase"
            >
                Презентации на русском
            </p>

            <h1
                class="max-w-[15ch] text-5xl leading-[1.02] font-extrabold md:text-7xl"
            >
                Презентация из одной строки
            </h1>

            <p class="text-muted-foreground mt-7 max-w-[52ch] text-lg leading-relaxed">
                Напишите тему — получите сверстанные слайды с фактами, датами и
                структурой. Есть готовый текст? Вставьте его, и мы разложим по
                слайдам, ничего не дописывая от себя.
            </p>

            <!-- Поле ввода как обещание продукта -->
            <div class="mt-10 max-w-2xl">
                <div
                    class="border-rule flex items-center gap-3 rounded-xl border bg-white/70 py-3 pr-3 pl-5 shadow-[0_1px_2px_rgba(23,21,15,.04),0_12px_28px_-20px_rgba(23,21,15,.35)]"
                >
                    <p class="text-muted-foreground flex-1 truncate text-[15px]">
                        Пётр I и его реформы — для восьмого класса
                    </p>
                    <Link
                        :href="register()"
                        class="bg-brand flex flex-none items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium text-white transition-opacity hover:opacity-90"
                    >
                        Собрать
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <p class="text-muted-foreground mt-3 text-sm">
                    Первая презентация — бесплатно, карта не нужна.
                </p>
            </div>
        </section>

        <!-- ── Оформление: живая примерочная ── -->
        <section class="border-rule border-t">
            <div class="mx-auto max-w-5xl px-6 py-16">
                <div class="mb-8 max-w-[56ch]">
                    <h2 class="text-2xl font-bold md:text-3xl">
                        Так выглядит результат
                    </h2>
                    <p class="text-muted-foreground mt-3 leading-relaxed">
                        {{ palettes.length }} цветовых гамм и {{ themes.length }}
                        темы оформления — {{ combos }} сочетаний. Попробуйте прямо
                        здесь: внутри сервиса переключение работает так же, без
                        перезагрузки и без ожидания.
                    </p>
                </div>

                <!-- Переключатели: те же ключи, что и в продукте -->
                <div class="mb-6 space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="text-muted-foreground w-14 shrink-0 text-xs tracking-wide uppercase"
                        >
                            Гамма
                        </span>
                        <button
                            v-for="p in palettes"
                            :key="p.key"
                            type="button"
                            :title="p.note"
                            :aria-pressed="palette.key === p.key"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs transition-colors"
                            :class="
                                palette.key === p.key
                                    ? 'border-foreground'
                                    : 'border-rule hover:border-foreground/40'
                            "
                            @click="palette = p"
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
                            {{ p.name }}
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="text-muted-foreground w-14 shrink-0 text-xs tracking-wide uppercase"
                        >
                            Тема
                        </span>
                        <button
                            v-for="t in themes"
                            :key="t.key"
                            type="button"
                            :title="t.note"
                            :aria-pressed="theme.key === t.key"
                            class="cursor-pointer rounded-lg border px-2.5 py-1.5 text-xs transition-colors"
                            :class="
                                theme.key === t.key
                                    ? 'border-foreground'
                                    : 'border-rule hover:border-foreground/40'
                            "
                            @click="theme = t"
                        >
                            {{ t.name }}
                            <span class="text-muted-foreground">· {{ t.font }}</span>
                        </button>
                    </div>
                </div>

                <!-- Переменные ставим на обёртку — карточки их наследуют,
                     ровно как слайды наследуют их от <html> в шаблоне -->
                <div :style="vars" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <SlideCard
                        variant="cover"
                        eyebrow="Презентация"
                        heading="Пётр I: реформы и их цена"
                        sub="Как модернизация изменила страну"
                    />

                    <SlideCard heading="Ключевые даты правления">
                        <div class="flex items-start gap-3">
                            <div v-for="y in ['1682', '1703', '1721']" :key="y" class="flex-1">
                                <div
                                    class="mb-2 h-1 w-full rounded-full"
                                    :style="{ background: 'var(--p-accent)' }"
                                />
                                <p
                                    class="text-base font-extrabold tabular-nums"
                                    :style="{
                                        fontFamily: 'var(--t-display)',
                                        color: 'var(--p-accent-ink)',
                                    }"
                                >
                                    {{ y }}
                                </p>
                            </div>
                        </div>
                    </SlideCard>

                    <SlideCard heading="Коллегии вместо приказов">
                        <div class="space-y-2.5">
                            <div v-for="n in 3" :key="n" class="flex gap-2.5">
                                <span
                                    class="flex size-4 flex-none items-center justify-center rounded-full text-[9px] font-bold"
                                    :style="{
                                        background: 'var(--p-accent-soft)',
                                        color: 'var(--p-accent-ink)',
                                    }"
                                >
                                    {{ n }}
                                </span>
                                <div class="flex-1 space-y-1">
                                    <div
                                        class="h-1.5 w-2/5 rounded-full"
                                        :style="{ background: 'var(--p-muted)' }"
                                    />
                                    <div
                                        class="h-1.5 w-full rounded-full"
                                        :style="{ background: 'var(--p-rule)' }"
                                    />
                                </div>
                            </div>
                        </div>
                    </SlideCard>
                </div>

                <p class="text-muted-foreground mt-5 text-sm">
                    <span class="text-foreground font-medium">{{ palette.name }}</span>
                    — {{ palette.note }}.
                    <span class="text-foreground font-medium">{{ theme.name }}</span>
                    — {{ theme.note.toLowerCase() }}.
                </p>
            </div>
        </section>

        <!-- ── Десять типов слайдов ── -->
        <section class="border-rule border-t">
            <div class="mx-auto grid max-w-5xl gap-10 px-6 py-16 md:grid-cols-[1fr_1.1fr]">
                <div>
                    <h2 class="text-2xl font-bold md:text-3xl">
                        Слайды не похожи друг на друга
                    </h2>
                    <p class="text-muted-foreground mt-3 leading-relaxed">
                        Каждый слайд получает свой тип вёрстки по смыслу: даты
                        становятся хронологией, сравнение — двумя колонками, цифры
                        — крупными акцентами. Не десять одинаковых списков подряд.
                    </p>
                </div>

                <ul class="flex flex-wrap content-start gap-2">
                    <li
                        v-for="name in layouts"
                        :key="name"
                        class="border-rule rounded-full border px-3 py-1.5 text-sm"
                    >
                        {{ name }}
                    </li>
                </ul>
            </div>
        </section>

        <!-- ── Как это работает ── -->
        <section class="border-rule border-t">
            <div class="mx-auto max-w-5xl px-6 py-16">
                <h2 class="mb-12 text-2xl font-bold md:text-3xl">Как это работает</h2>

                <ol class="grid gap-10 md:grid-cols-3">
                    <li v-for="(step, i) in steps" :key="step.title">
                        <p
                            class="font-display text-brand mb-4 text-sm font-bold tabular-nums"
                        >
                            {{ String(i + 1).padStart(2, '0') }}
                        </p>
                        <h3 class="mb-2 text-lg font-bold">{{ step.title }}</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            {{ step.text }}
                        </p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ── Что есть внутри ── -->
        <section class="border-rule border-t">
            <div class="mx-auto max-w-5xl px-6 py-16">
                <h2 class="mb-10 text-2xl font-bold md:text-3xl">
                    Что ещё есть внутри
                </h2>

                <div class="grid gap-10 md:grid-cols-3">
                    <div>
                        <Wand2 class="text-brand mb-4 size-5" />
                        <h3 class="mb-2 text-lg font-bold">Просмотр без перезагрузки</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            Слайды листаются прямо на странице, миниатюры слева. Меняете
                            гамму или тему — оформление перекрашивается мгновенно, а файл
                            тем временем перепечатывается сам. Перевыбор оформления
                            бесплатный: генерация уже оплачена.
                        </p>
                    </div>
                    <div>
                        <Pencil class="text-brand mb-4 size-5" />
                        <h3 class="mb-2 text-lg font-bold">Правка структуры</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            Заголовки, пункты, порядок слайдов, тип вёрстки — всё
                            редактируется руками. Превью рядом обновляется на ходу, пока
                            вы печатаете.
                        </p>
                    </div>
                    <div>
                        <Mic class="text-brand mb-4 size-5" />
                        <h3 class="mb-2 text-lg font-bold">Речь докладчика</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            К каждому слайду пишется, что сказать вслух. Отдельный файл на
                            A4 с оценкой времени — распечатать и держать в руках. Входит в
                            генерацию, отдельно платить не надо.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Отличие от чата ── -->
        <section class="border-rule border-t">
            <div class="mx-auto grid max-w-5xl gap-10 px-6 py-16 md:grid-cols-[1fr_1.1fr]">
                <h2 class="text-2xl font-bold md:text-3xl">
                    Чем это отличается от чата с нейросетью
                </h2>

                <div class="space-y-6">
                    <div>
                        <h3 class="mb-1.5 font-bold">На выходе файл, а не текст</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            Чат выдаёт список пунктов, который вы потом полчаса
                            раскладываете по слайдам. Здесь вёрстка уже сделана.
                        </p>
                    </div>
                    <div>
                        <h3 class="mb-1.5 font-bold">Уточнения до, а не после</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            Вопросы про аудиторию и глубину задаются заранее — не
                            приходится переписывать всё, потому что получилось
                            слишком сложно для восьмиклассников.
                        </p>
                    </div>
                    <div>
                        <h3 class="mb-1.5 font-bold">Оформление можно перебрать потом</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            Текст и вёрстка разделены, поэтому смена гаммы не переписывает
                            слайды заново. В чате пришлось бы просить всё сначала — и
                            получить другой текст.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Для кого ── -->
        <section class="border-rule border-t">
            <div class="mx-auto max-w-5xl px-6 py-16">
                <h2 class="mb-12 text-2xl font-bold md:text-3xl">Кому пригодится</h2>

                <div class="grid gap-10 md:grid-cols-3">
                    <div v-for="item in audiences" :key="item.title">
                        <h3 class="mb-2 text-lg font-bold">{{ item.title }}</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            {{ item.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Призыв ── -->
        <section class="border-rule border-t">
            <div class="mx-auto max-w-5xl px-6 py-24 text-center">
                <h2 class="mx-auto max-w-[18ch] text-3xl font-extrabold md:text-5xl">
                    Первая презентация — бесплатно
                </h2>
                <p class="text-muted-foreground mx-auto mt-5 max-w-[46ch] text-lg">
                    Проверьте на своей теме. Если результат не понравится — вы ничего
                    не потеряли.
                </p>
                <Link
                    :href="register()"
                    class="bg-foreground text-background mt-9 inline-flex items-center gap-2 rounded-lg px-7 py-3.5 font-medium transition-opacity hover:opacity-90"
                >
                    Создать презентацию
                    <ArrowRight class="size-4" />
                </Link>
            </div>
        </section>

        <footer class="border-rule border-t">
            <div
                class="text-muted-foreground mx-auto flex max-w-5xl flex-col gap-3 px-6 py-8 text-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="font-display text-foreground font-bold">Слайдуша</p>
                <p class="flex flex-wrap gap-4">
                    <Link href="/offer" class="hover:underline">Оферта</Link>
                    <Link href="/privacy" class="hover:underline">Конфиденциальность</Link>
                    <span>{{ new Date().getFullYear() }}</span>
                </p>
            </div>
        </footer>

        <CookieNotice />
    </div>
</template>
