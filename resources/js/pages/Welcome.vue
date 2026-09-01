<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import SlideCard from '@/components/landing/SlideCard.vue';
import { login, register } from '@/routes';

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
                    Тезис
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

        <!-- ── Что получается на выходе ── -->
        <section class="border-rule border-t">
            <div class="mx-auto max-w-5xl px-6 py-16">
                <div class="mb-10 flex items-baseline justify-between gap-6">
                    <h2 class="text-2xl font-bold md:text-3xl">
                        Так выглядит результат
                    </h2>
                    <p class="text-muted-foreground hidden text-sm md:block">
                        Шесть-двенадцать слайдов, три темы оформления
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <SlideCard
                        variant="cover"
                        heading="Пётр I: реформы и их цена"
                        sub="Как модернизация изменила страну"
                    />

                    <SlideCard heading="Ключевые даты правления">
                        <div class="flex items-start gap-3">
                            <div v-for="y in ['1682', '1703', '1721']" :key="y" class="flex-1">
                                <div class="bg-brand mb-2 h-1 w-full rounded-full opacity-30" />
                                <p class="font-display text-base font-extrabold tabular-nums">
                                    {{ y }}
                                </p>
                            </div>
                        </div>
                    </SlideCard>

                    <SlideCard heading="Коллегии вместо приказов">
                        <div class="space-y-2.5">
                            <div v-for="n in 3" :key="n" class="flex gap-2.5">
                                <span
                                    class="bg-brand-soft text-brand-ink flex size-4 flex-none items-center justify-center rounded-full text-[9px] font-bold"
                                >
                                    {{ n }}
                                </span>
                                <div class="flex-1 space-y-1">
                                    <div class="h-1.5 w-2/5 rounded-full bg-black/25" />
                                    <div class="h-1.5 w-full rounded-full bg-black/10" />
                                </div>
                            </div>
                        </div>
                    </SlideCard>
                </div>
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
                        <h3 class="mb-1.5 font-bold">Слайды не похожи друг на друга</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            Даты становятся таймлайном, сравнение — двумя колонками,
                            цифры — крупными акцентами. А не десятью одинаковыми
                            списками подряд.
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
                <p class="font-display text-foreground font-bold">Тезис</p>
                <p>Презентации из одной строки · {{ new Date().getFullYear() }}</p>
            </div>
        </footer>
    </div>
</template>
