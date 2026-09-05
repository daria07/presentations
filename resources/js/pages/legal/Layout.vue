<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    updatedAt: string;
}>();
</script>

<template>
    <Head :title="title" />

    <div class="landing bg-paper text-foreground min-h-screen">
        <header class="border-rule border-b">
            <div class="mx-auto flex h-16 max-w-3xl items-center justify-between px-6">
                <Link href="/" class="font-display text-lg font-extrabold tracking-tight">
                    Слайдуша
                </Link>
                <Link href="/" class="text-muted-foreground text-sm hover:underline">
                    На главную
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-12">
            <h1 class="font-display text-3xl font-extrabold tracking-tight">
                {{ title }}
            </h1>
            <p class="text-muted-foreground mt-2 text-sm">
                Редакция от {{ updatedAt }}
            </p>

            <!-- Типографика документа: одинаковая для оферты и политики -->
            <div class="legal mt-10">
                <slot />
            </div>
        </main>

        <footer class="border-rule border-t">
            <div class="text-muted-foreground mx-auto flex max-w-3xl flex-col gap-2 px-6 py-8 text-sm sm:flex-row sm:justify-between">
                <p class="font-display text-foreground font-bold">Слайдуша</p>
                <p class="flex gap-4">
                    <Link href="/offer" class="hover:underline">Оферта</Link>
                    <Link href="/privacy" class="hover:underline">Конфиденциальность</Link>
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.legal :deep(h2) {
    font-size: 1.125rem;
    font-weight: 700;
    margin-top: 2.5rem;
    margin-bottom: 0.75rem;
}

.legal :deep(p),
.legal :deep(li) {
    line-height: 1.7;
    margin-bottom: 0.75rem;
}

.legal :deep(ul) {
    padding-left: 1.25rem;
    list-style: disc;
}

.legal :deep(dl) {
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: 0.5rem 1.5rem;
    margin-top: 0.5rem;
}

.legal :deep(dt) {
    color: var(--muted-foreground, #6b7280);
}
</style>
