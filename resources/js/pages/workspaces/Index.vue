<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, Plus, Trash2, Users } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroy,
    show,
    store,
    update,
} from '@/actions/App/Http/Controllers/WorkspaceController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import MoveToTeamDialog from '@/components/workspace/MoveToTeamDialog.vue';
import ShareDialog from '@/components/workspace/ShareDialog.vue';
import { confirmDialog, promptDialog } from '@/lib/dialogs';
import { index as teamsIndex } from '@/routes/teams';
import { index } from '@/routes/workspaces';
import type { Team } from '@/types/team';
import type { Workspace } from '@/types/workspace';

const props = defineProps<{
    workspaces: Workspace[];
    teams: Team[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Workspaces', href: index() }],
    },
});

const page = usePage();
const currentUserId = page.props.auth.user?.id ?? null;

const movingWorkspace = ref<Workspace | null>(null);
const moveDialogOpen = ref(false);

function openMoveDialog(workspace: Workspace) {
    movingWorkspace.value = workspace;
    moveDialogOpen.value = true;
}

// Co-owners can rename a workspace; only the real owner can delete it.
function canRename(workspace: Workspace): boolean {
    return (
        workspace.owner_id === currentUserId || workspace.role === 'co_owner'
    );
}

function canDelete(workspace: Workspace): boolean {
    return workspace.owner_id === currentUserId;
}

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
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Workspaces"
                description="Collections, environments, and requests live inside a workspace."
            />
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <Button variant="outline" as-child>
                    <Link :href="teamsIndex()">
                        <Users class="size-4" />
                        Teams
                    </Link>
                </Button>
                <Button class="w-full sm:w-auto" @click="createWorkspace">
                    <Plus class="size-4" />
                    New workspace
                </Button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="workspace in workspaces"
                :key="workspace.id"
                class="group cursor-pointer transition-colors hover:border-primary"
                @click="router.visit(show(workspace).url)"
            >
                <CardHeader
                    class="flex flex-row items-start justify-between gap-2"
                >
                    <div class="min-w-0">
                        <CardTitle class="truncate">{{ workspace.name }}</CardTitle>
                        <Badge
                            v-if="workspace.team"
                            variant="outline"
                            class="mt-1.5 gap-1 text-[10px] text-muted-foreground"
                        >
                            <Users class="size-3" />
                            {{ workspace.team.name }}
                        </Badge>
                    </div>
                    <div class="-mt-1 -mr-1 flex shrink-0 items-center gap-1">
                        <span @click.stop>
                            <ShareDialog
                                :workspace="workspace"
                                :role="workspace.role ?? null"
                            />
                        </span>
                        <!--
                            Reveal-on-hover has no equivalent on a touch screen,
                            so below md the trigger is simply always visible.
                        -->
                        <DropdownMenu
                            v-if="canRename(workspace) || canDelete(workspace)"
                        >
                            <DropdownMenuTrigger as-child @click.stop>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 shrink-0 transition max-md:opacity-100 md:opacity-0 md:group-hover:opacity-100 md:data-[state=open]:opacity-100"
                                    aria-label="Workspace options"
                                >
                                    <MoreHorizontal class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" @click.stop>
                                <DropdownMenuItem
                                    v-if="canRename(workspace)"
                                    @click="renameWorkspace(workspace)"
                                >
                                    <Pencil class="size-3.5" /> Rename
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canDelete(workspace)"
                                    @click="openMoveDialog(workspace)"
                                >
                                    <Users class="size-3.5" />
                                    {{ workspace.team ? 'Change team' : 'Move to team' }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canDelete(workspace)"
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

        <MoveToTeamDialog
            v-model:open="moveDialogOpen"
            :workspace="movingWorkspace"
            :teams="props.teams"
        />
    </div>
</template>
