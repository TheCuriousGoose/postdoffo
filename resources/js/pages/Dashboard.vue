<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FolderKanban, Layers, Plus, Send } from '@lucide/vue';
import {
    show,
    store,
} from '@/actions/App/Http/Controllers/WorkspaceController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { promptDialog } from '@/lib/dialogs';
import { dashboard } from '@/routes';
import { index as workspacesIndex } from '@/routes/workspaces';
import type { RequestHistoryEntry, Workspace } from '@/types/workspace';

const props = defineProps<{
    workspaces: Workspace[];
    requestCount: number;
    recentHistory: (RequestHistoryEntry & {
        workspace: { id: number; name: string };
    })[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const collectionCount = props.workspaces.reduce(
    (total, workspace) => total + (workspace.collections_count ?? 0),
    0,
);

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

function statusVariant(status: number | null) {
    if (status === null) {
        return 'secondary' as const;
    }

    return status >= 200 && status < 300
        ? ('default' as const)
        : ('destructive' as const);
}

function formatDuration(ms: number | null) {
    return ms === null ? '—' : `${ms} ms`;
}

function formatExecutedAt(value: string) {
    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader
                    class="flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Workspaces</CardTitle
                    >
                    <Layers class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-semibold">
                        {{ workspaces.length }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Collections</CardTitle
                    >
                    <FolderKanban class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-semibold">
                        {{ collectionCount }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Requests</CardTitle
                    >
                    <Send class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-semibold">
                        {{ requestCount }}
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-1">
                <CardHeader class="flex-row items-center justify-between">
                    <CardTitle>Workspaces</CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="createWorkspace"
                    >
                        <Plus class="size-4" />
                    </Button>
                </CardHeader>
                <CardContent class="flex flex-col gap-1">
                    <Link
                        v-for="workspace in workspaces.slice(0, 6)"
                        :key="workspace.id"
                        :href="show(workspace).url"
                        class="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                    >
                        <span class="truncate">{{ workspace.name }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ workspace.collections_count ?? 0 }} collections
                        </span>
                    </Link>

                    <p
                        v-if="!workspaces.length"
                        class="px-2 py-1.5 text-sm text-muted-foreground"
                    >
                        No workspaces yet — create one to get started.
                    </p>

                    <Link
                        v-if="workspaces.length"
                        :href="workspacesIndex()"
                        class="px-2 pt-2 text-xs text-muted-foreground hover:text-foreground"
                    >
                        View all workspaces →
                    </Link>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Recent activity</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="recentHistory.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Request</TableHead>
                                <TableHead>Workspace</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Duration</TableHead>
                                <TableHead>When</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="entry in recentHistory"
                                :key="entry.id"
                            >
                                <TableCell
                                    class="max-w-64 truncate font-mono text-xs"
                                >
                                    <span class="mr-2 font-semibold">{{
                                        entry.method
                                    }}</span>
                                    {{ entry.url }}
                                </TableCell>
                                <TableCell>
                                    <Link
                                        :href="show(entry.workspace).url"
                                        class="hover:underline"
                                    >
                                        {{ entry.workspace.name }}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            statusVariant(entry.status_code)
                                        "
                                    >
                                        {{ entry.status_code ?? 'Error' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatDuration(entry.duration_ms) }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatExecutedAt(entry.executed_at) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <p v-else class="text-sm text-muted-foreground">
                        No requests have been sent yet.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
