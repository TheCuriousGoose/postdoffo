<script setup lang="ts">
import { Paperclip, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatBytes } from '@/lib/utils';
import type { FormField, RequestFile } from '@/types/workspace';
import VariableHighlightInput from './VariableHighlightInput.vue';

/**
 * A spreadsheet-style editor for header / query / form pairs. Rows are a real
 * table with borderless cell inputs, and a permanent blank row at the bottom
 * turns into a real row the moment you type in it (Postman-style), so there's
 * no separate "add" button. Keyed by index so the row you're typing in keeps
 * focus as it graduates from the trailing blank to a real row.
 *
 * With `allow-files` (form-data bodies only) each row also picks between a text
 * value and an uploaded file. The upload itself is the parent's job — it owns
 * the request id the file has to be stored against — so it comes in as the
 * `upload-file` callback, leaving the per-row pending state here where the row
 * that's waiting can show it.
 */
const props = defineProps<{
    modelValue: FormField[] | null;
    keyPlaceholder?: string;
    valuePlaceholder?: string;
    variables?: Record<string, unknown>;
    allowFiles?: boolean;
    uploadFile?: (file: File) => Promise<RequestFile>;
    deleteFile?: (id: number) => Promise<void>;
}>();

const emit = defineEmits<{
    'update:modelValue': [FormField[]];
}>();

const rows = computed(() => props.modelValue ?? []);

// The real rows plus one trailing blank the user can type into.
const displayRows = computed<FormField[]>(() => [
    ...rows.value,
    { key: '', value: '', enabled: true },
]);

// Keyed by row rather than collected into a ref array: only file rows render a
// picker, so an array's positions wouldn't line up with the row numbers the rest
// of this component works in.
const fileInputs = ref<Record<number, HTMLInputElement | null>>({});
const uploadingRows = ref<number[]>([]);

function setFileInput(
    index: number,
    el: Element | ComponentPublicInstance | null,
) {
    fileInputs.value[index] = el as HTMLInputElement | null;
}

function update(index: number, patch: Partial<FormField>) {
    if (index < rows.value.length) {
        emit(
            'update:modelValue',
            rows.value.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        );

        return;
    }

    // Typing in the trailing blank row promotes it to a real row.
    emit('update:modelValue', [
        ...rows.value,
        { key: '', value: '', enabled: true, ...patch },
    ]);
}

function removeRow(index: number) {
    emit(
        'update:modelValue',
        rows.value.filter((_, i) => i !== index),
    );
}

function setType(index: number, type: string) {
    // A row only ever holds one of the two, so switching drops what the other
    // mode was carrying rather than leaving a stale value behind the scenes.
    update(
        index,
        type === 'file'
            ? { type: 'file', value: '' }
            : { type: 'text', file_id: null, filename: null, size: null },
    );
}

/**
 * An imported collection carries the name of the file a row wants but never the
 * file itself — the JSON only ever held a path on the exporter's machine. Such a
 * row is flagged rather than left looking attached, since it would silently drop
 * out of the body on send.
 */
function isMissingFile(row: FormField): boolean {
    return row.type === 'file' && !row.file_id && !!row.filename;
}

function openFilePicker(index: number) {
    fileInputs.value[index]?.click();
}

async function onFilePicked(index: number, event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    // Reset first, so re-picking the same file still fires a change event.
    input.value = '';

    if (!file || !props.uploadFile) {
        return;
    }

    uploadingRows.value = [...uploadingRows.value, index];

    try {
        const uploaded = await props.uploadFile(file);

        update(index, {
            type: 'file',
            value: '',
            file_id: uploaded.id,
            filename: uploaded.filename,
            size: uploaded.size,
        });
    } catch {
        // Reporting the failure is the uploader's job; the row keeps whatever
        // file it already had.
    } finally {
        uploadingRows.value = uploadingRows.value.filter((i) => i !== index);
    }
}

async function clearFile(index: number) {
    const fileId = rows.value[index]?.file_id;

    update(index, { file_id: null, filename: null, size: null });

    if (fileId && props.deleteFile) {
        await props.deleteFile(fileId);
    }
}
</script>

<template>
    <div class="overflow-hidden rounded-md border">
        <table class="w-full table-fixed border-collapse">
            <thead>
                <tr
                    class="border-b bg-muted/40 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                >
                    <th class="w-9 border-r py-1.5"></th>
                    <th class="border-r px-2.5 py-1.5 text-left">
                        {{ keyPlaceholder ?? 'Key' }}
                    </th>
                    <th
                        v-if="allowFiles"
                        class="w-24 border-r px-2.5 py-1.5 text-left"
                    >
                        Type
                    </th>
                    <th class="px-2.5 py-1.5 text-left">
                        {{ valuePlaceholder ?? 'Value' }}
                    </th>
                    <th class="w-9 py-1.5"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, index) in displayRows"
                    :key="index"
                    class="group border-b last:border-b-0"
                >
                    <td class="border-r text-center align-middle">
                        <Checkbox
                            v-if="index < rows.length"
                            :model-value="row.enabled !== false"
                            class="mx-auto"
                            @update:model-value="
                                (v) => update(index, { enabled: !!v })
                            "
                        />
                    </td>
                    <td class="border-r p-0 align-middle">
                        <VariableHighlightInput
                            plain
                            :model-value="row.key"
                            :placeholder="keyPlaceholder ?? 'Key'"
                            :variables="variables"
                            class="font-mono"
                            @update:model-value="
                                (v) => update(index, { key: v })
                            "
                        />
                    </td>
                    <td v-if="allowFiles" class="border-r p-0 align-middle">
                        <Select
                            :model-value="row.type ?? 'text'"
                            @update:model-value="
                                (v) => setType(index, String(v))
                            "
                        >
                            <SelectTrigger
                                size="sm"
                                class="w-full rounded-none border-0 px-2.5 text-xs shadow-none focus-visible:ring-0 dark:bg-transparent dark:hover:bg-accent/40"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="text">Text</SelectItem>
                                <SelectItem value="file">File</SelectItem>
                            </SelectContent>
                        </Select>
                    </td>
                    <td class="p-0 align-middle">
                        <div
                            v-if="allowFiles && row.type === 'file'"
                            class="flex h-8 items-center gap-2 px-2.5"
                        >
                            <input
                                :ref="(el) => setFileInput(index, el)"
                                type="file"
                                class="hidden"
                                @change="(e) => onFilePicked(index, e)"
                            />

                            <Spinner
                                v-if="uploadingRows.includes(index)"
                                class="size-3.5 text-muted-foreground"
                            />

                            <button
                                v-else
                                type="button"
                                :title="
                                    isMissingFile(row)
                                        ? 'This file came from an import — pick it again to attach it'
                                        : undefined
                                "
                                class="flex min-w-0 flex-1 items-center gap-1.5 text-left text-sm"
                                :class="[
                                    row.filename
                                        ? 'font-mono'
                                        : 'text-muted-foreground hover:text-foreground',
                                    isMissingFile(row) &&
                                        'text-red-600 dark:text-red-400',
                                ]"
                                @click="openFilePicker(index)"
                            >
                                <Paperclip
                                    class="size-3.5 shrink-0"
                                    :class="
                                        isMissingFile(row)
                                            ? 'text-red-600 dark:text-red-400'
                                            : 'text-muted-foreground'
                                    "
                                />
                                <span class="truncate">{{
                                    row.filename ?? 'Select file'
                                }}</span>
                                <span
                                    v-if="isMissingFile(row)"
                                    class="shrink-0 text-xs"
                                    >missing</span
                                >
                                <span
                                    v-else-if="row.size != null"
                                    class="shrink-0 text-xs text-muted-foreground"
                                    >{{ formatBytes(row.size) }}</span
                                >
                            </button>

                            <button
                                v-if="
                                    row.filename &&
                                    !uploadingRows.includes(index)
                                "
                                type="button"
                                class="flex size-5 shrink-0 items-center justify-center rounded text-muted-foreground transition hover:bg-accent hover:text-foreground max-md:opacity-100 md:opacity-0 md:group-hover:opacity-100"
                                title="Remove file"
                                @click="clearFile(index)"
                            >
                                <X class="size-3" />
                            </button>
                        </div>

                        <VariableHighlightInput
                            v-else
                            plain
                            :model-value="row.value"
                            :placeholder="valuePlaceholder ?? 'Value'"
                            :variables="variables"
                            class="font-mono"
                            @update:model-value="
                                (v) => update(index, { value: v })
                            "
                        />
                    </td>
                    <td class="text-center align-middle">
                        <button
                            v-if="index < rows.length"
                            type="button"
                            class="mx-auto flex size-6 items-center justify-center rounded text-muted-foreground transition hover:bg-accent hover:text-foreground max-md:opacity-100 md:opacity-0 md:group-hover:opacity-100"
                            title="Remove"
                            @click="removeRow(index)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
