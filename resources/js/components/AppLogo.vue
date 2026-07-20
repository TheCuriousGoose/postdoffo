<script setup lang="ts">
import logo from '../../img/logo.webp';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md"
    >
        <img :src="logo" alt="PostDoffo" class="size-full object-cover" />
    </div>
    <div class="grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-display leading-tight font-semibold"
            >PostDoffo</span
        >
    </div>
</template>
