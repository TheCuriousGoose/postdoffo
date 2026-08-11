import { show as showRequestFile } from '@/actions/App/Http/Controllers/RequestFileController';
import type { BodyType, FormField, HttpMethod } from '@/types/workspace';

const LOCAL_SUFFIXES = ['.test', '.local', '.localhost'];

export function isLocalHost(hostname: string): boolean {
    const host = hostname.toLowerCase();

    if (host === 'localhost' || host === '127.0.0.1' || host === '::1') {
        return true;
    }

    return LOCAL_SUFFIXES.some((suffix) => host.endsWith(suffix));
}

export function isLocalUrl(url: string): boolean {
    try {
        return isLocalHost(new URL(url).hostname);
    } catch {
        return false;
    }
}

export type PreparedOutgoingRequest = {
    method: HttpMethod;
    url: string;
    headers: Record<string, string>;
    query_params: Record<string, string>;
    body: unknown;
    body_type: BodyType;
};

export type BrowserExecutionResult = {
    status: number | null;
    headers: Record<string, string[]>;
    body: string | null;
    duration_ms: number;
    error: string | null;
};

/** Pulls a stored form-data upload back off the server. Null if it's gone. */
async function fetchUpload(fileId: number): Promise<Blob | null> {
    const response = await fetch(showRequestFile.url(fileId), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    return response.ok ? await response.blob() : null;
}

function buildUrl(outgoing: PreparedOutgoingRequest): string {
    const url = new URL(outgoing.url);

    for (const [key, value] of Object.entries(outgoing.query_params)) {
        url.searchParams.set(key, value);
    }

    return url.toString();
}

async function buildBody(
    outgoing: PreparedOutgoingRequest,
): Promise<BodyInit | undefined> {
    const body = (outgoing.body ?? {}) as {
        raw?: string;
        json?: unknown;
        fields?: FormField[];
    };

    switch (outgoing.body_type) {
        case 'none':
            return undefined;
        case 'raw':
            return body.raw ?? '';
        case 'json':
            return JSON.stringify(body.json ?? {});
        case 'urlencoded': {
            const params = new URLSearchParams();

            for (const field of body.fields ?? []) {
                if (field.enabled === false || !field.key) {
                    continue;
                }

                params.set(field.key, field.value ?? '');
            }

            return params;
        }
        case 'form_data': {
            const form = new FormData();

            for (const field of body.fields ?? []) {
                if (field.enabled === false || !field.key) {
                    continue;
                }

                if (field.type !== 'file') {
                    form.set(field.key, field.value ?? '');

                    continue;
                }

                // Uploads live on the server, so firing from the browser means
                // pulling each one back down before it can be attached here.
                const blob = field.file_id
                    ? await fetchUpload(field.file_id)
                    : null;

                if (blob) {
                    form.set(field.key, blob, field.filename ?? 'file');
                }
            }

            return form;
        }
    }
}

/**
 * Fires an already-resolved request straight from the browser. Used for hosts that
 * only resolve locally (.test, .local, localhost) — the target must actually be
 * reachable from wherever the browser is running, including CORS allowing this
 * app's origin, since there is no server in the middle to route around that here.
 */
export async function sendViaBrowser(
    outgoing: PreparedOutgoingRequest,
): Promise<BrowserExecutionResult> {
    const start = performance.now();

    // fetch() sets its own multipart boundary / urlencoded content-type; letting an
    // explicit header through would produce a mismatched boundary and a body the
    // target can't parse.
    const headers = { ...outgoing.headers };

    if (
        outgoing.body_type === 'form_data' ||
        outgoing.body_type === 'urlencoded'
    ) {
        delete headers['Content-Type'];
        delete headers['content-type'];
    }

    try {
        // GET/HEAD requests may not carry a body, or fetch() throws before sending.
        const canHaveBody =
            outgoing.method !== 'GET' && outgoing.method !== 'HEAD';

        const response = await fetch(buildUrl(outgoing), {
            method: outgoing.method,
            headers,
            body: canHaveBody ? await buildBody(outgoing) : undefined,
        });

        const durationMs = Math.round(performance.now() - start);
        const responseHeaders: Record<string, string[]> = {};

        response.headers.forEach((value, key) => {
            responseHeaders[key] = [value];
        });

        return {
            status: response.status,
            headers: responseHeaders,
            body: await response.text(),
            duration_ms: durationMs,
            error: null,
        };
    } catch (error) {
        return {
            status: null,
            headers: {},
            body: null,
            duration_ms: Math.round(performance.now() - start),
            error:
                error instanceof Error
                    ? error.message
                    : 'The browser could not reach this host — check it is running and that it allows requests from this app (CORS).',
        };
    }
}
