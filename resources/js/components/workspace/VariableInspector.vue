<script setup lang="ts">
import {
    Building2,
    Eye,
    EyeOff,
    Globe,
    Layers,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useVariableInspector } from '@/composables/useVariableInspector';
import { useWorkspaceStore } from '@/stores/workspace';

const { state, inspect, close } = useVariableInspector();
const store = useWorkspaceStore();

const revealed = ref(false);

const current = computed(() =>
    state.key ? (store.activeScope.variables[state.key] ?? null) : null,
);

const others = computed(() =>
    store.activeScope.list.filter((variable) => variable.key !== state.key),
);

// Clamp the panel into the viewport so it never spills off the right/bottom.
const panelStyle = computed(() => {
    const width = 288;
    const left = Math.min(state.x, window.innerWidth - width - 12);

    return {
        left: `${Math.max(12, left)}px`,
        top: `${state.y + 10}px`,
    };
});

watch(
    () => state.key,
    () => {
        revealed.value = false;
    },
);

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        close();
    }
}

watch(
    () => state.visible,
    (visible) => {
        if (visible) {
            window.addEventListener('keydown', onKeydown);
        } else {
            window.removeEventListener('keydown', onKeydown);
        }
    },
);

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div v-if="state.visible" class="contents">
            <!-- click-away catcher -->
            <div class="fixed inset-0 z-40" @click="close" @wheel="close" />

            <div
                class="fixed z-50 w-72 overflow-hidden rounded-lg border border-border bg-popover text-popover-foreground shadow-lg"
                :style="panelStyle"
            >
                <!-- clicked variable -->
                <div class="border-b border-border p-3">
                    <div class="flex items-center justify-between gap-2">
                        <code
                            class="truncate font-mono text-sm font-medium text-orange-600 dark:text-orange-400"
                            >{{ state.key }}</code
                        >
                        <span
                            v-if="current"
                            class="flex shrink-0 items-center gap-1 rounded border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground"
                        >
                            <Globe
                                v-if="current.sourceType === 'environment'"
                                class="size-3"
                            />
                            <Building2
                                v-else-if="current.sourceType === 'workspace'"
                                class="size-3"
                            />
                            <Layers v-else class="size-3" />
                            {{ current.sourceName }}
                        </span>
                    </div>

                    <div
                        v-if="current"
                        class="mt-2 flex items-center gap-2 rounded-md bg-muted px-2 py-1.5"
                    >
                        <code class="flex-1 truncate font-mono text-xs">{{
                            current.isSecret && !revealed
                                ? '•'.repeat(
                                      Math.min(current.value.length || 8, 16),
                                  )
                                : current.value || '(empty)'
                        }}</code>
                        <button
                            v-if="current.isSecret"
                            type="button"
                            class="shrink-0 text-muted-foreground transition hover:text-foreground"
                            :title="revealed ? 'Hide value' : 'Reveal value'"
                            @click="revealed = !revealed"
                        >
                            <EyeOff v-if="revealed" class="size-3.5" />
                            <Eye v-else class="size-3.5" />
                        </button>
                    </div>

                    <p
                        v-else
                        class="mt-2 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400"
                    >
                        <TriangleAlert class="size-3.5" />
                        Not defined in this scope
                    </p>
                </div>

                <!-- other variables -->
                <div v-if="others.length" class="max-h-56 overflow-y-auto py-1">
                    <p
                        class="px-3 py-1 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Other variables
                    </p>
                    <button
                        v-for="variable in others"
                        :key="variable.key"
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-3 py-1.5 text-left transition hover:bg-accent"
                        @click="inspect(variable.key)"
                    >
                        <code
                            class="truncate font-mono text-xs text-orange-600 dark:text-orange-400"
                            >{{ variable.key }}</code
                        >
                        <span
                            class="truncate font-mono text-xs text-muted-foreground"
                            >{{
                                variable.isSecret
                                    ? '••••'
                                    : variable.value || '(empty)'
                            }}</span
                        >
                    </button>
                </div>

                <div
                    v-else-if="current"
                    class="px-3 py-2 text-xs text-muted-foreground"
                >
                    No other variables in scope.
                </div>
            </div>
        </div>
    </Teleport>
</template>
