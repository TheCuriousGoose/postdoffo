<script setup lang="ts">
import { Cookie, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    clear as clearCookies,
    destroy as destroyCookie,
    index as listCookies,
} from '@/actions/App/Http/Controllers/RequestCookieController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import ToolbarButton from '@/components/workspace/ToolbarButton.vue';
import { api } from '@/lib/api';
import type { RequestCookie } from '@/types/workspace';

/**
 * Shows the cookies this server is holding on the user's behalf, so a stale
 * session is something you can see and clear rather than something you work
 * out from a request that keeps coming back 401.
 */
const props = defineProps<{
    workspaceId: number;
}>();

const open = ref(false);
const cookies = ref<RequestCookie[]>([]);
const loading = ref(false);

async function load() {
    loading.value = true;

    try {
        cookies.value = await api.get<RequestCookie[]>(
            listCookies.url(props.workspaceId),
        );
    } catch {
        toast.error('Failed to load cookies');
    } finally {
        loading.value = false;
    }
}

function onOpenChange(next: boolean) {
    open.value = next;

    if (next) {
        load();
    }
}

async function removeCookie(id: number) {
    cookies.value = cookies.value.filter((cookie) => cookie.id !== id);

    try {
        await api.delete(destroyCookie.url(id));
    } catch {
        toast.error('Failed to delete cookie');
        load();
    }
}

async function clearAll() {
    const previous = cookies.value;
    cookies.value = [];

    try {
        await api.delete(clearCookies.url(props.workspaceId));
        toast.success('Cookies cleared');
    } catch {
        cookies.value = previous;
        toast.error('Failed to clear cookies');
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogTrigger as-child>
            <ToolbarButton label="Cookies">
                <Cookie class="size-4" />
            </ToolbarButton>
        </DialogTrigger>
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Cookies</DialogTitle>
                <DialogDescription>
                    Set by the APIs you call and sent back automatically on
                    matching requests, the way a browser would. These are yours
                    alone — other members of this workspace keep their own.
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-80 overflow-y-auto">
                <table v-if="cookies.length" class="w-full table-fixed text-sm">
                    <thead>
                        <tr
                            class="border-b text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            <th class="w-1/3 py-1.5 text-left">Domain</th>
                            <th class="w-1/4 py-1.5 text-left">Name</th>
                            <th class="py-1.5 text-left">Value</th>
                            <th class="w-9"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="cookie in cookies"
                            :key="cookie.id"
                            class="group border-b last:border-b-0"
                        >
                            <td class="truncate py-1.5 pr-2 font-mono text-xs">
                                {{ cookie.domain }}{{ cookie.path }}
                            </td>
                            <td class="truncate py-1.5 pr-2 font-mono text-xs">
                                {{ cookie.name }}
                            </td>
                            <td
                                class="truncate py-1.5 pr-2 font-mono text-xs text-muted-foreground"
                            >
                                {{ cookie.value }}
                            </td>
                            <td class="text-center">
                                <button
                                    type="button"
                                    class="mx-auto flex size-6 items-center justify-center rounded text-muted-foreground transition hover:bg-accent hover:text-foreground max-md:opacity-100 md:opacity-0 md:group-hover:opacity-100"
                                    title="Delete cookie"
                                    @click="removeCookie(cookie.id)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-else class="px-1 py-2 text-xs text-muted-foreground">
                    {{
                        loading
                            ? 'Loading…'
                            : 'No cookies yet. Send a request that returns a Set-Cookie header and it will show up here.'
                    }}
                </p>
            </div>

            <div v-if="cookies.length" class="flex justify-end">
                <Button variant="outline" size="sm" @click="clearAll"
                    >Clear all</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
