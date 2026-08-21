<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import {
    markAllRead,
    markRead,
    index as notificationsIndex,
} from '@/actions/App/Http/Controllers/NotificationController';
import { show as showWorkspace } from '@/actions/App/Http/Controllers/WorkspaceController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { api } from '@/lib/api';
import type { AppNotification } from '@/types/ui';

withDefaults(defineProps<{ variant?: 'sidebar' | 'icon' }>(), {
    variant: 'sidebar',
});

const notifications = ref<AppNotification[]>([]);
const unreadCount = ref(0);
const loaded = ref(false);

async function load() {
    const data = await api.get<{
        notifications: AppNotification[];
        unread_count: number;
    }>(notificationsIndex.url());
    notifications.value = data.notifications;
    unreadCount.value = data.unread_count;
    loaded.value = true;
}

async function onOpenChange(open: boolean) {
    if (open) {
        await load();
    }
}

async function onSelect(notification: AppNotification) {
    if (!notification.read_at) {
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
        await api.post(markRead.url({ notification: notification.id }));
    }

    if (notification.data.workspace_id) {
        router.visit(
            showWorkspace.url(notification.data.workspace_id as string),
        );
    }
}

async function onMarkAllRead() {
    unreadCount.value = 0;
    notifications.value = notifications.value.map((n) => ({
        ...n,
        read_at: n.read_at ?? new Date().toISOString(),
    }));
    await api.post(markAllRead.url());
}

onMounted(load);
</script>

<template>
    <DropdownMenu @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <Button
                v-if="variant === 'icon'"
                variant="ghost"
                size="icon"
                class="relative size-9"
                aria-label="Notifications"
            >
                <Bell class="size-4.5" />
                <Badge
                    v-if="unreadCount > 0"
                    class="absolute top-0 right-0 size-4 justify-center rounded-full p-0 text-[10px]"
                    >{{ unreadCount > 9 ? '9+' : unreadCount }}</Badge
                >
            </Button>
            <Button
                v-else
                variant="ghost"
                class="h-9 w-full justify-start gap-2 px-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0"
            >
                <Bell class="size-4.5 shrink-0" />
                <span class="group-data-[collapsible=icon]:hidden"
                    >Notifications</span
                >
                <Badge
                    v-if="unreadCount > 0"
                    class="ml-auto size-5 justify-center rounded-full p-0 text-[10px] group-data-[collapsible=icon]:absolute group-data-[collapsible=icon]:top-0 group-data-[collapsible=icon]:right-0 group-data-[collapsible=icon]:ml-0 group-data-[collapsible=icon]:size-4"
                    >{{ unreadCount > 9 ? '9+' : unreadCount }}</Badge
                >
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            :side="variant === 'icon' ? 'bottom' : 'top'"
            :align="variant === 'icon' ? 'end' : 'start'"
            class="w-80 p-0"
        >
            <div class="flex items-center justify-between border-b px-3 py-2">
                <span class="text-sm font-medium">Notifications</span>
                <Button
                    v-if="unreadCount > 0"
                    variant="ghost"
                    size="sm"
                    class="h-6 text-xs"
                    @click="onMarkAllRead"
                    >Mark all as read</Button
                >
            </div>
            <div class="max-h-96 overflow-y-auto">
                <p
                    v-if="loaded && !notifications.length"
                    class="px-3 py-6 text-center text-xs text-muted-foreground"
                >
                    No notifications yet.
                </p>
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="flex w-full items-start gap-2 border-b px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-accent"
                    @click="onSelect(notification)"
                >
                    <span
                        class="mt-1.5 size-1.5 shrink-0 rounded-full"
                        :class="
                            notification.read_at
                                ? 'bg-transparent'
                                : 'bg-blue-500'
                        "
                    />
                    <span class="flex-1">{{ notification.data.message }}</span>
                </button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
