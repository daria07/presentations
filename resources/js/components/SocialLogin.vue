<script setup lang="ts">
import { computed, reactive } from 'vue';

/* Вход и регистрация через VK ID, Mail.ru и Google над формой:
   VK и Mail.ru в одну строку, Google под ними на всю ширину. Обычные ссылки, а не переходы Inertia: дальше
   страницы VK и Google. Каждая кнопка видна, только если её приложение
   настроено на сервере.

   Логотипы — официальные файлы из брендбуков VK ID и Google, лежат в
   public/images/auth (vk.svg, google.svg, mailru.svg). Пока файла нет,
   кнопка остаётся с одним названием — не ломается */
const props = defineProps<{
    vk?: boolean;
    google?: boolean;
    /* Подпись под кнопками: на входе аккаунт тоже может создаться */
    consent?: boolean;
}>();

type Provider = { key: string; href: string; name: string; logo: string };

const providers = computed<Provider[]>(() =>
    [
        props.vk && {
            key: 'vk',
            href: '/auth/vk',
            name: 'VK',
            logo: '/images/auth/vk.svg',
        },
        // Почта Mail.ru входит через тот же VK ID: так у нас каждый
        // четвёртый — mail.ru, bk.ru, list.ru, inbox.ru
        props.vk && {
            key: 'mailru',
            href: '/auth/vk?provider=mail_ru',
            name: 'Mail.ru',
            logo: '/images/auth/mailru.svg',
        },
        // Отдельной строкой на всю ширину под VK и Mail.ru
        props.google && {
            key: 'google',
            href: '/auth/google',
            name: 'Google',
            logo: '/images/auth/google.svg',
        },
    ].filter((p): p is Provider => Boolean(p)),
);

/* Логотип не загрузился — прячем картинку, название остаётся */
const missing = reactive<Record<string, boolean>>({});
</script>

<template>
    <div class="grid gap-3">
        <p class="text-muted-foreground text-center text-sm">
            Войти в один клик
        </p>

        <div class="grid grid-cols-2 gap-2">
            <a
                v-for="p in providers"
                :key="p.key"
                :href="p.href"
                :aria-label="`Войти через ${p.name}`"
                :class="p.key === 'google' && 'col-span-2'"
                class="border-border bg-card hover:bg-secondary focus-visible:ring-ring/40 flex h-11 min-w-0 items-center justify-center gap-2 rounded-md border px-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none"
            >
                <img
                    v-if="!missing[p.key]"
                    :src="p.logo"
                    alt=""
                    class="size-5 flex-none"
                    @error="missing[p.key] = true"
                />
                <span class="truncate">{{ p.name }}</span>
            </a>
        </div>

        <p
            v-if="consent"
            class="text-muted-foreground text-left text-xs leading-relaxed"
        >
            Нет аккаунта — создадим: вы принимаете
            <a
                href="/offer"
                target="_blank"
                class="underline underline-offset-2"
                >оферту</a
            >
            и даёте
            <a
                href="/consent"
                target="_blank"
                class="underline underline-offset-2"
                >согласие на обработку данных</a
            >.
        </p>

        <!-- 24px над разделителем: 12px отступа сетки + mt-3 -->
        <div
            class="text-muted-foreground mt-3 flex items-center gap-3 text-xs"
            aria-hidden="true"
        >
            <span class="bg-border h-px flex-1" />
            или по почте
            <span class="bg-border h-px flex-1" />
        </div>
    </div>
</template>
