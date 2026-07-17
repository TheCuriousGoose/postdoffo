<script setup lang="ts">
import { History } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { RequestHistoryEntry } from '@/types/workspace';

defineProps<{
    history: RequestHistoryEntry[];
}>();

function statusVariant(status: number | null) {
    if (status === null) {
        return 'destructive';
    }

    if (status < 300) {
        return 'default';
    }

    if (status < 400) {
        return 'secondary';
    }

    return 'destructive';
}

function formatTime(iso: string) {
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <Sheet>
        <SheetTrigger as-child>
            <Button variant="outline" size="icon">
                <History class="size-4" />
            </Button>
        </SheetTrigger>
        <SheetContent class="w-96 sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Request History</SheetTitle>
            </SheetHeader>

            <div class="flex flex-col gap-2 overflow-y-auto px-4 pb-4">
                <div
                    v-for="entry in history"
                    :key="entry.id"
                    class="rounded-md border p-2 text-sm"
                >
                    <div class="flex items-center gap-2">
                        <Badge :variant="statusVariant(entry.status_code)">{{
                            entry.status_code ?? 'ERR'
                        }}</Badge>
                        <span class="font-mono text-xs font-semibold">{{
                            entry.method
                        }}</span>
                        <span class="truncate text-xs text-muted-foreground"
                            >{{ entry.duration_ms }} ms</span
                        >
                    </div>
                    <p class="mt-1 truncate font-mono text-xs">
                        {{ entry.url }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ formatTime(entry.executed_at) }}
                    </p>
                </div>

                <p v-if="!history.length" class="text-sm text-muted-foreground">
                    No requests have been executed yet.
                </p>
            </div>
        </SheetContent>
    </Sheet>
</template>
