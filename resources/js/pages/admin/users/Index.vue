<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { MoreHorizontal, ShieldCheck, ShieldOff, Trash2 } from '@lucide/vue';
import {
    destroy,
    updateRole,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { confirmDialog } from '@/lib/dialogs';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/users';
import type { AdminUser } from '@/types';

defineProps<{
    users: AdminUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Users', href: index() },
        ],
    },
});

const page = usePage();
const currentUserId = page.props.auth.user?.id ?? null;

async function toggleRole(user: AdminUser) {
    const nextRole = user.role === 'admin' ? 'user' : 'admin';

    const confirmed = await confirmDialog({
        title:
            nextRole === 'admin'
                ? `Make "${user.name}" an admin?`
                : `Remove admin access from "${user.name}"?`,
        description:
            nextRole === 'admin'
                ? 'They will be able to manage every user and workspace.'
                : 'They will lose access to the admin panel.',
        confirmText: nextRole === 'admin' ? 'Make admin' : 'Remove admin',
    });

    if (!confirmed) {
        return;
    }

    router.patch(
        updateRole(user.id).url,
        { role: nextRole },
        { preserveScroll: true },
    );
}

async function deleteUser(user: AdminUser) {
    const confirmed = await confirmDialog({
        title: `Delete "${user.name}"?`,
        description:
            'This permanently deletes the user and every workspace they own, along with its collections and requests. This cannot be undone.',
        confirmText: 'Delete user',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy(user.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin - Users" />

    <div class="rounded-lg border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>User</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead>Verified</TableHead>
                    <TableHead>Workspaces</TableHead>
                    <TableHead>Joined</TableHead>
                    <TableHead class="w-10" />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="user in users" :key="user.id">
                    <TableCell>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ user.name }}
                                <span
                                    v-if="user.id === currentUserId"
                                    class="text-xs text-muted-foreground"
                                >
                                    (you)
                                </span>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ user.email }}
                            </p>
                        </div>
                    </TableCell>
                    <TableCell>
                        <Badge
                            :variant="
                                user.role === 'admin' ? 'secondary' : 'outline'
                            "
                        >
                            {{ user.role === 'admin' ? 'Admin' : 'User' }}
                        </Badge>
                    </TableCell>
                    <TableCell class="text-sm text-muted-foreground">
                        {{ user.email_verified_at ? 'Yes' : 'No' }}
                    </TableCell>
                    <TableCell class="text-sm text-muted-foreground">
                        {{ user.owned_workspaces_count }}
                    </TableCell>
                    <TableCell class="text-sm text-muted-foreground">
                        {{ new Date(user.created_at).toLocaleDateString() }}
                    </TableCell>
                    <TableCell>
                        <DropdownMenu v-if="user.id !== currentUserId">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    aria-label="User options"
                                >
                                    <MoreHorizontal class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="toggleRole(user)">
                                    <component
                                        :is="
                                            user.role === 'admin'
                                                ? ShieldOff
                                                : ShieldCheck
                                        "
                                        class="size-3.5"
                                    />
                                    {{
                                        user.role === 'admin'
                                            ? 'Remove admin'
                                            : 'Make admin'
                                    }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    @click="deleteUser(user)"
                                >
                                    <Trash2 class="size-3.5" /> Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </TableCell>
                </TableRow>

                <TableRow v-if="!users.length">
                    <TableCell
                        colspan="6"
                        class="text-center text-sm text-muted-foreground"
                    >
                        No users yet.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
