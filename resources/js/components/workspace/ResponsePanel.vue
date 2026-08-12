<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    Copy,
    Download,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ToolbarButton from '@/components/workspace/ToolbarButton.vue';
import { highlight, tokenClass } from '@/lib/highlight';
import { formatBytes } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace';

const store = useWorkspaceStore();

const tab = computed(() => store.activeTab);
const response = computed(() => tab.value?.response ?? null);

// Bodies past this size skip pretty-printing/highlighting entirely (tokenizing
// renders one <span> per token, so a multi-MB response can produce hundreds of
// thousands of DOM nodes and lock up the tab) — shown as raw text instead.
const MAX_HIGHLIGHT_CHARS = 300_000;
// Bodies past this size aren't rendered at all until the user opts in, since
// even a plain <pre> of several MB of text is enough to make the page stutter.
const MAX_DISPLAY_CHARS = 3_000_000;

const forceShowBody = ref(false);

watch(response, () => {
    forceShowBody.value = false;
});

const bodyLength = computed(() => response.value?.body?.length ?? 0);
const isHugeBody = computed(() => bodyLength.value > MAX_DISPLAY_CHARS);
const shouldRenderBody = computed(
    () => !isHugeBody.value || forceShowBody.value,
);
const isHighlightable = computed(() => bodyLength.value <= MAX_HIGHLIGHT_CHARS);

const formattedBodySize = computed(() => formatBytes(bodyLength.value));

function downloadBody() {
    if (!response.value?.body) {
        return;
    }

    const blob = new Blob([response.value.body], {
        type: contentType.value || 'text/plain',
    });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    // Read off the header rather than off the parsed body, so a response too
    // large to pretty-print still lands with the right extension.
    anchor.download = contentType.value.toLowerCase().includes('json')
        ? 'response.json'
        : 'response.txt';
    anchor.click();
    URL.revokeObjectURL(url);
}

async function copyBody() {
    if (!response.value?.body) {
        return;
    }

    try {
        await navigator.clipboard.writeText(response.value.body);
        toast.success('Response body copied');
    } catch {
        toast.error('Failed to copy response body');
    }
}

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

    if (!body || !shouldRenderBody.value) {
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

    if (!body || !shouldRenderBody.value) {
        return '';
    }

    // Pretty-printing re-parses a JSON body we already parsed in bodyIsJson —
    // duplicate work, but re-parsing a bounded (<= MAX_DISPLAY_CHARS) string is
    // cheap next to the DOM cost the size gate above is actually guarding against.
    try {
        return JSON.stringify(JSON.parse(body), null, 2);
    } catch {
        return body;
    }
});

const bodyTokens = computed(() =>
    isHighlightable.value
        ? highlight(prettyBody.value, {
              mode: bodyIsJson.value ? 'json' : 'text',
          })
        : [],
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

/**
 * Cookies this response set, read off its own Set-Cookie headers. The jar the
 * server keeps is the source of truth for what gets sent next time (see the
 * Cookies dialog in the workspace header); this is the per-response view of
 * what changed it.
 */
const cookieRows = computed(() => {
    const headers = response.value?.headers ?? {};
    const key = Object.keys(headers).find(
        (name) => name.toLowerCase() === 'set-cookie',
    );

    return (key ? headers[key] : []).map((raw) => {
        const [pair, ...attributes] = raw.split(';');
        const equals = pair.indexOf('=');

        return {
            name: equals === -1 ? pair.trim() : pair.slice(0, equals).trim(),
            value: equals === -1 ? '' : pair.slice(equals + 1).trim(),
            attributes: attributes.map((a) => a.trim()).join('; '),
        };
    });
});

const isPreviewableHtml = computed(
    () =>
        contentType.value.toLowerCase().includes('html') &&
        shouldRenderBody.value,
);

const passedCount = computed(
    () => response.value?.test_results.filter((t) => t.passed).length ?? 0,
);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <!--
            Status bar, matching the request panel's header height so the two
            rules across the split line up. Size used to be visible only inside
            the "this body is enormous" warning, and downloading it was only
            possible from that same warning.
        -->
        <!--
            The stats scroll sideways in their own track so that copy/download
            stay pinned and reachable on a narrow screen instead of being pushed
            off the end of the row by the status, timing, size and test counts.
        -->
        <div class="flex h-10 shrink-0 items-center gap-3 border-b px-3">
            <span class="shrink-0 text-xs text-muted-foreground">Response</span>
            <template v-if="response && !response.error">
                <div
                    class="flex min-w-0 flex-1 items-center gap-3 overflow-x-auto"
                >
                    <Badge :variant="statusColor" class="font-mono">{{
                        response.status
                    }}</Badge>
                    <span
                        class="shrink-0 font-mono text-xs text-muted-foreground"
                        >{{ response.duration_ms }} ms</span
                    >
                    <span
                        class="shrink-0 font-mono text-xs text-muted-foreground"
                        >{{ formattedBodySize }}</span
                    >
                    <span
                        v-if="response.test_results.length"
                        class="shrink-0 font-mono text-xs"
                        :class="
                            passedCount === response.test_results.length
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-destructive'
                        "
                    >
                        {{ passedCount }}/{{ response.test_results.length }}
                        tests
                    </span>
                    <span
                        v-if="contentType"
                        class="ml-auto shrink-0 pl-3 font-mono text-xs text-muted-foreground"
                        >{{ contentType }}</span
                    >
                </div>
                <div
                    v-if="bodyLength"
                    class="-mr-2 flex shrink-0 items-center gap-1"
                >
                    <ToolbarButton label="Copy body" @click="copyBody">
                        <Copy class="size-4" />
                    </ToolbarButton>
                    <ToolbarButton label="Download body" @click="downloadBody">
                        <Download class="size-4" />
                    </ToolbarButton>
                </div>
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
            <div class="-mx-1 mt-2 shrink-0 overflow-x-auto px-1 pb-0.5">
                <TabsList>
                    <TabsTrigger value="body">Body</TabsTrigger>
                    <TabsTrigger value="preview">Preview</TabsTrigger>
                    <TabsTrigger value="headers"
                        >Headers ({{ headerRows.length }})</TabsTrigger
                    >
                    <TabsTrigger v-if="cookieRows.length" value="cookies"
                        >Cookies ({{ cookieRows.length }})</TabsTrigger
                    >
                    <TabsTrigger value="tests"
                        >Tests ({{ response.test_results.length }})</TabsTrigger
                    >
                </TabsList>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto pt-3">
                <TabsContent value="body">
                    <div
                        v-if="isHugeBody && !forceShowBody"
                        class="flex flex-col items-start gap-3 rounded-md border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
                    >
                        <div class="flex items-start gap-2">
                            <AlertTriangle
                                class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                            />
                            <p>
                                This response is
                                {{ formattedBodySize }} — rendering it could
                                freeze the page, so it isn't shown by default.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="forceShowBody = true"
                            >
                                Show anyway
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="downloadBody"
                            >
                                Download
                            </Button>
                        </div>
                    </div>
                    <pre
                        v-else
                        class="overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs whitespace-pre-wrap"
                    ><template v-if="prettyBody"><template v-if="isHighlightable"><span
                            v-for="(token, index) in bodyTokens"
                            :key="index"
                            :class="tokenClass(token.type)"
                        >{{ token.text }}</span></template><template v-else>{{ prettyBody }}</template></template><template v-else>(empty body)</template></pre>
                </TabsContent>

                <TabsContent value="preview" class="h-full">
                    <div
                        v-if="isHugeBody && !forceShowBody"
                        class="flex flex-col items-start gap-3 rounded-md border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
                    >
                        <div class="flex items-start gap-2">
                            <AlertTriangle
                                class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                            />
                            <p>
                                This response is
                                {{ formattedBodySize }} — too large to preview
                                by default.
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="forceShowBody = true"
                        >
                            Show anyway
                        </Button>
                    </div>
                    <iframe
                        v-else-if="isPreviewableHtml"
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
                                <TableCell
                                    class="align-top font-mono text-xs"
                                    >{{ row.name }}</TableCell
                                >
                                <!--
                                    A long Set-Cookie or CSP value would
                                    otherwise stretch the table far past the
                                    panel, so values wrap rather than scroll.
                                -->
                                <TableCell
                                    class="font-mono text-xs break-all whitespace-normal"
                                    >{{ row.value }}</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </TabsContent>

                <TabsContent value="cookies">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Value</TableHead>
                                <TableHead>Attributes</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="(cookie, index) in cookieRows"
                                :key="index"
                            >
                                <TableCell class="font-mono text-xs">{{
                                    cookie.name
                                }}</TableCell>
                                <TableCell
                                    class="max-w-40 truncate font-mono text-xs"
                                    >{{ cookie.value }}</TableCell
                                >
                                <TableCell
                                    class="font-mono text-xs text-muted-foreground"
                                    >{{ cookie.attributes }}</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p class="pt-2 text-xs text-muted-foreground">
                        Stored and sent back on matching requests. Manage the
                        whole jar from the cookie button in the workspace
                        header.
                    </p>
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
