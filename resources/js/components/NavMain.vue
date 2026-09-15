<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-1.5 py-0">
        <!-- Разрядка и верхний регистр — как в макете -->
        <SidebarGroupLabel
            class="h-auto px-1.5 pt-5 pb-2.5 text-xs font-semibold tracking-[0.1em] uppercase"
        >
            Работа
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <!-- Выбранный пункт в макете — белая пилюля с рамкой
                     и синей иконкой. Поверхность даёт токен
                     --sidebar-accent, остальное дописываем здесь -->
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="h-auto gap-[11px] rounded-[10px] border border-transparent px-3 py-[11px] text-base [&>svg]:size-[18px] data-[active=true]:border-sidebar-border data-[active=true]:font-semibold data-[active=true]:shadow-[0_1px_2px_rgba(21,22,26,.06)] data-[active=true]:[&>svg]:text-accent-foreground"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
