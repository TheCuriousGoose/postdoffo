<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    FolderTree,
    SendHorizontal,
    ShieldCheck,
    SquareStack,
    TrendingUp,
    Users,
} from '@lucide/vue';
import AreaChart from '@/components/admin/AreaChart.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminStatKey, AdminStats, AdminUser, ChartPoint } from '@/types';

const props = defineProps<{
    stats: AdminStats;
    userGrowth: ChartPoint[];
    requestActivity: ChartPoint[];
    recentUsers: AdminUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin', href: dashboard() }],
    },
});

const { getInitials } = useInitials();

const statCards: {
    label: string;
    key: AdminStatKey;
    icon: typeof Users;
    tint: string;
}[] = [
    {
        label: 'Users',
        key: 'users',
        icon: Users,
        tint: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    },
    {
        label: 'Admins',
        key: 'admins',
        icon: ShieldCheck,
        tint: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    },
    {
        label: 'Workspaces',
        key: 'workspaces',
        icon: SquareStack,
        tint: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    },
    {
        label: 'Collections',
        key: 'collections',
        icon: FolderTree,
        tint: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    },
    {
        label: 'Requests',
        key: 'requests',
        icon: SendHorizontal,
        tint: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    },
];

const requestsThisWeek = props.stats.requests.delta ?? 0;
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card v-for="card in statCards" :key="card.label" class="gap-0">
                <CardHeader class="flex flex-row items-center justify-between">
                    <span class="text-sm text-muted-foreground">{{
                        card.label
                    }}</span>
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                        :class="card.tint"
                    >
                        <component :is="card.icon" class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-semibold tabular-nums">
                        {{ stats[card.key].total.toLocaleString() }}
                    </div>
                    <div class="mt-1 flex h-4 items-center">
                        <span
                            v-if="(stats[card.key].delta ?? 0) > 0"
                            class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            <TrendingUp class="size-3" />
                            +{{ stats[card.key].delta }}
                            <span class="text-muted-foreground">this week</span>
                        </span>
                        <span v-else class="text-xs text-muted-foreground/60">
                            No change this week
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">User growth</CardTitle>
                    <CardDescription>
                        Total accounts over the last 30 days
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <AreaChart :points="userGrowth" :height="220" />
                </CardContent>
            </Card>

            <Card class="flex flex-col">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0"
                >
                    <CardTitle class="text-base">Recently joined</CardTitle>
                    <Link
                        :href="usersIndex()"
                        class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        View all
                    </Link>
                </CardHeader>
                <CardContent class="flex-1 divide-y divide-border pt-0">
                    <div
                        v-for="user in recentUsers"
                        :key="user.id"
                        class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0"
                    >
                        <Avatar class="size-8">
                            <AvatarFallback class="text-xs font-medium">
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ user.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ user.email }}
                            </p>
                        </div>
                        <Badge
                            v-if="user.role === 'admin'"
                            variant="secondary"
                            class="shrink-0"
                        >
                            Admin
                        </Badge>
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

        <Card>
            <CardHeader
                class="flex flex-row items-start justify-between space-y-0"
            >
                <div class="space-y-1.5">
                    <CardTitle class="text-base">Request activity</CardTitle>
                    <CardDescription>
                        Requests executed per day over the last 14 days
                    </CardDescription>
                </div>
                <Badge variant="outline" class="gap-1.5">
                    <Activity class="size-3.5 text-muted-foreground" />
                    <span class="tabular-nums"
                        >{{ requestsThisWeek.toLocaleString() }} this week</span
                    >
                </Badge>
            </CardHeader>
            <CardContent>
                <AreaChart :points="requestActivity" :height="200" />
            </CardContent>
        </Card>
    </div>
</template>
