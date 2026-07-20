<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Upload, X } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    importMethod as importCollection,
    store as storeCollection,
} from '@/actions/App/Http/Controllers/CollectionController';
import { index as workspacesIndex } from '@/actions/App/Http/Controllers/WorkspaceController';
import { Button } from '@/components/ui/button';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import CollectionTree from '@/components/workspace/CollectionTree.vue';
import EnvironmentSwitcher from '@/components/workspace/EnvironmentSwitcher.vue';
import HistoryPanel from '@/components/workspace/HistoryPanel.vue';
import RequestEditor from '@/components/workspace/RequestEditor.vue';
import ResponsePanel from '@/components/workspace/ResponsePanel.vue';
import ShareDialog from '@/components/workspace/ShareDialog.vue';
import { api } from '@/lib/api';
import { promptDialog } from '@/lib/dialogs';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace';
import type {
    ApiRequest,
    CollectionNode,
    Environment,
    RequestHistoryEntry,
    Workspace,
} from '@/types/workspace';

const props = defineProps<{
    workspace: Workspace;
    collectionTree: CollectionNode[];
    environments: Environment[];
    history: RequestHistoryEntry[];
    role: 'owner' | 'editor' | 'viewer' | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Workspaces', href: workspacesIndex() }],
    },
});

const store = useWorkspaceStore();
const importInput = ref<HTMLInputElement | null>(null);

const methodColor: Record<string, string> = {
    GET: 'text-blue-600 dark:text-blue-400',
    POST: 'text-green-600 dark:text-green-400',
    PUT: 'text-amber-600 dark:text-amber-400',
    PATCH: 'text-amber-600 dark:text-amber-400',
    DELETE: 'text-red-600 dark:text-red-400',
    HEAD: 'text-muted-foreground',
    OPTIONS: 'text-muted-foreground',
};

onMounted(() => {
    const active = props.environments.find((e) => e.is_active) ?? null;
    store.setWorkspace(props.workspace.id, active?.id ?? null);
});

// Keep the store's copy of the tree and environments current so the editor's
// "what's inherited / in scope" affordances update after partial reloads.
watch(
    () => props.collectionTree,
    (tree) => store.setCollectionTree(tree),
    { immediate: true, deep: true },
);

watch(
    () => props.environments,
    (environments) => {
        store.setEnvironments(environments);

        // Adopt the server's active environment when nothing is selected yet,
        // so an environment created during import (or on first load) is live
        // immediately without a manual switch.
        if (store.activeEnvironmentId == null) {
            const active = environments.find((e) => e.is_active);

            if (active) {
                store.setActiveEnvironment(active.id);
            }
        }
    },
    { immediate: true, deep: true },
);

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

    await api.post(storeCollection.url(props.workspace.id), { name });
    router.reload({ only: ['collectionTree'] });
}

async function onImportFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    try {
        const collection = JSON.parse(await file.text());
        await api.post(importCollection.url(props.workspace.id), {
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
    <Head :title="workspace.name" />

    <div class="flex h-full min-h-0 flex-1 flex-col">
        <div class="flex items-center justify-between border-b px-3 py-2">
            <h1 class="text-sm font-semibold">{{ workspace.name }}</h1>
            <div class="flex items-center gap-2">
                <ShareDialog :workspace="workspace" :role="role" />
                <EnvironmentSwitcher
                    :workspace-id="workspace.id"
                    :environments="environments"
                />
                <HistoryPanel :history="history" />
            </div>
        </div>

        <ResizablePanelGroup direction="horizontal" class="min-h-0 flex-1">
            <ResizablePanel
                :default-size="20"
                :min-size="15"
                class="flex flex-col border-r"
            >
                <div class="flex items-center justify-between px-2 py-2">
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
                <div class="flex-1 overflow-y-auto px-1 pb-2">
                    <CollectionTree
                        v-for="node in collectionTree"
                        :key="node.id"
                        :node="node"
                        :workspace-id="workspace.id"
                        :active-request-id="store.activeTabId"
                        @open-request="openRequest"
                    />
                    <p
                        v-if="!collectionTree.length"
                        class="px-2 py-4 text-xs text-muted-foreground"
                    >
                        No collections yet.
                    </p>
                </div>
            </ResizablePanel>

            <ResizableHandle />

            <ResizablePanel :default-size="80">
                <div class="flex h-full min-h-0 flex-col">
                    <div
                        v-if="store.tabs.length"
                        class="flex items-center gap-0.5 overflow-x-auto border-b px-2"
                    >
                        <button
                            v-for="tab in store.tabs"
                            :key="tab.requestId"
                            class="group -mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-xs transition-colors"
                            :class="
                                cn(
                                    tab.requestId === store.activeTabId
                                        ? 'border-orange-500 font-medium text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )
                            "
                            @click="store.setActiveTab(tab.requestId)"
                        >
                            <span
                                class="font-mono text-[10px] font-semibold"
                                :class="methodColor[tab.draft.method]"
                                >{{ tab.draft.method }}</span
                            >
                            <span class="max-w-40 truncate">{{
                                tab.draft.name || 'Untitled'
                            }}</span>
                            <span
                                class="relative flex size-3.5 items-center justify-center"
                            >
                                <span
                                    v-if="tab.dirty"
                                    class="size-1.5 rounded-full bg-orange-500 group-hover:hidden"
                                />
                                <X
                                    class="hidden size-3.5 rounded-sm p-0.5 text-muted-foreground group-hover:block hover:bg-accent hover:text-foreground"
                                    @click.stop="store.closeTab(tab.requestId)"
                                />
                            </span>
                        </button>
                    </div>

                    <ResizablePanelGroup
                        direction="vertical"
                        class="min-h-0 flex-1"
                    >
                        <ResizablePanel :default-size="55" :min-size="20">
                            <RequestEditor />
                        </ResizablePanel>
                        <ResizableHandle with-handle />
                        <ResizablePanel :default-size="45" :min-size="15">
                            <ResponsePanel />
                        </ResizablePanel>
                    </ResizablePanelGroup>
                </div>
            </ResizablePanel>
        </ResizablePanelGroup>
    </div>
</template>
