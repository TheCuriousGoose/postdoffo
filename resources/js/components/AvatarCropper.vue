<script setup lang="ts">
import { RotateCw, ZoomIn, ZoomOut } from '@lucide/vue';
import { useElementSize } from '@vueuse/core';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/** Edge length of the JPEG handed to the server. */
const OUTPUT_SIZE = 512;

/** Used until the frame has been measured (the dialog opens at 0 width). */
const FALLBACK_VIEWPORT = 288;

const MIN_ZOOM = 1;
const MAX_ZOOM = 5;

/** Pixels an arrow key moves the picture; hold shift for a coarser step. */
const NUDGE = 8;

const props = defineProps<{
    open: boolean;
    file: File | null;
    processing?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    save: [Blob];
}>();

const frame = ref<HTMLElement | null>(null);
const { width: frameWidth } = useElementSize(frame);

const image = ref<HTMLImageElement | null>(null);
const objectUrl = ref<string | null>(null);
const loading = ref(false);
const failed = ref(false);

const zoom = ref(MIN_ZOOM);
const rotation = ref(0);
const offset = reactive({ x: 0, y: 0 });

const viewport = computed(() => frameWidth.value || FALLBACK_VIEWPORT);

/**
 * Zoom 1 is the picture scaled so its shorter side exactly fills the frame —
 * the tightest fit that still leaves no empty corner to crop into.
 */
const baseScale = computed(() => {
    const source = image.value;

    if (!source) {
        return 1;
    }

    return viewport.value / Math.min(source.naturalWidth, source.naturalHeight);
});

const turned = computed(() => rotation.value % 180 !== 0);

/** On-screen size of the picture, in frame pixels, at the current zoom. */
const rendered = computed(() => {
    const source = image.value;

    if (!source) {
        return { width: 0, height: 0 };
    }

    const scale = baseScale.value * zoom.value;
    const width = source.naturalWidth * scale;
    const height = source.naturalHeight * scale;

    return turned.value ? { width: height, height: width } : { width, height };
});

const imageStyle = computed(() => ({
    width: `${(image.value?.naturalWidth ?? 0) * baseScale.value}px`,
    transform: [
        'translate(-50%, -50%)',
        `translate(${offset.x}px, ${offset.y}px)`,
        `rotate(${rotation.value}deg)`,
        `scale(${zoom.value})`,
    ].join(' '),
}));

/** Keeps the frame covered — the picture can never be dragged past its own edge. */
function clampOffset(): void {
    const maxX = Math.max(0, (rendered.value.width - viewport.value) / 2);
    const maxY = Math.max(0, (rendered.value.height - viewport.value) / 2);

    offset.x = Math.min(maxX, Math.max(-maxX, offset.x));
    offset.y = Math.min(maxY, Math.max(-maxY, offset.y));
}

/** Zooms about the middle of the frame, so what you're looking at stays put. */
function setZoom(next: number): void {
    const clamped = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, next));
    const ratio = clamped / zoom.value;

    offset.x *= ratio;
    offset.y *= ratio;
    zoom.value = clamped;

    clampOffset();
}

function rotate(): void {
    rotation.value = (rotation.value + 90) % 360;

    // The picture's on-screen box swaps sides, which can leave the frame
    // uncovered along the other axis.
    clampOffset();
}

function reset(): void {
    zoom.value = MIN_ZOOM;
    rotation.value = 0;
    offset.x = 0;
    offset.y = 0;
}

const isEdited = computed(
    () =>
        zoom.value !== MIN_ZOOM ||
        rotation.value !== 0 ||
        offset.x !== 0 ||
        offset.y !== 0,
);

function releaseImage(): void {
    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value);
        objectUrl.value = null;
    }

    image.value = null;
}

function load(file: File): void {
    releaseImage();
    reset();

    loading.value = true;
    failed.value = false;

    const url = URL.createObjectURL(file);
    objectUrl.value = url;

    const element = new Image();

    element.onload = () => {
        image.value = element;
        loading.value = false;
    };

    element.onerror = () => {
        loading.value = false;
        failed.value = true;
    };

    element.src = url;
}

watch(
    () => props.file,
    (file) => {
        if (file) {
            load(file);
        }
    },
    { immediate: true },
);

// A frame that changes width (rotating a phone, say) would otherwise strand the
// picture at offsets measured against the old one.
watch(viewport, (next, previous) => {
    if (previous > 0 && next > 0) {
        offset.x *= next / previous;
        offset.y *= next / previous;
        clampOffset();
    }
});

watch(
    () => props.open,
    (open) => {
        if (!open) {
            releaseImage();
        }
    },
);

onBeforeUnmount(releaseImage);

/**
 * Live pointers on the frame: one drags, two pinch. Tracking them by id is what
 * keeps a second finger from being read as a jump of the first.
 */
const pointers = new Map<number, { x: number; y: number }>();
let pinchDistance = 0;
let pinchZoom = MIN_ZOOM;

function spread(): number {
    const [first, second] = [...pointers.values()];

    return Math.hypot(second.x - first.x, second.y - first.y);
}

function onPointerDown(event: PointerEvent): void {
    if (!image.value) {
        return;
    }

    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (pointers.size === 2) {
        pinchDistance = spread();
        pinchZoom = zoom.value;
    }
}

function onPointerMove(event: PointerEvent): void {
    const previous = pointers.get(event.pointerId);

    if (!previous) {
        return;
    }

    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (pointers.size >= 2) {
        if (pinchDistance > 0) {
            setZoom((pinchZoom * spread()) / pinchDistance);
        }

        return;
    }

    offset.x += event.clientX - previous.x;
    offset.y += event.clientY - previous.y;

    clampOffset();
}

function onPointerUp(event: PointerEvent): void {
    pointers.delete(event.pointerId);

    // A finger lifting out of a pinch ends it; the one still down carries on as
    // a drag from wherever it already is, which is what the map already holds.
    if (pointers.size < 2) {
        pinchDistance = 0;
    }
}

function onWheel(event: WheelEvent): void {
    setZoom(zoom.value * (1 - event.deltaY / 400));
}

function onKeydown(event: KeyboardEvent): void {
    const step = event.shiftKey ? NUDGE * 4 : NUDGE;

    const moves: Record<string, () => void> = {
        ArrowLeft: () => (offset.x -= step),
        ArrowRight: () => (offset.x += step),
        ArrowUp: () => (offset.y -= step),
        ArrowDown: () => (offset.y += step),
        '+': () => setZoom(zoom.value + 0.1),
        '=': () => setZoom(zoom.value + 0.1),
        '-': () => setZoom(zoom.value - 0.1),
    };

    const move = moves[event.key];

    if (!move) {
        return;
    }

    event.preventDefault();
    move();
    clampOffset();
}

async function save(): Promise<void> {
    const source = image.value;

    if (!source) {
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = OUTPUT_SIZE;
    canvas.height = OUTPUT_SIZE;

    const context = canvas.getContext('2d');

    if (!context) {
        failed.value = true;

        return;
    }

    // Everything below is the on-screen transform replayed at output scale, so
    // what the circle showed is exactly what gets uploaded.
    const ratio = OUTPUT_SIZE / viewport.value;
    const scale = baseScale.value * zoom.value * ratio;

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';

    context.translate(
        OUTPUT_SIZE / 2 + offset.x * ratio,
        OUTPUT_SIZE / 2 + offset.y * ratio,
    );
    context.rotate((rotation.value * Math.PI) / 180);
    context.scale(scale, scale);
    context.drawImage(
        source,
        -source.naturalWidth / 2,
        -source.naturalHeight / 2,
    );

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', 0.92),
    );

    if (blob) {
        emit('save', blob);
    } else {
        failed.value = true;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Position your photo</DialogTitle>
                <DialogDescription>
                    Drag to move, scroll or pinch to zoom. Everything inside the
                    circle is kept.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div
                    ref="frame"
                    class="relative aspect-square w-full touch-none overflow-hidden rounded-xl bg-neutral-100 outline-none select-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-neutral-900"
                    :class="image ? 'cursor-grab active:cursor-grabbing' : ''"
                    role="application"
                    tabindex="0"
                    aria-label="Photo position. Use the arrow keys to move, plus and minus to zoom."
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                    @wheel.prevent="onWheel"
                    @keydown="onKeydown"
                >
                    <img
                        v-if="image && objectUrl"
                        :src="objectUrl"
                        alt=""
                        draggable="false"
                        class="absolute top-1/2 left-1/2 max-w-none origin-center"
                        :style="imageStyle"
                    />

                    <p
                        v-else
                        class="flex h-full items-center justify-center text-sm text-muted-foreground"
                    >
                        {{
                            failed
                                ? 'That image could not be opened.'
                                : 'Loading photo…'
                        }}
                    </p>

                    <!-- The crop circle: a ring plus the shadow that dims everything outside it. -->
                    <div
                        v-if="image"
                        class="pointer-events-none absolute inset-0 rounded-full shadow-[0_0_0_9999px_rgba(0,0,0,0.55)] ring-1 ring-white/70"
                        aria-hidden="true"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <ZoomOut class="size-4 shrink-0 text-muted-foreground" />
                    <input
                        type="range"
                        class="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-muted accent-primary disabled:opacity-50 [&::-webkit-slider-thumb]:size-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary"
                        :min="MIN_ZOOM"
                        :max="MAX_ZOOM"
                        step="0.01"
                        :value="zoom"
                        :disabled="!image"
                        aria-label="Zoom"
                        @input="
                            setZoom(
                                Number(
                                    ($event.target as HTMLInputElement).value,
                                ),
                            )
                        "
                    />
                    <ZoomIn class="size-4 shrink-0 text-muted-foreground" />
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!image"
                        @click="rotate"
                    >
                        <RotateCw />
                        Rotate
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="!image || !isEdited"
                        @click="reset"
                    >
                        Reset
                    </Button>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="processing"
                    @click="emit('update:open', false)"
                >
                    Cancel
                </Button>

                <Button
                    type="button"
                    :disabled="!image || loading || processing"
                    data-test="save-avatar-button"
                    @click="save"
                >
                    {{ processing ? 'Uploading…' : 'Save photo' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
