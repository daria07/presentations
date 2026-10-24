<script setup lang="ts">
/* Вход и регистрация через VK ID и Google — кнопки на обеих страницах.
   Обычные ссылки, а не переходы Inertia: дальше страницы VK и Google.
   Каждая кнопка видна, только если её приложение настроено на сервере */
defineProps<{
    vk?: boolean;
    google?: boolean;
    /* Подпись под кнопками: на входе аккаунт тоже может создаться */
    consent?: boolean;
}>();
</script>

<template>
    <div class="grid gap-3">
        <a
            v-if="vk"
            href="/auth/vk"
            class="flex h-10 w-full items-center justify-center rounded-md bg-[#0077FF] px-4 text-sm font-medium text-white transition-colors hover:bg-[#0066DB] focus-visible:ring-2 focus-visible:ring-[#0077FF]/40 focus-visible:outline-none"
        >
            Войти через VK ID
        </a>
        <!-- Половина наших пользователей — с Gmail -->
        <a
            v-if="google"
            href="/auth/google"
            class="border-border hover:bg-secondary flex h-10 w-full items-center justify-center rounded-md border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-[#4285F4]/40 focus-visible:outline-none"
        >
            Войти через Google
        </a>
        <!-- Почта Mail.ru входит через тот же VK ID: так у нас каждый
             четвёртый — mail.ru, bk.ru, list.ru, inbox.ru -->
        <a
            v-if="vk"
            href="/auth/vk?provider=mail_ru"
            class="border-border hover:bg-secondary flex h-10 w-full items-center justify-center rounded-md border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-[#005FF9]/40 focus-visible:outline-none"
        >
            Войти через почту Mail.ru
        </a>
        <p
            v-if="consent"
            class="text-muted-foreground text-center text-xs leading-relaxed"
        >
            Если аккаунта ещё нет, он будет создан: вы принимаете
            <a
                href="/offer"
                target="_blank"
                class="underline underline-offset-2"
                >условия оферты</a
            >
            и даёте
            <a
                href="/consent"
                target="_blank"
                class="underline underline-offset-2"
                >согласие на обработку персональных данных</a
            >.
        </p>
        <div
            class="text-muted-foreground flex items-center gap-3 text-xs"
            aria-hidden="true"
        >
            <span class="bg-border h-px flex-1" />
            или по почте
            <span class="bg-border h-px flex-1" />
        </div>
    </div>
</template>
