<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CreditCard, Plus, Presentation } from '@lucide/vue';
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

const mainNavItems: NavItem[] = [
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
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
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
                        <SidebarMenuButton
                            as-child
                            tooltip="Создать"
                            class="bg-foreground text-background hover:bg-foreground/90 hover:text-background active:bg-foreground/90 active:text-background"
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

        <div class="px-4 pb-2">
            <Link
                href="/billing"
                class="text-muted-foreground hover:text-foreground block text-xs transition-colors group-data-[collapsible=icon]:hidden"
            >
                <template v-if="credits > 0">
                    Осталось {{ credits }} {{ pluralize(credits) }}
                </template>
                <template v-else-if="!trialUsed">
                    Первая генерация бесплатно
                </template>
                <template v-else>
                    <span class="text-brand">Генерации закончились</span>
                </template>
            </Link>
        </div>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
