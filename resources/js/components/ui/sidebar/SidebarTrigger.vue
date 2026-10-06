<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Menu, PanelLeftClose, PanelLeftOpen } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { state, toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <!--
        На телефоне меню выезжает поверх экрана, а не раздвигает
        раскладку, и привычный знак для этого — три линии. Стрелки
        остаются на широком экране, где меню и правда сворачивается
        в колонку.

        Выбор по классам, а не по isMobile: эта величина вычисляется
        уже в браузере, и до её появления на экран успевала попасть
        стрелка — отсюда мигающий посторонний значок при загрузке.
        Классы работают сразу, ещё до оживления страницы.
    -->
    <Menu class="md:hidden" />
    <PanelLeftOpen v-if="state === 'collapsed'" class="hidden md:block" />
    <PanelLeftClose v-else class="hidden md:block" />
    <span class="sr-only">Меню</span>
  </Button>
</template>
