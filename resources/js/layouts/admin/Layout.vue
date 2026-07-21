<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutDashboard, Shield, Users } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';
import { index as workspacesIndex } from '@/routes/admin/workspaces';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutDashboard,
    },
    {
        title: 'Users',
        href: usersIndex(),
        icon: Users,
    },
    {
        title: 'Workspaces',
        href: workspacesIndex(),
        icon: Shield,
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Admin"
            description="Manage users and workspaces across the whole app."
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Admin"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-3xl">
                <section class="max-w-3xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
