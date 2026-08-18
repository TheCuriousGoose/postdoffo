<script setup lang="ts">
import { Terminal, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export type McpToken = {
    id: string;
    name: string;
    read_only: boolean;
    created_at_diff: string | null;
    expires_at_diff: string | null;
};

const props = defineProps<{ token: McpToken }>();

const emit = defineEmits<{
    revoke: [id: string, onError: () => void];
}>();

const isRevoking = ref(false);

const handleRevoke = () => {
    isRevoking.value = true;
    emit('revoke', props.token.id, () => {
        isRevoking.value = false;
    });
};
</script>

<template>
    <div class="flex items-center justify-between border-b p-4 last:border-b-0">
        <div class="flex min-w-0 items-center gap-4">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted"
            >
                <Terminal class="h-5 w-5 text-muted-foreground" />
            </div>
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                    <p class="truncate font-medium tracking-tight">
                        {{ token.name }}
                    </p>
                    <span
                        class="inline-flex shrink-0 items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border ring-inset"
                    >
                        {{ token.read_only ? 'Read-only' : 'Full access' }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    Created {{ token.created_at_diff }}
                    <template v-if="token.expires_at_diff">
                        <span class="mx-1 text-muted-foreground/50">/</span>
                        Expires {{ token.expires_at_diff }}
                    </template>
                </p>
            </div>
        </div>

        <Dialog>
            <DialogTrigger as-child>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                >
                    <Trash2 class="h-4 w-4" />
                    <span class="sr-only">Revoke</span>
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogTitle>Revoke token</DialogTitle>
                <DialogDescription>
                    Any MCP client still using the "{{ token.name }}" token will
                    stop working immediately. This cannot be undone — you would
                    need to create a new token and reconnect the client.
                </DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="isRevoking"
                        @click="handleRevoke"
                    >
                        {{ isRevoking ? 'Revoking...' : 'Revoke token' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
