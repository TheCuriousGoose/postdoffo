<script setup lang="ts">
import { computed, useTemplateRef } from 'vue';
import { useVariableInspector } from '@/composables/useVariableInspector';
import { highlight, tokenClass } from '@/lib/highlight';
import { cn } from '@/lib/utils';
import { variableAtOffset } from '@/lib/variableScope';

/**
 * Multi-line sibling of VariableHighlightInput: a real <textarea> (so native
 * editing, caret and selection are untouched) with its glyphs made transparent,
 * layered over a highlighted backdrop that scrolls in lockstep. JSON and
 * pm.* scripts get syntax colors; `{{variables}}` are flagged
 * resolved/unresolved when a scope is passed. If highlighting ever
 * mis-tokenizes, the textarea underneath still edits perfectly.
 */
defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    modelValue: string;
    language?: 'json' | 'script' | 'text';
    placeholder?: string;
    variables?: Record<string, unknown>;
    class?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [string];
}>();

const backdrop = useTemplateRef('backdrop');
const textarea = useTemplateRef('textarea');

const tokens = computed(() =>
    highlight(props.modelValue ?? '', {
        mode: props.language ?? 'text',
        resolved: props.variables
            ? (name) => name in props.variables!
            : undefined,
    }),
);

const { open: openInspector } = useVariableInspector();

function onInput(event: Event) {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
}

function syncScroll() {
    if (backdrop.value && textarea.value) {
        backdrop.value.scrollTop = textarea.value.scrollTop;
        backdrop.value.scrollLeft = textarea.value.scrollLeft;
    }
}

/** A click landing inside a `{{variable}}` opens its inspector. */
function onClick(event: MouseEvent) {
    const target = event.target as HTMLTextAreaElement;
    const offset = target.selectionStart;

    const key = variableAtOffset(props.modelValue ?? '', offset);

    if (key) {
        openInspector(key, event.clientX, event.clientY);
    }
}
</script>

<template>
    <div class="relative" :class="props.class">
        <pre
            ref="backdrop"
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 m-0 overflow-auto rounded-md border border-transparent p-3 font-mono text-sm leading-relaxed break-words whitespace-pre-wrap"
        ><span v-for="(token, index) in tokens" :key="index" :class="tokenClass(token.type)">{{ token.text }}</span><span> </span></pre>
        <textarea
            ref="textarea"
            v-bind="$attrs"
            :value="modelValue"
            :placeholder="placeholder"
            spellcheck="false"
            autocomplete="off"
            data-lpignore="true"
            data-1p-ignore="true"
            data-bwignore="true"
            data-form-type="other"
            :class="
                cn(
                    'relative z-10 block h-full min-h-48 w-full resize-y overflow-auto rounded-md border border-input bg-transparent p-3 font-mono text-sm leading-relaxed break-words whitespace-pre-wrap text-transparent caret-foreground shadow-xs outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30',
                )
            "
            @input="onInput"
            @scroll="syncScroll"
            @click="onClick"
        />
    </div>
</template>
