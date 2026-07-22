<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';

const posts = [
    {
        href: '/blog/import-postman-collections',
        title: 'What actually happens when you import a Postman collection',
        description:
            'A walk through the Postman v2.1 collection format: what folders, auth and headers look like on the wire, and where they land in PostDoffo.',
        date: 'July 22, 2026',
    },
    {
        href: '/blog/how-to-test-a-rest-api',
        title: 'How to test a REST API without writing a test suite',
        description:
            "Using PostDoffo's pm.test assertions to check status codes, response shape and timing on every request you send.",
        date: 'July 22, 2026',
    },
    {
        href: '/blog/environment-variables-explained',
        title: 'Environment variables in API requests, explained',
        description:
            'What environment variables actually solve, how {{variable}} interpolation works, and why secrets need their own handling.',
        date: 'July 22, 2026',
    },
];
</script>

<template>
    <Head title="Blog" />

    <div class="min-h-svh bg-background font-sans text-foreground">
        <SiteHeader />

        <section class="border-b border-border">
            <div class="mx-auto max-w-3xl px-6 py-16 sm:py-20">
                <h1
                    class="font-display text-4xl font-semibold tracking-tight sm:text-5xl"
                >
                    Blog
                </h1>
                <p class="mt-4 max-w-xl text-muted-foreground">
                    Guides on testing APIs, migrating from Postman, and
                    getting the most out of environments and scripting.
                </p>

                <div class="mt-12 divide-y divide-border border-t border-border">
                    <Link
                        v-for="post in posts"
                        :key="post.href"
                        :href="post.href"
                        class="group flex flex-col gap-2 py-8"
                    >
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ post.date }}
                        </p>
                        <h2
                            class="flex items-center gap-2 font-display text-xl font-semibold tracking-tight transition group-hover:text-orange-600 dark:group-hover:text-orange-400"
                        >
                            {{ post.title }}
                            <ArrowRight
                                class="size-4 shrink-0 opacity-0 transition group-hover:opacity-100"
                            />
                        </h2>
                        <p
                            class="max-w-xl text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ post.description }}
                        </p>
                    </Link>
                </div>
            </div>
        </section>

        <SiteFooter />
    </div>
</template>
