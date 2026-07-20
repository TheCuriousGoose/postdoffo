<script setup lang="ts">
import type { SplitterResizeHandleProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { GripVertical } from "@lucide/vue"
import { SplitterResizeHandle } from "reka-ui"
import { cn } from "@/lib/utils"

defineProps<SplitterResizeHandleProps & { class?: HTMLAttributes["class"], withHandle?: boolean }>()
</script>

<template>
  <SplitterResizeHandle
    data-slot="resizable-handle"
    :class="cn(
      'group bg-border hover:bg-primary/50 data-[state=drag]:bg-primary focus-visible:ring-ring relative flex w-px items-center justify-center transition-colors after:absolute after:inset-y-0 after:left-1/2 after:w-3 after:-translate-x-1/2 focus-visible:ring-1 focus-visible:ring-offset-1 focus-visible:outline-hidden data-[panel-group-direction=vertical]:h-px data-[panel-group-direction=vertical]:w-full data-[panel-group-direction=vertical]:after:left-0 data-[panel-group-direction=vertical]:after:h-3 data-[panel-group-direction=vertical]:after:w-full data-[panel-group-direction=vertical]:after:-translate-y-1/2 data-[panel-group-direction=vertical]:after:translate-x-0 [&[data-panel-group-direction=vertical]>div]:rotate-90',
      $props.class,
    )"
  >
    <div v-if="withHandle" class="bg-background text-muted-foreground z-10 flex h-4 w-3.5 items-center justify-center rounded-sm border shadow-sm transition-colors group-hover:border-primary">
      <GripVertical class="size-3" />
    </div>
  </SplitterResizeHandle>
</template>
