<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Menu, PanelLeftClose, PanelLeftOpen } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()
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
    <!-- На телефоне меню выезжает поверх экрана, а не раздвигает
         раскладку, и привычный знак для этого — три линии. Стрелки
         в панели остаются на широком экране, где меню и правда
         сворачивается в колонку -->
    <Menu v-if="isMobile" />
    <PanelLeftOpen v-else-if="state === 'collapsed'" />
    <PanelLeftClose v-else />
    <span class="sr-only">Меню</span>
  </Button>
</template>
