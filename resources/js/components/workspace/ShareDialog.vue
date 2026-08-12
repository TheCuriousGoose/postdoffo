<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Copy, Share2, X } from '@lucide/vue';
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
import {
    workspaceRoleDescriptions,
    workspaceRoleLabels,
} from '@/types/workspace';
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
// Co-owners share the owner's ability to hand out access — that is the whole
// point of the role — so everything that invites, re-roles or removes people is
// gated on this rather than on ownership.
const canManage = computed(
    () => props.role === 'owner' || props.role === 'co_owner',
);

const assignableRoles: WorkspaceMemberRole[] = ['co_owner', 'editor', 'viewer'];

/**
 * Only the owner can demote or remove a fellow co-owner — mirrors the guard in
 * WorkspaceMemberController, so a co-owner sees the control locked rather than
 * getting a 403 after the fact.
 */
function canManageMember(member: WorkspaceMember): boolean {
    if (member.role === 'owner') {
        return false;
    }

    return member.role === 'co_owner' ? isOwner.value : canManage.value;
}

function lockedReason(member: WorkspaceMember): string | undefined {
    return member.role === 'co_owner' && !isOwner.value
        ? 'Only the workspace owner can change a co-owner'
        : undefined;
}

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
        const email = inviteEmail.value.trim().toLowerCase();
        members.value = data.members;
        invitations.value = data.invitations;
        inviteEmail.value = '';

        // If a pending invitation was created (the invitee has no account yet),
        // copy its link straight away so it can be shared without waiting on
        // email. Existing users are added immediately and need no link.
        const pending = data.invitations.find(
            (i) => i.email.toLowerCase() === email,
        );

        if (pending) {
            await copyLink(
                pending,
                'Invitation created. Link copied — send it to them.',
            );
        } else {
            toast.success('Added to the workspace');
        }
    } catch (error) {
        inviteError.value = apiErrorMessage(error, 'Failed to send invitation');
    } finally {
        inviting.value = false;
    }
}

async function copyLink(
    invitation: WorkspaceInvitation,
    message = 'Invite link copied',
) {
    try {
        await navigator.clipboard.writeText(invitation.url);
        toast.success(message);
    } catch {
        toast.error('Could not copy the link');
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
                <DialogTitle class="truncate">
                    Share "{{ workspace.name }}"
                </DialogTitle>
                <DialogDescription>
                    Invite people by email, or copy an invite link to share
                    directly. They'll join with the role you pick.
                </DialogDescription>
            </DialogHeader>

            <!--
                Stacks on a phone: three controls side by side leaves the email
                field too narrow to read what you typed.
            -->
            <form
                v-if="canManage"
                class="flex flex-col gap-2 sm:flex-row sm:items-start"
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
                <div class="flex gap-2">
                    <Select v-model="inviteRole">
                        <SelectTrigger class="w-full sm:w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="value in assignableRoles"
                                :key="value"
                                :value="value"
                            >
                                {{ workspaceRoleLabels[value] }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit" :disabled="inviting">Invite</Button>
                </div>
            </form>

            <p
                v-if="canManage"
                class="-mt-2 text-xs text-muted-foreground"
                aria-live="polite"
            >
                {{ workspaceRoleDescriptions[inviteRole] }}
            </p>

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

                        <Select
                            v-if="canManageMember(member)"
                            :model-value="member.role"
                            @update:model-value="
                                (v) => changeRole(member, String(v))
                            "
                        >
                            <SelectTrigger class="h-8 w-28 shrink-0 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="value in assignableRoles"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ workspaceRoleLabels[value] }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Badge
                            v-else
                            variant="secondary"
                            class="shrink-0"
                            :title="lockedReason(member)"
                            >{{ workspaceRoleLabels[member.role] }}</Badge
                        >

                        <Button
                            v-if="
                                member.id === currentUserId
                                    ? member.role !== 'owner'
                                    : canManageMember(member)
                            "
                            variant="ghost"
                            size="icon"
                            class="size-7 shrink-0"
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
                    v-if="canManage && invitations.length"
                    class="flex flex-col gap-1 border-t pt-3"
                >
                    <span class="mb-1 text-xs font-medium text-muted-foreground"
                        >Pending invitations</span
                    >
                    <div
                        v-for="invitation in invitations"
                        :key="invitation.id"
                        class="flex items-center gap-1.5 py-1"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-muted-foreground">
                                {{ invitation.email }}
                            </p>
                        </div>
                        <Badge variant="outline" class="shrink-0">{{
                            workspaceRoleLabels[invitation.role]
                        }}</Badge>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 shrink-0"
                            title="Copy invite link"
                            @click="copyLink(invitation)"
                        >
                            <Copy class="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 shrink-0"
                            title="Revoke invitation"
                            @click="revokeInvitation(invitation)"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
