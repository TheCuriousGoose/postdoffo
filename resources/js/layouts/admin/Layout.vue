<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FileText, LayoutDashboard, ShieldCheck, Users } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { index as postsIndex } from '@/routes/admin/posts';
import { index as usersIndex } from '@/routes/admin/users';
import { index as workspacesIndex } from '@/routes/admin/workspaces';
import type { NavItem } from '@/types';

const page = usePage<{ marketingEnabled?: boolean }>();

// Blog management is only meaningful when the public marketing site (and its
// blog) is switched on. A self-hosted instance with marketing off has no
// public blog, so the entry is hidden there.
const sidebarNavItems = computed<NavItem[]>(() => [
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
    ...(page.props.marketingEnabled
        ? [{ title: 'Posts', href: postsIndex(), icon: FileText }]
        : []),
]);

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
