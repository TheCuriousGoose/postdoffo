<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import IconGithub from '@/components/IconGithub.vue';
import { GITHUB_URL } from '@/lib/links';
import { dashboard, login, register } from '@/routes';

// Kept deliberately short — the footer carries the full set of pages. A
// homepage anchor href (/#features) resolves back to the homepage from any
// page, rather than scrolling nowhere on a subpage.
const navLinks = [
    { href: '/#features', label: 'Features' },
    { href: '/blog', label: 'Blog' },
];
</script>

<template>
    <header
        class="sticky top-0 z-50 border-b border-border bg-background/85 backdrop-blur"
    >
        <div
            class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6 md:gap-6"
        >
            <Link :href="'/'" class="flex min-w-0 items-center gap-2">
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

            <!--
                On a phone the CTA shortens and its arrow drops rather than the
                row wrapping or scrolling — "Start building" plus a log-in link
                plus the mark does not fit across 375px otherwise.
            -->
            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                <a
                    :href="GITHUB_URL"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground transition hover:text-foreground max-sm:hidden"
                    title="PostDoffo on GitHub"
                >
                    <IconGithub class="size-5" />
                    <span class="sr-only">PostDoffo source code on GitHub</span>
                </a>
                <template v-if="$page.props.auth.user">
                    <Link
                        :href="dashboard()"
                        class="inline-flex items-center gap-1.5 rounded-md bg-orange-500 px-3 py-2 text-sm font-semibold whitespace-nowrap text-stone-950 transition hover:bg-orange-400 sm:px-4"
                    >
                        Dashboard
                        <ArrowRight class="size-4 max-sm:hidden" />
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-md px-2 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground transition hover:text-foreground sm:px-3"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-flex items-center gap-1.5 rounded-md bg-orange-500 px-3 py-2 text-sm font-semibold whitespace-nowrap text-stone-950 transition hover:bg-orange-400 sm:px-4"
                    >
                        <span class="sm:hidden">Sign up</span>
                        <span class="max-sm:hidden">Start building</span>
                        <ArrowRight class="size-4 max-sm:hidden" />
                    </Link>
                </template>
            </div>
        </div>
    </header>
</template>
