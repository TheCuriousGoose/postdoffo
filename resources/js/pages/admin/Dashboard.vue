<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    FolderTree,
    ShieldCheck,
    SquareStack,
    Users,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminStats, AdminUser } from '@/types';

defineProps<{
    stats: AdminStats;
    recentUsers: AdminUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin', href: dashboard() }],
    },
});

const statCards = (stats: AdminStats) => [
    { label: 'Users', value: stats.users, icon: Users },
    { label: 'Admins', value: stats.admins, icon: ShieldCheck },
    { label: 'Workspaces', value: stats.workspaces, icon: SquareStack },
    { label: 'Collections', value: stats.collections, icon: FolderTree },
];
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="space-y-8">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="card in statCards(stats)" :key="card.label">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        {{ card.label }}
                    </CardTitle>
                    <component
                        :is="card.icon"
                        class="size-4 text-muted-foreground"
                    />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-semibold">{{ card.value }}</div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="text-base">Recently joined</CardTitle>
                <Link
                    :href="usersIndex()"
                    class="text-sm text-muted-foreground hover:text-foreground"
                >
                    View all users
                </Link>
            </CardHeader>
            <CardContent class="divide-y divide-border">
                <div
                    v-for="user in recentUsers"
                    :key="user.id"
                    class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ user.name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ user.email }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <Badge v-if="user.role === 'admin'" variant="secondary">
                            Admin
                        </Badge>
                        <span class="text-xs text-muted-foreground">
                            {{ new Date(user.created_at).toLocaleDateString() }}
                        </span>
                    </div>
                </div>

                <p
                    v-if="!recentUsers.length"
                    class="py-3 text-sm text-muted-foreground"
                >
                    No users yet.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
