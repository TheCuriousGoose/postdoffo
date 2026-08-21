import { show as showRequest } from '@/actions/App/Http/Controllers/RequestController';
import { api } from '@/lib/api';
import { useWorkspaceStore } from '@/stores/workspace';
import type { ApiRequest } from '@/types/workspace';

/**
 * Opens a request as a tab, fetching the full request (body, headers,
 * scripts, ...) only the first time — every click after that reuses the
 * already-open tab. Shared by the sidebar tree and the command palette so
 * both jump to a request the same way. Only `id` is actually needed, so any
 * lightweight row shape (sidebar summary, palette search result, ...) works.
 */
export function useOpenRequest() {
    const store = useWorkspaceStore();

    async function openRequest(request: { id: string }): Promise<void> {
        if (store.tabs.some((tab) => tab.requestId === request.id)) {
            store.setActiveTab(request.id);

            return;
        }

        const full = await api.get<ApiRequest>(showRequest.url(request.id));
        store.openRequest(full);
    }

    return { openRequest };
}
