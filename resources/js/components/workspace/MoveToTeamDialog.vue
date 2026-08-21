<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { updateTeam } from '@/actions/App/Http/Controllers/WorkspaceController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Team } from '@/types/team';
import type { Workspace } from '@/types/workspace';

const props = defineProps<{
    open: boolean;
    workspace: Workspace | null;
    teams: Team[];
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const NONE = '__none__';
const selectedTeamId = ref<string>(NONE);
const saving = ref(false);

watch(
    () => props.workspace,
    (workspace) => {
        selectedTeamId.value = workspace?.team_id ?? NONE;
    },
    { immediate: true },
);

function save() {
    if (!props.workspace) {
        return;
    }

    saving.value = true;

    router.patch(
        updateTeam(props.workspace.id).url,
        { team_id: selectedTeamId.value === NONE ? null : selectedTeamId.value },
        {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle class="truncate">
                    Move "{{ workspace?.name }}"
                </DialogTitle>
                <DialogDescription>
                    Moving it into a team gives every member of that team
                    access. Moving it out only removes access that came
                    through the team — anyone invited directly keeps theirs.
                </DialogDescription>
            </DialogHeader>

            <Select v-model="selectedTeamId">
                <SelectTrigger class="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="NONE">No team (standalone)</SelectItem>
                    <SelectItem v-for="team in teams" :key="team.id" :value="team.id">
                        {{ team.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <DialogFooter>
                <Button :disabled="saving" @click="save">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
