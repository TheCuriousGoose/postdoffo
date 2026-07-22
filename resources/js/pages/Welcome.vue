<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    Globe,
    KeyRound,
    Layers,
    Plus,
    Upload,
    Users,
} from '@lucide/vue';
import IconGithub from '@/components/IconGithub.vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import { GITHUB_URL } from '@/lib/links';
import { dashboard, register } from '@/routes';

// Literal variable token, kept out of the template so the `}}` doesn't
// terminate Vue's mustache interpolation early.
const varBaseUrl = '{{base_url}}';

const capabilities = [
    {
        icon: Layers,
        name: 'Workspaces & collections',
        description:
            'Requests organised into workspaces, collections and nested folders.',
    },
    {
        icon: Globe,
        name: 'Environments',
        description:
            'Swap dev, staging and production with one active environment. Secrets stay masked.',
    },
    {
        icon: CheckCircle2,
        name: 'Request tests',
        description:
            'Assertions run on every send, with pass or fail shown next to the response.',
    },
    {
        icon: Users,
        name: 'Team roles',
        description:
            'Invite editors and viewers. Everyone works from one source of truth.',
    },
    {
        icon: KeyRound,
        name: 'Auth built in',
        description:
            'Bearer, Basic and API key, set per request or inherited from a folder.',
    },
    {
        icon: Upload,
        name: 'Postman import',
        description:
            'Bring a v2.1 export across with folders, headers and auth intact.',
    },
];

// Kept identical to the FAQPage JSON-LD in resources/views/app.blade.php so
// the structured data matches what's on the page.
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
</script>

<template>
    <Head title="Free, open-source Postman alternative for teams" />

    <div class="min-h-svh bg-background font-sans text-foreground">
        <SiteHeader />

        <!-- Hero: asymmetric split, editorial left / honest request figure right -->
        <section class="border-b border-border">
            <div
                class="mx-auto grid max-w-6xl gap-14 px-6 pt-20 pb-20 lg:grid-cols-12 lg:gap-12 lg:pt-24 lg:pb-28"
            >
                <div class="lg:col-span-7 lg:pr-8">
                    <h1
                        class="max-w-xl font-display text-5xl leading-[1.03] font-semibold tracking-tight text-balance sm:text-6xl"
                    >
                        The open-source API client for teams.
                    </h1>
                    <p
                        class="mt-6 max-w-md text-lg leading-relaxed text-pretty text-muted-foreground"
                    >
                        Collections, environments, request tests and sharing in
                        one fast workspace. Free on the hosted app, or run it on
                        your own servers.
                    </p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="$page.props.auth.user ? dashboard() : register()"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-500 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-orange-400 active:translate-y-px"
                        >
                            Start building
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            :href="'/self-hosting'"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-6 py-3 text-sm font-semibold transition hover:bg-accent active:translate-y-px"
                        >
                            <IconGithub class="size-4" />
                            Self-host
                        </Link>
                    </div>
                </div>

                <!-- Honest request/response figure: real syntax, no fake app
                     chrome (no tab bar, no window controls, no fake buttons). -->
                <figure class="lg:col-span-5 lg:self-center">
                    <div
                        class="overflow-hidden rounded-xl border border-border bg-card font-mono text-xs"
                    >
                        <div class="flex items-center gap-2 px-4 py-3">
                            <span
                                class="font-bold text-green-600 dark:text-green-400"
                                >GET</span
                            >
                            <span class="truncate text-muted-foreground">
                                <span
                                    class="text-orange-600 dark:text-orange-400"
                                    >{{ varBaseUrl }}</span
                                >/v1/customers</span
                            >
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 border-y border-border bg-muted/40 px-4 py-2.5"
                        >
                            <span
                                class="flex items-center gap-1.5 font-semibold text-green-600 dark:text-green-400"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-green-500"
                                />
                                200 OK
                            </span>
                            <span class="text-muted-foreground">142 ms</span>
                            <span class="text-muted-foreground">1.2 KB</span>
                        </div>
                        <pre
                            class="overflow-x-auto px-4 py-4 leading-relaxed"
                        ><span class="text-muted-foreground">{</span>
  <span class="text-orange-600 dark:text-orange-400">"id"</span>: <span class="text-green-600 dark:text-green-400">"cus_9fTq2x"</span>,
  <span class="text-orange-600 dark:text-orange-400">"email"</span>: <span class="text-green-600 dark:text-green-400">"ada@acme.dev"</span>,
  <span class="text-orange-600 dark:text-orange-400">"plan"</span>: <span class="text-green-600 dark:text-green-400">"team"</span>
<span class="text-muted-foreground">}</span></pre>
                    </div>
                    <figcaption class="mt-3 text-xs text-muted-foreground">
                        A request and its response. Nothing else in the way.
                    </figcaption>
                </figure>
            </div>
        </section>

        <!-- Capabilities: airy two-column list, no card chrome -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <div class="max-w-xl">
                    <h2
                        class="font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        Everything the job needs.
                    </h2>
                    <p class="mt-4 text-muted-foreground">
                        A focused toolkit for working with APIs. No bloat, no
                        plugins to wire up.
                    </p>
                </div>

                <div
                    class="mt-14 grid grid-cols-1 gap-x-12 gap-y-10 md:grid-cols-2"
                >
                    <div
                        v-for="item in capabilities"
                        :key="item.name"
                        class="flex gap-4"
                    >
                        <component
                            :is="item.icon"
                            class="mt-0.5 size-5 shrink-0 text-orange-500"
                            :stroke-width="1.75"
                        />
                        <div>
                            <h3
                                class="font-display font-semibold tracking-tight"
                            >
                                {{ item.name }}
                            </h3>
                            <p
                                class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ item.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Open source / self-host: editorial statement, no code block -->
        <section class="border-b border-border bg-muted/30">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <h2
                    class="max-w-2xl font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                >
                    Yours to run. Yours to read.
                </h2>
                <p class="mt-5 max-w-xl text-muted-foreground">
                    PostDoffo is open source. Use the hosted app, or clone the
                    repository and run the exact same thing on your own
                    infrastructure. Your requests and secrets stay on hardware
                    you control.
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-6">
                    <Link
                        :href="'/self-hosting'"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-orange-600 transition hover:text-orange-500 dark:text-orange-400"
                    >
                        Read the self-hosting guide
                        <ArrowRight class="size-4" />
                    </Link>
                    <a
                        :href="GITHUB_URL"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                    >
                        <IconGithub class="size-4" />
                        View the source
                    </a>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="border-b border-border">
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
        <section class="border-b border-border">
            <div class="mx-auto max-w-2xl px-6 py-24 text-center sm:py-32">
                <h2
                    class="font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    Ready to send your first request?
                </h2>
                <p class="mx-auto mt-5 max-w-md text-muted-foreground">
                    Create a workspace in seconds. Free, every feature, no seat
                    counting.
                </p>
                <div
                    class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <Link
                        :href="$page.props.auth.user ? dashboard() : register()"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-orange-500 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-orange-400 active:translate-y-px sm:w-auto"
                    >
                        Start building
                        <ArrowRight class="size-4" />
                    </Link>
                    <Link
                        :href="'/self-hosting'"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-border px-6 py-3 text-sm font-semibold transition hover:bg-accent active:translate-y-px sm:w-auto"
                    >
                        <IconGithub class="size-4" />
                        Self-host
                    </Link>
                </div>
            </div>
        </section>

        <SiteFooter />
    </div>
</template>
