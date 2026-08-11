<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Braces,
    ChevronRight,
    Copy,
    Download,
    Folder,
    FolderPlus,
    Layers,
    MoreHorizontal,
    Play,
    Plus,
    Settings2,
    Share2,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    store as storeCollection,
    update as updateCollection,
    destroy as destroyCollection,
    download as downloadCollection,
    reorder as reorderCollections,
} from '@/actions/App/Http/Controllers/CollectionController';
import {
    store as storeRequest,
    update as updateRequest,
    destroy as destroyRequest,
    reorder as reorderRequests,
} from '@/actions/App/Http/Controllers/RequestController';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { api } from '@/lib/api';
import { confirmDialog, promptDialog } from '@/lib/dialogs';
import { draggedItem, reorderIds } from '@/lib/dragState';
import type { DraggedItem } from '@/lib/dragState';
import { cn } from '@/lib/utils';
import type {
    AuthType,
    CollectionNode,
    KeyValuePair,
    RequestAuth,
    RequestSummary,
} from '@/types/workspace';
import AuthEditor from './AuthEditor.vue';
import CollectionRunnerDialog from './CollectionRunnerDialog.vue';
import ExportDialog from './ExportDialog.vue';
import KeyValueEditor from './KeyValueEditor.vue';

const props = defineProps<{
    node: CollectionNode;
    workspaceId: number;
    activeRequestId: number | null;
    depth?: number;
}>();

const emit = defineEmits<{
    'open-request': [RequestSummary];
    /** A sibling folder was dropped onto another sibling folder — reordering
     * needs the parent, since it's the one holding the shared array. */
    'reorder-child': [
        draggedId: number,
        targetId: number,
        position: 'before' | 'after',
    ];
}>();

const open = ref(true);

function reload() {
    router.reload({ only: ['collectionTree'] });
}

async function addFolder() {
    const name = await promptDialog({
        title: 'New folder',
        label: 'Folder name',
        confirmText: 'Create',
    });

    if (!name) {
        return;
    }

    await api.post(storeCollection.url(props.workspaceId), {
        name,
        parent_id: props.node.id,
    });
    reload();
}

async function addRequest() {
    const name = await promptDialog({
        title: 'New request',
        label: 'Request name',
        defaultValue: 'New Request',
        confirmText: 'Create',
    });

    if (!name) {
        return;
    }

    await api.post(storeRequest.url(props.node.id), {
        name,
        method: 'GET',
        url: '',
    });
    reload();
}

// Native HTML5 drag-and-drop. `draggedItem` is a module-scoped singleton (see
// lib/dragState.ts) shared by every row in the tree, since a drop target can
// sit in a completely different subtree than the drag started in — dropping
// onto a folder's own row always moves the dragged item into that folder;
// dropping onto a sibling in the *same* list reorders in place instead.
//
// 'before'/'after' draw a thin insertion line matching exactly where the
// item will land (top or bottom half of the row the pointer is over); 'into'
// highlights the whole row to signal "this becomes its new parent" instead.
type DropIntent = 'before' | 'after' | 'into' | null;

/** Which half of `event`'s target the pointer is over, for insertion lines. */
function positionFromEvent(event: DragEvent): 'before' | 'after' {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();

    return event.clientY - rect.top < rect.height / 2 ? 'before' : 'after';
}

/**
 * Three-zone split for a folder row when the dragged folder is a sibling: the
 * top/bottom edges reorder (before/after), while the middle nests the sibling
 * *into* this folder. Without the middle zone two siblings — including two
 * root folders — could only ever be reordered, never nested.
 */
function zoneFromEvent(event: DragEvent): DropIntent {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const offset = event.clientY - rect.top;

    if (offset < rect.height * 0.25) {
        return 'before';
    }

    if (offset > rect.height * 0.75) {
        return 'after';
    }

    return 'into';
}

const isDragging = computed(
    () =>
        draggedItem.value?.type === 'collection' &&
        draggedItem.value.id === props.node.id,
);

const folderDropIntent = ref<DropIntent>(null);

function onFolderDragStart() {
    draggedItem.value = {
        type: 'collection',
        id: props.node.id,
        parentId: props.node.parent_id,
    };
}

function onFolderDragEnd() {
    draggedItem.value = null;
    folderDropIntent.value = null;
}

function onFolderDragOver(event: DragEvent) {
    const item = draggedItem.value;

    if (!item || (item.type === 'collection' && item.id === props.node.id)) {
        folderDropIntent.value = null;

        return;
    }

    folderDropIntent.value =
        item.type === 'collection' && item.parentId === props.node.parent_id
            ? zoneFromEvent(event)
            : 'into';
}

function onFolderDragLeave() {
    folderDropIntent.value = null;
}

async function onFolderDrop() {
    const intent = folderDropIntent.value;
    folderDropIntent.value = null;

    const item = draggedItem.value;

    if (!item || (item.type === 'collection' && item.id === props.node.id)) {
        return;
    }

    if (intent === 'before' || intent === 'after') {
        // Same sibling list as this row — the parent owns that array.
        emit('reorder-child', item.id, props.node.id, intent);

        return;
    }

    await moveInto(item);
}

/** Move a dragged folder or request so it becomes part of `props.node`. */
async function moveInto(item: DraggedItem) {
    if (item.type === 'collection') {
        if (item.id === props.node.id) {
            return;
        }

        await api.patch(updateCollection.url(item.id), {
            parent_id: props.node.id,
            order: props.node.children.length,
        });
    } else {
        await api.patch(updateRequest.url(item.id), {
            collection_id: props.node.id,
            order: props.node.requests.length,
        });
    }

    draggedItem.value = null;
    reload();
}

function onRequestDragStart(request: RequestSummary) {
    draggedItem.value = {
        type: 'request',
        id: request.id,
        collectionId: props.node.id,
    };
}

function onRequestDragEnd() {
    draggedItem.value = null;
    requestDropIntent.value = null;
}

const dragOverRequestId = ref<number | null>(null);
const requestDropIntent = ref<DropIntent>(null);

function onRequestDragOver(event: DragEvent, request: RequestSummary) {
    dragOverRequestId.value = request.id;

    const item = draggedItem.value;

    requestDropIntent.value =
        item?.type === 'request' &&
        item.id !== request.id &&
        item.collectionId === props.node.id
            ? positionFromEvent(event)
            : 'into';
}

async function onRequestDrop(target: RequestSummary) {
    dragOverRequestId.value = null;
    const intent = requestDropIntent.value;
    requestDropIntent.value = null;

    const item = draggedItem.value;

    if (!item) {
        return;
    }

    if (item.type === 'collection') {
        await moveInto(item);

        return;
    }

    if (item.id === target.id) {
        return;
    }

    if (item.collectionId !== props.node.id) {
        // Dropped a request from a different folder onto this one — move it
        // in rather than trying to splice it into an array it isn't part of.
        await moveInto(item);

        return;
    }

    const ordered = reorderIds(
        props.node.requests.map((r) => r.id),
        item.id,
        target.id,
        intent === 'after' ? 'after' : 'before',
    );

    draggedItem.value = null;
    await api.patch(reorderRequests.url(props.node.id), {
        ordered_ids: ordered,
    });
    reload();
}

async function onChildReorder(
    draggedId: number,
    targetId: number,
    position: 'before' | 'after',
) {
    if (draggedId === targetId) {
        return;
    }

    const ordered = reorderIds(
        props.node.children.map((c) => c.id),
        draggedId,
        targetId,
        position,
    );

    draggedItem.value = null;
    await api.patch(reorderCollections.url(props.workspaceId), {
        parent_id: props.node.id,
        ordered_ids: ordered,
    });
    reload();
}

async function rename() {
    const name = await promptDialog({
        title: 'Rename collection',
        label: 'Collection name',
        defaultValue: props.node.name,
        confirmText: 'Rename',
    });

    if (!name || name === props.node.name) {
        return;
    }

    await api.patch(updateCollection.url(props.node.id), { name });
    reload();
}

async function remove() {
    const confirmed = await confirmDialog({
        title: `Delete "${props.node.name}"?`,
        description:
            'This will also delete everything inside it. This cannot be undone.',
        confirmText: 'Delete',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    await api.delete(destroyCollection.url(props.node.id));
    reload();
}

async function removeRequest(request: RequestSummary) {
    const confirmed = await confirmDialog({
        title: `Delete request "${request.name}"?`,
        description: 'This cannot be undone.',
        confirmText: 'Delete',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    await api.delete(destroyRequest.url(request.id));
    reload();
}

type SettingsSection = 'general' | 'variables' | 'headers' | 'auth' | 'share';

const settingsSections: {
    id: SettingsSection;
    label: string;
    icon: typeof Settings2;
}[] = [
    { id: 'general', label: 'General', icon: Settings2 },
    { id: 'variables', label: 'Variables', icon: Braces },
    { id: 'headers', label: 'Headers', icon: Layers },
    { id: 'auth', label: 'Authorization', icon: ShieldCheck },
    { id: 'share', label: 'Share', icon: Share2 },
];

const settingsOpen = ref(false);
const exportOpen = ref(false);
const runOpen = ref(false);
const activeSection = ref<SettingsSection>('general');
const settingsName = ref('');
const settingsVariables = ref<KeyValuePair[]>([]);
const settingsHeaders = ref<KeyValuePair[]>([]);
const settingsAuthType = ref<AuthType | null>(null);
const settingsAuth = ref<RequestAuth>(null);
const savingSettings = ref(false);
const copying = ref(false);

function openSettings() {
    activeSection.value = 'general';
    settingsName.value = props.node.name;
    settingsVariables.value = Object.entries(props.node.variables ?? {}).map(
        ([key, value]) => ({ key, value: String(value ?? ''), enabled: true }),
    );
    settingsHeaders.value = props.node.headers ? [...props.node.headers] : [];
    settingsAuthType.value = props.node.auth_type;
    settingsAuth.value = props.node.auth;
    settingsOpen.value = true;
}

async function saveSettings() {
    savingSettings.value = true;

    // Rows back into the `{ key: value }` shape the backend stores, dropping
    // blank keys and keeping the last value when a key is repeated.
    const variables: Record<string, string> = {};

    for (const row of settingsVariables.value) {
        if (row.key.trim() !== '') {
            variables[row.key.trim()] = row.value;
        }
    }

    const name = settingsName.value.trim();

    try {
        await api.patch(updateCollection.url(props.node.id), {
            name: name || props.node.name,
            variables: Object.keys(variables).length ? variables : null,
            headers: settingsHeaders.value.length
                ? settingsHeaders.value
                : null,
            auth_type: settingsAuthType.value,
            auth: settingsAuth.value,
        });
        settingsOpen.value = false;
        reload();
    } finally {
        savingSettings.value = false;
    }
}

async function copyExport() {
    copying.value = true;

    try {
        const data = await api.get<Record<string, unknown>>(
            downloadCollection.url(props.node.id),
        );
        await navigator.clipboard.writeText(JSON.stringify(data, null, 2));
        toast.success('Collection JSON copied to clipboard');
    } catch {
        toast.error('Failed to copy collection');
    } finally {
        copying.value = false;
    }
}

// Kept out of the template so the inner `}}` doesn't close Vue's interpolation.
const variableExample = '{{key}}';

// The number of collection-level settings that carry a value, for the sidebar.
const configuredCount = computed(() => ({
    variables: settingsVariables.value.filter((v) => v.key.trim() !== '')
        .length,
    headers: settingsHeaders.value.filter((h) => h.key.trim() !== '').length,
    auth: settingsAuthType.value && settingsAuthType.value !== 'none' ? 1 : 0,
}));

const methodColor: Record<string, string> = {
    GET: 'text-blue-600 dark:text-blue-400',
    POST: 'text-green-600 dark:text-green-400',
    PUT: 'text-amber-600 dark:text-amber-400',
    PATCH: 'text-amber-600 dark:text-amber-400',
    DELETE: 'text-red-600 dark:text-red-400',
    HEAD: 'text-muted-foreground',
    OPTIONS: 'text-muted-foreground',
};
</script>

<template>
    <Collapsible v-model:open="open">
        <div
            draggable="true"
            class="group/row relative flex cursor-grab items-center gap-1 rounded-md px-1 hover:bg-accent active:cursor-grabbing"
            :class="
                cn(
                    isDragging && 'opacity-40',
                    !isDragging &&
                        folderDropIntent === 'into' &&
                        'bg-primary/10 ring-2 ring-primary ring-inset',
                )
            "
            :style="{ paddingLeft: `${(depth ?? 0) * 12}px` }"
            @dragstart="onFolderDragStart"
            @dragend="onFolderDragEnd"
            @dragover.prevent.stop="onFolderDragOver"
            @dragleave="onFolderDragLeave"
            @drop.prevent.stop="onFolderDrop"
        >
            <div
                v-if="folderDropIntent === 'before'"
                class="absolute inset-x-1 top-0 z-10 h-0.5 -translate-y-1/2 rounded-full bg-primary"
            />
            <div
                v-if="folderDropIntent === 'after'"
                class="absolute inset-x-1 bottom-0 z-10 h-0.5 translate-y-1/2 rounded-full bg-primary"
            />

            <CollapsibleTrigger as-child>
                <button
                    class="flex min-w-0 flex-1 items-center gap-1.5 py-1 text-left text-sm"
                >
                    <ChevronRight
                        class="size-3.5 shrink-0 transition-transform"
                        :class="{ 'rotate-90': open }"
                    />
                    <Folder
                        class="size-3.5 shrink-0"
                        :class="
                            folderDropIntent === 'into'
                                ? 'text-primary'
                                : 'text-muted-foreground'
                        "
                    />
                    <span class="truncate">{{ node.name }}</span>
                </button>
            </CollapsibleTrigger>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-6 shrink-0 opacity-0 group-hover/row:opacity-100"
                    >
                        <MoreHorizontal class="size-3.5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem @click="addRequest">
                        <Plus class="size-3.5" /> New request
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="addFolder">
                        <FolderPlus class="size-3.5" /> New folder
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="rename">Rename</DropdownMenuItem>
                    <DropdownMenuItem @click="openSettings">
                        <Settings2 class="size-3.5" /> Settings
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="runOpen = true">
                        <Play class="size-3.5" /> Run collection
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="exportOpen = true">
                        <Download class="size-3.5" /> Export
                    </DropdownMenuItem>
                    <DropdownMenuItem variant="destructive" @click="remove">
                        <Trash2 class="size-3.5" /> Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <CollapsibleContent>
            <div
                v-for="request in node.requests"
                :key="request.id"
                draggable="true"
                class="group/row relative flex cursor-grab items-center gap-1 rounded-md px-1 hover:bg-accent active:cursor-grabbing"
                :class="
                    cn(
                        draggedItem?.type === 'request' &&
                            draggedItem.id === request.id &&
                            'opacity-40',
                        dragOverRequestId === request.id &&
                            requestDropIntent === 'into' &&
                            'bg-primary/10 ring-2 ring-primary ring-inset',
                    )
                "
                :style="{ paddingLeft: `${((depth ?? 0) + 1) * 12 + 18}px` }"
                @dragstart="onRequestDragStart(request)"
                @dragend="onRequestDragEnd"
                @dragover.prevent.stop="onRequestDragOver($event, request)"
                @dragleave="
                    dragOverRequestId === request.id &&
                    ((dragOverRequestId = null), (requestDropIntent = null))
                "
                @drop.prevent.stop="onRequestDrop(request)"
            >
                <div
                    v-if="
                        dragOverRequestId === request.id &&
                        requestDropIntent === 'before'
                    "
                    class="absolute inset-x-1 top-0 z-10 h-0.5 -translate-y-1/2 rounded-full bg-primary"
                />
                <div
                    v-if="
                        dragOverRequestId === request.id &&
                        requestDropIntent === 'after'
                    "
                    class="absolute inset-x-1 bottom-0 z-10 h-0.5 translate-y-1/2 rounded-full bg-primary"
                />

                <button
                    class="flex min-w-0 flex-1 items-center gap-2 py-1 text-left text-sm"
                    :class="
                        cn(
                            activeRequestId === request.id &&
                                'font-medium text-foreground',
                            activeRequestId !== request.id &&
                                'text-muted-foreground',
                        )
                    "
                    @click="emit('open-request', request)"
                >
                    <span
                        class="w-12 shrink-0 text-[10px] font-semibold"
                        :class="methodColor[request.method] ?? ''"
                        >{{ request.method }}</span
                    >
                    <span class="truncate">{{ request.name }}</span>
                </button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-6 shrink-0 opacity-0 group-hover/row:opacity-100"
                    @click="removeRequest(request)"
                >
                    <Trash2 class="size-3.5" />
                </Button>
            </div>

            <CollectionTree
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :workspace-id="workspaceId"
                :active-request-id="activeRequestId"
                :depth="(depth ?? 0) + 1"
                @open-request="(r) => emit('open-request', r)"
                @reorder-child="onChildReorder"
            />
        </CollapsibleContent>
    </Collapsible>

    <Dialog v-model:open="settingsOpen">
        <DialogContent
            class="flex h-[34rem] max-h-[85vh] w-full gap-0 overflow-hidden p-0 sm:max-w-3xl"
        >
            <!-- sidebar -->
            <aside class="flex w-52 shrink-0 flex-col border-r bg-muted/30 p-3">
                <div class="px-2 pt-1 pb-3">
                    <DialogTitle class="truncate text-sm font-semibold">{{
                        node.name
                    }}</DialogTitle>
                    <DialogDescription class="text-xs">
                        Collection settings
                    </DialogDescription>
                </div>
                <nav class="flex flex-col gap-0.5">
                    <button
                        v-for="section in settingsSections"
                        :key="section.id"
                        type="button"
                        class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm transition"
                        :class="
                            activeSection === section.id
                                ? 'bg-accent font-medium text-accent-foreground'
                                : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground'
                        "
                        @click="activeSection = section.id"
                    >
                        <component :is="section.icon" class="size-4 shrink-0" />
                        <span class="flex-1">{{ section.label }}</span>
                        <span
                            v-if="
                                section.id === 'variables' &&
                                configuredCount.variables
                            "
                            class="font-mono text-[10px] text-muted-foreground"
                            >{{ configuredCount.variables }}</span
                        >
                        <span
                            v-else-if="
                                section.id === 'headers' &&
                                configuredCount.headers
                            "
                            class="font-mono text-[10px] text-muted-foreground"
                            >{{ configuredCount.headers }}</span
                        >
                        <span
                            v-else-if="
                                section.id === 'auth' && configuredCount.auth
                            "
                            class="size-1.5 rounded-full bg-orange-500"
                        />
                    </button>
                </nav>
            </aside>

            <!-- content -->
            <div class="flex min-w-0 flex-1 flex-col">
                <div class="min-h-0 flex-1 overflow-y-auto p-6">
                    <template v-if="activeSection === 'general'">
                        <h3 class="text-sm font-semibold">General</h3>
                        <div class="mt-4 grid max-w-sm gap-2">
                            <label
                                for="collection-name"
                                class="text-sm font-medium"
                                >Name</label
                            >
                            <Input
                                id="collection-name"
                                v-model="settingsName"
                                autocomplete="off"
                            />
                        </div>
                    </template>

                    <template v-else-if="activeSection === 'variables'">
                        <h3 class="text-sm font-semibold">Variables</h3>
                        <p class="mt-1 mb-4 text-xs text-muted-foreground">
                            Reference these anywhere as
                            <code
                                class="rounded bg-muted px-1 font-mono text-orange-600 dark:text-orange-400"
                                >{{ variableExample }}</code
                            >. An active environment's variable of the same name
                            takes precedence.
                        </p>
                        <KeyValueEditor
                            v-model="settingsVariables"
                            key-placeholder="Variable"
                        />
                    </template>

                    <template v-else-if="activeSection === 'headers'">
                        <h3 class="text-sm font-semibold">Default headers</h3>
                        <p class="mt-1 mb-4 text-xs text-muted-foreground">
                            Sent with every request in this collection and its
                            subfolders, unless a request sets the same header.
                        </p>
                        <KeyValueEditor
                            v-model="settingsHeaders"
                            key-placeholder="Header"
                        />
                    </template>

                    <template v-else-if="activeSection === 'auth'">
                        <h3 class="mb-4 text-sm font-semibold">
                            Authorization
                        </h3>
                        <AuthEditor
                            v-model:auth-type="settingsAuthType"
                            v-model:auth="settingsAuth"
                            inherit-label="Uses the auth configured on the parent collection, if any."
                        />
                    </template>

                    <template v-else-if="activeSection === 'share'">
                        <h3 class="text-sm font-semibold">
                            Share this collection
                        </h3>
                        <p class="mt-1 mb-4 text-xs text-muted-foreground">
                            Export the whole tree as a Postman collection or an
                            OpenAPI spec, or copy the Postman JSON straight to
                            your clipboard.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                @click="exportOpen = true"
                            >
                                <Download class="size-4" />
                                Export…
                            </Button>
                            <Button
                                variant="outline"
                                :disabled="copying"
                                @click="copyExport"
                            >
                                <Copy class="size-4" />
                                Copy to clipboard
                            </Button>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end gap-2 border-t p-3">
                    <Button variant="ghost" @click="settingsOpen = false"
                        >Cancel</Button
                    >
                    <Button :disabled="savingSettings" @click="saveSettings"
                        >Save changes</Button
                    >
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <ExportDialog
        v-model:open="exportOpen"
        :collection-id="node.id"
        :collection-name="node.name"
    />

    <CollectionRunnerDialog v-model:open="runOpen" :node="node" />
</template>
