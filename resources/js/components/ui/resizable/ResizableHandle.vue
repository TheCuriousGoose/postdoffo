<script setup lang="ts">
import type { SplitterResizeHandleProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { GripVertical } from "@lucide/vue"
import { SplitterResizeHandle } from "reka-ui"
import { cn } from "@/lib/utils"

// The upstream styles were written against react-resizable-panels, which marks
// the handle with `data-panel-group-direction`. reka emits `data-orientation`
// instead, so every vertical rule here used to match nothing: a vertical split
// kept the horizontal `w-px` and collapsed to a 1px sliver as tall as whatever
// sat inside it, leaving the optional grip as the only thing you could grab.
// Keyed off the attribute reka actually sets, the divider is the full-width
// border it was meant to be, and the whole line is the drag target.
defineProps<SplitterResizeHandleProps & { class?: HTMLAttributes["class"], withHandle?: boolean }>()
</script>

<template>
  <SplitterResizeHandle
    data-slot="resizable-handle"
    :class="cn(
      'group bg-border hover:bg-primary/40 data-[state=drag]:bg-primary focus-visible:ring-ring relative flex w-px cursor-col-resize items-center justify-center transition-colors focus-visible:ring-1 focus-visible:ring-offset-1 focus-visible:outline-hidden',
      'after:absolute after:inset-y-0 after:left-1/2 after:w-4 after:-translate-x-1/2',
      'data-[orientation=vertical]:h-px data-[orientation=vertical]:w-full data-[orientation=vertical]:cursor-row-resize',
      'data-[orientation=vertical]:after:inset-x-0 data-[orientation=vertical]:after:top-1/2 data-[orientation=vertical]:after:left-0 data-[orientation=vertical]:after:h-4 data-[orientation=vertical]:after:w-full data-[orientation=vertical]:after:translate-x-0 data-[orientation=vertical]:after:-translate-y-1/2',
      '[&[data-orientation=vertical]>div]:rotate-90',
      $props.class,
    )"
  >
    <div v-if="withHandle" class="bg-background text-muted-foreground z-10 flex h-4 w-3.5 items-center justify-center rounded-sm border shadow-sm transition-colors group-hover:border-primary">
      <GripVertical class="size-3" />
    </div>
  </SplitterResizeHandle>
</template>
