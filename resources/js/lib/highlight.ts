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
    | 'operator'
    | 'comment'
    | 'plain'
    | 'variable'
    | 'variable-unresolved';

export type HighlightToken = {
    text: string;
    type: HighlightTokenType;
};

export type HighlightMode = 'json' | 'script' | 'text';

export type HighlightOptions = {
    mode: HighlightMode;
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
    operator: 'text-fuchsia-600 dark:text-fuchsia-400',
    comment: 'text-muted-foreground italic',
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

// Mirrors the token grammar accepted by the PHP script evaluator
// (app/Services/Scripting/ScriptExpression.php): strings, numbers, the
// comparison/logical operators it supports, bare identifiers (pm, its path
// segments, true/false/null), and `.(),` punctuation. A trailing catch-all
// keeps unrecognized characters (e.g. a stray `/`) visible instead of
// silently dropping them from the rendered backdrop.
const SCRIPT_PATTERN =
    /"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|-?\d+(?:\.\d+)?|==|!=|>=|<=|&&|\|\||[><!]|[A-Za-z_][A-Za-z0-9_]*|[.(),]|\s+|./g;

const SCRIPT_OPERATORS = new Set([
    '==',
    '!=',
    '>=',
    '<=',
    '&&',
    '||',
    '>',
    '<',
    '!',
]);

function classifyScriptToken(token: string): HighlightTokenType {
    if (token[0] === '"' || token[0] === "'") {
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

    if (SCRIPT_OPERATORS.has(token)) {
        return 'operator';
    }

    if (token.length === 1 && '.(),'.includes(token)) {
        return 'punctuation';
    }

    // Bare identifiers: `pm` itself and every segment of a `pm.foo.bar` path
    // (the only identifiers the grammar allows), styled like a JSON key.
    if (/^[A-Za-z_]/.test(token)) {
        return 'key';
    }

    return 'plain';
}

/**
 * A line is a comment only when, once trimmed, it starts with `//` — matching
 * ScriptRunner's line-based `str_starts_with($line, '//')` check exactly.
 * Trailing/inline `//` isn't treated as a comment (the runner doesn't
 * recognize it as one either), so it's tokenized as ordinary script text.
 */
function highlightScriptLine(
    line: string,
    resolved?: (name: string) => boolean,
): HighlightToken[] {
    if (line.trim().startsWith('//')) {
        const indent = line.slice(0, line.indexOf('//'));
        const tokens: HighlightToken[] = [];

        if (indent !== '') {
            tokens.push({ text: indent, type: 'plain' });
        }

        tokens.push({ text: line.slice(indent.length), type: 'comment' });

        return tokens;
    }

    const raw: HighlightToken[] = [];

    for (const match of line.matchAll(SCRIPT_PATTERN)) {
        raw.push({ text: match[0], type: classifyScriptToken(match[0]) });
    }

    return raw.flatMap((token) =>
        token.type === 'string'
            ? splitVariables(token.text, token.type, resolved)
            : [token],
    );
}

function highlightScript(
    src: string,
    resolved?: (name: string) => boolean,
): HighlightToken[] {
    const lines = src.split('\n');

    return lines.flatMap((line, index) => {
        const tokens = highlightScriptLine(line, resolved);

        return index < lines.length - 1
            ? [...tokens, { text: '\n', type: 'plain' as const }]
            : tokens;
    });
}

export function highlight(
    src: string,
    { mode, resolved }: HighlightOptions,
): HighlightToken[] {
    if (src === '') {
        return [];
    }

    if (mode === 'json') {
        return highlightJson(src, resolved);
    }

    if (mode === 'script') {
        return highlightScript(src, resolved);
    }

    return splitVariables(src, 'plain', resolved);
}
