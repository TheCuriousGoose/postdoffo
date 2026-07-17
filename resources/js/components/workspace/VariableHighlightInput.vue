<script setup lang="ts">
import { computed, useTemplateRef } from 'vue';
import { cn } from '@/lib/utils';

/**
 * A single-line text input that highlights {{variable}} placeholders inline —
 * a plain <input> can't render partial styling, so this overlays a matching
 * "backdrop" div (colored spans for variables, invisible text everywhere
 * else) behind a real input whose own text is set fully transparent. The
 * input keeps native caret/selection/typing behavior; only its glyph color
 * is hidden, so the backdrop's highlights show through underneath.
 */
defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    class?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [string];
}>();

const backdrop = useTemplateRef('backdrop');
const inputEl = useTemplateRef('inputEl');

const VARIABLE_PATTERN = /\{\{\s*[a-zA-Z0-9_.-]+\s*\}\}/g;

const segments = computed(() => {
    const value = props.modelValue ?? '';
    const parts: { text: string; isVariable: boolean }[] = [];
    let lastIndex = 0;

    for (const match of value.matchAll(VARIABLE_PATTERN)) {
        const index = match.index ?? 0;

        if (index > lastIndex) {
            parts.push({
                text: value.slice(lastIndex, index),
                isVariable: false,
            });
        }

        parts.push({ text: match[0], isVariable: true });
        lastIndex = index + match[0].length;
    }

    if (lastIndex < value.length || parts.length === 0) {
        parts.push({ text: value.slice(lastIndex), isVariable: false });
    }

    return parts;
});

function onInput(event: Event) {
    emit('update:modelValue', (event.target as HTMLInputElement).value);
}

function syncScroll() {
    if (backdrop.value && inputEl.value) {
        backdrop.value.scrollLeft = inputEl.value.scrollLeft;
    }
}
</script>

<template>
    <div class="relative" :class="props.class">
        <div
            ref="backdrop"
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 z-0 overflow-hidden rounded-md border border-transparent px-3 py-1 text-base whitespace-pre md:text-sm"
        >
            <span
                v-for="(part, index) in segments"
                :key="index"
                :class="
                    part.isVariable
                        ? 'rounded bg-amber-500/15 text-amber-600 dark:bg-amber-400/10 dark:text-amber-400'
                        : 'text-foreground'
                "
                >{{ part.text }}</span
            >
        </div>
        <input
            ref="inputEl"
            v-bind="$attrs"
            :value="modelValue"
            :placeholder="placeholder"
            data-slot="input"
            :class="
                cn(
                    'relative z-10 h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base text-transparent caret-foreground shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                )
            "
            @input="onInput"
            @scroll="syncScroll"
        />
    </div>
</template>
