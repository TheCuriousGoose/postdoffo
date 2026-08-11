<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import IconGithub from '@/components/IconGithub.vue';
import { GITHUB_URL } from '@/lib/links';
import { scripting } from '@/routes/docs';
import { privacy, terms } from '@/routes/legal';
</script>

<template>
    <footer>
        <div
            class="mx-auto grid max-w-6xl gap-10 px-6 py-14 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div class="sm:col-span-2 lg:col-span-2">
                <Link :href="'/'" class="flex items-center gap-2">
                    <AppLogo />
                </Link>
                <p class="mt-4 max-w-xs text-sm text-muted-foreground">
                    A fast, focused API workspace. Build, test and share without
                    the bloat.
                </p>
                <a
                    :href="GITHUB_URL"
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
                            href="/#features"
                            class="text-muted-foreground transition hover:text-foreground"
                            >Features</a
                        >
                    </li>
                    <li>
                        <a
                            href="/#testing"
                            class="text-muted-foreground transition hover:text-foreground"
                            >Testing</a
                        >
                    </li>
                    <li>
                        <Link
                            :href="'/import/postman'"
                            class="text-muted-foreground transition hover:text-foreground"
                            >Import from Postman</Link
                        >
                    </li>
                    <li>
                        <Link
                            :href="'/self-hosting'"
                            class="text-muted-foreground transition hover:text-foreground"
                            >Self-hosting</Link
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
                    Compare & learn
                </p>
                <ul class="mt-4 space-y-3 text-sm">
                    <li>
                        <Link
                            :href="'/vs/postman'"
                            class="text-muted-foreground transition hover:text-foreground"
                            >PostDoffo vs Postman</Link
                        >
                    </li>
                    <li>
                        <Link
                            :href="'/blog'"
                            class="text-muted-foreground transition hover:text-foreground"
                            >Blog</Link
                        >
                    </li>
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
</template>
