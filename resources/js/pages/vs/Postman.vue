<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import IconGithub from '@/components/IconGithub.vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import { GITHUB_URL } from '@/lib/links';
import { dashboard, register } from '@/routes';

const rows = [
    { postman: 'Cloud account required', postdoffo: 'Hosted or self-hosted, your choice' },
    { postman: 'Paid tiers and seat limits', postdoffo: 'Every feature free, no seat counting' },
    { postman: 'Closed source', postdoffo: 'Open source on GitHub' },
    {
        postman: 'Scripts run full Node.js',
        postdoffo: 'Scripts run a small, closed pm.* grammar — no eval()',
    },
    { postman: 'Workspaces, collections, environments', postdoffo: 'Workspaces, collections, environments' },
];

const notOurFocus = [
    'Mock servers and public API network',
    'Automated monitors and scheduled runs',
    'A desktop and CLI client alongside the web app',
];
</script>

<template>
    <Head title="PostDoffo vs Postman" />

    <div class="min-h-svh bg-background font-sans text-foreground">
        <SiteHeader />

        <!-- Hero -->
        <section class="relative overflow-hidden border-b border-border">
            <div
                class="pointer-events-none absolute inset-0 -z-10 [background-image:radial-gradient(var(--border)_1px,transparent_1px)] [mask-image:linear-gradient(to_bottom,black,transparent_85%)] [background-size:22px_22px] opacity-70"
                aria-hidden="true"
            />

            <div class="mx-auto max-w-3xl px-6 pt-20 pb-16 text-center lg:pt-28">
                <p
                    class="mx-auto flex w-fit items-center gap-2 font-mono text-xs tracking-widest text-muted-foreground uppercase"
                >
                    <span class="h-px w-6 bg-orange-500" />
                    PostDoffo vs Postman
                </p>

                <h1
                    class="mt-6 font-display text-5xl leading-[1.02] font-semibold tracking-tight text-balance sm:text-6xl"
                >
                    Same job. Different philosophy.
                </h1>

                <p
                    class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-pretty text-muted-foreground"
                >
                    PostDoffo covers the same daily workflow as Postman —
                    building requests, organizing collections, sharing with a
                    team — free, open source, and yours to host.
                </p>

                <div
                    class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <Link
                        :href="
                            $page.props.auth.user ? dashboard() : register()
                        "
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-500 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-orange-400"
                    >
                        Start building
                        <ArrowRight class="size-4" />
                    </Link>
                    <a
                        :href="GITHUB_URL"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-6 py-3 text-sm font-semibold transition hover:bg-accent"
                    >
                        <IconGithub class="size-4" />
                        Self-host
                    </a>
                </div>
            </div>
        </section>

        <!-- Comparison table -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-3xl px-6 py-20 sm:py-28">
                <h2
                    class="font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    The philosophy
                </h2>

                <div
                    class="mt-10 overflow-hidden rounded-xl border border-border"
                >
                    <div
                        class="grid grid-cols-2 border-b border-border bg-muted/40 font-mono text-xs tracking-widest text-muted-foreground uppercase"
                    >
                        <div class="px-5 py-3">Postman</div>
                        <div class="border-l border-border px-5 py-3">
                            PostDoffo
                        </div>
                    </div>
                    <div
                        v-for="row in rows"
                        :key="row.postman"
                        class="grid grid-cols-2 border-b border-border text-sm last:border-b-0"
                    >
                        <div class="px-5 py-4 text-muted-foreground">
                            {{ row.postman }}
                        </div>
                        <div class="border-l border-border px-5 py-4">
                            {{ row.postdoffo }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Honest scope note -->
        <section class="border-b border-border bg-stone-950 text-stone-100">
            <div class="mx-auto max-w-3xl px-6 py-16 sm:py-20">
                <p
                    class="flex items-center gap-2 font-mono text-xs tracking-widest text-stone-400 uppercase"
                >
                    <span class="h-px w-6 bg-orange-500" />
                    What we don't try to match
                </p>
                <h2
                    class="mt-6 font-display text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Postman does more, on purpose we don't chase all of it
                </h2>
                <p class="mt-4 text-stone-400">
                    Postman is a much larger platform. If any of these are
                    what you actually need day to day, PostDoffo may not be a
                    fit yet:
                </p>
                <ul class="mt-6 space-y-3 text-sm">
                    <li
                        v-for="item in notOurFocus"
                        :key="item"
                        class="flex items-start gap-3 text-stone-300"
                    >
                        <span
                            class="mt-1.5 size-1.5 shrink-0 rounded-full bg-orange-500"
                        />
                        {{ item }}
                    </li>
                </ul>
            </div>
        </section>

        <!-- Related -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-16">
                <div class="grid gap-6 sm:grid-cols-2">
                    <Link
                        :href="'/self-hosting'"
                        class="group rounded-xl border border-border bg-card p-6 transition hover:bg-accent"
                    >
                        <h3
                            class="flex items-center gap-2 font-display font-semibold tracking-tight"
                        >
                            Self-host PostDoffo
                            <ArrowRight
                                class="size-4 opacity-0 transition group-hover:opacity-100"
                            />
                        </h3>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            Requirements, setup steps, and the stack it runs
                            on.
                        </p>
                    </Link>
                    <Link
                        :href="'/blog'"
                        class="group rounded-xl border border-border bg-card p-6 transition hover:bg-accent"
                    >
                        <h3
                            class="flex items-center gap-2 font-display font-semibold tracking-tight"
                        >
                            Read the blog
                            <ArrowRight
                                class="size-4 opacity-0 transition group-hover:opacity-100"
                            />
                        </h3>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            Guides on testing, migrating, and scripting
                            requests.
                        </p>
                    </Link>
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
                    Bring your Postman collections. Keep the workflow.
                </h2>
                <p class="mx-auto mt-5 max-w-md text-muted-foreground">
                    Import any Postman v2.1 export and pick up where you left
                    off.
                </p>
                <div
                    class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <Link
                        :href="'/import/postman'"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-orange-500 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-orange-400 sm:w-auto"
                    >
                        Import a collection
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </div>
        </section>

        <div class="border-b border-border bg-muted/20">
            <p
                class="mx-auto max-w-6xl px-6 py-4 text-xs text-muted-foreground/70"
            >
                Postman is a trademark of Postman, Inc. PostDoffo is not
                affiliated with or endorsed by Postman, Inc.
            </p>
        </div>

        <SiteFooter />
    </div>
</template>
