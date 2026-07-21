<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutDashboard, ShieldCheck, Users } from '@lucide/vue';
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
        icon: ShieldCheck,
    },
];

// Admin routes are flat (/admin, /admin/users, /admin/workspaces) with no
// nested detail pages, so an exact match is correct here — isCurrentOrParentUrl's
// startsWith made "Dashboard" (/admin) match every other admin URL as a prefix,
// so it was highlighted no matter which admin page was open.
const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Admin"
            description="Manage users and workspaces across the whole app."
        />

        <div class="flex flex-col lg:flex-row lg:space-x-8">
            <aside class="w-full lg:w-48 lg:shrink-0">
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
                            { 'bg-muted': isCurrentUrl(item.href) },
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

            <div class="min-w-0 flex-1">
                <section class="space-y-8">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
