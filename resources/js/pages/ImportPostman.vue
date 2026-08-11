<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FileBraces, KeyRound, Layers, Upload } from '@lucide/vue';
import IconGithub from '@/components/IconGithub.vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import { GITHUB_URL } from '@/lib/links';
import { dashboard, register } from '@/routes';
import { scripting } from '@/routes/docs';

const carriesOver = [
    {
        icon: Layers,
        title: 'Folders and nesting',
        description:
            'Every sub-folder in the collection tree lands exactly where it was, in the same order.',
    },
    {
        icon: FileBraces,
        title: 'Requests, methods and bodies',
        description:
            'URLs, query params, raw and form bodies come across as-is, method included.',
    },
    {
        icon: KeyRound,
        title: 'Headers and auth',
        description:
            'Bearer, Basic and API key auth carry over, whether set on a request or inherited from a parent folder.',
    },
];

const steps = [
    {
        title: 'Export from Postman',
        description:
            'Right-click the collection in Postman, choose Export, and select the Collection v2.1 format.',
    },
    {
        title: 'Import into PostDoffo',
        description:
            'Open a workspace, choose Import, and drop the exported .json file in.',
    },
    {
        title: 'Keep working',
        description:
            'The full tree is there: folders, requests, headers and auth, ready to send.',
    },
];
</script>

<template>
    <Head title="Import Postman collections" />

    <div class="min-h-svh bg-background font-sans text-foreground">
        <SiteHeader />

        <!-- Hero -->
        <section class="relative overflow-hidden border-b border-border">
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
                            Migrating in
                        </p>

                        <h1
                            class="mt-6 font-display text-5xl leading-[1.02] font-semibold tracking-tight text-balance sm:text-6xl"
                        >
                            Import your Postman collections
                        </h1>

                        <p
                            class="mt-6 max-w-md text-lg leading-relaxed text-pretty text-muted-foreground"
                        >
                            Export any Postman v2.1 collection and drop it in.
                            The entire tree comes across intact — folders,
                            requests, headers and auth land exactly where they
                            belong.
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

                    <!-- collection tree visual -->
                    <div class="lg:col-span-6">
                        <div
                            class="rounded-xl border border-border bg-card p-6 shadow-lg shadow-black/5 dark:shadow-black/30"
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
                    </div>
                </div>
            </div>
        </section>

        <!-- What comes across -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <h2
                    class="max-w-xl font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    What comes across
                </h2>
                <p class="mt-4 max-w-md text-muted-foreground">
                    Nothing needs re-typing. The parts of a collection that
                    actually matter all survive the trip.
                </p>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div
                        v-for="item in carriesOver"
                        :key="item.title"
                        class="rounded-xl border border-border bg-card p-6"
                    >
                        <component
                            :is="item.icon"
                            class="size-6 text-orange-500"
                            :stroke-width="1.75"
                        />
                        <h3
                            class="mt-5 font-display font-semibold tracking-tight"
                        >
                            {{ item.title }}
                        </h3>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ item.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Steps -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <h2
                    class="max-w-xl font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    Three steps, no re-work
                </h2>

                <div class="relative mt-16 grid gap-12 md:grid-cols-3 md:gap-8">
                    <div
                        class="absolute inset-x-0 top-6 hidden h-px bg-border md:block"
                        aria-hidden="true"
                    />
                    <div
                        v-for="(step, i) in steps"
                        :key="step.title"
                        class="relative"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-full border border-border bg-background font-display font-semibold text-orange-600 dark:text-orange-400"
                        >
                            {{ i + 1 }}
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

        <!-- Honest note on scripting -->
        <section class="border-b border-border bg-stone-950 text-stone-100">
            <div class="mx-auto max-w-6xl px-6 py-16 sm:py-20">
                <div class="max-w-2xl">
                    <p
                        class="flex items-center gap-2 font-mono text-xs tracking-widest text-stone-400 uppercase"
                    >
                        <span class="h-px w-6 bg-orange-500" />
                        Worth knowing
                    </p>
                    <h2
                        class="mt-6 font-display text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        Scripts don't come across as JavaScript
                    </h2>
                    <p class="mt-4 text-stone-400">
                        Postman's pre-request and test scripts are plain
                        JavaScript. PostDoffo's are not — they run on a small,
                        closed <code class="text-stone-200">pm.*</code> grammar
                        with no <code class="text-stone-200">eval()</code>
                        behind it, so imported scripts won't run as-is and need
                        to be rewritten by hand. It's a deliberate trade-off for
                        safety, not an oversight.
                    </p>
                    <Link
                        :href="scripting()"
                        class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-orange-400 transition hover:text-orange-300"
                    >
                        Read the scripting reference
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
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
                            Moving the whole team? Run PostDoffo on your own
                            infrastructure.
                        </p>
                    </Link>
                    <Link
                        :href="'/blog/import-postman-collections'"
                        class="group rounded-xl border border-border bg-card p-6 transition hover:bg-accent"
                    >
                        <h3
                            class="flex items-center gap-2 font-display font-semibold tracking-tight"
                        >
                            What actually happens on import
                            <ArrowRight
                                class="size-4 opacity-0 transition group-hover:opacity-100"
                            />
                        </h3>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            A closer look at the Postman v2.1 format, folder by
                            folder.
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
                    Bring your collections. Keep working today.
                </h2>
                <p class="mx-auto mt-5 max-w-md text-muted-foreground">
                    Create a workspace, import your export, and pick up right
                    where Postman left off.
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
                </div>
            </div>
        </section>

        <SiteFooter />
    </div>
</template>
