<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Loader2, Play, Save } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { update as updateRequest } from '@/actions/App/Http/Controllers/RequestController';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { api } from '@/lib/api';
import { runRequest } from '@/lib/executeRequest';
import { scripting as scriptingDocs } from '@/routes/docs';
import { useWorkspaceStore } from '@/stores/workspace';
import type {
    AuthType,
    BodyType,
    HttpMethod,
    KeyValuePair,
    RequestAuth,
} from '@/types/workspace';
import AuthEditor from './AuthEditor.vue';
import CodeEditor from './CodeEditor.vue';
import KeyValueEditor from './KeyValueEditor.vue';
import VariableHighlightInput from './VariableHighlightInput.vue';
import VariableInspector from './VariableInspector.vue';
import VariableScopePopover from './VariableScopePopover.vue';

const store = useWorkspaceStore();

const scope = computed(() => store.activeScope);

const methods: HttpMethod[] = [
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'HEAD',
    'OPTIONS',
];
const bodyTypes: { value: BodyType; label: string }[] = [
    { value: 'none', label: 'None' },
    { value: 'raw', label: 'Raw' },
    { value: 'json', label: 'JSON' },
    { value: 'form_data', label: 'Form Data' },
    { value: 'urlencoded', label: 'URL Encoded' },
];

const methodColor: Record<string, string> = {
    GET: 'text-blue-600 dark:text-blue-400',
    POST: 'text-green-600 dark:text-green-400',
    PUT: 'text-amber-600 dark:text-amber-400',
    PATCH: 'text-amber-600 dark:text-amber-400',
    DELETE: 'text-red-600 dark:text-red-400',
    HEAD: 'text-muted-foreground',
    OPTIONS: 'text-muted-foreground',
};

const tab = computed(() => store.activeTab);

const rawBodyText = ref('');

watch(
    () => tab.value?.requestId,
    () => {
        const draft = tab.value?.draft;

        if (!draft) {
            return;
        }

        if (draft.body_type === 'raw') {
            rawBodyText.value = draft.body?.raw ?? '';
        } else if (draft.body_type === 'json') {
            rawBodyText.value =
                draft.body?.json !== undefined
                    ? JSON.stringify(draft.body.json, null, 2)
                    : '';
        } else {
            rawBodyText.value = '';
        }
    },
    { immediate: true },
);

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

function setFormFields(fields: KeyValuePair[]) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { body: { fields } });
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
        <!-- title -->
        <div class="flex items-center gap-2 border-b px-3 py-2">
            <span
                class="size-1.5 shrink-0 rounded-full transition-colors"
                :class="tab.dirty ? 'bg-orange-500' : 'bg-transparent'"
                :title="tab.dirty ? 'Unsaved changes' : ''"
            />
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

        <!-- address bar -->
        <div class="flex items-center gap-2 px-3 py-2.5">
            <Select
                :model-value="tab.draft.method"
                @update:model-value="(v) => setMethod(String(v))"
            >
                <SelectTrigger
                    class="w-24 font-mono text-xs font-semibold"
                    :class="methodColor[tab.draft.method]"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="m in methods"
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
                placeholder="https://api.example.com/users/{{userId}}"
                class="flex-1 font-mono text-sm"
                @update:model-value="setUrl"
            />

            <Button
                variant="outline"
                size="sm"
                :disabled="tab.saving"
                @click="save"
            >
                <Loader2 v-if="tab.saving" class="size-4 animate-spin" />
                <Save v-else class="size-4" />
                Save
            </Button>

            <Button size="sm" :disabled="tab.executing" @click="send">
                <Loader2 v-if="tab.executing" class="size-4 animate-spin" />
                <Play v-else class="size-4" />
                Send
            </Button>
        </div>

        <Tabs
            default-value="params"
            class="flex min-h-0 flex-1 flex-col px-3 pb-3"
        >
            <TabsList>
                <TabsTrigger value="params">Params</TabsTrigger>
                <TabsTrigger value="headers">Headers</TabsTrigger>
                <TabsTrigger value="auth">Auth</TabsTrigger>
                <TabsTrigger value="body">Body</TabsTrigger>
                <TabsTrigger value="scripts">Scripts</TabsTrigger>
                <TabsTrigger value="tests">Tests</TabsTrigger>
            </TabsList>

            <div class="min-h-0 flex-1 overflow-y-auto pt-3">
                <TabsContent value="params">
                    <KeyValueEditor
                        :model-value="tab.draft.query_params"
                        :variables="scope.variables"
                        key-placeholder="Param"
                        @update:model-value="setQueryParams"
                    />
                </TabsContent>

                <TabsContent value="headers" class="flex flex-col gap-4">
                    <!-- inherited / default headers, in the same table shape -->
                    <div v-if="scope.inheritedHeaders.length">
                        <p
                            class="mb-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Inherited from collections
                        </p>
                        <div class="overflow-hidden rounded-md border">
                            <table class="w-full table-fixed border-collapse">
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
                                            Source
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
                        <p class="mt-1.5 text-[11px] text-muted-foreground">
                            Add a header with the same name below to override
                            it.
                        </p>
                    </div>

                    <div>
                        <p
                            class="mb-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Request headers
                        </p>
                        <KeyValueEditor
                            :model-value="tab.draft.headers"
                            :variables="scope.variables"
                            key-placeholder="Header"
                            @update:model-value="setHeaders"
                        />
                    </div>
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
                    <Select
                        :model-value="tab.draft.body_type"
                        @update:model-value="(v) => setBodyType(String(v))"
                    >
                        <SelectTrigger class="w-40 text-xs">
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
                        @update:model-value="setFormFields"
                    />

                    <p v-else class="text-sm text-muted-foreground">
                        This request has no body.
                    </p>
                </TabsContent>

                <TabsContent value="scripts" class="flex h-full flex-col gap-2">
                    <p class="text-xs text-muted-foreground">
                        Runs before the request. Available:
                        <code class="font-mono">pm.variables.set(k, v)</code>,
                        <code class="font-mono"
                            >pm.request.headers.set(k, v)</code
                        >.
                        <Link
                            :href="scriptingDocs()"
                            target="_blank"
                            class="underline hover:text-foreground"
                            >Full scripting reference</Link
                        >
                    </p>
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
                </TabsContent>

                <TabsContent value="tests" class="flex h-full flex-col gap-2">
                    <p class="text-xs text-muted-foreground">
                        Runs after the response. Example:
                        <code class="font-mono"
                            >pm.test("status is 200", pm.response.status ==
                            200)</code
                        >
                        <Link
                            :href="scriptingDocs()"
                            target="_blank"
                            class="underline hover:text-foreground"
                            >Full scripting reference</Link
                        >
                    </p>
                    <CodeEditor
                        :model-value="tab.draft.test_script ?? ''"
                        language="script"
                        :variables="scope.variables"
                        placeholder='pm.test("status is 200", pm.response.status == 200)'
                        class="min-h-48 flex-1"
                        @update:model-value="(v) => setTestScript(String(v))"
                    />
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
