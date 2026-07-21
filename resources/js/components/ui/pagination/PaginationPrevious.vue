<script setup lang="ts">
import type { PaginationPrevProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { ChevronLeft } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import { PaginationPrev } from "reka-ui"
import { buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

const props = defineProps<PaginationPrevProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <PaginationPrev
    data-slot="pagination-previous"
    v-bind="delegatedProps"
    :class="cn(buttonVariants({ variant: 'ghost', size: 'icon-sm' }), props.class)"
    aria-label="Go to previous page"
  >
    <slot>
      <ChevronLeft class="size-4" />
    </slot>
  </PaginationPrev>
</template>
