<script setup lang="ts">
import { nextTick, useTemplateRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { promptState } from '@/lib/dialogs';

const input = useTemplateRef('input');

watch(
    () => promptState.open,
    async (open) => {
        if (!open) {
            return;
        }

        await nextTick();
        input.value?.$el?.focus?.();
        input.value?.$el?.select?.();
    },
);

function settle(value: string | null) {
    promptState.open = false;
    promptState.resolve(value);
}

function onOpenChange(value: boolean) {
    if (!value) {
        settle(null);
    }
}

function submit() {
    const value = promptState.value.trim();
    settle(value === '' ? null : value);
}
</script>

<template>
    <Dialog :open="promptState.open" @update:open="onOpenChange">
        <DialogContent
            :class="promptState.multiline ? 'sm:max-w-xl' : 'sm:max-w-sm'"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ promptState.title }}</DialogTitle>
                    <DialogDescription v-if="promptState.description">
                        {{ promptState.description }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label v-if="promptState.label" for="prompt-dialog-input">
                        {{ promptState.label }}
                    </Label>
                    <Textarea
                        v-if="promptState.multiline"
                        id="prompt-dialog-input"
                        ref="input"
                        v-model="promptState.value"
                        :placeholder="promptState.placeholder"
                        class="min-h-40 font-mono text-xs"
                    />
                    <Input
                        v-else
                        id="prompt-dialog-input"
                        ref="input"
                        v-model="promptState.value"
                        :placeholder="promptState.placeholder"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="settle(null)"
                    >
                        {{ promptState.cancelText }}
                    </Button>
                    <Button type="submit">
                        {{ promptState.confirmText }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
