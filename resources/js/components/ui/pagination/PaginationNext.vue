<script setup lang="ts">
import type { PaginationNextProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { ChevronRight } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import { PaginationNext } from "reka-ui"
import { buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

const props = defineProps<PaginationNextProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <PaginationNext
    data-slot="pagination-next"
    v-bind="delegatedProps"
    :class="cn(buttonVariants({ variant: 'ghost', size: 'icon-sm' }), props.class)"
    aria-label="Go to next page"
  >
    <slot>
      <ChevronRight class="size-4" />
    </slot>
  </PaginationNext>
</template>
