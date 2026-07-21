import {
    prepare as prepareRequest,
    record as recordRequest,
    send as sendRequest,
} from '@/actions/App/Http/Controllers/RequestController';
import { api } from '@/lib/api';
import { isLocalUrl, sendViaBrowser } from '@/lib/localRequest';
import type { PreparedOutgoingRequest } from '@/lib/localRequest';
import type { ExecutedResponse } from '@/types/workspace';

function withEnvironment(url: string, environmentId: number | null): string {
    return environmentId ? `${url}?environment_id=${environmentId}` : url;
}

/**
 * Resolve and fire one request, branching the same way RequestEditor's send
 * button does: .test/.local/localhost hosts only resolve on this machine, so
 * the browser fires them itself and reports the outcome back for test-script
 * execution + history recording; everything else is proxied through the server.
 *
 * `overrides` is the highest-precedence variable layer — used by the Collection
 * Runner to inject a data-file row's columns, and to chain a value captured by
 * an earlier request's test script (e.g. `pm.variables.set("token", ...)`) into
 * later requests in the same run.
 */
export async function runRequest(
    requestId: number,
    environmentId: number | null,
    overrides: Record<string, string> = {},
): Promise<ExecutedResponse> {
    const { outgoing, variables } = await api.post<{
        outgoing: PreparedOutgoingRequest;
        variables: Record<string, string>;
    }>(withEnvironment(prepareRequest.url(requestId), environmentId), {
        variables: overrides,
    });

    return isLocalUrl(outgoing.url)
        ? await api.post(recordRequest.url(requestId), {
              variables,
              ...(await sendViaBrowser(outgoing)),
          })
        : await api.post(sendRequest.url(requestId), { outgoing, variables });
}
