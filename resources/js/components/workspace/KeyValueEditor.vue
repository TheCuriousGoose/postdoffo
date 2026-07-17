<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { KeyValuePair } from '@/types/workspace';

const props = defineProps<{
    modelValue: KeyValuePair[] | null;
    keyPlaceholder?: string;
    valuePlaceholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [KeyValuePair[]];
}>();

const rows = computed(() => props.modelValue ?? []);

function update(index: number, patch: Partial<KeyValuePair>) {
    const next = rows.value.map((row, i) =>
        i === index ? { ...row, ...patch } : row,
    );
    emit('update:modelValue', next);
}

function addRow() {
    emit('update:modelValue', [
        ...rows.value,
        { key: '', value: '', enabled: true },
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
    <div class="flex flex-col gap-2">
        <div
            v-for="(row, index) in rows"
            :key="index"
            class="flex items-center gap-2"
        >
            <Checkbox
                :model-value="row.enabled !== false"
                @update:model-value="(v) => update(index, { enabled: !!v })"
            />
            <Input
                :model-value="row.key"
                :placeholder="keyPlaceholder ?? 'Key'"
                class="font-mono text-sm"
                @update:model-value="(v) => update(index, { key: String(v) })"
            />
            <Input
                :model-value="row.value"
                :placeholder="valuePlaceholder ?? 'Value'"
                class="font-mono text-sm"
                @update:model-value="(v) => update(index, { value: String(v) })"
            />
            <Button
                variant="ghost"
                size="icon"
                class="shrink-0"
                @click="removeRow(index)"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>

        <Button variant="outline" size="sm" class="w-fit" @click="addRow"
            >Add</Button
        >
    </div>
</template>
