<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes';

defineProps<{
    title: string;
    description: string;
    sections: { id: string; label: string }[];
}>();
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <header
            class="sticky top-0 z-50 border-b border-border/60 bg-background/80 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6"
            >
                <Link :href="home()" class="flex items-center gap-2">
                    <AppLogo />
                </Link>

                <Link
                    :href="home()"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="size-4" />
                    Back to home
                </Link>
            </div>
        </header>

        <div class="mx-auto max-w-6xl px-6 py-16 sm:py-20">
            <div class="max-w-2xl">
                <h1
                    class="font-display text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    {{ title }}
                </h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>

            <div class="mt-12 grid gap-12 lg:grid-cols-[220px_1fr]">
                <nav class="hidden lg:block">
                    <div class="sticky top-24 space-y-1 text-sm">
                        <a
                            v-for="section in sections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="block rounded-md px-3 py-1.5 text-muted-foreground transition hover:bg-accent hover:text-foreground"
                        >
                            {{ section.label }}
                        </a>
                    </div>
                </nav>

                <div
                    class="max-w-[70ch] space-y-10 text-sm leading-relaxed text-muted-foreground [&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.85em] [&_code]:text-foreground [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-foreground [&_li]:pl-1 [&_ol]:list-decimal [&_ol]:space-y-2 [&_ol]:pl-5 [&_p]:leading-relaxed [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:border [&_pre]:border-border/60 [&_pre]:bg-muted/50 [&_pre]:p-4 [&_pre]:font-mono [&_pre]:text-[0.85em] [&_pre]:text-foreground [&_strong]:font-medium [&_strong]:text-foreground [&_table]:w-full [&_table]:border-collapse [&_td]:border-b [&_td]:border-border/60 [&_td]:py-2 [&_td]:pr-4 [&_td]:align-top [&_th]:border-b [&_th]:border-border [&_th]:py-2 [&_th]:pr-4 [&_th]:text-left [&_th]:font-medium [&_th]:text-foreground [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-5"
                >
                    <slot />
                </div>
            </div>
        </div>

        <footer class="border-t border-border/60">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-8 text-sm text-muted-foreground"
            >
                <Link :href="home()" class="flex items-center gap-2">
                    <AppLogo />
                </Link>
                <p>&copy; {{ new Date().getFullYear() }} PostDoffo</p>
            </div>
        </footer>
    </div>
</template>
