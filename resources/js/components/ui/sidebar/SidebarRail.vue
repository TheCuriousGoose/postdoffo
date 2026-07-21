<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"
import { SIDEBAR_MAX_WIDTH_PX, SIDEBAR_MIN_WIDTH_PX, useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { toggleSidebar, state, width, setWidthPx, setResizing } = useSidebar()

// Dragging resizes the sidebar; a plain click (no meaningful pointer
// movement) falls back to the old behavior of toggling collapse. Assumes a
// left-side sidebar, the only side this app actually uses.
const DRAG_THRESHOLD_PX = 4

function onPointerDown(event: PointerEvent) {
  if (event.button !== 0 || state.value === "collapsed") {
    return
  }

  const startX = event.clientX
  const startWidthPx = Number.parseInt(width.value, 10) || SIDEBAR_MIN_WIDTH_PX
  let dragged = false

  function onPointerMove(moveEvent: PointerEvent) {
    const delta = moveEvent.clientX - startX

    if (!dragged && Math.abs(delta) < DRAG_THRESHOLD_PX) {
      return
    }

    if (!dragged) {
      dragged = true
      setResizing(true)
    }

    setWidthPx(startWidthPx + delta)
  }

  function onPointerUp() {
    window.removeEventListener("pointermove", onPointerMove)
    window.removeEventListener("pointerup", onPointerUp)

    if (dragged) {
      setResizing(false)
    } else {
      toggleSidebar()
    }
  }

  window.addEventListener("pointermove", onPointerMove)
  window.addEventListener("pointerup", onPointerUp)
}
</script>

<template>
  <button
    data-sidebar="rail"
    data-slot="sidebar-rail"
    :aria-label="state === 'collapsed' ? 'Expand sidebar' : 'Resize or collapse sidebar'"
    :tabindex="-1"
    :title="state === 'collapsed' ? 'Expand sidebar' : 'Drag to resize, click to collapse'"
    :style="{ '--sidebar-min-width': `${SIDEBAR_MIN_WIDTH_PX}px`, '--sidebar-max-width': `${SIDEBAR_MAX_WIDTH_PX}px` }"
    :class="cn(
      'hover:after:bg-sidebar-border absolute inset-y-0 z-20 hidden w-4 -translate-x-1/2 transition-all ease-linear group-data-[side=left]:-right-4 group-data-[side=right]:left-0 after:absolute after:inset-y-0 after:left-1/2 after:w-[2px] sm:flex',
      'in-data-[side=left]:cursor-col-resize in-data-[side=right]:cursor-col-resize',
      '[[data-side=left][data-state=collapsed]_&]:cursor-e-resize [[data-side=right][data-state=collapsed]_&]:cursor-w-resize',
      'hover:group-data-[collapsible=offcanvas]:bg-sidebar group-data-[collapsible=offcanvas]:translate-x-0 group-data-[collapsible=offcanvas]:after:left-full',
      '[[data-side=left][data-collapsible=offcanvas]_&]:-right-2',
      '[[data-side=right][data-collapsible=offcanvas]_&]:-left-2',
      props.class,
    )"
    @pointerdown="onPointerDown"
  >
    <slot />
  </button>
</template>
