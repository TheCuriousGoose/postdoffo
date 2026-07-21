export type HttpMethod =
    'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'HEAD' | 'OPTIONS';

export type BodyType = 'none' | 'raw' | 'json' | 'form_data' | 'urlencoded';

export type WorkspaceRole = 'owner' | 'editor' | 'viewer';

export type KeyValuePair = {
    key: string;
    value: string;
    enabled?: boolean;
};

export type AuthType = 'bearer' | 'basic' | 'apikey' | 'none';

export type RequestAuth = {
    token?: string;
    username?: string;
    password?: string;
    key?: string;
    value?: string;
    in?: 'header' | 'query';
} | null;

export type RequestBody = {
    raw?: string;
    json?: unknown;
    fields?: KeyValuePair[];
} | null;

export type ApiRequest = {
    id: number;
    collection_id: number;
    name: string;
    method: HttpMethod;
    url: string;
    order: number;
    headers: KeyValuePair[] | null;
    query_params: KeyValuePair[] | null;
    body: RequestBody;
    body_type: BodyType;
    auth_type: AuthType | null;
    auth: RequestAuth;
    pre_request_script: string | null;
    test_script: string | null;
    created_at: string;
    updated_at: string;
};

/**
 * Lightweight row shown in the sidebar tree. The full ApiRequest (body,
 * headers, scripts, ...) is fetched on demand when a tab opens — see
 * RequestController::show() — so a workspace with thousands of requests
 * doesn't ship megabytes of unopened request bodies on initial page load.
 */
export type RequestSummary = {
    id: number;
    collection_id: number;
    name: string;
    method: HttpMethod;
    order: number;
};

export type CollectionNode = {
    id: number;
    name: string;
    parent_id: number | null;
    order: number;
    variables: Record<string, string> | null;
    headers: KeyValuePair[] | null;
    auth_type: AuthType | null;
    auth: RequestAuth;
    requests: RequestSummary[];
    children: CollectionNode[];
};

export type EnvironmentVariable = {
    id: number;
    environment_id: number;
    key: string;
    value: string | null;
    is_secret: boolean;
};

export type Environment = {
    id: number;
    workspace_id: number;
    name: string;
    is_active: boolean;
    variables: EnvironmentVariable[];
};

export type Workspace = {
    id: number;
    name: string;
    owner_id: number;
    collections_count?: number;
    role?: WorkspaceRole | null;
};

export type WorkspaceMemberRole = 'editor' | 'viewer';

export type WorkspaceMember = {
    id: number;
    name: string;
    email: string;
    role: WorkspaceRole;
};

export type WorkspaceInvitation = {
    id: number;
    email: string;
    role: WorkspaceMemberRole;
    created_at: string;
    url: string;
};

export type TestResult = {
    name: string;
    passed: boolean;
    message: string | null;
};

export type ExecutedResponse = {
    status: number | null;
    headers: Record<string, string[]>;
    body: string | null;
    duration_ms: number;
    test_results: TestResult[];
    error: string | null;
    ok: boolean;
};

export type RequestHistoryEntry = {
    id: number;
    request_id: number | null;
    workspace_id: number;
    user_id: number | null;
    method: string;
    url: string;
    status_code: number | null;
    duration_ms: number | null;
    response_snapshot: ExecutedResponse | null;
    executed_at: string;
};
