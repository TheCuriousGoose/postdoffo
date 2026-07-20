<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    importMethod as importCollection,
    store as storeCollection,
} from '@/actions/App/Http/Controllers/CollectionController';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import CollectionTree from '@/components/workspace/CollectionTree.vue';
import { api } from '@/lib/api';
import { promptDialog } from '@/lib/dialogs';
import { index as workspacesIndex } from '@/routes/workspaces';
import { useWorkspaceStore } from '@/stores/workspace';
import type { ApiRequest, Workspace } from '@/types/workspace';

const page = usePage<{ workspace: Workspace }>();
const workspace = computed(() => page.props.workspace);
const store = useWorkspaceStore();

const importInput = ref<HTMLInputElement | null>(null);

function openRequest(request: ApiRequest) {
    store.openRequest(request);
}

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

async function onImportFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    try {
        const collection = JSON.parse(await file.text());
        await api.post(importCollection.url(workspace.value.id), {
            collection,
        });
        router.reload({ only: ['collectionTree', 'environments'] });
        toast.success('Collection imported, with a base environment');
    } catch {
        toast.error(
            'Failed to import collection — is this a valid Postman v2.1 export?',
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

            <div
                class="flex items-center justify-between px-2 py-1 group-data-[collapsible=icon]:hidden"
            >
                <span class="text-xs font-medium text-muted-foreground"
                    >Collections</span
                >
                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-6"
                        title="Import Postman collection"
                        @click="importInput?.click()"
                    >
                        <Upload class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-6"
                        title="New collection"
                        @click="newRootCollection"
                    >
                        <Plus class="size-3.5" />
                    </Button>
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
            <CollectionTree
                v-for="node in store.collectionTree"
                :key="node.id"
                :node="node"
                :workspace-id="workspace.id"
                :active-request-id="store.activeTabId"
                @open-request="openRequest"
            />
            <p
                v-if="!store.collectionTree.length"
                class="px-2 py-4 text-xs text-muted-foreground"
            >
                No collections yet.
            </p>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
            <SidebarMenu>
                <SidebarMenuItem>
                    <NotificationBell />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
</template>
