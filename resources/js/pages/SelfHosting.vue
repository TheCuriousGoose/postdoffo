<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Database,
    KeyRound,
    Layers,
    ServerCog,
} from '@lucide/vue';
import IconGithub from '@/components/IconGithub.vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import { GITHUB_URL } from '@/lib/links';
import { register } from '@/routes';

const requirements = [
    'PHP 8.3+',
    'Composer',
    'Node 18+',
    'MySQL, MariaDB or SQLite',
];

const setupSteps = [
    {
        command: 'composer install && npm install',
        description: 'Install the PHP and JavaScript dependencies.',
    },
    {
        command: 'cp .env.example .env && php artisan key:generate',
        description: 'Create your environment file and application key.',
    },
    {
        command: 'php artisan migrate',
        description: 'Set up the database schema.',
    },
    {
        command: 'npm run dev',
        description:
            'Start the Vite dev server, then point php artisan serve or a local vhost at /public.',
    },
];

const stack = [
    {
        icon: ServerCog,
        title: 'Laravel 13 + Inertia',
        description:
            'A conventional Laravel app under the hood — no separate API server to stand up or keep in sync.',
    },
    {
        icon: Database,
        title: 'MySQL, MariaDB or SQLite',
        description:
            'Bring whichever database you already run. SQLite works fine for a single-team instance.',
    },
    {
        icon: KeyRound,
        title: 'Fortify auth',
        description:
            'Password auth, 2FA and passkeys out of the box. GitHub and Google social login are optional, enabled by setting their client credentials.',
    },
    {
        icon: Layers,
        title: 'Vue 3 + Tailwind',
        description:
            'The same frontend as the hosted version, built with Vite — nothing stripped down for self-hosting.',
    },
];
</script>

<template>
    <Head title="Self-host PostDoffo" />

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
                    Self-hosting
                </p>

                <h1
                    class="mt-6 font-display text-5xl leading-[1.02] font-semibold tracking-tight text-balance sm:text-6xl"
                >
                    Run PostDoffo on your own infrastructure
                </h1>

                <p
                    class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-pretty text-muted-foreground"
                >
                    PostDoffo is open source. Clone the repository, point it at
                    your own database, and every workspace, collection and
                    secret stays on hardware you control.
                </p>

                <div
                    class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <a
                        :href="GITHUB_URL"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-500 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-orange-400"
                    >
                        <IconGithub class="size-4" />
                        View on GitHub
                    </a>
                    <Link
                        :href="register()"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-6 py-3 text-sm font-semibold transition hover:bg-accent"
                    >
                        Try the hosted version
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- Requirements -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <h2
                    class="max-w-xl font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    What you need
                </h2>

                <div class="mt-10 flex flex-wrap gap-3">
                    <span
                        v-for="req in requirements"
                        :key="req"
                        class="rounded-full border border-border px-4 py-2 font-mono text-sm text-muted-foreground"
                    >
                        {{ req }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Setup steps -->
        <section class="border-b border-border bg-stone-950 text-stone-100">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <div class="max-w-2xl">
                    <p
                        class="flex items-center gap-2 font-mono text-xs tracking-widest text-stone-400 uppercase"
                    >
                        <span class="h-px w-6 bg-orange-500" />
                        Getting started
                    </p>
                    <h2
                        class="mt-6 font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        Four commands to a running instance
                    </h2>
                </div>

                <div
                    class="mt-12 overflow-hidden rounded-xl border border-white/10 bg-white/[0.03]"
                >
                    <div
                        v-for="(step, i) in setupSteps"
                        :key="step.command"
                        class="flex flex-col gap-2 border-b border-white/10 p-5 last:border-b-0 sm:flex-row sm:items-center sm:gap-6"
                    >
                        <span
                            class="font-mono text-xs text-stone-500"
                            >{{ String(i + 1).padStart(2, '0') }}</span
                        >
                        <code
                            class="rounded bg-black/40 px-3 py-1.5 font-mono text-sm text-orange-300"
                            >{{ step.command }}</code
                        >
                        <p class="text-sm text-stone-400 sm:ml-auto">
                            {{ step.description }}
                        </p>
                    </div>
                </div>

                <p class="mt-6 text-sm text-stone-400">
                    Full setup, development and testing commands are in the
                    <a
                        :href="GITHUB_URL"
                        target="_blank"
                        rel="noopener"
                        class="text-orange-400 underline-offset-4 hover:underline"
                        >repository README</a
                    >.
                </p>
            </div>
        </section>

        <!-- Stack -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:py-28">
                <h2
                    class="max-w-xl font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    The same app, running your way
                </h2>
                <p class="mt-4 max-w-md text-muted-foreground">
                    Self-hosting isn't a stripped-down edition. It's the exact
                    same codebase as the hosted version.
                </p>

                <div class="mt-12 grid gap-6 md:grid-cols-2">
                    <div
                        v-for="item in stack"
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

        <!-- Related -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-6xl px-6 py-16">
                <div class="grid gap-6 sm:grid-cols-2">
                    <Link
                        :href="'/import/postman'"
                        class="group rounded-xl border border-border bg-card p-6 transition hover:bg-accent"
                    >
                        <h3
                            class="flex items-center gap-2 font-display font-semibold tracking-tight"
                        >
                            Import your Postman collections
                            <ArrowRight
                                class="size-4 opacity-0 transition group-hover:opacity-100"
                            />
                        </h3>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            Bring an existing v2.1 export with you, hosted or
                            self-hosted.
                        </p>
                    </Link>
                    <Link
                        :href="'/vs/postman'"
                        class="group rounded-xl border border-border bg-card p-6 transition hover:bg-accent"
                    >
                        <h3
                            class="flex items-center gap-2 font-display font-semibold tracking-tight"
                        >
                            PostDoffo vs Postman
                            <ArrowRight
                                class="size-4 opacity-0 transition group-hover:opacity-100"
                            />
                        </h3>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            See how the two compare beyond hosting.
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
                    Not ready to self-host? Start hosted, free.
                </h2>
                <p class="mx-auto mt-5 max-w-md text-muted-foreground">
                    Try PostDoffo on the hosted instance first. Move to your
                    own infrastructure whenever you're ready — it's the same
                    app either way.
                </p>
                <div
                    class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <Link
                        :href="register()"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-orange-500 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-orange-400 sm:w-auto"
                    >
                        Start building
                        <ArrowRight class="size-4" />
                    </Link>
                    <a
                        :href="GITHUB_URL"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-border px-6 py-3 text-sm font-semibold transition hover:bg-accent sm:w-auto"
                    >
                        <IconGithub class="size-4" />
                        View the source
                    </a>
                </div>
            </div>
        </section>

        <SiteFooter />
    </div>
</template>
