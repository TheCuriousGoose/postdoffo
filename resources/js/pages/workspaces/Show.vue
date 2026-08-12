<script setup lang="ts">
import { router, Head } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { store as storeRequest } from '@/actions/App/Http/Controllers/RequestController';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import CookieManagerDialog from '@/components/workspace/CookieManagerDialog.vue';
import EnvironmentSwitcher from '@/components/workspace/EnvironmentSwitcher.vue';
import HistoryPanel from '@/components/workspace/HistoryPanel.vue';
import RequestEditor from '@/components/workspace/RequestEditor.vue';
import ResponsePanel from '@/components/workspace/ResponsePanel.vue';
import ShareDialog from '@/components/workspace/ShareDialog.vue';
import { useOpenRequest } from '@/composables/useOpenRequest';
import { api } from '@/lib/api';
import { promptDialog } from '@/lib/dialogs';
import { methodColor } from '@/lib/http';
import { useWorkspaceStore } from '@/stores/workspace';
import type {
    ApiRequest,
    CollectionNode,
    Environment,
    RequestHistoryEntry,
    Workspace,
    WorkspaceRole,
    WorkspaceVariable,
} from '@/types/workspace';

const props = defineProps<{
    workspace: Workspace;
    collectionTree: CollectionNode[];
    environments: Environment[];
    workspaceVariables: WorkspaceVariable[];
    history: RequestHistoryEntry[];
    role: WorkspaceRole | null;
}>();

const store = useWorkspaceStore();
const { openRequest } = useOpenRequest();

/**
 * Below md there isn't the vertical room to stack a request editor and a
 * response on top of each other and leave either usable, so the two swap places
 * behind a switcher instead of sharing a draggable split.
 */
const isCompact = useMediaQuery('(max-width: 767px)');
const compactPane = ref<'request' | 'response'>('request');

// Sending is the one moment where you always want the other pane: bring the
// response forward as soon as it lands, the way the split view would have
// shown it without asking.
watch(
    () => store.activeTab?.response,
    (response) => {
        if (response && isCompact.value) {
            compactPane.value = 'response';
        }
    },
);

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
        <!--
            Tools are grouped by what they act on rather than lined up in the
            order they happened to be built: the environment (which changes what
            every request resolves to), then the workspace's own data, then
            sharing. Everything in here is on the h-8 chrome scale.
        -->
        <header class="flex h-10 shrink-0 items-center gap-2 border-b px-3">
            <SidebarTrigger class="-ml-1" />

            <h1 class="min-w-0 truncate text-sm font-semibold">
                {{ workspace.name }}
            </h1>

            <!--
                The dividers are grouping hints, not controls — they are the
                first thing to go when the row has to scroll on a phone.
            -->
            <div
                class="ml-auto flex min-w-0 items-center gap-1 overflow-x-auto"
            >
                <EnvironmentSwitcher
                    :workspace-id="workspace.id"
                    :environments="environments"
                />

                <Separator
                    orientation="vertical"
                    class="mx-1 !h-5 max-md:hidden"
                />

                <CookieManagerDialog :workspace-id="workspace.id" />
                <HistoryPanel :history="history" />

                <Separator
                    orientation="vertical"
                    class="mx-1 !h-5 max-md:hidden"
                />

                <ShareDialog :workspace="workspace" :role="role" />
            </div>
        </header>

        <!--
            Close is its own button rather than an icon nested inside the tab's
            button, so it is reachable by keyboard and announces itself; the
            unsaved dot and the close cross share one fixed-width slot so tabs
            don't resize under the pointer.
        -->
        <div
            v-if="store.tabs.length"
            class="flex h-9 shrink-0 items-stretch overflow-x-auto border-b px-2"
        >
            <div
                v-for="tab in store.tabs"
                :key="tab.requestId"
                class="group relative flex shrink-0 items-center pr-1 text-xs transition-colors"
                :class="
                    tab.requestId === store.activeTabId
                        ? 'font-medium text-foreground'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <button
                    type="button"
                    class="flex items-center gap-2 py-2 pr-1 pl-3"
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
                </button>

                <button
                    type="button"
                    class="flex size-4 shrink-0 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent hover:text-foreground"
                    :aria-label="`Close ${tab.draft.name || 'Untitled'}`"
                    @click="store.closeTab(tab.requestId)"
                >
                    <span
                        v-if="tab.dirty"
                        class="size-1.5 rounded-full bg-orange-500 group-hover:hidden"
                        :title="'Unsaved changes'"
                    />
                    <X class="hidden size-3 group-hover:block" />
                </button>

                <span
                    v-if="tab.requestId === store.activeTabId"
                    class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-orange-500"
                />
            </div>
        </div>

        <ResizablePanelGroup
            v-if="!isCompact"
            direction="vertical"
            class="min-h-0 flex-1"
        >
            <ResizablePanel :default-size="55" :min-size="20">
                <RequestEditor />
            </ResizablePanel>
            <!--
                The divider itself is the drag target, the way the sidebar's
                edge is, rather than a grip widget you have to aim at.
            -->
            <ResizableHandle />
            <ResizablePanel :default-size="45" :min-size="15">
                <ResponsePanel />
            </ResizablePanel>
        </ResizablePanelGroup>

        <!--
            Both panes stay mounted so switching between them never discards an
            in-progress body or a response you already have.
        -->
        <template v-else>
            <div class="flex shrink-0 border-b" role="tablist">
                <button
                    v-for="pane in ['request', 'response'] as const"
                    :key="pane"
                    type="button"
                    role="tab"
                    :aria-selected="compactPane === pane"
                    class="flex-1 border-b-2 py-2 text-xs font-medium capitalize transition-colors"
                    :class="
                        compactPane === pane
                            ? 'border-orange-500 text-foreground'
                            : 'border-transparent text-muted-foreground'
                    "
                    @click="compactPane = pane"
                >
                    {{ pane }}
                </button>
            </div>

            <div class="min-h-0 flex-1">
                <div v-show="compactPane === 'request'" class="h-full">
                    <RequestEditor />
                </div>
                <div v-show="compactPane === 'response'" class="h-full">
                    <ResponsePanel />
                </div>
            </div>
        </template>
    </div>
</template>
