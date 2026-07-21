<script setup lang="ts">
import type { PaginationListProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { PaginationList } from "reka-ui"
import { cn } from "@/lib/utils"

type PaginationSlotItem = { type: "ellipsis" } | { type: "page"; value: number }

const props = defineProps<PaginationListProps & { class?: HTMLAttributes["class"] }>()

defineSlots<{
  default: (props: { items: PaginationSlotItem[] }) => unknown
}>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <PaginationList
    v-slot="{ items }"
    data-slot="pagination-content"
    v-bind="delegatedProps"
    :class="cn('flex flex-row items-center gap-1', props.class)"
  >
    <slot :items="items" />
  </PaginationList>
</template>
