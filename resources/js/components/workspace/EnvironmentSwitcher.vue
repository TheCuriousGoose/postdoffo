<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, SlidersHorizontal } from '@lucide/vue';
import { ref } from 'vue';
import { activate } from '@/actions/App/Http/Controllers/EnvironmentController';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ToolbarButton from '@/components/workspace/ToolbarButton.vue';
import VariablesDialog from '@/components/workspace/VariablesDialog.vue';
import { useCreateEnvironment } from '@/composables/useCreateEnvironment';
import { api } from '@/lib/api';
import { useWorkspaceStore } from '@/stores/workspace';
import type { Environment } from '@/types/workspace';

/**
 * Picking which environment is in effect, and the way into everything else
 * about variables. Editing used to live behind a second dialog here with its
 * own environment picker; it now opens the one manager, so switching and
 * editing can't disagree about which environment they mean.
 */
const props = defineProps<{
    workspaceId: number;
    environments: Environment[];
}>();

const store = useWorkspaceStore();
const { createEnvironment } = useCreateEnvironment();
const managerOpen = ref(false);

/**
 * Creating from inside the list is a select value rather than a button in the
 * dropdown's footer: the popup owns pointer handling for everything inside it,
 * and an item is the one shape it reliably delivers a click for.
 */
const NEW_ENVIRONMENT = '__new_environment__';

async function onSelect(value: string) {
    if (value === NEW_ENVIRONMENT) {
        const id = await createEnvironment(props.workspaceId);

        // Created from the "which environment is active" control, so it becomes
        // the active one — picking it from this list is the whole point.
        if (id !== null) {
            await switchTo(id);
        }

        return;
    }

    await switchTo(Number(value));
}

async function switchTo(id: number) {
    store.setActiveEnvironment(id);
    await api.post(activate.url(id));
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
            @update:model-value="(v) => onSelect(String(v))"
        >
            <SelectTrigger size="sm" class="w-44 text-xs">
                <SelectValue
                    :placeholder="
                        environments.length
                            ? 'No environment'
                            : 'No environments yet'
                    "
                />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="env in environments"
                    :key="env.id"
                    :value="String(env.id)"
                    >{{ env.name }}</SelectItem
                >

                <SelectSeparator v-if="environments.length" />

                <SelectItem
                    :value="NEW_ENVIRONMENT"
                    class="text-muted-foreground"
                >
                    <Plus class="size-3.5" />
                    New environment
                </SelectItem>
            </SelectContent>
        </Select>

        <ToolbarButton label="Manage variables" @click="managerOpen = true">
            <SlidersHorizontal class="size-4" />
        </ToolbarButton>

        <VariablesDialog
            v-model:open="managerOpen"
            :workspace-id="workspaceId"
        />
    </div>
</template>
