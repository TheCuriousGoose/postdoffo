<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Boxes,
    Check,
    CheckCircle2,
    Globe,
    History,
    KeyRound,
    Layers,
    Play,
    Plus,
    Settings2,
    ShieldCheck,
    Upload,
    Users,
    XCircle,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import IconGithub from '@/components/IconGithub.vue';
import { dashboard, login, register } from '@/routes';
import { scripting } from '@/routes/docs';
import { privacy, terms } from '@/routes/legal';

// Public source repository. Surfaced in the header and footer so the
// open-source story is one click away and search engines can tie the
// entity to its repo (see Organization.sameAs in app.blade.php).
const github = 'https://github.com/TheCuriousGoose/postdoffo';

// Literal variable tokens, kept out of the template so the `}}` doesn't
// terminate Vue's mustache interpolation early.
const varBaseUrl = '{{base_url}}';
const varPlaceholder = '{{ variables }}';

const tickerTokens = [
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'HEAD',
    'OPTIONS',
    '200 OK',
    '201 CREATED',
    '204',
    '301',
    '400',
    '401',
    '404',
    '429',
    '500',
    'Bearer',
    'Basic',
    'API key',
    'application/json',
    '{{base_url}}',
    '{{token}}',
];

const features = [
    {
        icon: Boxes,
        title: 'Workspaces',
        description:
            'Every project gets its own space. Collections, environments and history stay neatly apart, never tangled together.',
        span: 'md:col-span-6',
        lead: true,
    },
    {
        icon: Layers,
        title: 'Nested collections',
        description:
            'Fold requests into collections and sub-folders. Headers and auth cascade down the whole tree.',
        span: 'md:col-span-6',
    },
    {
        icon: Globe,
        title: 'Environments',
        description:
            'Reusable variables per workspace. Flip between dev, staging and production in a click.',
        span: 'md:col-span-4',
    },
    {
        icon: History,
        title: 'Request history',
        description:
            'Every call is recorded with its response. Jump back and replay any of them instantly.',
        span: 'md:col-span-4',
    },
    {
        icon: Users,
        title: 'Team roles',
        description:
            'Invite people as editors or viewers. Everyone works from the same source of truth.',
        span: 'md:col-span-4',
    },
    {
        icon: ShieldCheck,
        title: 'Auth, built in',
        description:
            'Bearer, Basic and API key, set per request or inherited from a parent collection. No plugins, no scripts to copy around.',
        span: 'md:col-span-12',
        wide: true,
    },
];

const steps = [
    {
        icon: Upload,
        title: 'Import or start fresh',
        description:
            'Drop in a Postman export or build your first collection by hand. Organized in seconds.',
    },
    {
        icon: Play,
        title: 'Send and inspect',
        description:
            'Fire a request, read the status, timing and body, and watch your assertions run.',
    },
    {
        icon: Users,
        title: 'Share with your team',
        description:
            'Invite teammates and keep every collection and environment in sync.',
    },
];

const testResults = [
    { label: 'status is 200', ok: true },
    { label: 'response under 500 ms', ok: true },
    { label: 'body has an id', ok: true },
    { label: 'currency equals usd', ok: false },
];

const faqs = [
    {
        question: 'Is PostDoffo really free?',
        answer: 'Yes. Every feature is on the free plan: unlimited workspaces, collections, environments and team members. There is no paid tier and no seat counting.',
    },
    {
        question: 'Can I import my existing Postman collections?',
        answer: 'Import any Postman v2.1 export and the full tree comes across intact: nested folders, requests, headers and auth all land in place.',
    },
    {
        question: 'How do environment variables work?',
        answer: 'Reference variables like {{base_url}} or {{token}} anywhere in a request. Switch environments to swap every value at once, and mark sensitive values as secret.',
    },
    {
        question: 'Can I write tests for my requests?',
        answer: 'Each request has a pre-request script and a test script. Assertions run every time you send, and results appear next to the response as pass or fail.',
    },
    {
        question: 'Who can see my requests and secrets?',
        answer: 'Only you and the teammates you invite to a workspace. Roles decide whether a member can edit or only view. See the privacy policy for how data is stored.',
    },
];

const navLinks = [
    { href: '#features', label: 'Features' },
    { href: '#testing', label: 'Testing' },
    { href: '#faq', label: 'FAQ' },
];
</script>

<template>
    <Head title="Free, open-source Postman alternative for teams" />

    <div class="min-h-svh bg-background font-sans text-foreground">
        <!-- Nav -->
        <header
            class="sticky top-0 z-50 border-b border-border bg-background/85 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-6 px-6"
            >
                <Link :href="'/'" class="flex items-center gap-2">
                    <AppLogo />
                </Link>

                <nav
                    class="hidden items-center gap-8 font-mono text-xs text-muted-foreground md:flex"
                >
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="tracking-wide transition hover:text-foreground"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="flex items-center gap-2">
                    <a
                        :href="github"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground transition hover:text-foreground"
                        title="PostDoffo on GitHub"
                    >
                        <IconGithub class="size-5" />
                        <span class="sr-only">PostDoffo source code on GitHub</span>
                    </a>
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="dashboard()"
                            class="inline-flex items-center gap-1.5 rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-orange-400"
                        >
                            Dashboard
                            <ArrowRight class="size-4" />
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex items-center gap-1.5 rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-orange-400"
                        >
                            Start building
                            <ArrowRight class="size-4" />
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden border-b border-border">
            <!-- dotted technical canvas -->
            <div
                class="pointer-events-none absolute inset-0 -z-10 [background-image:radial-gradient(var(--border)_1px,transparent_1px)] [mask-image:linear-gradient(to_bottom,black,transparent_85%)] [background-size:22px_22px] opacity-70"
                aria-hidden="true"
            />

            <div class="mx-auto max-w-6xl px-6 pt-20 pb-16 lg:pt-28">
                <div class="grid gap-14 lg:grid-cols-12 lg:gap-10">
                    <div class="lg:col-span-6">
                        <p
                            class="flex items-center gap-2 font-mono text-xs tracking-widest text-muted-foreground uppercase"
                        >
                            <span class="h-px w-6 bg-orange-500" />
                            Open-source HTTP client for teams
                        </p>

                        <h1
                            class="mt-6 font-display text-5xl leading-[1.02] font-semibold tracking-tight text-balance sm:text-6xl"
                        >
                            Build and ship APIs
                            <br class="hidden sm:block" />
                            without the busywork.
                        </h1>

                        <p
                            class="mt-6 max-w-md text-lg leading-relaxed text-pretty text-muted-foreground"
                        >
                            PostDoffo keeps your collections, environments,
                            secrets and team in one fast workspace. No installs,
                            no config files.
                        </p>

                        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <Link
                                :href="
                                    $page.props.auth.user
                                        ? dashboard()
                                        : register()
                                "
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-500 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-orange-400"
                            >
                                Start building
                                <ArrowRight class="size-4" />
                            </Link>
                            <a
                                :href="github"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-6 py-3 text-sm font-semibold transition hover:bg-accent"
                            >
                                <IconGithub class="size-4" />
                                Self-host
                            </a>
                        </div>

                        <p class="mt-5 font-mono text-xs text-muted-foreground">
                            Free and open source. Import your Postman
                            collections in seconds.
                        </p>
                    </div>

                    <!-- editor-style request panel -->
                    <div class="lg:col-span-6">
                        <div
                            class="overflow-hidden rounded-xl border border-border bg-card shadow-xl shadow-black/5 dark:shadow-black/40"
                        >
                            <!-- tab bar -->
                            <div
                                class="flex items-center gap-1 border-b border-border bg-muted/40 px-3 pt-2"
                            >
                                <div
                                    class="flex items-center gap-2 rounded-t-md border-x border-t border-border bg-card px-3 py-2 font-mono text-xs"
                                >
                                    <span
                                        class="text-green-600 dark:text-green-400"
                                        >POST</span
                                    >
                                    <span class="text-muted-foreground"
                                        >charges</span
                                    >
                                </div>
                                <div
                                    class="px-3 py-2 font-mono text-xs text-muted-foreground/60"
                                >
                                    customers
                                </div>
                            </div>

                            <!-- request line -->
                            <div class="flex items-center gap-2 p-3">
                                <span
                                    class="rounded bg-green-500/10 px-2 py-1.5 font-mono text-xs font-bold text-green-600 dark:text-green-400"
                                    >POST</span
                                >
                                <div
                                    class="min-w-0 flex-1 truncate rounded-md border border-border bg-background px-3 py-1.5 font-mono text-sm text-muted-foreground"
                                >
                                    <span
                                        class="text-orange-600 dark:text-orange-400"
                                        >{{ varBaseUrl }}</span
                                    >/v1/charges
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-orange-500 px-3 py-1.5 font-mono text-xs font-bold text-stone-950"
                                >
                                    <Play class="size-3.5" /> SEND
                                </button>
                            </div>

                            <!-- status row -->
                            <div
                                class="flex flex-wrap items-center gap-x-4 gap-y-1 border-y border-border bg-muted/30 px-3 py-2 font-mono text-xs"
                            >
                                <span
                                    class="flex items-center gap-1.5 font-semibold text-green-600 dark:text-green-400"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-green-500"
                                    />
                                    200 OK
                                </span>
                                <span class="text-muted-foreground"
                                    >128 ms</span
                                >
                                <span class="text-muted-foreground"
                                    >1.2 KB</span
                                >
                                <span class="ml-auto text-muted-foreground/70"
                                    >application/json</span
                                >
                            </div>

                            <!-- response body with line numbers -->
                            <div class="flex font-mono text-xs leading-relaxed">
                                <div
                                    class="shrink-0 border-r border-border py-3 pr-3 pl-4 text-right text-muted-foreground/40 select-none"
                                >
                                    <div v-for="n in 6" :key="n">{{ n }}</div>
                                </div>
                                <pre
                                    class="overflow-x-auto py-3 pl-4"
                                ><span class="text-muted-foreground">{</span>
  <span class="text-orange-600 dark:text-orange-400">"id"</span>: <span class="text-green-600 dark:text-green-400">"ch_1M8fq2"</span>,
  <span class="text-orange-600 dark:text-orange-400">"amount"</span>: <span class="text-sky-600 dark:text-sky-400">4200</span>,
  <span class="text-orange-600 dark:text-orange-400">"currency"</span>: <span class="text-green-600 dark:text-green-400">"eur"</span>,
  <span class="text-orange-600 dark:text-orange-400">"status"</span>: <span class="text-green-600 dark:text-green-400">"succeeded"</span>
<span class="text-muted-foreground">}</span></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- methods ticker -->
            <div class="border-t border-border">
                <div class="ticker-mask overflow-hidden py-3">
                    <div class="ticker-track flex w-max gap-2.5">
                        <span
                            v-for="(token, i) in [
                                ...tickerTokens,
                                ...tickerTokens,
                            ]"
                            :key="i"
                            class="rounded border border-border px-2.5 py-1 font-mono text-xs text-muted-foreground"
                        >
                            {{ token }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="scroll-mt-20 border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <div
                    class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between"
                >
                    <h2
                        class="max-w-xl font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        Everything the job needs.
                        <span class="text-muted-foreground"
                            >Nothing it doesn't.</span
                        >
                    </h2>
                    <p class="max-w-sm text-sm text-muted-foreground">
                        A focused toolkit for working with APIs, with defaults
                        that stay out of your way.
                    </p>
                </div>

                <div
                    class="mt-12 grid grid-cols-1 gap-px overflow-hidden rounded-xl border border-border bg-border md:grid-cols-12"
                >
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        :class="feature.span"
                        class="group flex flex-col bg-background p-8 transition-colors hover:bg-card"
                    >
                        <div
                            :class="feature.wide ? 'sm:flex-row sm:gap-6' : ''"
                            class="flex flex-1 flex-col"
                        >
                            <component
                                :is="feature.icon"
                                class="size-6 text-orange-500"
                                :stroke-width="1.75"
                            />
                            <div
                                :class="feature.wide ? 'sm:mt-0' : 'mt-5'"
                                class="flex-1"
                            >
                                <h3
                                    :class="feature.lead ? 'text-lg' : ''"
                                    class="font-display font-semibold tracking-tight"
                                >
                                    {{ feature.title }}
                                </h3>
                                <p
                                    class="mt-2 max-w-md text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ feature.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Environments & variables -->
        <section id="environments" class="scroll-mt-20 border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <div class="grid items-center gap-14 lg:grid-cols-2">
                    <!-- copy -->
                    <div>
                        <p
                            class="flex items-center gap-2 font-mono text-xs tracking-widest text-muted-foreground uppercase"
                        >
                            <span class="h-px w-6 bg-orange-500" />
                            Environments
                        </p>
                        <h2
                            class="mt-6 font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            One request, every environment
                        </h2>
                        <p class="mt-4 max-w-md text-muted-foreground">
                            Drop
                            <code
                                class="rounded bg-muted px-1.5 py-0.5 font-mono text-sm text-orange-600 dark:text-orange-400"
                                >{{ varPlaceholder }}</code
                            >
                            into any URL, header or body. Switch environments to
                            repoint every request at once, and keep tokens out
                            of sight by marking them secret.
                        </p>
                        <ul class="mt-8 space-y-4 text-sm">
                            <li
                                v-for="item in [
                                    'Reusable variables scoped per workspace',
                                    'Swap dev, staging and production in a click',
                                    'Secret values masked everywhere in the UI',
                                ]"
                                :key="item"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="flex size-5 shrink-0 items-center justify-center rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400"
                                >
                                    <Check class="size-3.5" />
                                </span>
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <!-- visual -->
                    <div
                        class="rounded-xl border border-border bg-card p-5 shadow-lg shadow-black/5 dark:shadow-black/30"
                    >
                        <div
                            class="flex items-center justify-between rounded-lg border border-border bg-background px-3 py-2"
                        >
                            <div class="flex items-center gap-2 text-sm">
                                <Settings2 class="size-4 text-orange-500" />
                                <span class="font-medium">Production</span>
                            </div>
                            <span
                                class="rounded-full bg-green-500/15 px-2 py-0.5 font-mono text-[11px] text-green-600 dark:text-green-400"
                            >
                                active
                            </span>
                        </div>

                        <div
                            class="mt-4 flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 font-mono text-sm"
                        >
                            <span
                                class="rounded bg-sky-500/10 px-1.5 py-0.5 text-xs font-bold text-sky-600 dark:text-sky-400"
                                >GET</span
                            >
                            <span class="truncate text-muted-foreground">
                                <span
                                    class="rounded bg-orange-500/15 px-1 text-orange-600 dark:text-orange-400"
                                    >{{ varBaseUrl }}</span
                                >/v1/customers</span
                            >
                        </div>

                        <div class="mt-4 divide-y divide-border">
                            <div
                                v-for="variable in [
                                    {
                                        key: 'base_url',
                                        value: 'https://api.acme.dev',
                                        secret: false,
                                    },
                                    {
                                        key: 'token',
                                        value: '••••••••••••',
                                        secret: true,
                                    },
                                    {
                                        key: 'account_id',
                                        value: 'acct_4Q7x',
                                        secret: false,
                                    },
                                ]"
                                :key="variable.key"
                                class="flex items-center justify-between gap-3 py-2.5 font-mono text-xs"
                            >
                                <span
                                    class="text-orange-600 dark:text-orange-400"
                                    >{{ variable.key }}</span
                                >
                                <span
                                    class="flex items-center gap-2 truncate text-muted-foreground"
                                >
                                    {{ variable.value }}
                                    <KeyRound
                                        v-if="variable.secret"
                                        class="size-3 text-muted-foreground/60"
                                    />
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Scripting & tests (dark band) -->
        <section id="testing" class="scroll-mt-20 bg-stone-950 text-stone-100">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <div class="max-w-2xl">
                    <p
                        class="flex items-center gap-2 font-mono text-xs tracking-widest text-stone-400 uppercase"
                    >
                        <span class="h-px w-6 bg-orange-500" />
                        Scripting & tests
                    </p>
                    <h2
                        class="mt-6 font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        Assertions that run on every send
                    </h2>
                    <p class="mt-4 text-stone-400">
                        Write a test script once and PostDoffo runs it the
                        moment a response comes back. Catch a broken endpoint
                        before it reaches your app. Need a token or timestamp
                        first? A pre-request script runs ahead of the call.
                    </p>
                </div>

                <div class="mt-12 grid gap-6 lg:grid-cols-2">
                    <!-- code -->
                    <div
                        class="overflow-hidden rounded-xl border border-white/10 bg-white/[0.03]"
                    >
                        <div
                            class="flex items-center gap-2 border-b border-white/10 px-4 py-2.5 font-mono text-xs text-stone-400"
                        >
                            <Play class="size-3.5" />
                            test script
                        </div>
                        <div class="flex font-mono text-xs leading-relaxed">
                            <div
                                class="shrink-0 border-r border-white/10 py-4 pr-3 pl-4 text-right text-stone-600 select-none"
                            >
                                <div v-for="n in 6" :key="n">{{ n }}</div>
                            </div>
                            <pre
                                class="overflow-x-auto py-4 pl-4 text-stone-300"
                            ><span class="text-sky-400">test</span>(<span class="text-green-400">"status is 200"</span>, () <span class="text-orange-400">=></span> {
  <span class="text-sky-400">expect</span>(res.status).<span class="text-sky-400">toBe</span>(<span class="text-orange-300">200</span>)
})
<span class="text-sky-400">test</span>(<span class="text-green-400">"has an id"</span>, () <span class="text-orange-400">=></span> {
  <span class="text-sky-400">expect</span>(res.body.id).<span class="text-sky-400">toBeDefined</span>()
})</pre>
                        </div>
                    </div>

                    <!-- results -->
                    <div
                        class="rounded-xl border border-white/10 bg-white/[0.03] p-5 font-mono text-sm"
                    >
                        <div
                            class="flex items-center justify-between border-b border-white/10 pb-3 text-xs text-stone-400"
                        >
                            <span>results</span>
                            <span class="text-green-400">3 / 4 passed</span>
                        </div>
                        <ul class="mt-4 space-y-3">
                            <li
                                v-for="test in testResults"
                                :key="test.label"
                                class="flex items-center gap-2.5"
                            >
                                <CheckCircle2
                                    v-if="test.ok"
                                    class="size-4 shrink-0 text-green-400"
                                />
                                <XCircle
                                    v-else
                                    class="size-4 shrink-0 text-red-400"
                                />
                                <span
                                    :class="
                                        test.ok
                                            ? 'text-stone-300'
                                            : 'text-red-300'
                                    "
                                    >{{ test.label }}</span
                                >
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Workflow -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <h2
                    class="max-w-xl font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    From zero to first response
                </h2>
                <p class="mt-4 max-w-md text-muted-foreground">
                    No install, no config files, no ceremony. You are inspecting
                    a live response within a minute of signing up.
                </p>

                <div class="relative mt-16 grid gap-12 md:grid-cols-3 md:gap-8">
                    <div
                        class="absolute inset-x-0 top-6 hidden h-px bg-border md:block"
                        aria-hidden="true"
                    />
                    <div
                        v-for="step in steps"
                        :key="step.title"
                        class="relative"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-full border border-border bg-background text-orange-600 dark:text-orange-400"
                        >
                            <component :is="step.icon" class="size-5" />
                        </div>
                        <h3
                            class="mt-6 font-display text-lg font-semibold tracking-tight"
                        >
                            {{ step.title }}
                        </h3>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ step.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Import -->
        <section id="import" class="scroll-mt-20 border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <div class="grid items-center gap-14 lg:grid-cols-2">
                    <div
                        class="order-last rounded-xl border border-border bg-card p-6 shadow-lg shadow-black/5 lg:order-first dark:shadow-black/30"
                    >
                        <div
                            class="flex items-center justify-between font-mono text-xs text-muted-foreground"
                        >
                            <span>payments.postman_collection.json</span>
                            <Upload class="size-3.5" />
                        </div>
                        <div
                            class="mt-4 space-y-2 border-t border-border pt-4 text-sm text-muted-foreground"
                        >
                            <div class="flex items-center gap-2">
                                <Layers class="size-3.5 text-orange-500" />
                                Payments API
                            </div>
                            <div class="flex items-center gap-2 pl-5">
                                <Layers class="size-3.5" /> Charges
                            </div>
                            <div
                                class="flex items-center gap-2 pl-10 font-mono text-xs"
                            >
                                <span
                                    class="font-bold text-green-600 dark:text-green-400"
                                    >POST</span
                                >
                                Create charge
                            </div>
                            <div
                                class="flex items-center gap-2 pl-10 font-mono text-xs"
                            >
                                <span
                                    class="font-bold text-sky-600 dark:text-sky-400"
                                    >GET</span
                                >
                                List charges
                            </div>
                            <div class="flex items-center gap-2 pl-5">
                                <Layers class="size-3.5" /> Customers
                            </div>
                            <div
                                class="flex items-center gap-2 pl-10 font-mono text-xs"
                            >
                                <span
                                    class="font-bold text-amber-600 dark:text-amber-400"
                                    >PUT</span
                                >
                                Update customer
                            </div>
                        </div>
                    </div>

                    <div>
                        <p
                            class="flex items-center gap-2 font-mono text-xs tracking-widest text-muted-foreground uppercase"
                        >
                            <span class="h-px w-6 bg-orange-500" />
                            Migrating in
                        </p>
                        <h2
                            class="mt-6 font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            Bring your Postman collections with you
                        </h2>
                        <p class="mt-4 max-w-md text-muted-foreground">
                            Import any Postman v2.1 export and the entire tree
                            comes across intact. Folders, requests, headers and
                            auth land exactly where they belong.
                        </p>
                        <ul class="mt-8 space-y-4 text-sm">
                            <li
                                v-for="item in [
                                    'One-click JSON import',
                                    'Folders and nesting preserved',
                                    'Headers and auth carried over',
                                ]"
                                :key="item"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="flex size-5 shrink-0 items-center justify-center rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400"
                                >
                                    <Check class="size-3.5" />
                                </span>
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="scroll-mt-20 border-b border-border">
            <div class="mx-auto max-w-3xl px-6 py-20 sm:py-28">
                <h2
                    class="font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    Questions, answered
                </h2>

                <div class="mt-12 border-t border-border">
                    <details
                        v-for="faq in faqs"
                        :key="faq.question"
                        class="group border-b border-border"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-start gap-4 py-5 font-display font-medium transition select-none hover:text-foreground [&::-webkit-details-marker]:hidden"
                        >
                            <Plus
                                class="mt-0.5 size-4 shrink-0 text-orange-500 transition-transform duration-200 group-open:rotate-45"
                            />
                            {{ faq.question }}
                        </summary>
                        <p
                            class="pb-5 pl-8 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ faq.answer }}
                        </p>
                    </details>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="relative overflow-hidden border-b border-border">
            <div
                class="pointer-events-none absolute inset-0 -z-10 [background-image:radial-gradient(var(--border)_1px,transparent_1px)] [mask-image:linear-gradient(to_top,black,transparent_85%)] [background-size:22px_22px] opacity-70"
                aria-hidden="true"
            />
            <div class="mx-auto max-w-6xl px-6 py-24 text-center sm:py-32">
                <h2
                    class="mx-auto max-w-2xl font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    Ready to fire off your first request?
                </h2>
                <p class="mx-auto mt-5 max-w-md text-muted-foreground">
                    Create a workspace in seconds. PostDoffo is free, every
                    feature, no strings attached.
                </p>
                <div
                    class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <Link
                        :href="$page.props.auth.user ? dashboard() : register()"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-orange-500 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-orange-400 sm:w-auto"
                    >
                        Start building
                        <ArrowRight class="size-4" />
                    </Link>
                    <a
                        :href="github"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-border px-6 py-3 text-sm font-semibold transition hover:bg-accent sm:w-auto"
                    >
                        <IconGithub class="size-4" />
                        Self-host on GitHub
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer>
            <div
                class="mx-auto grid max-w-6xl gap-10 px-6 py-14 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div class="sm:col-span-2 lg:col-span-2">
                    <Link :href="'/'" class="flex items-center gap-2">
                        <AppLogo />
                    </Link>
                    <p class="mt-4 max-w-xs text-sm text-muted-foreground">
                        A fast, focused API workspace. Build, test and share
                        without the bloat.
                    </p>
                    <a
                        :href="github"
                        target="_blank"
                        rel="noopener"
                        class="mt-5 inline-flex items-center gap-2 text-sm text-muted-foreground transition hover:text-foreground"
                    >
                        <IconGithub class="size-4" />
                        Source on GitHub
                    </a>
                </div>

                <div>
                    <p
                        class="font-mono text-xs tracking-widest text-muted-foreground uppercase"
                    >
                        Product
                    </p>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li>
                            <a
                                href="#features"
                                class="text-muted-foreground transition hover:text-foreground"
                                >Features</a
                            >
                        </li>
                        <li>
                            <a
                                href="#testing"
                                class="text-muted-foreground transition hover:text-foreground"
                                >Testing</a
                            >
                        </li>
                        <li>
                            <a
                                href="#import"
                                class="text-muted-foreground transition hover:text-foreground"
                                >Postman import</a
                            >
                        </li>
                        <li>
                            <a
                                href="#faq"
                                class="text-muted-foreground transition hover:text-foreground"
                                >FAQ</a
                            >
                        </li>
                        <li>
                            <Link
                                :href="scripting()"
                                class="text-muted-foreground transition hover:text-foreground"
                                >Scripting docs</Link
                            >
                        </li>
                    </ul>
                </div>

                <div>
                    <p
                        class="font-mono text-xs tracking-widest text-muted-foreground uppercase"
                    >
                        Legal
                    </p>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li>
                            <Link
                                :href="privacy()"
                                class="text-muted-foreground transition hover:text-foreground"
                                >Privacy policy</Link
                            >
                        </li>
                        <li>
                            <Link
                                :href="terms()"
                                class="text-muted-foreground transition hover:text-foreground"
                                >Terms of service</Link
                            >
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-border">
                <div
                    class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-6 font-mono text-xs text-muted-foreground sm:flex-row"
                >
                    <p>&copy; {{ new Date().getFullYear() }} PostDoffo</p>
                    <p>Fire away.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.ticker-mask {
    -webkit-mask-image: linear-gradient(
        to right,
        transparent,
        black 6%,
        black 94%,
        transparent
    );
    mask-image: linear-gradient(
        to right,
        transparent,
        black 6%,
        black 94%,
        transparent
    );
}

@media (prefers-reduced-motion: no-preference) {
    .ticker-track {
        animation: ticker-scroll 42s linear infinite;
    }
}

@keyframes ticker-scroll {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(calc(-50% - 0.3125rem));
    }
}
</style>
