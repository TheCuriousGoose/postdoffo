<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    show,
    store,
} from '@/actions/App/Http/Controllers/WorkspaceController';
import { promptDialog } from '@/lib/dialogs';
import { index } from '@/routes/workspaces';
import type { Workspace } from '@/types/workspace';

defineProps<{
    workspaces: Workspace[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Workspaces', href: index() }],
    },
});

async function createWorkspace() {
    const name = await promptDialog({
        title: 'New workspace',
        label: 'Workspace name',
        defaultValue: 'My Workspace',
        confirmText: 'Create',
    });

    if (!name) {
        return;
    }

    router.post(store().url, { name });
}
</script>

<template>
    <Head title="Workspaces" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Workspaces"
                description="Collections, environments, and requests live inside a workspace."
            />
            <Button @click="createWorkspace">
                <Plus class="size-4" />
                New workspace
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card
                v-for="workspace in workspaces"
                :key="workspace.id"
                class="cursor-pointer transition-colors hover:border-primary"
                @click="router.visit(show(workspace).url)"
            >
                <CardHeader>
                    <CardTitle>{{ workspace.name }}</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    {{ workspace.collections_count ?? 0 }} collections
                </CardContent>
            </Card>

            <p v-if="!workspaces.length" class="text-sm text-muted-foreground">
                No workspaces yet — create one to get started.
            </p>
        </div>
    </div>
</template>
