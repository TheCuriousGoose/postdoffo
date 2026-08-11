import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { store as storeEnvironment } from '@/actions/App/Http/Controllers/EnvironmentController';
import { api } from '@/lib/api';
import { promptDialog } from '@/lib/dialogs';

/**
 * Creating an environment is reachable from two places now — the switcher in
 * the toolbar and the variables manager — so the prompt, the request and the
 * reload live here rather than being written out twice with two slightly
 * different wordings. The new id comes back so each caller can decide what
 * happens next: the switcher activates it, the manager just selects it.
 */
export function useCreateEnvironment() {
    async function createEnvironment(
        workspaceId: number,
    ): Promise<number | null> {
        const name = await promptDialog({
            title: 'New environment',
            label: 'Environment name',
            placeholder: 'Production',
            confirmText: 'Create',
        });

        if (!name) {
            return null;
        }

        try {
            const created = await api.post<{ id: number }>(
                storeEnvironment.url(workspaceId),
                { name },
            );
            router.reload({ only: ['environments'] });

            return created.id;
        } catch {
            toast.error('Failed to create environment');

            return null;
        }
    }

    return { createEnvironment };
}
