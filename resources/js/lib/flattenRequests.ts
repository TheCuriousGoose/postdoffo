import type { CollectionNode, HttpMethod } from '@/types/workspace';

export type RunnableRequest = {
    id: number;
    name: string;
    method: HttpMethod;
};

/**
 * Every request under `node`, in run order: the node's own requests first
 * (in their existing order), then each child folder recursively. Adapted
 * from CommandPalette's tree walker, scoped to a single subtree instead of
 * the whole workspace.
 */
export function flattenRequests(node: CollectionNode): RunnableRequest[] {
    const entries: RunnableRequest[] = node.requests.map((request) => ({
        id: request.id,
        name: request.name,
        method: request.method,
    }));

    for (const child of node.children) {
        entries.push(...flattenRequests(child));
    }

    return entries;
}
