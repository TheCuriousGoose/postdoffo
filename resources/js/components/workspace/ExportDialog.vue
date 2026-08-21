<script setup lang="ts">
import { Check, Download, FileCode2, FileJson } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { download as downloadCollection } from '@/actions/App/Http/Controllers/CollectionController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { api } from '@/lib/api';
import { cn } from '@/lib/utils';

type ExportFormat = 'postman' | 'openapi';

const props = defineProps<{
    open: boolean;
    collectionId: string;
    collectionName: string;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
}>();

const formats: {
    id: ExportFormat;
    label: string;
    description: string;
    extension: string;
    icon: typeof FileJson;
}[] = [
    {
        id: 'postman',
        label: 'Postman Collection',
        description:
            'v2.1 format — import into Postman, Insomnia, or another PostDoffo workspace.',
        extension: 'postman_collection.json',
        icon: FileJson,
    },
    {
        id: 'openapi',
        label: 'OpenAPI',
        description:
            '3.0 spec — for API docs, code generators, and OpenAPI-aware tooling.',
        extension: 'openapi.json',
        icon: FileCode2,
    },
];

const selected = ref<ExportFormat>('postman');
const exporting = ref(false);

function slug(value: string): string {
    return (
        value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'collection'
    );
}

async function runExport() {
    exporting.value = true;

    try {
        const format = formats.find((f) => f.id === selected.value)!;
        const data = await api.get<Record<string, unknown>>(
            downloadCollection.url(props.collectionId, {
                query: { format: format.id },
            }),
        );

        const blob = new Blob([JSON.stringify(data, null, 2)], {
            type: 'application/json',
        });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `${slug(props.collectionName)}.${format.extension}`;
        anchor.click();
        URL.revokeObjectURL(url);

        emit('update:open', false);
    } catch {
        toast.error('Failed to export collection');
    } finally {
        exporting.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Export "{{ collectionName }}"</DialogTitle>
                <DialogDescription>
                    Choose a format to download this collection and everything
                    inside it.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <button
                    v-for="format in formats"
                    :key="format.id"
                    type="button"
                    class="flex items-start gap-3 rounded-lg border p-3 text-left transition"
                    :class="
                        cn(
                            selected === format.id
                                ? 'border-orange-500 bg-orange-500/5 ring-1 ring-orange-500'
                                : 'border-border hover:bg-accent/50',
                        )
                    "
                    @click="selected = format.id"
                >
                    <component
                        :is="format.icon"
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    />
                    <span class="min-w-0 flex-1">
                        <span
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            {{ format.label }}
                        </span>
                        <span
                            class="mt-0.5 block text-xs text-muted-foreground"
                        >
                            {{ format.description }}
                        </span>
                    </span>
                    <Check
                        v-if="selected === format.id"
                        class="mt-0.5 size-4 shrink-0 text-orange-500"
                    />
                </button>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="ghost" @click="emit('update:open', false)">
                    Cancel
                </Button>
                <Button :disabled="exporting" @click="runExport">
                    <Download class="size-4" />
                    Export
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
