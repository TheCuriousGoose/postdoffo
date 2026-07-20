<script setup lang="ts">
import { Braces, Globe, Layers, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { collectReferencedVariables } from '@/lib/variableScope';
import { useWorkspaceStore } from '@/stores/workspace';

const store = useWorkspaceStore();

const scope = computed(() => store.activeScope);

const undefinedVariables = computed(() => {
    const draft = store.activeTab?.draft;

    if (!draft) {
        return [];
    }

    return collectReferencedVariables(draft).filter(
        (key) => !(key in scope.value.variables),
    );
});

function mask(value: string): string {
    return value ? '•'.repeat(Math.min(value.length, 12)) : '(empty)';
}
</script>

<template>
    <Popover>
        <PopoverTrigger
            class="inline-flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1.5 font-mono text-xs text-muted-foreground transition hover:bg-accent hover:text-foreground"
            :title="'Variables in scope for this request'"
        >
            <Braces class="size-3.5" />
            {{ scope.list.length }}
            <span
                v-if="undefinedVariables.length"
                class="flex items-center gap-1 text-amber-600 dark:text-amber-400"
            >
                <TriangleAlert class="size-3.5" />
                {{ undefinedVariables.length }}
            </span>
        </PopoverTrigger>

        <PopoverContent align="end" class="w-80 p-0">
            <div class="border-b border-border px-3 py-2.5">
                <p class="text-sm font-medium">Variables in scope</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Resolved for this request, nearest source wins.
                </p>
            </div>

            <!-- referenced but undefined -->
            <div
                v-if="undefinedVariables.length"
                class="border-b border-border bg-amber-500/5 px-3 py-2.5"
            >
                <p
                    class="flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                >
                    <TriangleAlert class="size-3.5" />
                    Used but not defined
                </p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <code
                        v-for="key in undefinedVariables"
                        :key="key"
                        class="rounded bg-red-500/10 px-1.5 py-0.5 font-mono text-xs text-red-600 dark:text-red-400"
                    >
                        {{ key }}
                    </code>
                </div>
            </div>

            <div class="max-h-72 overflow-y-auto py-1">
                <p
                    v-if="!scope.list.length"
                    class="px-3 py-6 text-center text-xs text-muted-foreground"
                >
                    No variables in scope yet. Add them to an environment, or to
                    a parent collection's Settings.
                </p>

                <div
                    v-for="variable in scope.list"
                    :key="variable.key"
                    class="flex items-center justify-between gap-3 px-3 py-1.5"
                >
                    <div class="min-w-0">
                        <code
                            class="font-mono text-xs text-orange-600 dark:text-orange-400"
                            >{{ variable.key }}</code
                        >
                        <p
                            class="truncate font-mono text-xs text-muted-foreground"
                        >
                            {{
                                variable.isSecret
                                    ? mask(variable.value)
                                    : variable.value || '(empty)'
                            }}
                        </p>
                    </div>
                    <span
                        class="flex shrink-0 items-center gap-1 rounded border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground"
                        :title="`Source: ${variable.sourceName}`"
                    >
                        <Globe
                            v-if="variable.sourceType === 'environment'"
                            class="size-3"
                        />
                        <Layers v-else class="size-3" />
                        <span class="max-w-24 truncate">{{
                            variable.sourceName
                        }}</span>
                    </span>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
