<script setup lang="ts">
import type { SplitterPanelProps } from "reka-ui"
import { SplitterPanel } from "reka-ui"

const props = defineProps<SplitterPanelProps>()
</script>

<template>
  <SplitterPanel data-slot="resizable-panel" v-bind="props">
    <slot />
  </SplitterPanel>
</template>
