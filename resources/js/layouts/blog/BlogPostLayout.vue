<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';

defineProps<{
    title: string;
    description: string;
    date: string;
}>();
</script>

<template>
    <div class="min-h-svh bg-background font-sans text-foreground">
        <SiteHeader />

        <article class="mx-auto max-w-3xl px-6 py-16 sm:py-20">
            <Link
                :href="'/blog'"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                Back to blog
            </Link>

            <p class="mt-8 font-mono text-xs text-muted-foreground">
                {{ date }}
            </p>
            <h1
                class="mt-3 font-display text-3xl font-semibold tracking-tight sm:text-4xl"
            >
                {{ title }}
            </h1>
            <p class="mt-4 text-lg text-muted-foreground">
                {{ description }}
            </p>

            <div
                class="mt-12 max-w-[70ch] space-y-6 text-sm leading-relaxed text-muted-foreground [&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.85em] [&_code]:text-foreground [&_h2]:pt-4 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-foreground [&_li]:pl-1 [&_ol]:list-decimal [&_ol]:space-y-2 [&_ol]:pl-5 [&_p]:leading-relaxed [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:border [&_pre]:border-border/60 [&_pre]:bg-muted/50 [&_pre]:p-4 [&_pre]:font-mono [&_pre]:text-[0.85em] [&_pre]:text-foreground [&_strong]:font-medium [&_strong]:text-foreground [&_table]:w-full [&_table]:border-collapse [&_td]:border-b [&_td]:border-border/60 [&_td]:py-2 [&_td]:pr-4 [&_td]:align-top [&_th]:border-b [&_th]:border-border [&_th]:py-2 [&_th]:pr-4 [&_th]:text-left [&_th]:font-medium [&_th]:text-foreground [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-5"
            >
                <slot />
            </div>
        </article>

        <SiteFooter />
    </div>
</template>
