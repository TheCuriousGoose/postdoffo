export type HttpMethod =
    'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'HEAD' | 'OPTIONS';

export type BodyType = 'none' | 'raw' | 'json' | 'form_data' | 'urlencoded';

export type WorkspaceRole = 'owner' | 'co_owner' | 'editor' | 'viewer';

export type KeyValuePair = {
    key: string;
    value: string;
    enabled?: boolean;
};

/**
 * A row in a form-data body. A `file` row carries an uploaded request_files id
 * instead of a value — the file itself lives on the server, and `filename` /
 * `size` are only kept so the row can describe it without another round trip.
 */
export type FormField = KeyValuePair & {
    type?: 'text' | 'file';
    file_id?: number | null;
    filename?: string | null;
    size?: number | null;
};

export type RequestFile = {
    id: number;
    request_id: string;
    filename: string;
    mime_type: string | null;
    size: number;
};

/** A cookie the server is holding for this user, in this workspace. */
export type RequestCookie = {
    id: number;
    domain: string;
    path: string;
    name: string;
    value: string;
    expires_at: string | null;
    secure: boolean;
    http_only: boolean;
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
    fields?: FormField[];
} | null;

export type ApiRequest = {
    id: string;
    collection_id: string;
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
    id: string;
    collection_id: string;
    name: string;
    method: HttpMethod;
    order: number;
};

export type CollectionNode = {
    id: string;
    name: string;
    parent_id: string | null;
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
    workspace_id: string;
    name: string;
    is_active: boolean;
    variables: EnvironmentVariable[];
};

/**
 * A workspace-level "global" variable — the lowest-precedence layer, applied
 * whatever environment is active. Mirrors {@link EnvironmentVariable}.
 */
export type WorkspaceVariable = {
    id: number;
    workspace_id: string;
    key: string;
    value: string | null;
    is_secret: boolean;
};

export type Workspace = {
    id: string;
    name: string;
    owner_id: number;
    collections_count?: number;
    role?: WorkspaceRole | null;
};

/**
 * The roles a workspace can hand out. `owner` is not among them — it comes from
 * the workspace's `owner_id`, and there is exactly one of it. Mirrors
 * {@link \App\Enums\WorkspaceRole::assignableValues()}.
 */
export type WorkspaceMemberRole = 'co_owner' | 'editor' | 'viewer';

export const workspaceRoleLabels: Record<WorkspaceRole, string> = {
    owner: 'Owner',
    co_owner: 'Co-owner',
    editor: 'Editor',
    viewer: 'Viewer',
};

/** What each assignable role gets you, shown next to it when picking one. */
export const workspaceRoleDescriptions: Record<WorkspaceMemberRole, string> = {
    co_owner: 'Can edit everything and share the workspace with others',
    editor: 'Can create and edit collections, requests, and environments',
    viewer: 'Can send requests and read everything, but not change it',
};

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
    variables: Record<string, string>;
};

export type RequestHistoryEntry = {
    id: number;
    request_id: string | null;
    workspace_id: string;
    user_id: number | null;
    method: string;
    url: string;
    status_code: number | null;
    duration_ms: number | null;
    response_snapshot: ExecutedResponse | null;
    executed_at: string;
};
