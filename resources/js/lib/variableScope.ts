import type {
    ApiRequest,
    AuthType,
    CollectionNode,
    Environment,
    WorkspaceVariable,
} from '@/types/workspace';

/**
 * Client-side mirror of the backend `VariableResolver`. Given the collection
 * tree, a request's collection and the active environment, it works out exactly
 * what a request resolves to at send time — the variable map (with the source
 * each value wins from), the inherited default headers, and the inherited auth.
 *
 * Keeping this in sync with `app/Services/VariableResolver.php` is what lets the
 * UI show "what's inherited and from where" without a server round-trip.
 */

export type VariableSourceType = 'environment' | 'collection' | 'workspace';

export type ResolvedVariable = {
    id: number | null;
    key: string;
    value: string;
    isSecret: boolean;
    sourceType: VariableSourceType;
    sourceName: string;
};

export type InheritedHeader = {
    key: string;
    value: string;
    sourceName: string;
};

export type InheritedAuth = {
    type: AuthType;
    sourceName: string;
};

export type VariableScope = {
    variables: Record<string, ResolvedVariable>;
    list: ResolvedVariable[];
    chain: { id: string; name: string }[];
    inheritedHeaders: InheritedHeader[];
    inheritedAuth: InheritedAuth | null;
};

export const EMPTY_SCOPE: VariableScope = {
    variables: {},
    list: [],
    chain: [],
    inheritedHeaders: [],
    inheritedAuth: null,
};

const VARIABLE_PATTERN = /\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/g;

/**
 * Walk the tree to the collection with `collectionId`, returning the full
 * ancestor path root-first (so a nearer collection overrides its ancestors,
 * matching the backend's `collectionChain`).
 */
export function findCollectionChain(
    tree: CollectionNode[],
    collectionId: string | null | undefined,
    trail: CollectionNode[] = [],
): CollectionNode[] {
    if (collectionId == null) {
        return [];
    }

    for (const node of tree) {
        const next = [...trail, node];

        if (node.id === collectionId) {
            return next;
        }

        const deeper = findCollectionChain(node.children, collectionId, next);

        if (deeper.length) {
            return deeper;
        }
    }

    return [];
}

export function buildVariableScope(
    tree: CollectionNode[],
    collectionId: string | null | undefined,
    environment: Environment | null,
    workspaceVariables: WorkspaceVariable[] = [],
): VariableScope {
    const chain = findCollectionChain(tree, collectionId);
    const variables: Record<string, ResolvedVariable> = {};

    // Workspace globals are the base layer — everything below overrides them.
    for (const variable of workspaceVariables) {
        variables[variable.key] = {
            id: variable.id,
            key: variable.key,
            value: variable.value ?? '',
            isSecret: variable.is_secret,
            sourceType: 'workspace',
            sourceName: 'Workspace globals',
        };
    }

    // Collections next, root-first, so a nearer folder overrides its parent.
    for (const node of chain) {
        for (const [key, value] of Object.entries(node.variables ?? {})) {
            variables[key] = {
                id: null,
                key,
                value: String(value ?? ''),
                isSecret: false,
                sourceType: 'collection',
                sourceName: node.name,
            };
        }
    }

    // The active environment overrides collection values, matching the backend.
    if (environment) {
        for (const variable of environment.variables) {
            variables[variable.key] = {
                id: variable.id,
                key: variable.key,
                value: variable.value ?? '',
                isSecret: variable.is_secret,
                sourceType: 'environment',
                sourceName: environment.name,
            };
        }
    }

    // Default headers: root-first, nearer overrides, disabled/blank dropped.
    const headerMap: Record<string, InheritedHeader> = {};

    for (const node of chain) {
        for (const header of node.headers ?? []) {
            if (header.enabled === false || header.key === '') {
                continue;
            }

            headerMap[header.key] = {
                key: header.key,
                value: header.value,
                sourceName: node.name,
            };
        }
    }

    // Inherited auth: nearest-defined-wins down the chain.
    let inheritedAuth: InheritedAuth | null = null;

    for (const node of chain) {
        if (node.auth_type !== null) {
            inheritedAuth = { type: node.auth_type, sourceName: node.name };
        }
    }

    return {
        variables,
        list: Object.values(variables).sort((a, b) =>
            a.key.localeCompare(b.key),
        ),
        chain: chain.map((node) => ({ id: node.id, name: node.name })),
        inheritedHeaders: Object.values(headerMap),
        inheritedAuth,
    };
}

/** Every distinct `{{variable}}` key referenced anywhere in a request draft. */
export function collectReferencedVariables(draft: ApiRequest): string[] {
    const found = new Set<string>();

    const scan = (value: unknown): void => {
        if (typeof value === 'string') {
            for (const match of value.matchAll(VARIABLE_PATTERN)) {
                found.add(match[1]);
            }

            return;
        }

        if (Array.isArray(value)) {
            value.forEach(scan);

            return;
        }

        if (value && typeof value === 'object') {
            Object.values(value).forEach(scan);
        }
    };

    scan(draft.url);
    scan(draft.headers);
    scan(draft.query_params);
    scan(draft.auth);
    scan(draft.body);

    return [...found];
}

/** Pull the variable key out of a `{{ key }}` token, or null if it isn't one. */
export function variableKeyOf(token: string): string | null {
    const match = token.match(/^\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}$/);

    return match ? match[1] : null;
}

/**
 * The variable key of the `{{...}}` token spanning `offset` in `text`, or null.
 * Used to turn a click/caret position inside an input into the variable the
 * user is pointing at, so we can open its inspector.
 */
export function variableAtOffset(text: string, offset: number): string | null {
    for (const match of text.matchAll(VARIABLE_PATTERN)) {
        const start = match.index ?? 0;
        const end = start + match[0].length;

        if (offset >= start && offset <= end) {
            return match[1];
        }
    }

    return null;
}
