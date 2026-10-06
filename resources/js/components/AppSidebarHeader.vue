<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="border-border bg-panel flex h-[60px] shrink-0 items-center gap-3.5 border-b px-7 text-base font-semibold transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <!--
            На телефоне логотип слева, кнопка меню справа: большой палец
            дотягивается до края экрана, а не до его середины. На широком
            экране кнопка остаётся слева, у самой колонки меню, которую
            она сворачивает.
        -->
        <div class="flex w-full min-w-0 items-center gap-2">
            <SidebarTrigger class="order-2 ml-auto md:order-none md:-ml-1" />

            <!--
                На телефоне меню спрятано, и логотипа на экране не видно
                нигде. Ставим его сюда: заодно это привычный способ
                вернуться на главную. Хлебные крошки там не нужны —
                раздел и так назван заголовком страницы.
            -->
            <Link
                :href="dashboard()"
                class="order-1 flex items-center gap-2 md:hidden"
            >
                <AppLogo />
            </Link>

            <!-- Обёрткой, а не классом на самом компоненте: у него
                 свой корень, и класс туда попадал бы сквозным
                 наследованием — поведение, которое легко сломать -->
            <div
                v-if="breadcrumbs && breadcrumbs.length > 0"
                class="order-3 hidden min-w-0 md:order-none md:block"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </header>
</template>
