<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Layers, MoreHorizontal, Search, Trash2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { destroy } from '@/actions/App/Http/Controllers/Admin/WorkspaceController';
import DataPagination from '@/components/admin/DataPagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
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
import type { AdminWorkspace, Paginated } from '@/types';

const props = defineProps<{
    workspaces: Paginated<AdminWorkspace>;
    filters: {
        search: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Workspaces', href: index() },
        ],
    },
});

const search = ref(props.filters.search);

function visit(pageNumber?: number) {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            page: pageNumber,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['workspaces', 'filters'],
        },
    );
}

// Debounce keystrokes so we navigate once the user pauses, not on every letter.
watchDebounced(search, () => visit(), { debounce: 300 });

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

    <Card class="gap-0 py-0">
        <CardHeader
            class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <CardTitle class="text-base">Workspaces</CardTitle>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ workspaces.total }}
                    {{ workspaces.total === 1 ? 'workspace' : 'workspaces' }}
                    across the whole app.
                </p>
            </div>

            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search workspaces…"
                    class="w-full pl-8 sm:w-64"
                />
            </div>
        </CardHeader>

        <Separator />

        <CardContent class="px-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-6">Workspace</TableHead>
                        <TableHead>Owner</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Members</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Collections</TableHead
                        >
                        <TableHead class="hidden sm:table-cell"
                            >Created</TableHead
                        >
                        <TableHead class="w-10 pr-6" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="workspace in workspaces.data"
                        :key="workspace.id"
                    >
                        <TableCell class="pl-6 font-medium">
                            {{ workspace.name }}
                        </TableCell>
                        <TableCell>
                            <div class="min-w-0">
                                <p class="truncate text-sm">
                                    {{ workspace.owner.name }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ workspace.owner.email }}
                                </p>
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground md:table-cell"
                        >
                            {{ workspace.members_count }}
                        </TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground lg:table-cell"
                        >
                            {{ workspace.collections_count }}
                        </TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground sm:table-cell"
                        >
                            {{
                                new Date(
                                    workspace.created_at,
                                ).toLocaleDateString()
                            }}
                        </TableCell>
                        <TableCell class="pr-6">
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
                </TableBody>
            </Table>

            <div
                v-if="!workspaces.data.length"
                class="flex flex-col items-center gap-2 py-16 text-center"
            >
                <Layers class="size-8 text-muted-foreground/50" />
                <p class="text-sm text-muted-foreground">
                    {{
                        filters.search
                            ? 'No workspaces match your search.'
                            : 'No workspaces yet.'
                    }}
                </p>
            </div>
        </CardContent>

        <template v-if="workspaces.data.length">
            <Separator />
            <DataPagination :meta="workspaces" @update:page="visit" />
        </template>
    </Card>
</template>
