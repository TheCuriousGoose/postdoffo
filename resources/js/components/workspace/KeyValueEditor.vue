<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import type { KeyValuePair } from '@/types/workspace';
import VariableHighlightInput from './VariableHighlightInput.vue';

/**
 * A spreadsheet-style editor for header / query / form pairs. Rows are a real
 * table with borderless cell inputs, and a permanent blank row at the bottom
 * turns into a real row the moment you type in it (Postman-style), so there's
 * no separate "add" button. Keyed by index so the row you're typing in keeps
 * focus as it graduates from the trailing blank to a real row.
 */
const props = defineProps<{
    modelValue: KeyValuePair[] | null;
    keyPlaceholder?: string;
    valuePlaceholder?: string;
    variables?: Record<string, unknown>;
}>();

const emit = defineEmits<{
    'update:modelValue': [KeyValuePair[]];
}>();

const rows = computed(() => props.modelValue ?? []);

// The real rows plus one trailing blank the user can type into.
const displayRows = computed<KeyValuePair[]>(() => [
    ...rows.value,
    { key: '', value: '', enabled: true },
]);

function update(index: number, patch: Partial<KeyValuePair>) {
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
                    <td class="p-0 align-middle">
                        <VariableHighlightInput
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
                            class="mx-auto flex size-6 items-center justify-center rounded text-muted-foreground opacity-0 transition group-hover:opacity-100 hover:bg-accent hover:text-foreground"
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
