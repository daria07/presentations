<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

// Куки у нас только технические — сессия и CSRF. Отказаться от них
// нельзя (без сессии не будет входа), поэтому это уведомление,
// а не согласие: одна кнопка «Понятно», без выбора и без блокировки.
const KEY = 'cookie-notice-seen';

const visible = ref(false);

onMounted(() => {
    try {
        visible.value = localStorage.getItem(KEY) !== '1';
    } catch {
        // Приватный режим или запрет хранилища — просто не показываем,
        // навязываться в такой ситуации бессмысленно
        visible.value = false;
    }
});

function dismiss() {
    visible.value = false;

    try {
        localStorage.setItem(KEY, '1');
    } catch {
        // Не смогли запомнить — покажем в следующий раз, невелика беда
    }
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div
            v-if="visible"
            role="status"
            class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-xl sm:inset-x-auto sm:right-6 sm:bottom-6"
        >
            <div
                class="bg-background border-rule flex flex-col gap-3 rounded-lg border p-4 shadow-lg sm:flex-row sm:items-center sm:gap-5"
            >
                <p class="text-muted-foreground text-sm leading-relaxed">
                    Сайт использует только технические cookie — они хранят вашу
                    сессию и защищают формы. Рекламных и аналитических трекеров
                    нет. Подробнее в
                    <a
                        href="/privacy"
                        target="_blank"
                        class="text-foreground underline underline-offset-2"
                    >политике конфиденциальности</a>.
                </p>

                <Button size="sm" class="shrink-0" @click="dismiss">
                    Понятно
                </Button>
            </div>
        </div>
    </Transition>
</template>
