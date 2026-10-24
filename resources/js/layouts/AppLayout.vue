<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import CookieNotice from '@/components/CookieNotice.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { serverGoals } from '@/lib/metrika';
import type { ServerGoal } from '@/lib/metrika';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

/* Цели от сервера (регистрация). После регистрации человек попадает
   на страницу внутри приложения — эта раскладка есть у каждой из них */
const page = usePage();

watch(
    () => page.props.goals as ServerGoal[] | undefined,
    (goals) => serverGoals(goals),
    { immediate: true },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>

    <CookieNotice />
</template>
