<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CreditCard, Plus, Presentation, ShieldCheck } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const credits = computed(() => Number(page.props.auth?.credits ?? 0));
const trialUsed = computed(() => Boolean(page.props.auth?.trialUsed));

// «1 генерация», «2 генерации», «5 генераций» — иначе интерфейс
// выглядит машинным ровно в том месте, где речь о деньгах
function pluralize(n: number): string {
    const ten = n % 10;
    const hundred = n % 100;

    if (ten === 1 && hundred !== 11) return 'генерация';
    if (ten >= 2 && ten <= 4 && (hundred < 12 || hundred > 14)) return 'генерации';

    return 'генераций';
}

const isAdmin = computed(() => Boolean(page.props.auth?.isAdmin));

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Презентации',
        href: '/presentations',
        icon: Presentation,
    },
    {
        title: 'Тарифы',
        href: '/billing',
        icon: CreditCard,
    },
    // Пункт видят только свои — маршрут всё равно закрыт на сервере,
    // это лишь чтобы не мозолил глаза остальным
    ...(isAdmin.value
        ? [{ title: 'Админка', href: '/admin', icon: ShieldCheck }]
        : []),
]);
</script>

<template>
    <!--
        variant="sidebar", а не "inset": при inset shadcn красит всё
        полотно цветом меню, а содержимое кладёт плавающей панелью со
        скруглением, отступами и тенью. В макете меню прижато к краю,
        между ним и содержимым волосяная линейка, полотно одно на всю
        страницу — это и даёт вариант по умолчанию.
    -->
    <Sidebar collapsible="icon" variant="sidebar" class="border-sidebar-border">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 pt-0 pb-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <!-- Размеры из макета: 16px, отступы 13/16,
                             скругление 11, мягкая тень -->
                        <SidebarMenuButton
                            as-child
                            tooltip="Создать"
                            class="bg-foreground text-background hover:bg-foreground/90 hover:text-background active:bg-foreground/90 active:text-background h-auto gap-2.5 rounded-[11px] px-4 py-[13px] text-base font-semibold shadow-[0_4px_12px_rgba(21,22,26,.18)] [&>svg]:size-[17px]"
                        >
                            <Link href="/presentations/new">
                                <Plus />
                                <span>Создать</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <!--
            Плашка остатка, как в макете. Полосы прогресса нет: в макете
            она показывает «35 / 50», а знаменателя у нас не существует —
            баланс не ограничен сверху. Рисовать шкалу от выдуманного
            максимума значит показывать неправду.
        -->
        <div class="px-3 pb-2 group-data-[collapsible=icon]:hidden">
            <Link
                href="/billing"
                class="border-sidebar-border bg-card hover:border-action/40 block rounded-xl border px-3.5 py-3 transition-colors"
            >
                <template v-if="credits > 0">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-muted-foreground text-[13px]">
                            {{ pluralize(credits) === 'генерация' ? 'Генерация' : 'Генерации' }}
                        </span>
                        <span class="text-[15px] font-bold tabular-nums">
                            {{ credits }}
                        </span>
                    </div>
                </template>
                <template v-else-if="!trialUsed">
                    <span class="text-[13px] font-medium">
                        Первая генерация бесплатно
                    </span>
                </template>
                <template v-else>
                    <span class="text-destructive text-[13px] font-medium">
                        Генерации закончились
                    </span>
                </template>
            </Link>
        </div>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
