<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { confirmState } from '@/lib/dialogs';

function settle(value: boolean) {
    confirmState.open = false;
    confirmState.resolve(value);
}

function onOpenChange(value: boolean) {
    if (!value) {
        settle(false);
    }
}
</script>

<template>
    <Dialog :open="confirmState.open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ confirmState.title }}</DialogTitle>
                <DialogDescription v-if="confirmState.description">
                    {{ confirmState.description }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="settle(false)">
                    {{ confirmState.cancelText }}
                </Button>
                <Button
                    :variant="
                        confirmState.variant === 'destructive'
                            ? 'destructive'
                            : 'default'
                    "
                    @click="settle(true)"
                >
                    {{ confirmState.confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
