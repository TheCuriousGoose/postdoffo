<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { InheritedAuth } from '@/lib/variableScope';
import type { AuthType, RequestAuth } from '@/types/workspace';
import VariableHighlightInput from './VariableHighlightInput.vue';

const props = defineProps<{
    authType: AuthType | null;
    auth: RequestAuth;
    inheritLabel?: string;
    variables?: Record<string, unknown>;
    /** What this request would inherit from its collection chain, if anything. */
    inheritedAuth?: InheritedAuth | null;
}>();

const authTypeLabels: Record<AuthType, string> = {
    bearer: 'Bearer Token',
    basic: 'Basic Auth',
    apikey: 'API Key',
    none: 'No Auth',
};

const emit = defineEmits<{
    'update:authType': [AuthType | null];
    'update:auth': [RequestAuth];
}>();

const typeOptions: { value: string; label: string }[] = [
    { value: 'inherit', label: 'Inherit from parent' },
    { value: 'none', label: 'No Auth' },
    { value: 'bearer', label: 'Bearer Token' },
    { value: 'basic', label: 'Basic Auth' },
    { value: 'apikey', label: 'API Key' },
];

const selected = computed(() => props.authType ?? 'inherit');

function setType(value: string) {
    if (value === 'inherit') {
        emit('update:authType', null);
        emit('update:auth', null);

        return;
    }

    emit('update:authType', value as AuthType);

    if (value === 'none') {
        emit('update:auth', null);
    } else if (value === 'bearer') {
        emit('update:auth', { token: props.auth?.token ?? '' });
    } else if (value === 'basic') {
        emit('update:auth', {
            username: props.auth?.username ?? '',
            password: props.auth?.password ?? '',
        });
    } else if (value === 'apikey') {
        emit('update:auth', {
            key: props.auth?.key ?? '',
            value: props.auth?.value ?? '',
            in: props.auth?.in ?? 'header',
        });
    }
}

function setField(patch: Partial<NonNullable<RequestAuth>>) {
    emit('update:auth', { ...props.auth, ...patch });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid max-w-xs gap-2">
            <Label>Type</Label>
            <Select
                :model-value="selected"
                @update:model-value="(v) => setType(String(v))"
            >
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in typeOptions"
                        :key="option.value"
                        :value="option.value"
                        >{{ option.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>

        <template v-if="selected === 'inherit'">
            <div
                v-if="inheritedAuth && inheritedAuth.type !== 'none'"
                class="flex items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-2 text-xs"
            >
                <span
                    class="rounded bg-orange-500/15 px-1.5 py-0.5 font-mono font-medium text-orange-600 dark:text-orange-400"
                >
                    {{ authTypeLabels[inheritedAuth.type] }}
                </span>
                <span class="text-muted-foreground">
                    inherited from
                    <span class="font-medium text-foreground">{{
                        inheritedAuth.sourceName
                    }}</span>
                </span>
            </div>
            <p
                v-else-if="inheritedAuth && inheritedAuth.type === 'none'"
                class="text-xs text-muted-foreground"
            >
                <span class="font-medium text-foreground">{{
                    inheritedAuth.sourceName
                }}</span>
                sends no auth, so this request won't either.
            </p>
            <p v-else class="text-xs text-muted-foreground">
                {{
                    inheritLabel ??
                    'Nothing to inherit yet. Set auth on a parent collection and it will apply here automatically.'
                }}
            </p>
        </template>

        <p
            v-else-if="selected === 'none'"
            class="text-xs text-muted-foreground"
        >
            No Authorization header is sent — this overrides any auth inherited
            from a parent collection or folder.
        </p>

        <div v-else-if="selected === 'bearer'" class="grid max-w-sm gap-2">
            <Label for="auth-token">Token</Label>
            <VariableHighlightInput
                id="auth-token"
                :model-value="auth?.token ?? ''"
                :variables="variables"
                placeholder="{{token}}"
                class="font-mono text-sm"
                @update:model-value="(v) => setField({ token: v })"
            />
        </div>

        <div v-else-if="selected === 'basic'" class="grid max-w-sm gap-3">
            <div class="grid gap-2">
                <Label for="auth-username">Username</Label>
                <VariableHighlightInput
                    id="auth-username"
                    :model-value="auth?.username ?? ''"
                    :variables="variables"
                    class="font-mono text-sm"
                    @update:model-value="(v) => setField({ username: v })"
                />
            </div>
            <div class="grid gap-2">
                <Label for="auth-password">Password</Label>
                <Input
                    id="auth-password"
                    type="password"
                    :model-value="auth?.password ?? ''"
                    autocomplete="off"
                    data-lpignore="true"
                    data-1p-ignore="true"
                    data-bwignore="true"
                    data-form-type="other"
                    class="font-mono text-sm"
                    @update:model-value="
                        (v) => setField({ password: String(v) })
                    "
                />
            </div>
        </div>

        <div v-else-if="selected === 'apikey'" class="grid max-w-sm gap-3">
            <div class="grid gap-2">
                <Label for="auth-key">Key</Label>
                <VariableHighlightInput
                    id="auth-key"
                    :model-value="auth?.key ?? ''"
                    :variables="variables"
                    placeholder="X-API-Key"
                    class="font-mono text-sm"
                    @update:model-value="(v) => setField({ key: v })"
                />
            </div>
            <div class="grid gap-2">
                <Label for="auth-value">Value</Label>
                <VariableHighlightInput
                    id="auth-value"
                    :model-value="auth?.value ?? ''"
                    :variables="variables"
                    placeholder="{{api_key}}"
                    class="font-mono text-sm"
                    @update:model-value="(v) => setField({ value: v })"
                />
            </div>
            <div class="grid gap-2">
                <Label>Add to</Label>
                <Select
                    :model-value="auth?.in ?? 'header'"
                    @update:model-value="
                        (v) =>
                            setField({ in: v === 'query' ? 'query' : 'header' })
                    "
                >
                    <SelectTrigger class="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="header">Header</SelectItem>
                        <SelectItem value="query">Query Params</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    </div>
</template>
