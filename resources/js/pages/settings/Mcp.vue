<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, Copy, Plug, Terminal } from '@lucide/vue';
import { ref } from 'vue';
import McpController from '@/actions/App/Http/Controllers/Settings/McpController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import type { McpApp } from '@/components/mcp/McpAppItem.vue';
import McpAppItem from '@/components/mcp/McpAppItem.vue';
import type { McpToken } from '@/components/mcp/McpTokenItem.vue';
import McpTokenItem from '@/components/mcp/McpTokenItem.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/mcp';

const props = defineProps<{
    serverUrl: string;
    tokenEnvName: string;
    personalAccessTokensAvailable: boolean;
    tokens: McpToken[];
    connectedApps: McpApp[];
    newToken: { name: string; value: string; read_only: boolean } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'MCP access', href: edit() }],
    },
});

const copied = ref<string | null>(null);

const copy = async (text: string, key: string) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    window.setTimeout(() => {
        if (copied.value === key) {
            copied.value = null;
        }
    }, 2000);
};

const revokeToken = (id: string, onError: () => void) => {
    router.delete(McpController.destroyToken.url(id), {
        preserveScroll: true,
        onError,
    });
};

const disconnectApp = (clientId: string, onError: () => void) => {
    router.delete(McpController.destroyApp.url(clientId), {
        preserveScroll: true,
        onError,
    });
};

const localCommand = `${props.tokenEnvName}=<token> php artisan mcp:start postdoffo`;
</script>

<template>
    <Head title="MCP access" />

    <h1 class="sr-only">MCP access</h1>

    <div class="space-y-12">
        <div class="space-y-6">
            <Heading
                variant="small"
                title="MCP access"
                description="Let an AI assistant work in your workspaces — building collections, writing test scripts and running them — through the Model Context Protocol."
            />

            <div
                class="rounded-lg border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
            >
                An assistant connected here acts as you. It can reach every
                workspace you are a member of, and your role in each one still
                decides what it may change — it cannot do anything you could not
                do yourself in the app.
            </div>
        </div>

        <!-- Remote clients: OAuth -->
        <section class="space-y-4">
            <Heading
                variant="small"
                title="Connect a hosted assistant"
                description="For clients that connect over the web, such as Claude. Add this server URL and approve the request on the consent screen that follows."
            />

            <div class="flex items-center gap-2">
                <code
                    class="min-w-0 flex-1 truncate rounded-md border border-border bg-muted px-3 py-2 font-mono text-sm"
                >
                    {{ serverUrl }}
                </code>
                <Button
                    variant="secondary"
                    size="sm"
                    type="button"
                    @click="copy(serverUrl, 'url')"
                >
                    <component
                        :is="copied === 'url' ? Check : Copy"
                        class="h-4 w-4"
                    />
                    <span class="sr-only">Copy server URL</span>
                </Button>
            </div>

            <p class="text-sm text-muted-foreground">
                No token needed — the client registers itself and you approve it
                once. Approved apps appear below and can be disconnected at any
                time.
            </p>
        </section>

        <!-- Local clients: stdio -->
        <section class="space-y-4">
            <Heading
                variant="small"
                title="Connect a local assistant"
                description="For clients that run the server as a local process, such as Claude Code. These authenticate with a personal access token instead of the browser flow."
            />

            <div class="flex items-center gap-2">
                <code
                    class="min-w-0 flex-1 overflow-x-auto rounded-md border border-border bg-muted px-3 py-2 font-mono text-sm whitespace-nowrap"
                >
                    {{ localCommand }}
                </code>
                <Button
                    variant="secondary"
                    size="sm"
                    type="button"
                    @click="copy(localCommand, 'command')"
                >
                    <component
                        :is="copied === 'command' ? Check : Copy"
                        class="h-4 w-4"
                    />
                    <span class="sr-only">Copy command</span>
                </Button>
            </div>
        </section>

        <!-- The freshly minted token, shown once -->
        <section
            v-if="newToken"
            class="space-y-3 rounded-lg border border-primary/40 bg-primary/5 p-4"
        >
            <div class="flex items-center gap-2">
                <Terminal class="h-4 w-4 text-primary" />
                <p class="font-medium tracking-tight">
                    Your new token: {{ newToken.name }}
                </p>
            </div>

            <p class="text-sm text-muted-foreground">
                Copy it now — this is the only time it is shown. If you lose it,
                revoke it and create another.
            </p>

            <div class="flex items-center gap-2">
                <code
                    class="min-w-0 flex-1 overflow-x-auto rounded-md border border-border bg-background px-3 py-2 font-mono text-xs whitespace-nowrap"
                >
                    {{ newToken.value }}
                </code>
                <Button
                    size="sm"
                    type="button"
                    @click="copy(newToken.value, 'token')"
                >
                    <component
                        :is="copied === 'token' ? Check : Copy"
                        class="h-4 w-4"
                    />
                    <span class="sr-only">Copy token</span>
                </Button>
            </div>
        </section>

        <!-- Personal access tokens -->
        <section class="space-y-6">
            <Heading
                variant="small"
                title="Personal access tokens"
                description="Tokens you have issued for local MCP clients."
            />

            <div class="overflow-hidden rounded-lg border border-border">
                <template v-if="tokens.length">
                    <McpTokenItem
                        v-for="token in tokens"
                        :key="token.id"
                        :token="token"
                        @revoke="revokeToken"
                    />
                </template>

                <div v-else class="p-8 text-center">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted"
                    >
                        <Terminal class="h-7 w-7 text-muted-foreground" />
                    </div>
                    <p class="font-medium">No tokens yet</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Create one to connect a local MCP client
                    </p>
                </div>
            </div>

            <Form
                v-if="personalAccessTokensAvailable"
                v-bind="McpController.storeToken.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="token-name">Token name</Label>
                    <Input
                        id="token-name"
                        name="name"
                        placeholder="Claude Code on my laptop"
                        required
                        maxlength="255"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="flex items-start gap-3">
                    <Checkbox id="read-only" name="read_only" value="1" />
                    <div class="grid gap-1 leading-none">
                        <Label for="read-only">Read-only</Label>
                        <p class="text-sm text-muted-foreground">
                            The assistant can look at your workspaces but cannot
                            create, change or delete anything, and cannot send
                            requests.
                        </p>
                    </div>
                </div>

                <Button :disabled="processing" data-test="create-mcp-token">
                    Create token
                </Button>
            </Form>

            <p
                v-else
                class="rounded-lg border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
            >
                This installation cannot issue personal access tokens yet. An
                administrator needs to run
                <code class="font-mono"
                    >php artisan passport:client --personal</code
                >. Hosted assistants that connect over the web are unaffected.
            </p>
        </section>

        <!-- OAuth-connected apps -->
        <section class="space-y-6">
            <Heading
                variant="small"
                title="Connected applications"
                description="Assistants you have approved through the browser."
            />

            <div class="overflow-hidden rounded-lg border border-border">
                <template v-if="connectedApps.length">
                    <McpAppItem
                        v-for="app in connectedApps"
                        :key="app.client_id"
                        :app="app"
                        @disconnect="disconnectApp"
                    />
                </template>

                <div v-else class="p-8 text-center">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted"
                    >
                        <Plug class="h-7 w-7 text-muted-foreground" />
                    </div>
                    <p class="font-medium">Nothing connected</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Apps you approve will be listed here
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
