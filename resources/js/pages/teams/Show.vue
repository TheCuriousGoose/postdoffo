<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    destroy as destroyTeam,
    update as updateTeam,
} from '@/actions/App/Http/Controllers/TeamController';
import {
    show as showWorkspace,
    store as storeWorkspace,
} from '@/actions/App/Http/Controllers/WorkspaceController';
import Heading from '@/components/Heading.vue';
import ShareDialog from '@/components/team/ShareDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { confirmDialog, promptDialog } from '@/lib/dialogs';
import { index as teamsIndex } from '@/routes/teams';
import type { Team, TeamRole } from '@/types/team';
import type { Workspace } from '@/types/workspace';

const props = defineProps<{
    team: Team;
    workspaces: Workspace[];
    role: TeamRole | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Teams', href: teamsIndex() }],
    },
});

const page = usePage();
const currentUserId = page.props.auth.user?.id ?? null;

const canManage = computed(
    () => props.role === 'owner' || props.role === 'admin',
);
const canDelete = computed(() => props.team.owner_id === currentUserId);

async function renameTeam() {
    const name = await promptDialog({
        title: 'Rename team',
        label: 'Team name',
        defaultValue: props.team.name,
        confirmText: 'Rename',
    });

    if (!name || name === props.team.name) {
        return;
    }

    router.patch(updateTeam(props.team.id).url, { name }, { preserveScroll: true });
}

async function deleteTeam() {
    const confirmed = await confirmDialog({
        title: `Delete "${props.team.name}"?`,
        description:
            'Every workspace this team owns is kept, but goes back to standalone — nobody who only had access through this team can reach them anymore. This cannot be undone.',
        confirmText: 'Delete team',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroyTeam(props.team.id).url);
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

    router.post(storeWorkspace().url, { name, team_id: props.team.id });
}
</script>

<template>
    <Head :title="team.name" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                :title="team.name"
                description="Every member here gets access to every workspace below."
            />
            <div class="flex flex-wrap items-center gap-2">
                <ShareDialog :team="team" :role="role" />
                <Button
                    v-if="canManage"
                    class="w-full sm:w-auto"
                    @click="createWorkspace"
                >
                    <Plus class="size-4" />
                    New workspace
                </Button>
                <DropdownMenu v-if="canManage || canDelete">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="icon" aria-label="Team options">
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem v-if="canManage" @click="renameTeam">
                            <Pencil class="size-3.5" /> Rename
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="canDelete"
                            variant="destructive"
                            @click="deleteTeam"
                        >
                            <Trash2 class="size-3.5" /> Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="workspace in workspaces"
                :key="workspace.id"
                class="cursor-pointer transition-colors hover:border-primary"
                @click="router.visit(showWorkspace(workspace).url)"
            >
                <CardHeader>
                    <CardTitle class="truncate">{{ workspace.name }}</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    {{ workspace.collections_count ?? 0 }} collections
                </CardContent>
            </Card>

            <p v-if="!workspaces.length" class="text-sm text-muted-foreground">
                No workspaces yet<span v-if="canManage">
                    — create one, or move an existing workspace in from its
                    options menu.</span
                ><span v-else>.</span>
            </p>
        </div>
    </div>
</template>
