<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Share2, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyMember,
    destroyInvitation,
    index as membersIndex,
    store as storeInvitation,
    updateRole,
} from '@/actions/App/Http/Controllers/WorkspaceMemberController';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { useInitials } from '@/composables/useInitials';
import { ApiError, api } from '@/lib/api';
import { confirmDialog } from '@/lib/dialogs';
import type {
    Workspace,
    WorkspaceInvitation,
    WorkspaceMember,
    WorkspaceMemberRole,
    WorkspaceRole,
} from '@/types/workspace';

const props = defineProps<{
    workspace: Workspace;
    role: WorkspaceRole | null;
}>();

const page = usePage<{ auth: { user: { id: number } } }>();
const currentUserId = computed(() => page.props.auth.user.id);
const { getInitials } = useInitials();

const open = ref(false);
const loading = ref(false);
const members = ref<WorkspaceMember[]>([]);
const invitations = ref<WorkspaceInvitation[]>([]);

const inviteEmail = ref('');
const inviteRole = ref<WorkspaceMemberRole>('editor');
const inviting = ref(false);
const inviteError = ref<string | null>(null);

const isOwner = computed(() => props.role === 'owner');

watch(open, (isOpen) => {
    if (isOpen) {
        loadMembers();
    } else {
        inviteEmail.value = '';
        inviteError.value = null;
    }
});

async function loadMembers() {
    loading.value = true;

    try {
        const data = await api.get<{
            members: WorkspaceMember[];
            invitations: WorkspaceInvitation[];
        }>(membersIndex.url(props.workspace.id));
        members.value = data.members;
        invitations.value = data.invitations;
    } catch {
        toast.error('Failed to load members');
    } finally {
        loading.value = false;
    }
}

function apiErrorMessage(error: unknown, fallback: string): string {
    if (error instanceof ApiError) {
        const body = error.body as {
            message?: string;
            errors?: Record<string, string[]>;
        } | null;

        return body?.errors?.email?.[0] ?? body?.message ?? fallback;
    }

    return fallback;
}

async function sendInvite() {
    if (!inviteEmail.value.trim()) {
        return;
    }

    inviting.value = true;
    inviteError.value = null;

    try {
        const data = await api.post<{
            members: WorkspaceMember[];
            invitations: WorkspaceInvitation[];
        }>(storeInvitation.url(props.workspace.id), {
            email: inviteEmail.value.trim(),
            role: inviteRole.value,
        });
        members.value = data.members;
        invitations.value = data.invitations;
        inviteEmail.value = '';
        toast.success('Invitation sent');
    } catch (error) {
        inviteError.value = apiErrorMessage(error, 'Failed to send invitation');
    } finally {
        inviting.value = false;
    }
}

async function changeRole(member: WorkspaceMember, role: string) {
    try {
        const data = await api.patch<{
            members: WorkspaceMember[];
            invitations: WorkspaceInvitation[];
        }>(
            updateRole.url({
                workspace: props.workspace.id,
                member: member.id,
            }),
            {
                role,
            },
        );
        members.value = data.members;
        invitations.value = data.invitations;
    } catch (error) {
        toast.error(apiErrorMessage(error, 'Failed to update role'));
    }
}

async function removeMember(member: WorkspaceMember) {
    const isSelf = member.id === currentUserId.value;

    const confirmed = await confirmDialog({
        title: isSelf ? 'Leave workspace?' : `Remove ${member.name}?`,
        description: isSelf
            ? `You will lose access to "${props.workspace.name}".`
            : `${member.name} will lose access to this workspace.`,
        confirmText: isSelf ? 'Leave' : 'Remove',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    try {
        await api.delete(
            destroyMember.url({
                workspace: props.workspace.id,
                member: member.id,
            }),
        );
        members.value = members.value.filter((m) => m.id !== member.id);

        if (isSelf) {
            open.value = false;
        }
    } catch (error) {
        toast.error(apiErrorMessage(error, 'Failed to remove member'));
    }
}

async function revokeInvitation(invitation: WorkspaceInvitation) {
    const confirmed = await confirmDialog({
        title: 'Revoke invitation?',
        description: `The invitation sent to ${invitation.email} will no longer be valid.`,
        confirmText: 'Revoke',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    try {
        await api.delete(
            destroyInvitation.url({
                workspace: props.workspace.id,
                invitation: invitation.id,
            }),
        );
        invitations.value = invitations.value.filter(
            (i) => i.id !== invitation.id,
        );
    } catch {
        toast.error('Failed to revoke invitation');
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">
                <Share2 class="size-3.5" />
                Share
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Share "{{ workspace.name }}"</DialogTitle>
                <DialogDescription>
                    Invite people to collaborate on this workspace.
                </DialogDescription>
            </DialogHeader>

            <form
                v-if="isOwner"
                class="flex items-start gap-2"
                @submit.prevent="sendInvite"
            >
                <div class="flex-1">
                    <Input
                        v-model="inviteEmail"
                        type="email"
                        placeholder="Email address"
                        :disabled="inviting"
                        required
                    />
                    <p v-if="inviteError" class="mt-1 text-xs text-destructive">
                        {{ inviteError }}
                    </p>
                </div>
                <Select v-model="inviteRole">
                    <SelectTrigger class="w-28">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="editor">Editor</SelectItem>
                        <SelectItem value="viewer">Viewer</SelectItem>
                    </SelectContent>
                </Select>
                <Button type="submit" :disabled="inviting">Invite</Button>
            </form>

            <div class="flex max-h-96 flex-col gap-4 overflow-y-auto">
                <div class="flex flex-col gap-1">
                    <span
                        v-if="loading"
                        class="py-4 text-center text-xs text-muted-foreground"
                        >Loading…</span
                    >
                    <div
                        v-for="member in members"
                        v-else
                        :key="member.id"
                        class="flex items-center gap-2 py-1.5"
                    >
                        <Avatar class="size-7">
                            <AvatarFallback class="text-xs">
                                {{ getInitials(member.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ member.name }}
                                <span
                                    v-if="member.id === currentUserId"
                                    class="text-xs font-normal text-muted-foreground"
                                    >(you)</span
                                >
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ member.email }}
                            </p>
                        </div>

                        <Badge
                            v-if="member.role === 'owner'"
                            variant="secondary"
                            >Owner</Badge
                        >
                        <Select
                            v-else-if="isOwner"
                            :model-value="member.role"
                            @update:model-value="
                                (v) => changeRole(member, String(v))
                            "
                        >
                            <SelectTrigger class="h-8 w-24 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="editor">Editor</SelectItem>
                                <SelectItem value="viewer">Viewer</SelectItem>
                            </SelectContent>
                        </Select>
                        <Badge v-else variant="secondary" class="capitalize">{{
                            member.role
                        }}</Badge>

                        <Button
                            v-if="
                                member.role !== 'owner' &&
                                (isOwner || member.id === currentUserId)
                            "
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :title="
                                member.id === currentUserId
                                    ? 'Leave workspace'
                                    : 'Remove member'
                            "
                            @click="removeMember(member)"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </div>
                </div>

                <div
                    v-if="isOwner && invitations.length"
                    class="flex flex-col gap-1 border-t pt-3"
                >
                    <span class="mb-1 text-xs font-medium text-muted-foreground"
                        >Pending invitations</span
                    >
                    <div
                        v-for="invitation in invitations"
                        :key="invitation.id"
                        class="flex items-center gap-2 py-1"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-muted-foreground">
                                {{ invitation.email }}
                            </p>
                        </div>
                        <Badge variant="outline" class="capitalize">{{
                            invitation.role
                        }}</Badge>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 text-xs"
                            @click="revokeInvitation(invitation)"
                            >Revoke</Button
                        >
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
