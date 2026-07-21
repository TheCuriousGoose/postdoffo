<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { MoreHorizontal, Trash2 } from '@lucide/vue';
import { destroy } from '@/actions/App/Http/Controllers/Admin/WorkspaceController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { confirmDialog } from '@/lib/dialogs';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/workspaces';
import type { AdminWorkspace } from '@/types';

defineProps<{
    workspaces: AdminWorkspace[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Workspaces', href: index() },
        ],
    },
});

async function deleteWorkspace(workspace: AdminWorkspace) {
    const confirmed = await confirmDialog({
        title: `Delete "${workspace.name}"?`,
        description: `This permanently deletes the workspace owned by ${workspace.owner.name}, along with every collection, request and environment inside it. This cannot be undone.`,
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
    <Head title="Admin - Workspaces" />

    <div class="rounded-lg border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Workspace</TableHead>
                    <TableHead>Owner</TableHead>
                    <TableHead>Members</TableHead>
                    <TableHead>Collections</TableHead>
                    <TableHead>Created</TableHead>
                    <TableHead class="w-10" />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="workspace in workspaces" :key="workspace.id">
                    <TableCell class="font-medium">
                        {{ workspace.name }}
                    </TableCell>
                    <TableCell>
                        <div class="min-w-0">
                            <p class="truncate text-sm">
                                {{ workspace.owner.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ workspace.owner.email }}
                            </p>
                        </div>
                    </TableCell>
                    <TableCell class="text-sm text-muted-foreground">
                        {{ workspace.members_count }}
                    </TableCell>
                    <TableCell class="text-sm text-muted-foreground">
                        {{ workspace.collections_count }}
                    </TableCell>
                    <TableCell class="text-sm text-muted-foreground">
                        {{ new Date(workspace.created_at).toLocaleDateString() }}
                    </TableCell>
                    <TableCell>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    aria-label="Workspace options"
                                >
                                    <MoreHorizontal class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    variant="destructive"
                                    @click="deleteWorkspace(workspace)"
                                >
                                    <Trash2 class="size-3.5" /> Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </TableCell>
                </TableRow>

                <TableRow v-if="!workspaces.length">
                    <TableCell
                        colspan="6"
                        class="text-center text-sm text-muted-foreground"
                    >
                        No workspaces yet.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
