<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Settings2 } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    activate,
    store as storeEnvironment,
} from '@/actions/App/Http/Controllers/EnvironmentController';
import {
    destroy as destroyVariable,
    store as storeVariable,
    update as updateVariable,
} from '@/actions/App/Http/Controllers/EnvironmentVariableController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { api } from '@/lib/api';
import { promptDialog } from '@/lib/dialogs';
import { useWorkspaceStore } from '@/stores/workspace';
import type { Environment } from '@/types/workspace';

const props = defineProps<{
    workspaceId: number;
    environments: Environment[];
}>();

const store = useWorkspaceStore();
const dialogOpen = ref(false);
const managingId = ref<number | null>(null);

function currentEnvironment(): Environment | null {
    return props.environments.find((e) => e.id === managingId.value) ?? null;
}

async function onSwitch(value: string) {
    const id = Number(value);
    store.setActiveEnvironment(id);
    await api.post(activate.url(id));
    router.reload({ only: ['environments'] });
}

async function newEnvironment() {
    const name = await promptDialog({
        title: 'New environment',
        label: 'Environment name',
        defaultValue: 'New Environment',
        confirmText: 'Create',
    });

    if (!name) {
        return;
    }

    await api.post(storeEnvironment.url(props.workspaceId), { name });
    router.reload({ only: ['environments'] });
}

function openManage(id: number) {
    managingId.value = id;
    dialogOpen.value = true;
}

async function addVariable() {
    if (!managingId.value) {
        return;
    }

    await api.post(storeVariable.url(managingId.value), { key: '', value: '' });
    router.reload({ only: ['environments'] });
}

async function saveVariable(
    variableId: number,
    patch: { key?: string; value?: string; is_secret?: boolean },
) {
    try {
        await api.patch(updateVariable.url(variableId), patch);
    } catch {
        toast.error('Failed to save variable');
    }
}

async function removeVariable(variableId: number) {
    await api.delete(destroyVariable.url(variableId));
    router.reload({ only: ['environments'] });
}
</script>

<template>
    <div class="flex items-center gap-1">
        <Select
            :model-value="
                store.activeEnvironmentId
                    ? String(store.activeEnvironmentId)
                    : undefined
            "
            @update:model-value="(v) => onSwitch(String(v))"
        >
            <SelectTrigger class="w-48 text-xs">
                <SelectValue placeholder="No environment" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="env in environments"
                    :key="env.id"
                    :value="String(env.id)"
                    >{{ env.name }}</SelectItem
                >
            </SelectContent>
        </Select>

        <Dialog v-model:open="dialogOpen">
            <DialogTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon"
                    @click="
                        openManage(
                            store.activeEnvironmentId ?? environments[0]?.id,
                        )
                    "
                >
                    <Settings2 class="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Environments</DialogTitle>
                </DialogHeader>

                <div class="flex items-center gap-2">
                    <Select
                        :model-value="
                            managingId ? String(managingId) : undefined
                        "
                        @update:model-value="(v) => (managingId = Number(v))"
                    >
                        <SelectTrigger class="w-56">
                            <SelectValue placeholder="Select environment" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="env in environments"
                                :key="env.id"
                                :value="String(env.id)"
                                >{{ env.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <Button variant="outline" size="sm" @click="newEnvironment"
                        >New environment</Button
                    >
                </div>

                <div
                    v-if="currentEnvironment()"
                    class="flex max-h-80 flex-col gap-2 overflow-y-auto"
                >
                    <div
                        v-for="variable in currentEnvironment()!.variables"
                        :key="variable.id"
                        class="flex items-center gap-2"
                    >
                        <Input
                            :model-value="variable.key"
                            placeholder="Key"
                            class="font-mono text-sm"
                            @change="
                                (e: Event) =>
                                    saveVariable(variable.id, {
                                        key: (e.target as HTMLInputElement)
                                            .value,
                                    })
                            "
                        />
                        <Input
                            :model-value="variable.value ?? ''"
                            :type="variable.is_secret ? 'password' : 'text'"
                            placeholder="Value"
                            class="font-mono text-sm"
                            @change="
                                (e: Event) =>
                                    saveVariable(variable.id, {
                                        value: (e.target as HTMLInputElement)
                                            .value,
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
                                        saveVariable(variable.id, {
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
    </div>
</template>
