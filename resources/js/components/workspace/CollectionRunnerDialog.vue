<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronRight,
    Download,
    Loader2,
    Play,
    Square,
    Upload,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { parseCsv } from '@/lib/csv';
import { runRequest } from '@/lib/executeRequest';
import { flattenRequests } from '@/lib/flattenRequests';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace';
import type { CollectionNode, HttpMethod, TestResult } from '@/types/workspace';

const props = defineProps<{
    open: boolean;
    node: CollectionNode;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
}>();

const store = useWorkspaceStore();

const requests = computed(() => flattenRequests(props.node));

const environmentId = ref<number | null>(null);
const delayMs = ref(0);
const iterations = ref(1);
const dataRows = ref<Record<string, string>[] | null>(null);
const dataFileName = ref('');
const fileInput = ref<HTMLInputElement | null>(null);

type RunResult = {
    iteration: number;
    requestId: number;
    name: string;
    method: HttpMethod;
    status: number | null;
    ok: boolean;
    durationMs: number;
    testResults: TestResult[];
    error: string | null;
};

const running = ref(false);
const cancelled = ref(false);
const results = ref<RunResult[]>([]);
const expandedIndex = ref<number | null>(null);

function toggleExpand(index: number) {
    const result = results.value[index];

    if (!result.error && !result.testResults.length) {
        return;
    }

    expandedIndex.value = expandedIndex.value === index ? null : index;
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            environmentId.value = store.activeEnvironmentId;
        }
    },
);

async function onFileChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    try {
        const text = await file.text();
        const rows = file.name.toLowerCase().endsWith('.json')
            ? (JSON.parse(text) as Record<string, string>[])
            : parseCsv(text);

        if (!Array.isArray(rows) || !rows.length) {
            throw new Error('empty');
        }

        dataRows.value = rows;
        dataFileName.value = file.name;
        iterations.value = rows.length;
    } catch {
        toast.error('Could not read that file as CSV or JSON rows');
    } finally {
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
}

function clearDataFile() {
    dataRows.value = null;
    dataFileName.value = '';
    iterations.value = 1;
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function runAll() {
    if (!requests.value.length || running.value) {
        return;
    }

    running.value = true;
    cancelled.value = false;
    results.value = [];
    expandedIndex.value = null;

    const iterationCount = dataRows.value
        ? dataRows.value.length
        : Math.max(1, iterations.value);
    let carried: Record<string, string> = {};

    outer: for (let iteration = 0; iteration < iterationCount; iteration++) {
        const rowVars = dataRows.value?.[iteration] ?? {};

        for (const request of requests.value) {
            if (cancelled.value) {
                break outer;
            }

            try {
                const response = await runRequest(
                    request.id,
                    environmentId.value,
                    {
                        ...rowVars,
                        ...carried,
                    },
                );

                carried = { ...carried, ...response.variables };

                results.value.push({
                    iteration,
                    requestId: request.id,
                    name: request.name,
                    method: request.method,
                    status: response.status,
                    ok: response.ok,
                    durationMs: response.duration_ms,
                    testResults: response.test_results,
                    error: response.error,
                });
            } catch {
                results.value.push({
                    iteration,
                    requestId: request.id,
                    name: request.name,
                    method: request.method,
                    status: null,
                    ok: false,
                    durationMs: 0,
                    testResults: [],
                    error: 'Request failed to execute',
                });
            }

            if (delayMs.value > 0 && !cancelled.value) {
                await sleep(delayMs.value);
            }
        }
    }

    running.value = false;
    router.reload({ only: ['history'] });
}

function cancel() {
    cancelled.value = true;
}

const summary = computed(() => {
    let passed = 0;
    let total = 0;
    let errors = 0;

    for (const result of results.value) {
        passed += result.testResults.filter((t) => t.passed).length;
        total += result.testResults.length;

        if (!result.ok) {
            errors++;
        }
    }

    return { runs: results.value.length, passed, total, errors };
});

function statusVariant(
    result: RunResult,
): 'default' | 'secondary' | 'destructive' {
    if (result.status === null) {
        return 'destructive';
    }

    if (result.status < 300) {
        return 'default';
    }

    if (result.status < 400) {
        return 'secondary';
    }

    return 'destructive';
}

function exportResults() {
    const blob = new Blob([JSON.stringify(results.value, null, 2)], {
        type: 'application/json',
    });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'run-results.json';
    anchor.click();
    URL.revokeObjectURL(url);
}

function close() {
    if (running.value) {
        cancel();
    }

    emit('update:open', false);
}
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(value) => (value ? emit('update:open', true) : close())"
    >
        <DialogContent
            class="flex max-h-[85vh] flex-col gap-4 overflow-hidden sm:max-w-2xl"
        >
            <DialogHeader>
                <DialogTitle>Run "{{ node.name }}"</DialogTitle>
                <DialogDescription>
                    Runs {{ requests.length }} request{{
                        requests.length === 1 ? '' : 's'
                    }}
                    in this folder, in order.
                </DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Environment</label
                    >
                    <Select
                        :model-value="
                            environmentId ? String(environmentId) : undefined
                        "
                        :disabled="running"
                        @update:model-value="
                            (v) => (environmentId = v ? Number(v) : null)
                        "
                    >
                        <SelectTrigger class="text-xs">
                            <SelectValue placeholder="No environment" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="env in store.environments"
                                :key="env.id"
                                :value="String(env.id)"
                            >
                                {{ env.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Delay between requests (ms)</label
                    >
                    <Input
                        v-model.number="delayMs"
                        type="number"
                        min="0"
                        :disabled="running"
                    />
                </div>

                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Iterations</label
                    >
                    <Input
                        v-model.number="iterations"
                        type="number"
                        min="1"
                        :disabled="running || !!dataRows"
                    />
                </div>

                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Data file (CSV or JSON)</label
                    >
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1 justify-start text-xs"
                            :disabled="running"
                            @click="fileInput?.click()"
                        >
                            <Upload class="size-3.5" />
                            <span class="truncate">{{
                                dataFileName || 'Upload…'
                            }}</span>
                        </Button>
                        <Button
                            v-if="dataRows"
                            variant="ghost"
                            size="sm"
                            :disabled="running"
                            @click="clearDataFile"
                        >
                            Clear
                        </Button>
                    </div>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv,.json,application/json,text/csv"
                        class="hidden"
                        @change="onFileChange"
                    />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button :disabled="running || !requests.length" @click="runAll">
                    <Loader2 v-if="running" class="size-4 animate-spin" />
                    <Play v-else class="size-4" />
                    Run
                </Button>
                <Button v-if="running" variant="outline" @click="cancel">
                    <Square class="size-4" />
                    Stop
                </Button>
                <Button
                    v-if="results.length && !running"
                    variant="outline"
                    @click="exportResults"
                >
                    <Download class="size-4" />
                    Export results
                </Button>

                <div
                    v-if="results.length"
                    class="ml-auto flex items-center gap-3 text-xs"
                >
                    <span class="text-muted-foreground"
                        >{{ summary.runs }} requests run</span
                    >
                    <span
                        v-if="summary.total"
                        :class="
                            summary.passed === summary.total
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-destructive'
                        "
                    >
                        {{ summary.passed }}/{{ summary.total }} tests
                    </span>
                    <span v-if="summary.errors" class="text-destructive"
                        >{{ summary.errors }} failed</span
                    >
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto rounded-md border">
                <div
                    v-if="!results.length"
                    class="flex h-24 items-center justify-center text-sm text-muted-foreground"
                >
                    {{
                        running
                            ? 'Running…'
                            : 'Results will appear here once you run.'
                    }}
                </div>
                <div v-else class="divide-y">
                    <div v-for="(result, index) in results" :key="index">
                        <div
                            class="flex items-center gap-2.5 px-3 py-2 text-sm"
                            :class="
                                result.error || result.testResults.length
                                    ? 'cursor-pointer hover:bg-accent/50'
                                    : ''
                            "
                            @click="toggleExpand(index)"
                        >
                            <ChevronRight
                                class="size-3 shrink-0 text-muted-foreground transition-transform"
                                :class="
                                    cn(
                                        expandedIndex === index && 'rotate-90',
                                        !result.error &&
                                            !result.testResults.length &&
                                            'invisible',
                                    )
                                "
                            />
                            <CheckCircle2
                                v-if="result.ok"
                                class="size-3.5 shrink-0 text-green-600 dark:text-green-400"
                            />
                            <XCircle
                                v-else
                                class="size-3.5 shrink-0 text-destructive"
                            />
                            <span
                                v-if="dataRows || iterations > 1"
                                class="w-6 shrink-0 font-mono text-[10px] text-muted-foreground"
                            >
                                #{{ result.iteration + 1 }}
                            </span>
                            <span
                                class="w-14 shrink-0 font-mono text-[10px] font-semibold text-muted-foreground"
                                >{{ result.method }}</span
                            >
                            <span class="min-w-0 flex-1 truncate">{{
                                result.name
                            }}</span>
                            <span
                                v-if="result.testResults.length"
                                class="shrink-0 font-mono text-xs text-muted-foreground"
                            >
                                {{
                                    result.testResults.filter((t) => t.passed)
                                        .length
                                }}/{{ result.testResults.length }}
                            </span>
                            <Badge
                                :variant="statusVariant(result)"
                                class="shrink-0 font-mono"
                            >
                                {{ result.status ?? 'ERR' }}
                            </Badge>
                            <span
                                class="w-14 shrink-0 text-right font-mono text-xs text-muted-foreground"
                                >{{ result.durationMs }} ms</span
                            >
                        </div>

                        <div
                            v-if="expandedIndex === index"
                            class="flex flex-col gap-2 bg-muted/30 px-3 py-2.5 pl-9 text-xs"
                        >
                            <div
                                v-if="result.error"
                                class="rounded-md border border-destructive/40 bg-destructive/10 p-2 text-destructive"
                            >
                                {{ result.error }}
                            </div>
                            <div
                                v-for="(test, testIndex) in result.testResults"
                                :key="testIndex"
                                class="flex items-start gap-2"
                            >
                                <CheckCircle2
                                    v-if="test.passed"
                                    class="mt-0.5 size-3.5 shrink-0 text-green-600 dark:text-green-400"
                                />
                                <XCircle
                                    v-else
                                    class="mt-0.5 size-3.5 shrink-0 text-destructive"
                                />
                                <div>
                                    <p>{{ test.name }}</p>
                                    <p
                                        v-if="test.message"
                                        class="text-muted-foreground"
                                    >
                                        {{ test.message }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="close">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
