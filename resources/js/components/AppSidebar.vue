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
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const credits = computed(() => Number(page.props.auth?.credits ?? 0));
const trialUsed = computed(() => Boolean(page.props.auth?.trialUsed));

const isAdmin = computed(() => Boolean(page.props.auth?.isAdmin));

/* Любой переход из меню на телефоне должен его закрывать: иначе
   оно остаётся поверх той самой страницы, ради которой нажали */
const { isMobile, setOpenMobile } = useSidebar();

function close() {
    if (isMobile.value) setOpenMobile(false);
}

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
                    <!--
                        Обычная ссылка, а не SidebarMenuButton: логотип не
                        пункт меню, и подсветка на наведении делала вид,
                        будто рядом с «Презентациями» есть ещё один раздел.
                    -->
                    <Link
                        :href="dashboard()"
                        @click="close"
                        class="flex h-12 items-center gap-2 rounded-md p-2 text-[var(--foreground)] group-data-[collapsible=icon]:p-0!"
                    >
                        <AppLogo />
                    </Link>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-4 pt-0 pb-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <!-- Размеры из макета: 16px, отступы 13/16,
                             скругление 11, мягкая тень -->
                        <SidebarMenuButton
                            as-child
                            tooltip="Создать"
                            class="bg-foreground text-background hover:bg-foreground/90 hover:text-background active:bg-foreground/90 active:text-background h-auto gap-2.5 rounded-[11px] px-4 py-[13px] text-base font-semibold shadow-[0_4px_12px_rgba(21,22,26,.18)] [&>svg]:size-[17px]"
                        >
                            <Link href="/presentations/new" @click="close">
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
                @click="close"
                class="border-sidebar-border bg-card hover:border-action/40 block rounded-xl border px-3.5 py-3 transition-colors"
            >
                <template v-if="credits > 0">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-muted-foreground text-[13px]">
                            Осталось генераций:
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

            <!--
                Правовые документы. Нужны именно здесь: человек читает
                их не при регистрации, а когда решает заплатить, хочет
                отозвать согласие или удалить аккаунт — то есть находясь
                внутри кабинета. Набрано мелко и приглушённо: это не то,
                чем пользуются, но то, что всегда должно быть под рукой.

                В свёрнутом меню скрыто — там и обычные подписи не видны.
            -->
            <p
                class="text-muted-foreground flex flex-wrap gap-x-2 gap-y-1 px-2 pb-1 text-[11px] leading-tight group-data-[collapsible=icon]:hidden"
            >
                <Link
                    href="/offer"
                    class="hover:text-foreground transition-colors"
                    @click="close"
                >
                    Оферта
                </Link>
                <span aria-hidden="true">·</span>
                <Link
                    href="/privacy"
                    class="hover:text-foreground transition-colors"
                    @click="close"
                >
                    Конфиденциальность
                </Link>
                <span aria-hidden="true">·</span>
                <Link
                    href="/consent"
                    class="hover:text-foreground transition-colors"
                    @click="close"
                >
                    Согласие на обработку
                </Link>
            </p>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
