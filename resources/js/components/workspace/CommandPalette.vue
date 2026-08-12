<script setup lang="ts">
import { CornerDownLeft, Search } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useOpenRequest } from '@/composables/useOpenRequest';
import { methodColor } from '@/lib/http';
import { useWorkspaceStore } from '@/stores/workspace';
import type { CollectionNode } from '@/types/workspace';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
}>();

const store = useWorkspaceStore();
const { openRequest } = useOpenRequest();

const query = ref('');
const activeIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

type Entry = {
    id: number;
    name: string;
    method: string;
    path: string;
    haystack: string;
};

/** Every request in the tree, flattened with its folder breadcrumb — the
 * palette only lists requests, since jumping to a request tab is the
 * actionable outcome; folders are just context in the breadcrumb. */
function flatten(nodes: CollectionNode[], parents: string[] = []): Entry[] {
    const entries: Entry[] = [];

    for (const node of nodes) {
        const path = [...parents, node.name];

        for (const request of node.requests) {
            entries.push({
                id: request.id,
                name: request.name,
                method: request.method,
                path: path.join(' / '),
                haystack: `${request.name} ${path.join(' ')}`.toLowerCase(),
            });
        }

        entries.push(...flatten(node.children, path));
    }

    return entries;
}

const allEntries = computed(() => flatten(store.collectionTree));

const results = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) {
        return allEntries.value;
    }

    return allEntries.value.filter((entry) => entry.haystack.includes(term));
});

watch(results, () => {
    activeIndex.value = 0;
});

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            query.value = '';
            activeIndex.value = 0;
            await nextTick();
            inputRef.value?.focus();
        }
    },
);

function select(entry: Entry) {
    void openRequest({ id: entry.id });
    emit('update:open', false);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = Math.min(
            activeIndex.value + 1,
            results.value.length - 1,
        );
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const entry = results.value[activeIndex.value];

        if (entry) {
            select(entry);
        }
    }
}

function onGlobalKeydown(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        emit('update:open', !props.open);
    }
}

onMounted(() => window.addEventListener('keydown', onGlobalKeydown));
onUnmounted(() => window.removeEventListener('keydown', onGlobalKeydown));
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent
            :show-close-button="false"
            class="top-[16%] max-w-[calc(100%-2rem)] translate-y-0 gap-0 overflow-hidden rounded-xl border-0 p-0 shadow-2xl sm:max-w-xl"
        >
            <DialogTitle class="sr-only">Search requests</DialogTitle>

            <div class="flex items-center gap-2.5 border-b px-4">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    ref="inputRef"
                    v-model="query"
                    type="text"
                    placeholder="Search requests…"
                    autocomplete="off"
                    class="h-12 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    @keydown="onKeydown"
                />
                <kbd
                    class="hidden shrink-0 rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground sm:inline-block"
                    >Esc</kbd
                >
            </div>

            <div class="max-h-80 overflow-y-auto p-2">
                <button
                    v-for="(entry, index) in results"
                    :key="entry.id"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm"
                    :class="index === activeIndex ? 'bg-accent' : ''"
                    @mouseenter="activeIndex = index"
                    @click="select(entry)"
                >
                    <span
                        class="w-12 shrink-0 font-mono text-[10px] font-semibold"
                        :class="methodColor[entry.method] ?? ''"
                        >{{ entry.method }}</span
                    >
                    <span class="min-w-0 flex-1 truncate">{{
                        entry.name
                    }}</span>
                    <span
                        v-if="entry.path"
                        class="shrink-0 truncate text-xs text-muted-foreground"
                        >{{ entry.path }}</span
                    >
                </button>

                <p
                    v-if="!results.length"
                    class="px-3 py-6 text-center text-xs text-muted-foreground"
                >
                    No matching requests.
                </p>
            </div>

            <div
                class="flex items-center gap-4 border-t bg-muted/30 px-4 py-2 text-[11px] text-muted-foreground"
            >
                <span class="flex items-center gap-1">
                    <kbd class="rounded border bg-background px-1">↑</kbd>
                    <kbd class="rounded border bg-background px-1">↓</kbd>
                    to navigate
                </span>
                <span class="flex items-center gap-1">
                    <CornerDownLeft class="size-3" />
                    to open
                </span>
            </div>
        </DialogContent>
    </Dialog>
</template>
