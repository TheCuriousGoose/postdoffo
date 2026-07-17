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
import type { AuthType, RequestAuth } from '@/types/workspace';
import VariableHighlightInput from './VariableHighlightInput.vue';

const props = defineProps<{
    authType: AuthType | null;
    auth: RequestAuth;
    inheritLabel?: string;
}>();

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

        <p v-if="selected === 'inherit'" class="text-xs text-muted-foreground">
            {{
                inheritLabel ??
                'Uses the auth configured on the parent collection or folder, if any.'
            }}
        </p>

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
