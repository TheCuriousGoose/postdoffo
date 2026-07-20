<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';
import {
    destroy,
    show,
    store,
    update,
} from '@/actions/App/Http/Controllers/WorkspaceController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import ShareDialog from '@/components/workspace/ShareDialog.vue';
import { confirmDialog, promptDialog } from '@/lib/dialogs';
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

const page = usePage();
const currentUserId = page.props.auth.user?.id ?? null;

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

async function renameWorkspace(workspace: Workspace) {
    const name = await promptDialog({
        title: 'Rename workspace',
        label: 'Workspace name',
        defaultValue: workspace.name,
        confirmText: 'Rename',
    });

    if (!name || name === workspace.name) {
        return;
    }

    router.patch(update(workspace.id).url, { name }, { preserveScroll: true });
}

async function deleteWorkspace(workspace: Workspace) {
    const confirmed = await confirmDialog({
        title: `Delete "${workspace.name}"?`,
        description:
            'This permanently deletes the workspace and every collection, request and environment inside it. This cannot be undone.',
        confirmText: 'Delete workspace',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy(workspace.id).url, { preserveScroll: true });
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
                class="group cursor-pointer transition-colors hover:border-primary"
                @click="router.visit(show(workspace).url)"
            >
                <CardHeader
                    class="flex flex-row items-start justify-between gap-2"
                >
                    <CardTitle class="truncate">{{ workspace.name }}</CardTitle>
                    <div class="-mt-1 -mr-1 flex shrink-0 items-center gap-1">
                        <span @click.stop>
                            <ShareDialog
                                :workspace="workspace"
                                :role="workspace.role ?? null"
                            />
                        </span>
                        <DropdownMenu
                            v-if="workspace.owner_id === currentUserId"
                        >
                            <DropdownMenuTrigger as-child @click.stop>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 shrink-0 opacity-0 transition group-hover:opacity-100 data-[state=open]:opacity-100"
                                    aria-label="Workspace options"
                                >
                                    <MoreHorizontal class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" @click.stop>
                                <DropdownMenuItem
                                    @click="renameWorkspace(workspace)"
                                >
                                    <Pencil class="size-3.5" /> Rename
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    @click="deleteWorkspace(workspace)"
                                >
                                    <Trash2 class="size-3.5" /> Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
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
