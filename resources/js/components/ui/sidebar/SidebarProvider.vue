<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { defaultDocument, useMediaQuery } from "@vueuse/core"
import { TooltipProvider } from "reka-ui"
import { computed, ref } from "vue"
import { cn } from "@/lib/utils"
import {
  provideSidebarContext,
  SIDEBAR_COOKIE_MAX_AGE,
  SIDEBAR_MAX_WIDTH_PX,
  SIDEBAR_MIN_WIDTH_PX,
  SIDEBAR_WIDTH,
  SIDEBAR_WIDTH_COOKIE_NAME,
  SIDEBAR_WIDTH_ICON,
} from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const isMobile = useMediaQuery("(max-width: 768px)")
const openMobile = ref(false)

function setOpenMobile(value: boolean) {
  openMobile.value = value
}

// The sidebar can no longer be collapsed, only resized, so `open` never
// changes after mount. Kept as state (rather than a literal) because
// Sidebar.vue's `data-state` attribute and NavUser's dropdown positioning
// still read it.
const open = ref(true)
const state = computed(() => open.value ? "expanded" : "collapsed")

function readWidthCookie(): number | null {
  const match = defaultDocument?.cookie.match(new RegExp(`${SIDEBAR_WIDTH_COOKIE_NAME}=(\\d+)`))
  const px = match ? Number(match[1]) : null

  return px && Number.isFinite(px) ? px : null
}

const width = ref(`${readWidthCookie() ?? Number.parseInt(SIDEBAR_WIDTH, 10) * 16}px`)
const resizing = ref(false)

function setWidthPx(px: number) {
  const clamped = Math.min(SIDEBAR_MAX_WIDTH_PX, Math.max(SIDEBAR_MIN_WIDTH_PX, px))
  width.value = `${clamped}px`
  document.cookie = `${SIDEBAR_WIDTH_COOKIE_NAME}=${clamped}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`
}

function setResizing(value: boolean) {
  resizing.value = value
}

provideSidebarContext({
  state,
  open,
  isMobile,
  openMobile,
  setOpenMobile,
  width,
  setWidthPx,
  resizing,
  setResizing,
})
</script>

<template>
  <TooltipProvider :delay-duration="0">
    <div
      data-slot="sidebar-wrapper"
      :style="{
        '--sidebar-width': width,
        '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
      }"
      :class="cn('group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full', props.class)"
      v-bind="$attrs"
    >
      <slot />
    </div>
  </TooltipProvider>
</template>
