<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronRight,
    Loader2,
    Play,
    Save,
    TerminalSquare,
    Wand2,
} from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import {
    curl as curlRequest,
    update as updateRequest,
} from '@/actions/App/Http/Controllers/RequestController';
import {
    destroy as destroyRequestFile,
    store as storeRequestFile,
} from '@/actions/App/Http/Controllers/RequestFileController';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ToolbarButton from '@/components/workspace/ToolbarButton.vue';
import { api, ApiError } from '@/lib/api';
import { runRequest } from '@/lib/executeRequest';
import { httpMethods, methodColor } from '@/lib/http';
import { scripting as scriptingDocs } from '@/routes/docs';
import { useWorkspaceStore } from '@/stores/workspace';
import type {
    AuthType,
    BodyType,
    FormField,
    HttpMethod,
    KeyValuePair,
    RequestAuth,
    RequestFile,
} from '@/types/workspace';
import AuthEditor from './AuthEditor.vue';
import CodeEditor from './CodeEditor.vue';
import KeyValueEditor from './KeyValueEditor.vue';
import VariableHighlightInput from './VariableHighlightInput.vue';
import VariableInspector from './VariableInspector.vue';
import VariableScopePopover from './VariableScopePopover.vue';

const store = useWorkspaceStore();

const scope = computed(() => store.activeScope);

const bodyTypes: { value: BodyType; label: string }[] = [
    { value: 'none', label: 'None' },
    { value: 'raw', label: 'Raw' },
    { value: 'json', label: 'JSON' },
    { value: 'form_data', label: 'Form Data' },
    { value: 'urlencoded', label: 'URL Encoded' },
];

const tab = computed(() => store.activeTab);

// Proxies the active tab's raw body text. It lives on the tab rather than here
// so it survives this component unmounting when the layout switches between the
// split and stacked views — see OpenTab.bodyText.
const rawBodyText = computed({
    get: () => tab.value?.bodyText ?? '',
    set: (value: string) => {
        if (tab.value) {
            store.setBodyText(tab.value.requestId, value);
        }
    },
});

/**
 * What each tab is carrying, surfaced on the tab itself. Without these the only
 * way to find out whether a request had a body, an auth override or a test was
 * to open all six tabs and look — the panels were the only evidence they
 * contained anything.
 */
function filledCount(rows: KeyValuePair[] | null | undefined): number {
    return (rows ?? []).filter(
        (row) => row.enabled !== false && row.key.trim() !== '',
    ).length;
}

const paramCount = computed(() => filledCount(tab.value?.draft.query_params));
const headerCount = computed(() => filledCount(tab.value?.draft.headers));

const hasAuth = computed(() => {
    const type = tab.value?.draft.auth_type;

    return type != null && type !== 'none';
});

const hasBody = computed(() => {
    const type = tab.value?.draft.body_type;

    return type != null && type !== 'none';
});

const hasPreRequestScript = computed(
    () => (tab.value?.draft.pre_request_script ?? '').trim() !== '',
);

const hasTestScript = computed(
    () => (tab.value?.draft.test_script ?? '').trim() !== '',
);

/** Reformat the JSON body in place, leaving invalid JSON untouched. */
function formatJsonBody() {
    if (rawBodyText.value.trim() === '') {
        return;
    }

    try {
        rawBodyText.value = JSON.stringify(
            JSON.parse(rawBodyText.value),
            null,
            2,
        );
    } catch {
        toast.error('Body is not valid JSON');
    }
}

function setMethod(method: string) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { method: method as HttpMethod });
}

/** Split a URL into its base (path) and raw query string. */
function splitUrl(url: string): { base: string; query: string } {
    const index = url.indexOf('?');

    return index === -1
        ? { base: url, query: '' }
        : { base: url.slice(0, index), query: url.slice(index + 1) };
}

/** Parse a raw query string into rows, leaving {{variables}} untouched. */
function parseQuery(query: string): KeyValuePair[] {
    if (query === '') {
        return [];
    }

    return query.split('&').map((part) => {
        const eq = part.indexOf('=');

        return {
            key: eq === -1 ? part : part.slice(0, eq),
            value: eq === -1 ? '' : part.slice(eq + 1),
            enabled: true,
        };
    });
}

/** Rebuild a query string from the enabled, non-blank rows. */
function buildQuery(params: KeyValuePair[]): string {
    return params
        .filter((p) => p.enabled !== false && p.key.trim() !== '')
        .map((p) => `${p.key}=${p.value}`)
        .join('&');
}

// The URL bar and the Params table are two views of the same query string, so
// editing either keeps the other in sync — just like Postman.
function setUrl(url: string) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, {
        url,
        query_params: parseQuery(splitUrl(url).query),
    });
}

function setName(name: string) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { name });
}

function setHeaders(headers: KeyValuePair[]) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { headers });
}

function setQueryParams(query_params: KeyValuePair[]) {
    if (!tab.value) {
        return;
    }

    const base = splitUrl(tab.value.draft.url).base;
    const query = buildQuery(query_params);

    store.updateDraft(tab.value.requestId, {
        query_params,
        url: query ? `${base}?${query}` : base,
    });
}

function setAuthType(auth_type: AuthType | null) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { auth_type });
}

function setAuth(auth: RequestAuth) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { auth });
}

function setBodyType(bodyType: string) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { body_type: bodyType as BodyType });
}

function setFormFields(fields: FormField[]) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { body: { fields } });
}

/**
 * Stores a picked file against the request straight away, before the request
 * itself is saved — the field only ever carries the id it gets back, so the
 * upload has to exist server-side first for saving the body to mean anything.
 */
async function uploadFormFile(file: File): Promise<RequestFile> {
    if (!tab.value) {
        throw new Error('No request open');
    }

    try {
        return await api.upload<RequestFile>(
            storeRequestFile.url(tab.value.requestId),
            'file',
            file,
        );
    } catch (error) {
        toast.error(
            error instanceof ApiError && error.status === 422
                ? 'That file is too large to upload'
                : 'Failed to upload file',
        );

        throw error;
    }
}

/**
 * Copies the request as a curl command. The snippet is built server-side from
 * the same resolved payload a send would use, so what lands on the clipboard
 * runs as-is in a terminal — variables interpolated, auth already computed —
 * rather than being a template full of {{placeholders}}.
 */
async function copyAsCurl() {
    if (!tab.value || !commitBodyText()) {
        return;
    }

    const id = tab.value.requestId;

    if (tab.value.dirty) {
        await save();
    }

    try {
        const environmentId = store.activeEnvironmentId;
        const { command } = await api.get<{ command: string }>(
            curlRequest.url(id) +
                (environmentId ? `?environment_id=${environmentId}` : ''),
        );

        await navigator.clipboard.writeText(command);
        toast.success('Copied as cURL');
    } catch {
        toast.error('Failed to copy as cURL');
    }
}

async function deleteFormFile(fileId: number) {
    try {
        await api.delete(destroyRequestFile.url(fileId));
    } catch {
        toast.error('Failed to remove file');
    }
}

function setPreRequestScript(pre_request_script: string) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { pre_request_script });
}

function setTestScript(test_script: string) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { test_script });
}

/** Commit the raw text editor content into the draft's body shape before save/send. */
function commitBodyText(): boolean {
    if (!tab.value) {
        return true;
    }

    const draft = tab.value.draft;

    if (draft.body_type === 'raw') {
        store.updateDraft(tab.value.requestId, {
            body: { raw: rawBodyText.value },
        });

        return true;
    }

    if (draft.body_type === 'json') {
        if (rawBodyText.value.trim() === '') {
            store.updateDraft(tab.value.requestId, { body: { json: {} } });

            return true;
        }

        try {
            const parsed = JSON.parse(rawBodyText.value);
            store.updateDraft(tab.value.requestId, { body: { json: parsed } });

            return true;
        } catch {
            toast.error('Body is not valid JSON');

            return false;
        }
    }

    return true;
}

async function save() {
    if (!tab.value || !commitBodyText()) {
        return;
    }

    const id = tab.value.requestId;
    store.setSaving(id, true);

    try {
        const draft = tab.value.draft;
        const saved = await api.patch(updateRequest.url(id), {
            name: draft.name,
            method: draft.method,
            url: draft.url,
            headers: draft.headers,
            query_params: draft.query_params,
            body: draft.body,
            body_type: draft.body_type,
            auth_type: draft.auth_type,
            auth: draft.auth,
            pre_request_script: draft.pre_request_script,
            test_script: draft.test_script,
        });

        store.markSaved(id, saved as typeof draft);
        toast.success('Request saved');
    } catch {
        toast.error('Failed to save request');
    } finally {
        store.setSaving(id, false);
    }
}

async function send() {
    if (!tab.value || !commitBodyText()) {
        return;
    }

    const id = tab.value.requestId;

    if (tab.value.dirty) {
        await save();
    }

    store.setExecuting(id, true);

    try {
        const response = await runRequest(id, store.activeEnvironmentId);

        store.setResponse(id, response);
    } catch {
        toast.error('Request failed to execute');
    } finally {
        store.setExecuting(id, false);
    }
}
</script>

<template>
    <div v-if="tab" class="flex h-full min-h-0 flex-col">
        <!--
            Unsaved state lives on the tab's dot and on whether Save is
            available; it used to also have a dot of its own here, which meant
            two indicators for one fact, neither of them actionable.
        -->
        <div class="flex h-10 shrink-0 items-center gap-2 border-b px-3">
            <input
                :value="tab.draft.name"
                placeholder="Request name"
                autocomplete="off"
                data-lpignore="true"
                data-1p-ignore="true"
                data-bwignore="true"
                data-form-type="other"
                class="min-w-0 flex-1 bg-transparent text-sm font-medium outline-none placeholder:text-muted-foreground/70"
                @input="(e) => setName((e.target as HTMLInputElement).value)"
            />
            <VariableScopePopover />
        </div>

        <!--
            Address bar: every control on the same h-8 chrome scale. Below sm
            the method + URL keep a row to themselves and the actions drop
            underneath — squeezed onto one line the URL field is too narrow to
            read back what you typed, which is the one thing it exists for.
        -->
        <div
            class="flex shrink-0 flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center"
        >
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <Select
                    :model-value="tab.draft.method"
                    @update:model-value="(v) => setMethod(String(v))"
                >
                    <SelectTrigger
                        size="sm"
                        class="w-24 shrink-0 font-mono text-xs font-semibold"
                        :class="methodColor[tab.draft.method]"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="m in httpMethods"
                            :key="m"
                            :value="m"
                            class="font-mono text-xs font-semibold"
                            :class="methodColor[m]"
                            >{{ m }}</SelectItem
                        >
                    </SelectContent>
                </Select>

                <VariableHighlightInput
                    :model-value="tab.draft.url"
                    :variables="scope.variables"
                    size="sm"
                    placeholder="https://api.example.com/users/{{userId}}"
                    class="min-w-0 flex-1 font-mono text-sm"
                    @update:model-value="setUrl"
                />
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <ToolbarButton label="Copy as cURL" @click="copyAsCurl">
                    <TerminalSquare class="size-4" />
                </ToolbarButton>

                <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 sm:flex-none"
                    :disabled="tab.saving || !tab.dirty"
                    :title="tab.dirty ? 'Save changes' : 'No unsaved changes'"
                    @click="save"
                >
                    <Loader2 v-if="tab.saving" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    Save
                </Button>

                <Button
                    size="sm"
                    class="flex-1 sm:flex-none"
                    :disabled="tab.executing"
                    @click="send"
                >
                    <Loader2 v-if="tab.executing" class="size-4 animate-spin" />
                    <Play v-else class="size-4" />
                    Send
                </Button>
            </div>
        </div>

        <Tabs
            default-value="params"
            class="flex min-h-0 flex-1 flex-col px-3 pb-3"
        >
            <!--
                Counts for the things you can count, a dot for the things that
                are simply on or off, so the tab row is a summary of the request
                rather than six identical words.
            -->
            <!--
                Six tabs do not fit across a phone, so the strip scrolls
                sideways rather than wrapping into a second row that would eat
                the editor's height.
            -->
            <div class="-mx-1 shrink-0 overflow-x-auto px-1 pb-0.5">
                <TabsList>
                    <TabsTrigger value="params">
                        Params
                        <span
                            v-if="paramCount"
                            class="font-mono text-[10px] text-muted-foreground"
                            >{{ paramCount }}</span
                        >
                    </TabsTrigger>
                    <TabsTrigger value="auth">
                        Auth
                        <span
                            v-if="hasAuth"
                            class="size-1.5 rounded-full bg-muted-foreground"
                        />
                    </TabsTrigger>
                    <TabsTrigger value="headers">
                        Headers
                        <span
                            v-if="headerCount"
                            class="font-mono text-[10px] text-muted-foreground"
                            >{{ headerCount }}</span
                        >
                    </TabsTrigger>
                    <TabsTrigger value="body">
                        Body
                        <span
                            v-if="hasBody"
                            class="size-1.5 rounded-full bg-muted-foreground"
                        />
                    </TabsTrigger>
                    <TabsTrigger value="scripts">
                        Pre-request
                        <span
                            v-if="hasPreRequestScript"
                            class="size-1.5 rounded-full bg-muted-foreground"
                        />
                    </TabsTrigger>
                    <TabsTrigger value="tests">
                        Tests
                        <span
                            v-if="hasTestScript"
                            class="size-1.5 rounded-full bg-muted-foreground"
                        />
                    </TabsTrigger>
                </TabsList>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto pt-3">
                <TabsContent value="params">
                    <KeyValueEditor
                        :model-value="tab.draft.query_params"
                        :variables="scope.variables"
                        key-placeholder="Param"
                        @update:model-value="setQueryParams"
                    />
                    <p class="mt-2 text-xs text-muted-foreground">
                        These are the query string in the URL bar, split into
                        rows. Editing either updates the other.
                    </p>
                </TabsContent>

                <TabsContent value="headers" class="flex flex-col gap-3">
                    <KeyValueEditor
                        :model-value="tab.draft.headers"
                        :variables="scope.variables"
                        key-placeholder="Header"
                        @update:model-value="setHeaders"
                    />

                    <!--
                        Inherited headers are reference material, not something
                        you edit here, so they fold away instead of pushing the
                        editable table down the panel on every request that has
                        a parent collection.
                    -->
                    <Collapsible v-if="scope.inheritedHeaders.length">
                        <CollapsibleTrigger
                            class="group flex w-full items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <ChevronRight
                                class="size-3.5 transition-transform group-data-[state=open]:rotate-90"
                            />
                            {{ scope.inheritedHeaders.length }} header{{
                                scope.inheritedHeaders.length === 1 ? '' : 's'
                            }}
                            inherited from parent collections
                        </CollapsibleTrigger>
                        <CollapsibleContent class="pt-2">
                            <div class="overflow-hidden rounded-md border">
                                <table
                                    class="w-full table-fixed border-collapse"
                                >
                                    <thead>
                                        <tr
                                            class="border-b bg-muted/40 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            <th
                                                class="border-r px-2.5 py-1.5 text-left"
                                            >
                                                Header
                                            </th>
                                            <th
                                                class="border-r px-2.5 py-1.5 text-left"
                                            >
                                                Value
                                            </th>
                                            <th
                                                class="w-32 px-2.5 py-1.5 text-left"
                                            >
                                                From
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="header in scope.inheritedHeaders"
                                            :key="header.key"
                                            class="border-b font-mono text-xs last:border-b-0"
                                        >
                                            <td
                                                class="truncate border-r px-2.5 py-1.5"
                                            >
                                                {{ header.key }}
                                            </td>
                                            <td
                                                class="truncate border-r px-2.5 py-1.5 text-muted-foreground"
                                            >
                                                {{ header.value }}
                                            </td>
                                            <td
                                                class="truncate px-2.5 py-1.5 text-muted-foreground"
                                            >
                                                {{ header.sourceName }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Add a header with the same name above to
                                override one of these.
                            </p>
                        </CollapsibleContent>
                    </Collapsible>
                </TabsContent>

                <TabsContent value="auth">
                    <AuthEditor
                        :auth-type="tab.draft.auth_type"
                        :auth="tab.draft.auth"
                        :variables="scope.variables"
                        :inherited-auth="scope.inheritedAuth"
                        @update:auth-type="setAuthType"
                        @update:auth="setAuth"
                    />
                </TabsContent>

                <TabsContent value="body" class="flex flex-col gap-3">
                    <!-- body toolbar: what kind of body, and acting on it -->
                    <div class="flex items-center gap-2">
                        <Select
                            :model-value="tab.draft.body_type"
                            @update:model-value="(v) => setBodyType(String(v))"
                        >
                            <SelectTrigger size="sm" class="w-40 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="bt in bodyTypes"
                                    :key="bt.value"
                                    :value="bt.value"
                                    >{{ bt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Button
                            v-if="tab.draft.body_type === 'json'"
                            variant="ghost"
                            size="sm"
                            class="ml-auto"
                            @click="formatJsonBody"
                        >
                            <Wand2 class="size-3.5" />
                            Format
                        </Button>
                    </div>

                    <CodeEditor
                        v-if="
                            tab.draft.body_type === 'raw' ||
                            tab.draft.body_type === 'json'
                        "
                        v-model="rawBodyText"
                        :language="
                            tab.draft.body_type === 'json' ? 'json' : 'text'
                        "
                        :variables="scope.variables"
                        :placeholder="
                            tab.draft.body_type === 'json'
                                ? 'JSON request body'
                                : 'Raw request body'
                        "
                        class="min-h-48"
                    />

                    <KeyValueEditor
                        v-else-if="
                            tab.draft.body_type === 'form_data' ||
                            tab.draft.body_type === 'urlencoded'
                        "
                        :model-value="tab.draft.body?.fields ?? []"
                        :variables="scope.variables"
                        :allow-files="tab.draft.body_type === 'form_data'"
                        :upload-file="uploadFormFile"
                        :delete-file="deleteFormFile"
                        @update:model-value="setFormFields"
                    />

                    <p
                        v-else
                        class="rounded-md border border-dashed px-4 py-6 text-center text-xs text-muted-foreground"
                    >
                        No body is sent with this request. Pick a type above to
                        add one.
                    </p>
                </TabsContent>

                <!--
                    Editor first, reference underneath. The cheat sheet used to
                    sit on top, so the editor started a paragraph down the panel
                    and every glance at a script had to step over the same three
                    lines of documentation.
                -->
                <TabsContent value="scripts" class="flex h-full flex-col gap-2">
                    <CodeEditor
                        :model-value="tab.draft.pre_request_script ?? ''"
                        language="script"
                        :variables="scope.variables"
                        placeholder='pm.variables.set("timestamp", "123")'
                        class="min-h-48 flex-1"
                        @update:model-value="
                            (v) => setPreRequestScript(String(v))
                        "
                    />
                    <p class="shrink-0 text-xs text-muted-foreground">
                        Runs before the request.
                        <code class="font-mono">pm.variables.set(k, v)</code>,
                        <code class="font-mono"
                            >pm.request.headers.set(k, v)</code
                        >.
                        <Link
                            :href="scriptingDocs()"
                            target="_blank"
                            class="underline hover:text-foreground"
                            >Scripting reference</Link
                        >
                    </p>
                </TabsContent>

                <TabsContent value="tests" class="flex h-full flex-col gap-2">
                    <CodeEditor
                        :model-value="tab.draft.test_script ?? ''"
                        language="script"
                        :variables="scope.variables"
                        placeholder='pm.test("status is 200", pm.response.status == 200)'
                        class="min-h-48 flex-1"
                        @update:model-value="(v) => setTestScript(String(v))"
                    />
                    <p class="shrink-0 text-xs text-muted-foreground">
                        Runs after the response, and the results show in the
                        Tests tab below.
                        <code class="font-mono"
                            >pm.test("status is 200", pm.response.status ==
                            200)</code
                        >.
                        <Link
                            :href="scriptingDocs()"
                            target="_blank"
                            class="underline hover:text-foreground"
                            >Scripting reference</Link
                        >
                    </p>
                </TabsContent>
            </div>
        </Tabs>
    </div>

    <div
        v-else
        class="flex h-full items-center justify-center text-sm text-muted-foreground"
    >
        Select or create a request to get started.
    </div>

    <VariableInspector />
</template>
