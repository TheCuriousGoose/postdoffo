<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Camera } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AvatarController from '@/actions/App/Http/Controllers/Settings/AvatarController';
import AvatarCropper from '@/components/AvatarCropper.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import type { User } from '@/types';

const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

const props = defineProps<{
    user: User;
    /** Server-side upload ceiling, so an oversized file is caught before it's sent. */
    maxKilobytes: number;
}>();

const input = ref<HTMLInputElement | null>(null);
const picked = ref<File | null>(null);
const cropping = ref(false);
const processing = ref(false);
const dragging = ref(false);

const maxMegabytes = computed(
    () => Math.round((props.maxKilobytes / 1024) * 10) / 10,
);

function browse(): void {
    input.value?.click();
}

function accept(file: File | null | undefined): void {
    if (!file) {
        return;
    }

    if (!ACCEPTED.includes(file.type)) {
        toast.error('Choose a JPEG, PNG, WebP or GIF image.');

        return;
    }

    if (file.size > props.maxKilobytes * 1024) {
        toast.error(`Images must be smaller than ${maxMegabytes.value} MB.`);

        return;
    }

    picked.value = file;
    cropping.value = true;
}

function onFileChange(event: Event): void {
    const element = event.target as HTMLInputElement;

    accept(element.files?.[0]);

    // Clearing it means picking the same file twice in a row still fires change.
    element.value = '';
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    accept(event.dataTransfer?.files?.[0]);
}

function upload(blob: Blob): void {
    processing.value = true;

    router.post(
        AvatarController.store.url(),
        { avatar: new File([blob], 'avatar.jpg', { type: 'image/jpeg' }) },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                cropping.value = false;
                picked.value = null;
            },
            onError: (errors) => toast.error(errors.avatar ?? 'Upload failed.'),
            onFinish: () => (processing.value = false),
        },
    );
}

function remove(): void {
    router.delete(AvatarController.destroy.url(), { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-5">
        <button
            type="button"
            class="group relative rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            :aria-label="
                user.avatar ? 'Change profile picture' : 'Add a profile picture'
            "
            data-test="open-avatar-picker"
            @click="browse"
            @dragenter.prevent="dragging = true"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="onDrop"
        >
            <Avatar
                class="size-20 rounded-full transition"
                :class="dragging ? 'ring-2 ring-primary ring-offset-2' : ''"
            >
                <AvatarImage
                    v-if="user.avatar"
                    :src="user.avatar"
                    :alt="user.name"
                />
                <AvatarFallback
                    class="bg-neutral-200 text-xl font-semibold text-black dark:bg-neutral-700 dark:text-white"
                >
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>

            <span
                class="absolute inset-0 flex items-center justify-center rounded-full bg-black/55 text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
                :class="dragging ? 'opacity-100' : ''"
            >
                <Camera class="size-5" />
            </span>
        </button>

        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="browse"
                >
                    {{ user.avatar ? 'Change photo' : 'Upload photo' }}
                </Button>

                <Button
                    v-if="user.avatar"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground"
                    data-test="remove-avatar-button"
                    @click="remove"
                >
                    Remove
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">
                JPEG, PNG, WebP or GIF, up to {{ maxMegabytes }} MB. Drop a file
                on the circle to upload it.
            </p>
        </div>

        <input
            ref="input"
            type="file"
            class="hidden"
            :accept="ACCEPTED.join(',')"
            data-test="avatar-input"
            @change="onFileChange"
        />

        <AvatarCropper
            v-model:open="cropping"
            :file="picked"
            :processing="processing"
            @save="upload"
        />
    </div>
</template>
