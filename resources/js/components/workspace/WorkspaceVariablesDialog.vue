<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyVariable,
    store as storeVariable,
    update as updateVariable,
} from '@/actions/App/Http/Controllers/WorkspaceVariableController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { api } from '@/lib/api';
import { useWorkspaceStore } from '@/stores/workspace';
import type { WorkspaceVariable } from '@/types/workspace';

const props = defineProps<{
    workspaceId: number;
}>();

const store = useWorkspaceStore();

async function addVariable() {
    try {
        await api.post(storeVariable.url(props.workspaceId), {
            key: `new_variable_${Date.now()}`,
            value: '',
        });
        router.reload({ only: ['workspaceVariables'] });
    } catch {
        toast.error('Failed to add variable');
    }
}

/**
 * The row is patched locally as well as on the server: Checkbox is a controlled
 * component, so the Secret toggle renders whatever `variable.is_secret` says and
 * springs straight back to its old state on click unless the local row moves
 * with it. Value's password/text masking reads the same flag.
 */
async function saveVariable(
    variable: WorkspaceVariable,
    patch: { key?: string; value?: string; is_secret?: boolean },
) {
    const previous = { ...variable };

    Object.assign(variable, patch);

    try {
        await api.patch(updateVariable.url(variable.id), patch);
    } catch {
        Object.assign(variable, previous);
        toast.error('Failed to save variable');
    }
}

async function removeVariable(variableId: number) {
    await api.delete(destroyVariable.url(variableId));
    router.reload({ only: ['workspaceVariables'] });
}
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                title="Workspace globals"
                class="size-8"
            >
                <Building2 class="size-4" />
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Workspace globals</DialogTitle>
                <DialogDescription>
                    The base variable layer, applied to every request whatever
                    environment is active. A collection or environment variable
                    of the same name overrides these.
                </DialogDescription>
            </DialogHeader>

            <div class="flex max-h-80 flex-col gap-2 overflow-y-auto">
                <div
                    v-for="variable in store.workspaceVariables"
                    :key="variable.id"
                    class="flex items-center gap-2"
                >
                    <Input
                        :model-value="variable.key"
                        placeholder="Key"
                        autocomplete="off"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-bwignore="true"
                        data-form-type="other"
                        class="font-mono text-sm"
                        @change="
                            (e: Event) =>
                                saveVariable(variable, {
                                    key: (e.target as HTMLInputElement).value,
                                })
                        "
                    />
                    <Input
                        :model-value="variable.value ?? ''"
                        :type="variable.is_secret ? 'password' : 'text'"
                        placeholder="Value"
                        autocomplete="off"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-bwignore="true"
                        data-form-type="other"
                        class="font-mono text-sm"
                        @change="
                            (e: Event) =>
                                saveVariable(variable, {
                                    value: (e.target as HTMLInputElement).value,
                                })
                        "
                    />
                    <label
                        class="flex shrink-0 items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Checkbox
                            :model-value="variable.is_secret"
                            @update:model-value="
                                (v) =>
                                    saveVariable(variable, {
                                        is_secret: !!v,
                                    })
                            "
                        />
                        Secret
                    </label>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="removeVariable(variable.id)"
                        >Remove</Button
                    >
                </div>

                <p
                    v-if="!store.workspaceVariables.length"
                    class="px-1 py-2 text-xs text-muted-foreground"
                >
                    No workspace globals yet.
                </p>

                <Button
                    variant="outline"
                    size="sm"
                    class="w-fit"
                    @click="addVariable"
                    >Add variable</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
