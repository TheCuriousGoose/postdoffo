<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    importMethod as importCollection,
    reorder as reorderCollections,
    store as storeCollection,
    update as updateCollection,
} from '@/actions/App/Http/Controllers/CollectionController';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import CollectionTree from '@/components/workspace/CollectionTree.vue';
import CommandPalette from '@/components/workspace/CommandPalette.vue';
import ToolbarButton from '@/components/workspace/ToolbarButton.vue';
import { useOpenRequest } from '@/composables/useOpenRequest';
import { api } from '@/lib/api';
import { promptDialog } from '@/lib/dialogs';
import { draggedItem, reorderIds } from '@/lib/dragState';
import {
    index as workspacesIndex,
    show as workspacesShow,
} from '@/routes/workspaces';
import { useWorkspaceStore } from '@/stores/workspace';
import type { Workspace } from '@/types/workspace';

const page = usePage<{ workspace: Workspace }>();
const workspace = computed(() => page.props.workspace);
const store = useWorkspaceStore();
const { openRequest } = useOpenRequest();

const importInput = ref<HTMLInputElement | null>(null);
const paletteOpen = ref(false);

async function newRootCollection() {
    const name = await promptDialog({
        title: 'New collection',
        label: 'Collection name',
        defaultValue: 'New Collection',
        confirmText: 'Create',
    });

    if (!name) {
        return;
    }

    await api.post(storeCollection.url(workspace.value.id), { name });
    router.reload({ only: ['collectionTree'] });
}

// Same sibling-reorder handoff CollectionTree does for nested folders, but
// for the root-level collections rendered directly here (parent_id: null) —
// cross-folder moves are handled entirely inside CollectionTree itself.
async function onRootReorder(
    draggedId: number,
    targetId: number,
    position: 'before' | 'after',
) {
    if (draggedId === targetId) {
        return;
    }

    const ordered = reorderIds(
        store.collectionTree.map((n) => n.id),
        draggedId,
        targetId,
        position,
    );

    await api.patch(reorderCollections.url(workspace.value.id), {
        parent_id: null,
        ordered_ids: ordered,
    });
    router.reload({ only: ['collectionTree'] });
}

// Dropping a folder onto the empty area of the collections list (i.e. not onto
// another row — those drops call stopPropagation) promotes it back to the root.
// Cross-folder moves onto a *row* are handled inside CollectionTree itself.
const rootDropActive = ref(false);

function onRootDragOver() {
    const item = draggedItem.value;
    // Only a folder that isn't already at the root has anywhere to go here.
    rootDropActive.value =
        item?.type === 'collection' && item.parentId !== null;
}

function onRootDragLeave() {
    rootDropActive.value = false;
}

async function onRootDrop() {
    const item = draggedItem.value;
    rootDropActive.value = false;

    if (!item || item.type !== 'collection' || item.parentId === null) {
        return;
    }

    draggedItem.value = null;
    await api.patch(updateCollection.url(item.id), {
        parent_id: null,
        order: store.collectionTree.length,
    });
    router.reload({ only: ['collectionTree'] });
}

// The import endpoint sniffs the payload: a single collection lands here, a
// Postman environment export becomes an environment, and a multi-collection
// bundle spins up a whole new workspace we navigate to. `type` is absent on the
// (backwards-compatible) single-collection response.
type ImportResult =
    | { type: 'environment' }
    | { type: 'workspace'; workspace_id: number; name: string }
    | { type?: never };

async function onImportFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    try {
        const collection = JSON.parse(await file.text());
        const result = await api.post<ImportResult>(
            importCollection.url(workspace.value.id),
            { collection },
        );

        if (result.type === 'workspace') {
            toast.success(`Imported into a new workspace "${result.name}"`);
            router.visit(workspacesShow(result.workspace_id).url);

            return;
        }

        if (result.type === 'environment') {
            router.reload({ only: ['environments'] });
            toast.success('Environment imported');

            return;
        }

        router.reload({ only: ['collectionTree', 'environments'] });
        toast.success('Collection imported, with a base environment');
    } catch {
        toast.error(
            'Failed to import — is this a valid Postman collection, environment, or bundle?',
        );
    } finally {
        if (importInput.value) {
            importInput.value.value = '';
        }
    }
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="workspacesIndex()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <button
                type="button"
                class="mx-2 mb-1 flex items-center gap-2 rounded-md border bg-background px-2.5 py-1.5 text-left text-muted-foreground transition group-data-[collapsible=icon]:hidden hover:bg-accent"
                @click="paletteOpen = true"
            >
                <Search class="size-3.5 shrink-0" />
                <span class="flex-1 truncate text-xs">Search requests…</span>
                <kbd
                    class="hidden shrink-0 rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] sm:inline-block"
                    >Ctrl K</kbd
                >
            </button>

            <div
                class="flex items-center justify-between px-2 py-1 group-data-[collapsible=icon]:hidden"
            >
                <span class="text-xs font-medium text-muted-foreground"
                    >Collections</span
                >
                <div class="flex items-center gap-1">
                    <ToolbarButton
                        label="Import a Postman collection, environment, or bundle"
                        size="sm"
                        @click="importInput?.click()"
                    >
                        <Upload class="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton
                        label="New collection"
                        size="sm"
                        @click="newRootCollection"
                    >
                        <Plus class="size-3.5" />
                    </ToolbarButton>
                </div>
            </div>
            <input
                ref="importInput"
                type="file"
                accept="application/json"
                class="hidden"
                @change="onImportFile"
            />
        </SidebarHeader>

        <SidebarContent class="px-1 pb-2 group-data-[collapsible=icon]:hidden">
            <div
                class="min-h-full rounded-md transition-shadow ring-inset"
                :class="rootDropActive && 'bg-primary/5 ring-2 ring-primary'"
                @dragover.prevent="onRootDragOver"
                @dragleave="onRootDragLeave"
                @drop.prevent="onRootDrop"
            >
                <CollectionTree
                    v-for="node in store.collectionTree"
                    :key="node.id"
                    :node="node"
                    :workspace-id="workspace.id"
                    :active-request-id="store.activeTabId"
                    @open-request="openRequest"
                    @reorder-child="onRootReorder"
                />
                <p
                    v-if="!store.collectionTree.length"
                    class="px-2 py-4 text-xs text-muted-foreground"
                >
                    No collections yet.
                </p>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
            <SidebarMenu>
                <SidebarMenuItem>
                    <NotificationBell />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>

    <CommandPalette v-model:open="paletteOpen" />
</template>
