<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronRight,
    Folder,
    FolderPlus,
    MoreHorizontal,
    Plus,
    Settings2,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import {
    store as storeCollection,
    update as updateCollection,
    destroy as destroyCollection,
} from '@/actions/App/Http/Controllers/CollectionController';
import {
    store as storeRequest,
    destroy as destroyRequest,
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { api } from '@/lib/api';
import { confirmDialog, promptDialog } from '@/lib/dialogs';
import { cn } from '@/lib/utils';
import type {
    ApiRequest,
    AuthType,
    CollectionNode,
    KeyValuePair,
    RequestAuth,
} from '@/types/workspace';
import AuthEditor from './AuthEditor.vue';
import KeyValueEditor from './KeyValueEditor.vue';

const props = defineProps<{
    node: CollectionNode;
    workspaceId: number;
    activeRequestId: number | null;
    depth?: number;
}>();

const emit = defineEmits<{
    'open-request': [ApiRequest];
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

async function removeRequest(request: ApiRequest) {
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

const settingsOpen = ref(false);
const settingsHeaders = ref<KeyValuePair[]>([]);
const settingsAuthType = ref<AuthType | null>(null);
const settingsAuth = ref<RequestAuth>(null);
const savingSettings = ref(false);

function openSettings() {
    settingsHeaders.value = props.node.headers ? [...props.node.headers] : [];
    settingsAuthType.value = props.node.auth_type;
    settingsAuth.value = props.node.auth;
    settingsOpen.value = true;
}

async function saveSettings() {
    savingSettings.value = true;

    try {
        await api.patch(updateCollection.url(props.node.id), {
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
            class="group flex items-center gap-1 rounded-md px-1 hover:bg-accent"
            :style="{ paddingLeft: `${(depth ?? 0) * 12}px` }"
        >
            <CollapsibleTrigger as-child>
                <button
                    class="flex flex-1 items-center gap-1.5 py-1 text-left text-sm"
                >
                    <ChevronRight
                        class="size-3.5 shrink-0 transition-transform"
                        :class="{ 'rotate-90': open }"
                    />
                    <Folder class="size-3.5 shrink-0 text-muted-foreground" />
                    <span class="truncate">{{ node.name }}</span>
                </button>
            </CollapsibleTrigger>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-6 shrink-0 opacity-0 group-hover:opacity-100"
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
                        <Settings2 class="size-3.5" /> Headers &amp; Auth
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
                class="group flex items-center gap-1 rounded-md px-1 hover:bg-accent"
                :style="{ paddingLeft: `${((depth ?? 0) + 1) * 12 + 18}px` }"
            >
                <button
                    class="flex flex-1 items-center gap-2 truncate py-1 text-left text-sm"
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
                    class="size-6 shrink-0 opacity-0 group-hover:opacity-100"
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
            />
        </CollapsibleContent>
    </Collapsible>

    <Dialog v-model:open="settingsOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ node.name }} — Headers &amp; Auth</DialogTitle>
            </DialogHeader>

            <Tabs default-value="headers">
                <TabsList>
                    <TabsTrigger value="headers">Default headers</TabsTrigger>
                    <TabsTrigger value="auth">Auth</TabsTrigger>
                </TabsList>

                <TabsContent value="headers" class="pt-3">
                    <p class="mb-2 text-xs text-muted-foreground">
                        Sent with every request in this collection and its
                        subfolders, unless overridden by a more specific header.
                    </p>
                    <KeyValueEditor v-model="settingsHeaders" />
                </TabsContent>

                <TabsContent value="auth" class="pt-3">
                    <AuthEditor
                        v-model:auth-type="settingsAuthType"
                        v-model:auth="settingsAuth"
                        inherit-label="Uses the auth configured on the parent collection, if any."
                    />
                </TabsContent>
            </Tabs>

            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="settingsOpen = false"
                    >Cancel</Button
                >
                <Button :disabled="savingSettings" @click="saveSettings"
                    >Save</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
