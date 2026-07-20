<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { redirect } from '@/actions/App/Http/Controllers/Auth/SocialAuthController';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

withDefaults(
    defineProps<{
        label?: string;
    }>(),
    {
        label: 'Or continue with',
    },
);

const page = usePage();

// Only render a provider whose OAuth credentials are configured server-side.
const enabled = computed(() => page.props.socialProviders ?? []);
</script>

<template>
    <div v-if="enabled.length">
        <div
            class="grid gap-3"
            :class="enabled.length > 1 ? 'grid-cols-2' : ''"
        >
            <Button
                v-if="enabled.includes('google')"
                variant="outline"
                as-child
                class="w-full"
            >
                <a :href="redirect({ provider: 'google' }).url">
                    <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            fill="#4285F4"
                            d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.47c-.28 1.5-1.13 2.78-2.41 3.63v3.02h3.9c2.28-2.1 3.6-5.2 3.6-8.84z"
                        />
                        <path
                            fill="#34A853"
                            d="M12 24c3.24 0 5.96-1.07 7.95-2.9l-3.9-3.02c-1.08.73-2.46 1.16-4.05 1.16-3.12 0-5.76-2.1-6.7-4.93H1.26v3.11C3.24 21.3 7.3 24 12 24z"
                        />
                        <path
                            fill="#FBBC05"
                            d="M5.3 14.31c-.24-.73-.38-1.5-.38-2.31s.14-1.58.38-2.31V6.58H1.26A11.98 11.98 0 0 0 0 12c0 1.93.46 3.76 1.26 5.42l4.04-3.11z"
                        />
                        <path
                            fill="#EA4335"
                            d="M12 4.75c1.76 0 3.35.61 4.6 1.8l3.45-3.45C17.95 1.19 15.24 0 12 0 7.3 0 3.24 2.7 1.26 6.58l4.04 3.11c.94-2.83 3.58-4.94 6.7-4.94z"
                        />
                    </svg>
                    Google
                </a>
            </Button>

            <Button
                v-if="enabled.includes('github')"
                variant="outline"
                as-child
                class="w-full"
            >
                <a :href="redirect({ provider: 'github' }).url">
                    <svg
                        class="size-4 fill-current"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M12 0C5.37 0 0 5.37 0 12c0 5.3 3.44 9.8 8.21 11.39.6.11.82-.26.82-.58 0-.29-.01-1.04-.02-2.04-3.34.73-4.04-1.61-4.04-1.61-.55-1.39-1.34-1.76-1.34-1.76-1.09-.75.08-.73.08-.73 1.21.08 1.84 1.24 1.84 1.24 1.07 1.84 2.81 1.31 3.5 1 .11-.78.42-1.31.76-1.61-2.67-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.12-.3-.54-1.52.12-3.18 0 0 1.01-.32 3.3 1.23a11.5 11.5 0 0 1 6.02 0c2.29-1.55 3.3-1.23 3.3-1.23.66 1.66.24 2.88.12 3.18.77.84 1.23 1.91 1.23 3.22 0 4.61-2.8 5.63-5.48 5.92.43.37.81 1.1.81 2.22 0 1.6-.02 2.89-.02 3.29 0 .32.22.7.83.58C20.56 21.8 24 17.3 24 12c0-6.63-5.37-12-12-12Z"
                        />
                    </svg>
                    GitHub
                </a>
            </Button>
        </div>

        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <Separator class="w-full" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">
                    {{ label }}
                </span>
            </div>
        </div>
    </div>
</template>
