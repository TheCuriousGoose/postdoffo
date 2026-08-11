<script setup lang="ts">
import { router, Head } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import { store as storeRequest } from '@/actions/App/Http/Controllers/RequestController';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import CookieManagerDialog from '@/components/workspace/CookieManagerDialog.vue';
import EnvironmentSwitcher from '@/components/workspace/EnvironmentSwitcher.vue';
import HistoryPanel from '@/components/workspace/HistoryPanel.vue';
import RequestEditor from '@/components/workspace/RequestEditor.vue';
import ResponsePanel from '@/components/workspace/ResponsePanel.vue';
import ShareDialog from '@/components/workspace/ShareDialog.vue';
import WorkspaceVariablesDialog from '@/components/workspace/WorkspaceVariablesDialog.vue';
import { useOpenRequest } from '@/composables/useOpenRequest';
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
    WorkspaceVariable,
} from '@/types/workspace';

const props = defineProps<{
    workspace: Workspace;
    collectionTree: CollectionNode[];
    environments: Environment[];
    workspaceVariables: WorkspaceVariable[];
    history: RequestHistoryEntry[];
    role: 'owner' | 'editor' | 'viewer' | null;
}>();

const store = useWorkspaceStore();
const { openRequest } = useOpenRequest();

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

function onGlobalKeydown(event: KeyboardEvent) {
    if (!event.altKey || event.ctrlKey || event.metaKey) {
        return;
    }

    const key = event.key.toLowerCase();

    if (key === 'w') {
        if (store.activeTabId === null) {
            return;
        }

        event.preventDefault();
        store.closeTab(store.activeTabId);
    } else if (key === 't') {
        event.preventDefault();
        addRequestViaShortcut();
    }
}

// Alt+T: same "create a request" flow as a collection's own "New request"
// menu item, targeting the active tab's collection (so it lands next to what
// you're already looking at) or the first root collection otherwise.
async function addRequestViaShortcut() {
    const collectionId =
        store.activeTab?.draft.collection_id ??
        store.collectionTree[0]?.id ??
        null;

    if (collectionId === null) {
        toast.error('Create a collection first');

        return;
    }

    const name = await promptDialog({
        title: 'New request',
        label: 'Request name',
        defaultValue: 'New Request',
        confirmText: 'Create',
    });

    if (!name) {
        return;
    }

    const created = await api.post<ApiRequest>(storeRequest.url(collectionId), {
        name,
        method: 'GET',
        url: '',
    });
    router.reload({ only: ['collectionTree'] });
    await openRequest(created);
}

onMounted(() => window.addEventListener('keydown', onGlobalKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onGlobalKeydown));

watch(
    () => props.collectionTree,
    (tree) => store.setCollectionTree(tree),
    { immediate: true, deep: true },
);

watch(
    () => props.workspaceVariables,
    (variables) => store.setWorkspaceVariables(variables),
    { immediate: true, deep: true },
);

watch(
    () => props.environments,
    (environments) => {
        store.setEnvironments(environments);

        if (store.activeEnvironmentId == null) {
            const active = environments.find((e) => e.is_active);

            if (active) {
                store.setActiveEnvironment(active.id);
            }
        }
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <Head :title="workspace.name" />

    <div class="flex h-full min-h-0 flex-1 flex-col">
        <div class="flex items-center justify-between border-b px-3 py-2">
            <h1 class="text-sm font-semibold">{{ workspace.name }}</h1>
            <div class="flex items-center gap-2">
                <ShareDialog :workspace="workspace" :role="role" />
                <CookieManagerDialog :workspace-id="workspace.id" />
                <WorkspaceVariablesDialog :workspace-id="workspace.id" />
                <EnvironmentSwitcher
                    :workspace-id="workspace.id"
                    :environments="environments"
                />
                <HistoryPanel :history="history" />
            </div>
        </div>

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

        <ResizablePanelGroup direction="vertical" class="min-h-0 flex-1">
            <ResizablePanel :default-size="55" :min-size="20">
                <RequestEditor />
            </ResizablePanel>
            <ResizableHandle with-handle />
            <ResizablePanel :default-size="45" :min-size="15">
                <ResponsePanel />
            </ResizablePanel>
        </ResizablePanelGroup>
    </div>
</template>
