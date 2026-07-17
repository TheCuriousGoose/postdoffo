export class ApiError extends Error {
    constructor(
        public status: number,
        public body: unknown,
    ) {
        super(`Request failed with status ${status}`);
    }
}

function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const token = getCookie('XSRF-TOKEN');

    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            ...options.headers,
        },
    });

    if (!response.ok) {
        const body = await response.json().catch(() => null);

        throw new ApiError(response.status, body);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return response.json() as Promise<T>;
}

export const api = {
    get: <T>(url: string): Promise<T> => request<T>(url),
    post: <T>(url: string, data?: unknown): Promise<T> =>
        request<T>(url, { method: 'POST', body: JSON.stringify(data ?? {}) }),
    patch: <T>(url: string, data?: unknown): Promise<T> =>
        request<T>(url, { method: 'PATCH', body: JSON.stringify(data ?? {}) }),
    delete: <T>(url: string): Promise<T> =>
        request<T>(url, { method: 'DELETE' }),
};
