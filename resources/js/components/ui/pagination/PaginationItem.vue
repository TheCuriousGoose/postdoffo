<script setup lang="ts">
import type { PaginationListItemProps } from "reka-ui"
import { PaginationListItem } from "reka-ui"
import { buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

const props = defineProps<PaginationListItemProps & { isActive?: boolean }>()
</script>

<template>
  <PaginationListItem
    data-slot="pagination-item"
    :value="props.value"
    :class="cn(
      buttonVariants({
        variant: props.isActive ? 'outline' : 'ghost',
        size: 'icon-sm',
      }),
      'tabular-nums',
    )"
  >
    <slot>{{ props.value }}</slot>
  </PaginationListItem>
</template>
