import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { buildVariableScope, EMPTY_SCOPE } from '@/lib/variableScope';
import type { VariableScope } from '@/lib/variableScope';
import type {
    ApiRequest,
    CollectionNode,
    Environment,
    ExecutedResponse,
    WorkspaceVariable,
} from '@/types/workspace';

export type OpenTab = {
    requestId: number;
    draft: ApiRequest;
    /**
     * The body as raw text while it is being edited. It only folds back into
     * `draft.body` on save or send (a half-typed JSON body cannot be parsed),
     * so it has to live here rather than in the editor component: the editor
     * unmounts whenever the layout switches between the split and stacked
     * views — rotating a phone is enough — and text typed since the last save
     * would go with it. Keeping it per tab also means switching tabs no longer
     * discards it.
     */
    bodyText: string;
    dirty: boolean;
    response: ExecutedResponse | null;
    executing: boolean;
    saving: boolean;
};

/** Seeds {@link OpenTab.bodyText} from a request as loaded from the server. */
function bodyTextFor(request: ApiRequest): string {
    if (request.body_type === 'raw') {
        return request.body?.raw ?? '';
    }

    if (request.body_type === 'json') {
        return request.body?.json !== undefined
            ? JSON.stringify(request.body.json, null, 2)
            : '';
    }

    return '';
}

/**
 * Live "IDE-like" session state for a workspace: which environment is active and
 * which requests are open in tabs, each with its own unsaved draft and last
 * response. This is intentionally separate from Inertia page props — page props
 * carry the initial load, this store carries state that changes on every click
 * without a full page visit.
 */
export const useWorkspaceStore = defineStore('workspace', () => {
    const workspaceId = ref<number | null>(null);
    const activeEnvironmentId = ref<number | null>(null);
    const tabs = ref<OpenTab[]>([]);
    const activeTabId = ref<number | null>(null);
    const collectionTree = ref<CollectionNode[]>([]);
    const environments = ref<Environment[]>([]);
    const workspaceVariables = ref<WorkspaceVariable[]>([]);

    const activeTab = computed(
        () =>
            tabs.value.find((tab) => tab.requestId === activeTabId.value) ??
            null,
    );

    const activeEnvironment = computed(
        () =>
            environments.value.find(
                (environment) => environment.id === activeEnvironmentId.value,
            ) ?? null,
    );

    /**
     * What the active request resolves to at send time: the variable map with
     * each value's source, the inherited default headers and the inherited auth.
     * Drives every "what's inherited / what's set" affordance in the editor.
     */
    const activeScope = computed<VariableScope>(() => {
        const draft = activeTab.value?.draft;

        if (!draft) {
            return EMPTY_SCOPE;
        }

        return buildVariableScope(
            collectionTree.value,
            draft.collection_id,
            activeEnvironment.value,
            workspaceVariables.value,
        );
    });

    function setCollectionTree(tree: CollectionNode[]): void {
        collectionTree.value = tree;
    }

    function setEnvironments(next: Environment[]): void {
        environments.value = next;
    }

    function setWorkspaceVariables(next: WorkspaceVariable[]): void {
        workspaceVariables.value = next;
    }

    function setWorkspace(id: number, environmentId: number | null): void {
        if (workspaceId.value !== id) {
            tabs.value = [];
            activeTabId.value = null;
        }

        workspaceId.value = id;
        activeEnvironmentId.value = environmentId;
    }

    function setActiveEnvironment(environmentId: number | null): void {
        activeEnvironmentId.value = environmentId;
    }

    function openRequest(request: ApiRequest): void {
        const existing = tabs.value.find((tab) => tab.requestId === request.id);

        if (!existing) {
            tabs.value.push({
                requestId: request.id,
                draft: { ...request },
                bodyText: bodyTextFor(request),
                dirty: false,
                response: null,
                executing: false,
                saving: false,
            });
        }

        activeTabId.value = request.id;
    }

    function closeTab(requestId: number): void {
        const index = tabs.value.findIndex(
            (tab) => tab.requestId === requestId,
        );

        if (index === -1) {
            return;
        }

        tabs.value.splice(index, 1);

        if (activeTabId.value === requestId) {
            activeTabId.value = tabs.value.at(-1)?.requestId ?? null;
        }
    }

    function setActiveTab(requestId: number): void {
        if (tabs.value.some((tab) => tab.requestId === requestId)) {
            activeTabId.value = requestId;
        }
    }

    function updateDraft(requestId: number, patch: Partial<ApiRequest>): void {
        const tab = tabs.value.find((t) => t.requestId === requestId);

        if (!tab) {
            return;
        }

        Object.assign(tab.draft, patch);
        tab.dirty = true;
    }

    function setBodyText(requestId: number, bodyText: string): void {
        const tab = tabs.value.find((t) => t.requestId === requestId);

        if (tab) {
            tab.bodyText = bodyText;
        }
    }

    /**
     * Apply a change that has already been persisted elsewhere — renaming a
     * request from the collection tree, say — so an open tab follows along
     * without claiming it now has unsaved edits.
     */
    function patchSaved(requestId: number, patch: Partial<ApiRequest>): void {
        const tab = tabs.value.find((t) => t.requestId === requestId);

        if (tab) {
            Object.assign(tab.draft, patch);
        }
    }

    function markSaved(requestId: number, saved: ApiRequest): void {
        const tab = tabs.value.find((t) => t.requestId === requestId);

        if (!tab) {
            return;
        }

        tab.draft = { ...saved };
        tab.dirty = false;
    }

    function setExecuting(requestId: number, executing: boolean): void {
        const tab = tabs.value.find((t) => t.requestId === requestId);

        if (tab) {
            tab.executing = executing;
        }
    }

    function setSaving(requestId: number, saving: boolean): void {
        const tab = tabs.value.find((t) => t.requestId === requestId);

        if (tab) {
            tab.saving = saving;
        }
    }

    function setResponse(
        requestId: number,
        response: ExecutedResponse | null,
    ): void {
        const tab = tabs.value.find((t) => t.requestId === requestId);

        if (tab) {
            tab.response = response;
        }
    }

    return {
        workspaceId,
        activeEnvironmentId,
        tabs,
        activeTabId,
        collectionTree,
        environments,
        workspaceVariables,
        activeTab,
        activeEnvironment,
        activeScope,
        setWorkspace,
        setActiveEnvironment,
        setCollectionTree,
        setEnvironments,
        setWorkspaceVariables,
        openRequest,
        closeTab,
        setActiveTab,
        updateDraft,
        setBodyText,
        patchSaved,
        markSaved,
        setExecuting,
        setSaving,
        setResponse,
    };
});
