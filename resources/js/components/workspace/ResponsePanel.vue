<script setup lang="ts">
import { CheckCircle2, XCircle } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { highlight, tokenClass } from '@/lib/highlight';
import { useWorkspaceStore } from '@/stores/workspace';

const store = useWorkspaceStore();

const tab = computed(() => store.activeTab);
const response = computed(() => tab.value?.response ?? null);

const statusColor = computed(() => {
    const status = response.value?.status;

    if (status === null || status === undefined) {
        return 'destructive';
    }

    if (status < 300) {
        return 'default';
    }

    if (status < 400) {
        return 'secondary';
    }

    return 'destructive';
});

const bodyIsJson = computed(() => {
    const body = response.value?.body;

    if (!body) {
        return false;
    }

    try {
        JSON.parse(body);

        return true;
    } catch {
        return false;
    }
});

const prettyBody = computed(() => {
    const body = response.value?.body;

    if (!body) {
        return '';
    }

    try {
        return JSON.stringify(JSON.parse(body), null, 2);
    } catch {
        return body;
    }
});

const bodyTokens = computed(() =>
    highlight(prettyBody.value, { json: bodyIsJson.value }),
);

const headerRows = computed(() => {
    const headers = response.value?.headers ?? {};

    return Object.entries(headers).map(([name, values]) => ({
        name,
        value: values.join(', '),
    }));
});

const contentType = computed(() => {
    const headers = response.value?.headers ?? {};
    const key = Object.keys(headers).find(
        (name) => name.toLowerCase() === 'content-type',
    );

    return key ? headers[key].join('; ') : '';
});

const isPreviewableHtml = computed(() =>
    contentType.value.toLowerCase().includes('html'),
);

const passedCount = computed(
    () => response.value?.test_results.filter((t) => t.passed).length ?? 0,
);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <!-- header / status bar -->
        <div class="flex h-10 shrink-0 items-center gap-3 border-b px-3">
            <span
                class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                >Response</span
            >
            <template v-if="response && !response.error">
                <Badge :variant="statusColor" class="font-mono">{{
                    response.status
                }}</Badge>
                <span class="font-mono text-xs text-muted-foreground"
                    >{{ response.duration_ms }} ms</span
                >
                <span
                    v-if="response.test_results.length"
                    class="font-mono text-xs"
                    :class="
                        passedCount === response.test_results.length
                            ? 'text-green-600 dark:text-green-400'
                            : 'text-destructive'
                    "
                >
                    {{ passedCount }}/{{ response.test_results.length }} tests
                </span>
                <span
                    v-if="contentType"
                    class="ml-auto max-w-[45%] truncate font-mono text-xs text-muted-foreground"
                    >{{ contentType }}</span
                >
            </template>
        </div>

        <!-- states -->
        <div
            v-if="!tab"
            class="flex flex-1 items-center justify-center text-sm text-muted-foreground"
        >
            No request selected.
        </div>

        <div
            v-else-if="tab.executing"
            class="flex flex-1 items-center justify-center text-sm text-muted-foreground"
        >
            Sending request…
        </div>

        <div
            v-else-if="!response"
            class="flex flex-1 items-center justify-center text-sm text-muted-foreground"
        >
            Send a request to see the response here.
        </div>

        <div v-else-if="response.error" class="p-3">
            <div
                class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
            >
                {{ response.error }}
            </div>
        </div>

        <Tabs
            v-else
            default-value="body"
            class="flex min-h-0 flex-1 flex-col px-3 pb-3"
        >
            <TabsList class="mt-2">
                <TabsTrigger value="body">Body</TabsTrigger>
                <TabsTrigger value="preview">Preview</TabsTrigger>
                <TabsTrigger value="headers"
                    >Headers ({{ headerRows.length }})</TabsTrigger
                >
                <TabsTrigger value="tests"
                    >Tests ({{ response.test_results.length }})</TabsTrigger
                >
            </TabsList>

            <div class="min-h-0 flex-1 overflow-y-auto pt-3">
                <TabsContent value="body">
                    <pre
                        class="overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs whitespace-pre-wrap"
                    ><template v-if="prettyBody"><span
                            v-for="(token, index) in bodyTokens"
                            :key="index"
                            :class="tokenClass(token.type)"
                        >{{ token.text }}</span></template><template v-else>(empty body)</template></pre>
                </TabsContent>

                <TabsContent value="preview" class="h-full">
                    <iframe
                        v-if="isPreviewableHtml"
                        :srcdoc="response.body ?? ''"
                        sandbox=""
                        title="Response preview"
                        class="h-[70vh] w-full rounded-md border bg-white"
                    />
                    <p v-else class="text-sm text-muted-foreground">
                        Preview is only available for HTML responses.
                        <template v-if="contentType">
                            This response's Content-Type is
                            <code class="font-mono">{{ contentType }}</code
                            >.
                        </template>
                    </p>
                </TabsContent>

                <TabsContent value="headers">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Value</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in headerRows" :key="row.name">
                                <TableCell class="font-mono text-xs">{{
                                    row.name
                                }}</TableCell>
                                <TableCell class="font-mono text-xs">{{
                                    row.value
                                }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </TabsContent>

                <TabsContent value="tests">
                    <ul class="flex flex-col gap-2">
                        <li
                            v-for="(result, index) in response.test_results"
                            :key="index"
                            class="flex items-start gap-2 rounded-md border p-2 text-sm"
                            :class="
                                result.passed
                                    ? 'border-green-500/30 bg-green-500/5'
                                    : 'border-destructive/30 bg-destructive/5'
                            "
                        >
                            <CheckCircle2
                                v-if="result.passed"
                                class="mt-0.5 size-4 shrink-0 text-green-600"
                            />
                            <XCircle
                                v-else
                                class="mt-0.5 size-4 shrink-0 text-destructive"
                            />
                            <div>
                                <p>{{ result.name }}</p>
                                <p
                                    v-if="result.message"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ result.message }}
                                </p>
                            </div>
                        </li>
                        <li
                            v-if="!response.test_results.length"
                            class="text-sm text-muted-foreground"
                        >
                            No tests defined for this request.
                        </li>
                    </ul>
                </TabsContent>
            </div>
        </Tabs>
    </div>
</template>
