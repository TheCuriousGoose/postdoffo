<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * The single icon-button shape used across workspace chrome: ghost, square, and
 * never unlabelled. Before this, one row of tools mixed ghost with outline and
 * size-8 with size-9, and half of them explained themselves with a native
 * `title` while the other half explained nothing at all.
 *
 * The root stays a single `Button` on purpose. Most of these are the child of a
 * `DialogTrigger as-child` / `SheetTrigger as-child`, which merges its click
 * handler and its element ref onto whatever it wraps; a component that renders
 * a fragment (which is what wrapping this in reka's Tooltip would produce)
 * silently swallows both. Styled tooltips are still possible here, but they
 * have to go *outside* the dialog trigger in each caller rather than inside
 * this component.
 */
const props = withDefaults(
    defineProps<{
        /** The accessible name, and the hover hint. */
        label: string;
        /** `md` for chrome bars, `sm` for dense rows (tree rows, sidebar headers). */
        size?: 'sm' | 'md';
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'md' },
);
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="icon"
        :aria-label="props.label"
        :title="props.label"
        :class="
            cn(
                'shrink-0',
                props.size === 'sm' ? 'size-6' : 'size-8',
                props.class,
            )
        "
    >
        <slot />
    </Button>
</template>
