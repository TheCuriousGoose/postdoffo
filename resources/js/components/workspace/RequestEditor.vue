<script setup lang="ts">
import { Loader2, Play, Save } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { computed, ref, watch } from 'vue';
import {
    execute as executeRequest,
    update as updateRequest,
} from '@/actions/App/Http/Controllers/RequestController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { api } from '@/lib/api';
import { useWorkspaceStore } from '@/stores/workspace';
import type {
    AuthType,
    BodyType,
    HttpMethod,
    KeyValuePair,
    RequestAuth,
} from '@/types/workspace';
import AuthEditor from './AuthEditor.vue';
import KeyValueEditor from './KeyValueEditor.vue';
import VariableHighlightInput from './VariableHighlightInput.vue';

const store = useWorkspaceStore();

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

function setUrl(url: string) {
    if (!tab.value) {
        return;
    }

    store.updateDraft(tab.value.requestId, { url });
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

    store.updateDraft(tab.value.requestId, { query_params });
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
        const url = executeRequest.url(id);
        const withEnv = store.activeEnvironmentId
            ? `${url}?environment_id=${store.activeEnvironmentId}`
            : url;
        const response = await api.post(withEnv);
        store.setResponse(id, response as never);
    } catch {
        toast.error('Request failed to execute');
    } finally {
        store.setExecuting(id, false);
    }
}
</script>

<template>
    <div v-if="tab" class="flex h-full min-h-0 flex-col gap-3 p-3">
        <Input
            :model-value="tab.draft.name"
            class="max-w-sm text-sm font-medium"
            @update:model-value="(v) => setName(String(v))"
        />

        <div class="flex items-center gap-2">
            <Select
                :model-value="tab.draft.method"
                @update:model-value="(v) => setMethod(String(v))"
            >
                <SelectTrigger class="w-28 font-mono text-xs font-semibold">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="m in methods" :key="m" :value="m">{{
                        m
                    }}</SelectItem>
                </SelectContent>
            </Select>

            <VariableHighlightInput
                :model-value="tab.draft.url"
                placeholder="https://api.example.com/users/{{userId}}"
                class="flex-1 font-mono text-sm"
                @update:model-value="setUrl"
            />

            <Button variant="outline" :disabled="tab.saving" @click="save">
                <Loader2 v-if="tab.saving" class="size-4 animate-spin" />
                <Save v-else class="size-4" />
                Save
            </Button>

            <Button :disabled="tab.executing" @click="send">
                <Loader2 v-if="tab.executing" class="size-4 animate-spin" />
                <Play v-else class="size-4" />
                Send
            </Button>
        </div>

        <Tabs default-value="params" class="flex min-h-0 flex-1 flex-col">
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
                        key-placeholder="Param"
                        @update:model-value="setQueryParams"
                    />
                </TabsContent>

                <TabsContent value="headers">
                    <KeyValueEditor
                        :model-value="tab.draft.headers"
                        key-placeholder="Header"
                        @update:model-value="setHeaders"
                    />
                </TabsContent>

                <TabsContent value="auth">
                    <AuthEditor
                        :auth-type="tab.draft.auth_type"
                        :auth="tab.draft.auth"
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
                                >{{ bt.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>

                    <Textarea
                        v-if="
                            tab.draft.body_type === 'raw' ||
                            tab.draft.body_type === 'json'
                        "
                        v-model="rawBodyText"
                        :placeholder="
                            tab.draft.body_type === 'json'
                                ? '{\n  &quot;key&quot;: &quot;{{value}}&quot;\n}'
                                : 'Raw request body'
                        "
                        class="min-h-48 font-mono text-sm"
                    />

                    <KeyValueEditor
                        v-else-if="
                            tab.draft.body_type === 'form_data' ||
                            tab.draft.body_type === 'urlencoded'
                        "
                        :model-value="tab.draft.body?.fields ?? []"
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
                    </p>
                    <Textarea
                        :model-value="tab.draft.pre_request_script ?? ''"
                        class="min-h-48 flex-1 font-mono text-sm"
                        placeholder='pm.variables.set("timestamp", "123")'
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
                    </p>
                    <Textarea
                        :model-value="tab.draft.test_script ?? ''"
                        class="min-h-48 flex-1 font-mono text-sm"
                        placeholder='pm.test("status is 200", pm.response.status == 200)'
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
</template>
