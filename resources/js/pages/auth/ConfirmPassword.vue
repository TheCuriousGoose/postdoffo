<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit as securityEdit } from '@/routes/security';
import { store } from '@/routes/password/confirm';

defineProps<{
    hasPassword: boolean;
    hasPasskeys: boolean;
}>();

defineOptions({
    layout: {
        title: 'Confirm password',
        description:
            'This is a secure area of the application. Please confirm your password before continuing.',
    },
});
</script>

<template>
    <Head title="Confirm password" />

    <PasskeyVerify
        v-if="hasPasskeys"
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirm with passkey"
        loading-label="Confirming..."
        separator="Or confirm with password"
        :hide-separator="!hasPassword"
    />

    <Form
        v-if="hasPassword"
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    Confirm password
                </Button>
            </div>
        </div>
    </Form>

    <p
        v-if="!hasPassword && !hasPasskeys"
        class="text-sm text-muted-foreground"
    >
        You don't have a password or passkey set up, so there's nothing to
        confirm with.
        <Link
            :href="securityEdit()"
            class="underline underline-offset-4 hover:text-foreground"
            >Set a password</Link
        >
        in security settings to continue.
    </p>
</template>
