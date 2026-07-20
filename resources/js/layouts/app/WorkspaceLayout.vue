<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PromptDialog from '@/components/PromptDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import WorkspaceSidebar from '@/components/workspace/WorkspaceSidebar.vue';
</script>

<template>
    <AppShell variant="sidebar">
        <WorkspaceSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <slot />
        </AppContent>
        <Toaster />
        <ConfirmDialog />
        <PromptDialog />
    </AppShell>
</template>
