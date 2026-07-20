/**
 * Tiny, dependency-free tokenizer for the request/response body views. It
 * returns typed tokens (never HTML strings) so the caller renders them as
 * escaped Vue text — no `v-html`, no XSS surface from arbitrary response
 * bodies. It also understands `{{variables}}` inside strings so the editor can
 * flag unresolved ones the same way the single-line inputs do.
 */

export type HighlightTokenType =
    | 'key'
    | 'string'
    | 'number'
    | 'boolean'
    | 'null'
    | 'punctuation'
    | 'plain'
    | 'variable'
    | 'variable-unresolved';

export type HighlightToken = {
    text: string;
    type: HighlightTokenType;
};

export type HighlightOptions = {
    json: boolean;
    /**
     * When provided, `{{name}}` tokens are detected and marked resolved or not
     * via this predicate. Omit it to skip variable detection entirely (e.g. for
     * a response body, where placeholders are already interpolated).
     */
    resolved?: (name: string) => boolean;
};

const VARIABLE_PATTERN = /\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/g;

// Strings, literals, numbers, structural punctuation, whitespace, then a
// catch-all so malformed JSON still tokenizes instead of dropping characters.
const JSON_PATTERN =
    /"(?:\\.|[^"\\])*"|\b(?:true|false)\b|\bnull\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|[{}[\]:,]|\s+|[^\s{}[\]:,"]+/g;

const CLASS_MAP: Record<HighlightTokenType, string> = {
    key: 'text-sky-600 dark:text-sky-400',
    string: 'text-emerald-600 dark:text-emerald-400',
    number: 'text-violet-600 dark:text-violet-400',
    boolean: 'text-rose-600 dark:text-rose-400',
    null: 'text-rose-600 dark:text-rose-400',
    punctuation: 'text-muted-foreground',
    plain: 'text-foreground',
    variable:
        'rounded bg-amber-500/15 text-amber-600 dark:bg-amber-400/10 dark:text-amber-400',
    'variable-unresolved':
        'rounded bg-red-500/10 text-red-600 underline decoration-red-500/60 decoration-dashed underline-offset-2 dark:text-red-400',
};

export function tokenClass(type: HighlightTokenType): string {
    return CLASS_MAP[type];
}

/** Split a run of text into plain and `{{variable}}` tokens. */
function splitVariables(
    text: string,
    base: HighlightTokenType,
    resolved?: (name: string) => boolean,
): HighlightToken[] {
    if (!resolved || !text.includes('{{')) {
        return text === '' ? [] : [{ text, type: base }];
    }

    const parts: HighlightToken[] = [];
    let lastIndex = 0;

    for (const match of text.matchAll(VARIABLE_PATTERN)) {
        const index = match.index ?? 0;

        if (index > lastIndex) {
            parts.push({ text: text.slice(lastIndex, index), type: base });
        }

        parts.push({
            text: match[0],
            type: resolved(match[1]) ? 'variable' : 'variable-unresolved',
        });
        lastIndex = index + match[0].length;
    }

    if (lastIndex < text.length) {
        parts.push({ text: text.slice(lastIndex), type: base });
    }

    return parts;
}

function classifyRaw(token: string): HighlightTokenType {
    if (token[0] === '"') {
        return 'string';
    }

    if (token === 'true' || token === 'false') {
        return 'boolean';
    }

    if (token === 'null') {
        return 'null';
    }

    if (/^-?\d/.test(token)) {
        return 'number';
    }

    if (token.length === 1 && '{}[]:,'.includes(token)) {
        return 'punctuation';
    }

    return 'plain';
}

function highlightJson(
    src: string,
    resolved?: (name: string) => boolean,
): HighlightToken[] {
    const raw: HighlightToken[] = [];

    for (const match of src.matchAll(JSON_PATTERN)) {
        raw.push({ text: match[0], type: classifyRaw(match[0]) });
    }

    // A string is a key when the next non-whitespace token is a colon.
    for (let i = 0; i < raw.length; i++) {
        if (raw[i].type !== 'string') {
            continue;
        }

        let j = i + 1;

        while (
            j < raw.length &&
            raw[j].type === 'plain' &&
            raw[j].text.trim() === ''
        ) {
            j++;
        }

        if (j < raw.length && raw[j].text === ':') {
            raw[i].type = 'key';
        }
    }

    // Expand variables inside strings/keys; leave structural tokens intact.
    return raw.flatMap((token) =>
        token.type === 'string' ||
        token.type === 'key' ||
        token.type === 'plain'
            ? splitVariables(token.text, token.type, resolved)
            : [token],
    );
}

export function highlight(
    src: string,
    { json, resolved }: HighlightOptions,
): HighlightToken[] {
    if (src === '') {
        return [];
    }

    if (json) {
        return highlightJson(src, resolved);
    }

    return splitVariables(src, 'plain', resolved);
}
