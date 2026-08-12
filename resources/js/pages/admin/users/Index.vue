<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    MoreHorizontal,
    Search,
    ShieldCheck,
    ShieldOff,
    Trash2,
    Users as UsersIcon,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref, watch } from 'vue';
import {
    destroy,
    updateRole,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import DataPagination from '@/components/admin/DataPagination.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useInitials } from '@/composables/useInitials';
import { confirmDialog } from '@/lib/dialogs';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/users';
import type { AdminUser, Paginated } from '@/types';

const props = defineProps<{
    users: Paginated<AdminUser>;
    filters: {
        search: string;
        role: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Users', href: index() },
        ],
    },
});

const { getInitials } = useInitials();
const page = usePage();
const currentUserId = page.props.auth.user?.id ?? null;

const search = ref(props.filters.search);
const role = ref(props.filters.role || 'all');

function visit(pageNumber?: number) {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            role: role.value !== 'all' ? role.value : undefined,
            page: pageNumber,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters'],
        },
    );
}

// Debounce keystrokes so we navigate once the user pauses, not on every letter.
watchDebounced(search, () => visit(), { debounce: 300 });
watch(role, () => visit());

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

    <Card class="gap-0 py-0">
        <CardHeader
            class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <CardTitle class="text-base">Users</CardTitle>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ users.total }}
                    {{ users.total === 1 ? 'person has' : 'people have' }} an
                    account.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search users…"
                        class="w-full pl-8 sm:w-56"
                    />
                </div>
                <Select v-model="role">
                    <SelectTrigger class="w-32">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All roles</SelectItem>
                        <SelectItem value="admin">Admins</SelectItem>
                        <SelectItem value="user">Users</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </CardHeader>

        <Separator />

        <CardContent class="px-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <!--
                            Who they are and what they can do carry the row; the
                            supporting columns drop away on narrow screens rather
                            than pushing the name and the actions menu off into a
                            sideways scroll.
                        -->
                        <TableHead class="pl-6">User</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Verified</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Workspaces</TableHead
                        >
                        <TableHead class="hidden sm:table-cell"
                            >Joined</TableHead
                        >
                        <TableHead class="w-10 pr-6" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in users.data" :key="user.id">
                        <TableCell class="pl-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <Avatar class="size-8">
                                    <AvatarFallback class="text-xs font-medium">
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
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
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ user.email }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="
                                    user.role === 'admin'
                                        ? 'secondary'
                                        : 'outline'
                                "
                            >
                                {{ user.role === 'admin' ? 'Admin' : 'User' }}
                            </Badge>
                        </TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground md:table-cell"
                        >
                            {{ user.email_verified_at ? 'Yes' : 'No' }}
                        </TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground lg:table-cell"
                        >
                            {{ user.owned_workspaces_count }}
                        </TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground sm:table-cell"
                        >
                            {{ new Date(user.created_at).toLocaleDateString() }}
                        </TableCell>
                        <TableCell class="pr-6">
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
                </TableBody>
            </Table>

            <div
                v-if="!users.data.length"
                class="flex flex-col items-center gap-2 py-16 text-center"
            >
                <UsersIcon class="size-8 text-muted-foreground/50" />
                <p class="text-sm text-muted-foreground">
                    {{
                        filters.search || filters.role !== 'all'
                            ? 'No users match your filters.'
                            : 'No users yet.'
                    }}
                </p>
            </div>
        </CardContent>

        <template v-if="users.data.length">
            <Separator />
            <DataPagination :meta="users" @update:page="visit" />
        </template>
    </Card>
</template>
