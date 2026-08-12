<script setup lang="ts">
import { Eye, EyeOff, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';

/**
 * The one table shape for variables, whichever layer they live in. Workspace
 * globals and each environment used to render their own hand-rolled row of
 * bordered `Input`s, so the same three fields looked like two different
 * features; this matches the grid `KeyValueEditor` uses for headers and params
 * so the whole app edits key/value pairs the same way.
 *
 * Rows are persisted individually by the parent (each one is its own record),
 * so this emits intent rather than a whole array: the trailing blank row emits
 * `create` once it has a key, and every other edit emits `update`.
 */
export type VariableRow = {
    id: number;
    key: string;
    value: string | null;
    is_secret: boolean;
};

defineProps<{
    rows: VariableRow[];
    /**
     * Per-key annotation, shown as a chip on the row. Used to spell out which
     * layer actually wins when the same key is defined more than once.
     */
    notes?: Record<string, string>;
    emptyLabel?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    create: [{ key: string; value: string }];
    update: [
        row: VariableRow,
        patch: { key?: string; value?: string; is_secret?: boolean },
    ];
    remove: [row: VariableRow];
}>();

const revealed = ref<number[]>([]);
const draftKey = ref('');
const draftValue = ref('');

function toggleReveal(id: number) {
    revealed.value = revealed.value.includes(id)
        ? revealed.value.filter((each) => each !== id)
        : [...revealed.value, id];
}

function isRevealed(row: VariableRow): boolean {
    return !row.is_secret || revealed.value.includes(row.id);
}

// The blank row becomes real once it has a name and focus leaves the row
// entirely. Committing per input instead would mean tabbing from name to value
// files the variable early and then throws away the value typed a second later;
// waiting for the row to lose focus keeps one POST per variable and keeps both
// fields. An abandoned half-typed row never reaches the server at all.
function onDraftFocusOut(event: FocusEvent) {
    const row = event.currentTarget as HTMLElement;
    const goingTo = event.relatedTarget as Node | null;

    if (goingTo && row.contains(goingTo)) {
        return;
    }

    commitDraft();
}

function commitDraft() {
    const key = draftKey.value.trim();

    if (key === '') {
        return;
    }

    emit('create', { key, value: draftValue.value });
    draftKey.value = '';
    draftValue.value = '';
}
</script>

<template>
    <div>
        <div class="overflow-hidden rounded-md border">
            <table class="w-full table-fixed border-collapse">
                <thead>
                    <tr
                        class="border-b bg-muted/40 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        <th class="w-1/3 border-r px-2.5 py-1.5 text-left">
                            Name
                        </th>
                        <th class="border-r px-2.5 py-1.5 text-left">Value</th>
                        <th class="w-20 border-r px-2.5 py-1.5 text-left">
                            Secret
                        </th>
                        <th class="w-9 py-1.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="group border-b last:border-b-0"
                    >
                        <td class="border-r p-0 align-middle">
                            <input
                                :value="row.key"
                                :disabled="disabled"
                                placeholder="Name"
                                autocomplete="off"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                data-form-type="other"
                                class="h-8 w-full bg-transparent px-2.5 font-mono text-sm outline-none placeholder:text-muted-foreground focus-visible:bg-accent/40 disabled:opacity-50"
                                @change="
                                    (e) =>
                                        emit('update', row, {
                                            key: (e.target as HTMLInputElement)
                                                .value,
                                        })
                                "
                            />
                        </td>
                        <td class="border-r p-0 align-middle">
                            <div class="flex items-center">
                                <input
                                    :value="row.value ?? ''"
                                    :type="
                                        isRevealed(row) ? 'text' : 'password'
                                    "
                                    :disabled="disabled"
                                    placeholder="Value"
                                    autocomplete="off"
                                    data-lpignore="true"
                                    data-1p-ignore="true"
                                    data-bwignore="true"
                                    data-form-type="other"
                                    class="h-8 min-w-0 flex-1 bg-transparent px-2.5 font-mono text-sm outline-none placeholder:text-muted-foreground focus-visible:bg-accent/40 disabled:opacity-50"
                                    @change="
                                        (e) =>
                                            emit('update', row, {
                                                value: (
                                                    e.target as HTMLInputElement
                                                ).value,
                                            })
                                    "
                                />
                                <span
                                    v-if="notes?.[row.key]"
                                    class="mr-1.5 shrink-0 rounded border px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                    >{{ notes[row.key] }}</span
                                >
                                <button
                                    v-if="row.is_secret"
                                    type="button"
                                    class="mr-1.5 flex size-5 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground"
                                    :aria-label="
                                        isRevealed(row)
                                            ? 'Hide value'
                                            : 'Reveal value'
                                    "
                                    :title="
                                        isRevealed(row)
                                            ? 'Hide value'
                                            : 'Reveal value'
                                    "
                                    @click="toggleReveal(row.id)"
                                >
                                    <EyeOff
                                        v-if="isRevealed(row)"
                                        class="size-3.5"
                                    />
                                    <Eye v-else class="size-3.5" />
                                </button>
                            </div>
                        </td>
                        <td class="border-r px-2.5 text-center align-middle">
                            <Checkbox
                                :model-value="row.is_secret"
                                :disabled="disabled"
                                class="mx-auto"
                                :aria-label="`Mark ${row.key} as secret`"
                                @update:model-value="
                                    (v) =>
                                        emit('update', row, { is_secret: !!v })
                                "
                            />
                        </td>
                        <td class="text-center align-middle">
                            <button
                                v-if="!disabled"
                                type="button"
                                class="mx-auto flex size-6 items-center justify-center rounded text-muted-foreground transition hover:bg-accent hover:text-foreground max-md:opacity-100 md:opacity-0 md:group-hover:opacity-100"
                                :aria-label="`Delete ${row.key}`"
                                title="Delete"
                                @click="emit('remove', row)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </td>
                    </tr>

                    <tr
                        v-if="!disabled"
                        class="border-b last:border-b-0"
                        @focusout="onDraftFocusOut"
                    >
                        <td class="border-r p-0 align-middle">
                            <input
                                v-model="draftKey"
                                placeholder="Name"
                                autocomplete="off"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                data-form-type="other"
                                class="h-8 w-full bg-transparent px-2.5 font-mono text-sm outline-none placeholder:text-muted-foreground focus-visible:bg-accent/40"
                                @keydown.enter="commitDraft"
                            />
                        </td>
                        <td class="border-r p-0 align-middle">
                            <input
                                v-model="draftValue"
                                placeholder="Value"
                                autocomplete="off"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                data-form-type="other"
                                class="h-8 w-full bg-transparent px-2.5 font-mono text-sm outline-none placeholder:text-muted-foreground focus-visible:bg-accent/40"
                                @keydown.enter="commitDraft"
                            />
                        </td>
                        <td class="border-r"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="!rows.length" class="mt-2 text-xs text-muted-foreground">
            {{ emptyLabel ?? 'No variables here yet.' }}
        </p>
    </div>
</template>
