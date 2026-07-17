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
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-2 p-3">
        <div
            v-if="!tab"
            class="flex h-full items-center justify-center text-sm text-muted-foreground"
        >
            No request selected.
        </div>

        <div
            v-else-if="tab.executing"
            class="flex h-full items-center justify-center text-sm text-muted-foreground"
        >
            Sending request…
        </div>

        <div
            v-else-if="!response"
            class="flex h-full items-center justify-center text-sm text-muted-foreground"
        >
            Send a request to see the response here.
        </div>

        <template v-else>
            <div
                v-if="response.error"
                class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
            >
                {{ response.error }}
            </div>

            <div v-else class="flex items-center gap-3 text-sm">
                <Badge :variant="statusColor">{{ response.status }}</Badge>
                <span class="text-muted-foreground"
                    >{{ response.duration_ms }} ms</span
                >
                <span
                    v-if="response.test_results.length"
                    class="text-muted-foreground"
                >
                    {{
                        response.test_results.filter((t) => t.passed).length
                    }}/{{ response.test_results.length }} tests passed
                </span>
            </div>

            <Tabs default-value="body" class="flex min-h-0 flex-1 flex-col">
                <TabsList>
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
                            >{{ prettyBody || '(empty body)' }}</pre>
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
                                <TableRow
                                    v-for="row in headerRows"
                                    :key="row.name"
                                >
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
        </template>
    </div>
</template>
