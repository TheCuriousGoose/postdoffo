<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { PanelLeft } from "@lucide/vue"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { useSidebar } from "./utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()

// The desktop sidebar is always expanded (it resizes rather than collapses), so
// this only ever drives the mobile off-canvas sheet.
const { openMobile, setOpenMobile } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :aria-expanded="openMobile"
    aria-label="Toggle collections sidebar"
    :class="cn('size-8 shrink-0 md:hidden', props.class)"
    @click="setOpenMobile(!openMobile)"
  >
    <PanelLeft class="size-4" />
  </Button>
</template>
