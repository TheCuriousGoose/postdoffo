<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';

defineProps<{
    posts: {
        title: string;
        slug: string;
        excerpt: string;
        date: string | null;
    }[];
}>();
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

                <div
                    v-if="posts.length"
                    class="mt-12 divide-y divide-border border-t border-border"
                >
                    <Link
                        v-for="post in posts"
                        :key="post.slug"
                        :href="`/blog/${post.slug}`"
                        class="group flex flex-col gap-2 py-8"
                    >
                        <p
                            v-if="post.date"
                            class="font-mono text-xs text-muted-foreground"
                        >
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
                            {{ post.excerpt }}
                        </p>
                    </Link>
                </div>

                <p v-else class="mt-12 text-sm text-muted-foreground">
                    No posts yet. Check back soon.
                </p>
            </div>
        </section>

        <SiteFooter />
    </div>
</template>
