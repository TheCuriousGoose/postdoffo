<script setup lang="ts">
import { computed, useTemplateRef } from 'vue';
import { useVariableInspector } from '@/composables/useVariableInspector';
import { cn } from '@/lib/utils';
import { variableAtOffset } from '@/lib/variableScope';

/**
 * A single-line text input that highlights {{variable}} placeholders inline —
 * a plain <input> can't render partial styling, so this overlays a matching
 * "backdrop" div (colored spans for variables, invisible text everywhere
 * else) behind a real input whose own text is set fully transparent. The
 * input keeps native caret/selection/typing behavior; only its glyph color
 * is hidden, so the backdrop's highlights show through underneath.
 *
 * `plain` drops the border/shadow so it can sit flush inside a table cell.
 */
defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    class?: string;
    plain?: boolean;
    /**
     * Resolved variable keys in scope for this request. When provided, a
     * `{{variable}}` that resolves is highlighted amber and one that doesn't is
     * flagged red, so a typo or a missing environment value is obvious inline.
     * Omit it to highlight every `{{variable}}` amber (no resolution check).
     */
    variables?: Record<string, unknown>;
}>();

const emit = defineEmits<{
    'update:modelValue': [string];
}>();

const backdrop = useTemplateRef('backdrop');
const inputEl = useTemplateRef('inputEl');

const VARIABLE_PATTERN = /\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/g;

type Segment = {
    text: string;
    isVariable: boolean;
    resolved: boolean;
};

const segments = computed<Segment[]>(() => {
    const value = props.modelValue ?? '';
    const parts: Segment[] = [];
    const scope = props.variables;
    let lastIndex = 0;

    for (const match of value.matchAll(VARIABLE_PATTERN)) {
        const index = match.index ?? 0;

        if (index > lastIndex) {
            parts.push({
                text: value.slice(lastIndex, index),
                isVariable: false,
                resolved: false,
            });
        }

        parts.push({
            text: match[0],
            isVariable: true,
            // Without a scope we can't tell, so treat everything as resolved.
            resolved: scope ? match[1] in scope : true,
        });
        lastIndex = index + match[0].length;
    }

    if (lastIndex < value.length || parts.length === 0) {
        parts.push({
            text: value.slice(lastIndex),
            isVariable: false,
            resolved: false,
        });
    }

    return parts;
});

// Padding must be identical on the input and its backdrop or the highlight
// drifts out of alignment with the real glyphs.
const padClass = computed(() =>
    props.plain ? 'h-8 px-2.5 py-1.5' : 'h-9 px-3 py-1',
);

const { open: openInspector } = useVariableInspector();

function onInput(event: Event) {
    emit('update:modelValue', (event.target as HTMLInputElement).value);
}

function syncScroll() {
    if (backdrop.value && inputEl.value) {
        backdrop.value.scrollLeft = inputEl.value.scrollLeft;
    }
}

/** A click landing inside a `{{variable}}` opens its inspector. */
function onClick(event: MouseEvent) {
    const target = event.target as HTMLInputElement;
    const offset = target.selectionStart;

    if (offset === null) {
        return;
    }

    const key = variableAtOffset(props.modelValue ?? '', offset);

    if (key) {
        openInspector(key, event.clientX, event.clientY);
    }
}
</script>

<template>
    <div class="relative" :class="props.class">
        <div
            ref="backdrop"
            aria-hidden="true"
            :class="
                cn(
                    'pointer-events-none absolute inset-0 z-0 overflow-hidden border border-transparent text-base whitespace-pre md:text-sm',
                    padClass,
                )
            "
        >
            <span
                v-for="(part, index) in segments"
                :key="index"
                :class="[
                    !part.isVariable && 'text-foreground',
                    part.isVariable &&
                        part.resolved &&
                        'rounded bg-amber-500/15 text-amber-600 dark:bg-amber-400/10 dark:text-amber-400',
                    part.isVariable &&
                        !part.resolved &&
                        'rounded bg-red-500/10 text-red-600 underline decoration-red-500/60 decoration-dashed underline-offset-2 dark:text-red-400',
                ]"
                >{{ part.text }}</span
            >
        </div>
        <input
            ref="inputEl"
            :value="modelValue"
            :placeholder="placeholder"
            data-slot="input"
            :class="
                cn(
                    'relative z-10 w-full min-w-0 bg-transparent text-base text-transparent caret-foreground outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                    padClass,
                    plain
                        ? 'focus-visible:bg-accent/40'
                        : 'rounded-md border border-input shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30 dark:aria-invalid:ring-destructive/40',
                )
            "
            v-bind="$attrs"
            autocomplete="off"
            autocapitalize="off"
            autocorrect="off"
            spellcheck="false"
            data-lpignore="true"
            data-1p-ignore="true"
            data-bwignore="true"
            data-form-type="other"
            @input="onInput"
            @scroll="syncScroll"
            @click="onClick"
        />
    </div>
</template>
