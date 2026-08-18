<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { redirect } from '@/actions/App/Http/Controllers/Auth/SocialAuthController';
import ProviderIcon from '@/components/ProviderIcon.vue';
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
                    <ProviderIcon provider="google" />
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
                    <ProviderIcon provider="github" />
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
