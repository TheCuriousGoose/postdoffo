import { ref } from 'vue';

export type DraggedItem =
    | { type: 'request'; id: string; collectionId: string }
    | { type: 'collection'; id: string; parentId: string | null };

/**
 * What's currently being dragged in the collection tree, shared across every
 * row (folders and requests alike) via a single module-scoped ref rather than
 * prop-drilled through the recursive CollectionTree component. A drop target
 * can live in a completely different subtree than the row a drag started on,
 * so passing this through props/emits would mean threading it through every
 * level in between.
 */
export const draggedItem = ref<DraggedItem | null>(null);

/** Re-insert `draggedId` immediately before/after `targetId`, order preserved. */
export function reorderIds(
    ids: string[],
    draggedId: string,
    targetId: string,
    position: 'before' | 'after',
): string[] {
    const next = ids.filter((id) => id !== draggedId);
    const targetIndex = next.indexOf(targetId);
    next.splice(
        position === 'before' ? targetIndex : targetIndex + 1,
        0,
        draggedId,
    );

    return next;
}
