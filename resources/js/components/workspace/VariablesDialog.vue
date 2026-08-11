<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Check,
    Globe,
    Layers,
    MoreHorizontal,
    Plus,
    Building2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyEnvironment,
    activate as activateEnvironment,
    update as updateEnvironment,
} from '@/actions/App/Http/Controllers/EnvironmentController';
import {
    destroy as destroyEnvironmentVariable,
    store as storeEnvironmentVariable,
    update as updateEnvironmentVariable,
} from '@/actions/App/Http/Controllers/EnvironmentVariableController';
import {
    destroy as destroyWorkspaceVariable,
    store as storeWorkspaceVariable,
    update as updateWorkspaceVariable,
} from '@/actions/App/Http/Controllers/WorkspaceVariableController';
import { Button } from '@/components/ui/button';
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
import VariableTable from '@/components/workspace/VariableTable.vue';
import type { VariableRow } from '@/components/workspace/VariableTable.vue';
import { useCreateEnvironment } from '@/composables/useCreateEnvironment';
import { api } from '@/lib/api';
import { confirmDialog, promptDialog } from '@/lib/dialogs';
import { useWorkspaceStore } from '@/stores/workspace';

/**
 * Every variable layer in one place.
 *
 * These used to be two separate dialogs behind two separate toolbar buttons —
 * "Workspace globals" and "Environments" — each with its own table, and the
 * environments one carried a second environment picker of its own, so you could
 * be editing Staging while Production was the one actually in effect. Nothing
 * anywhere stated which layer beat which. Now the layers are the navigation,
 * the active one is marked as active, and a name defined twice says so on the
 * row itself.
 */
const props = defineProps<{
    workspaceId: number;
}>();

const open = defineModel<boolean>('open', { default: false });

const store = useWorkspaceStore();
const { createEnvironment } = useCreateEnvironment();

type Selection = { kind: 'globals' } | { kind: 'environment'; id: number };

const selection = ref<Selection>({ kind: 'globals' });

const environments = computed(() => store.environments);

const selectedEnvironment = computed(() => {
    const current = selection.value;

    return current.kind === 'environment'
        ? (environments.value.find((e) => e.id === current.id) ?? null)
        : null;
});

const activeEnvironment = computed(
    () => environments.value.find((e) => e.is_active) ?? null,
);

const rows = computed<VariableRow[]>(() => {
    if (selection.value.kind === 'globals') {
        return store.workspaceVariables.map((variable) => ({
            id: variable.id,
            key: variable.key,
            value: variable.value,
            is_secret: variable.is_secret,
        }));
    }

    return (selectedEnvironment.value?.variables ?? []).map((variable) => ({
        id: variable.id,
        key: variable.key,
        value: variable.value,
        is_secret: variable.is_secret,
    }));
});

/**
 * What a row on the current layer is actually worth at send time. Globals lose
 * to the active environment, and an environment that isn't active doesn't apply
 * at all — both are worth saying out loud on the row rather than leaving the
 * user to infer them from a resolved value somewhere else.
 */
const notes = computed<Record<string, string>>(() => {
    const result: Record<string, string> = {};

    if (selection.value.kind === 'globals') {
        for (const variable of activeEnvironment.value?.variables ?? []) {
            if (store.workspaceVariables.some((w) => w.key === variable.key)) {
                result[variable.key] =
                    `overridden by ${activeEnvironment.value?.name}`;
            }
        }

        return result;
    }

    for (const variable of selectedEnvironment.value?.variables ?? []) {
        if (store.workspaceVariables.some((w) => w.key === variable.key)) {
            result[variable.key] = 'overrides global';
        }
    }

    return result;
});

const isEditable = computed(
    () =>
        selection.value.kind === 'globals' ||
        selectedEnvironment.value !== null,
);

function reloadGlobals() {
    router.reload({ only: ['workspaceVariables'] });
}

function reloadEnvironments() {
    router.reload({ only: ['environments'] });
}

async function createVariable(draft: { key: string; value: string }) {
    try {
        if (selection.value.kind === 'globals') {
            await api.post(
                storeWorkspaceVariable.url(props.workspaceId),
                draft,
            );
            reloadGlobals();

            return;
        }

        if (!selectedEnvironment.value) {
            return;
        }

        await api.post(
            storeEnvironmentVariable.url(selectedEnvironment.value.id),
            draft,
        );
        reloadEnvironments();
    } catch {
        toast.error('Failed to add variable');
    }
}

async function patchVariable(
    row: VariableRow,
    patch: { key?: string; value?: string; is_secret?: boolean },
) {
    const url =
        selection.value.kind === 'globals'
            ? updateWorkspaceVariable.url(row.id)
            : updateEnvironmentVariable.url(row.id);

    try {
        await api.patch(url, patch);
    } catch {
        toast.error('Failed to save variable');
    } finally {
        // Reloading either way keeps the row showing what the server actually
        // stored, including after a failure that left the input out of step.
        if (selection.value.kind === 'globals') {
            reloadGlobals();
        } else {
            reloadEnvironments();
        }
    }
}

async function deleteVariable(row: VariableRow) {
    try {
        if (selection.value.kind === 'globals') {
            await api.delete(destroyWorkspaceVariable.url(row.id));
            reloadGlobals();

            return;
        }

        await api.delete(destroyEnvironmentVariable.url(row.id));
        reloadEnvironments();
    } catch {
        toast.error('Failed to delete variable');
    }
}

// Creating from here selects the new environment without activating it: this is
// the managing surface, so it shouldn't quietly change which values are live.
// The switcher's own "New environment" does activate, since that control is
// about what's in effect.
async function newEnvironment() {
    const id = await createEnvironment(props.workspaceId);

    if (id !== null) {
        selection.value = { kind: 'environment', id };
    }
}

async function renameEnvironment(id: number, currentName: string) {
    const name = await promptDialog({
        title: 'Rename environment',
        label: 'Environment name',
        defaultValue: currentName,
        confirmText: 'Rename',
    });

    if (!name || name === currentName) {
        return;
    }

    await api.patch(updateEnvironment.url(id), { name });
    reloadEnvironments();
}

async function removeEnvironment(id: number, name: string) {
    const confirmed = await confirmDialog({
        title: `Delete "${name}"?`,
        description:
            'Its variables go with it. Requests using them will fall back to the workspace globals, if there are any.',
        confirmText: 'Delete',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    await api.delete(destroyEnvironment.url(id));

    if (store.activeEnvironmentId === id) {
        store.setActiveEnvironment(null);
    }

    selection.value = { kind: 'globals' };
    reloadEnvironments();
}

async function activate(id: number) {
    store.setActiveEnvironment(id);
    await api.post(activateEnvironment.url(id));
    reloadEnvironments();
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="flex h-[42rem] max-h-[90vh] w-full gap-0 overflow-hidden p-0 sm:max-w-2xl lg:max-w-4xl xl:max-w-5xl"
        >
            <!-- layers -->
            <aside class="flex w-56 shrink-0 flex-col border-r bg-muted/30 p-3">
                <div class="px-2 pt-1 pb-3">
                    <DialogTitle class="text-sm font-semibold"
                        >Variables</DialogTitle
                    >
                    <DialogDescription class="text-xs">
                        Nearest layer wins: environment, then collection, then
                        global.
                    </DialogDescription>
                </div>

                <nav
                    class="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto"
                >
                    <button
                        type="button"
                        class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm transition"
                        :class="
                            selection.kind === 'globals'
                                ? 'bg-accent font-medium text-accent-foreground'
                                : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground'
                        "
                        @click="selection = { kind: 'globals' }"
                    >
                        <Building2 class="size-4 shrink-0" />
                        <span class="flex-1 truncate">Globals</span>
                        <span class="font-mono text-[10px]">{{
                            store.workspaceVariables.length || ''
                        }}</span>
                    </button>

                    <p
                        class="px-2.5 pt-4 pb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Environments
                    </p>

                    <button
                        v-for="environment in environments"
                        :key="environment.id"
                        type="button"
                        class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm transition"
                        :class="
                            selection.kind === 'environment' &&
                            selection.id === environment.id
                                ? 'bg-accent font-medium text-accent-foreground'
                                : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground'
                        "
                        @click="
                            selection = {
                                kind: 'environment',
                                id: environment.id,
                            }
                        "
                    >
                        <Globe class="size-4 shrink-0" />
                        <span class="flex-1 truncate">{{
                            environment.name
                        }}</span>
                        <Check
                            v-if="environment.is_active"
                            class="size-3.5 shrink-0 text-orange-500"
                        />
                        <span v-else class="font-mono text-[10px]">{{
                            environment.variables.length || ''
                        }}</span>
                    </button>

                    <p
                        v-if="!environments.length"
                        class="px-2.5 py-1 text-xs text-muted-foreground"
                    >
                        None yet.
                    </p>

                    <Button
                        variant="ghost"
                        size="sm"
                        class="mt-1 justify-start text-muted-foreground"
                        @click="newEnvironment"
                    >
                        <Plus class="size-3.5" />
                        New environment
                    </Button>
                </nav>

                <p
                    class="mt-3 flex items-start gap-2 border-t px-2.5 pt-3 text-[11px] text-muted-foreground"
                >
                    <Layers class="mt-px size-3.5 shrink-0" />
                    <span>
                        Collection variables sit between these two layers, and
                        are edited on the collection itself.
                    </span>
                </p>
            </aside>

            <!-- selected layer -->
            <div class="flex min-w-0 flex-1 flex-col">
                <!--
                    pr-12 keeps the actions menu clear of the dialog's own close
                    button, which floats over this row at top-4 right-4.
                -->
                <div
                    class="flex h-14 shrink-0 items-center gap-2 border-b pr-12 pl-6"
                >
                    <template v-if="selection.kind === 'globals'">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-sm font-semibold">
                                Globals
                            </h3>
                            <p class="truncate text-xs text-muted-foreground">
                                Applied to every request, whatever environment
                                is active.
                            </p>
                        </div>
                    </template>

                    <template v-else-if="selectedEnvironment">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-sm font-semibold">
                                {{ selectedEnvironment.name }}
                            </h3>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    selectedEnvironment.is_active
                                        ? 'Active — these values are the ones in effect.'
                                        : 'Not active, so these values are not applied yet.'
                                }}
                            </p>
                        </div>

                        <Button
                            v-if="!selectedEnvironment.is_active"
                            variant="outline"
                            size="sm"
                            @click="activate(selectedEnvironment.id)"
                        >
                            Activate
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    aria-label="Environment actions"
                                >
                                    <MoreHorizontal class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    @click="
                                        renameEnvironment(
                                            selectedEnvironment.id,
                                            selectedEnvironment.name,
                                        )
                                    "
                                >
                                    Rename
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    @click="
                                        removeEnvironment(
                                            selectedEnvironment.id,
                                            selectedEnvironment.name,
                                        )
                                    "
                                >
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-6">
                    <VariableTable
                        v-if="isEditable"
                        :rows="rows"
                        :notes="notes"
                        :empty-label="
                            selection.kind === 'globals'
                                ? 'No globals yet. Anything you add here is available to every request in the workspace.'
                                : 'No variables in this environment yet.'
                        "
                        @create="createVariable"
                        @update="patchVariable"
                        @remove="deleteVariable"
                    />

                    <p v-else class="text-sm text-muted-foreground">
                        Select a layer on the left.
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
